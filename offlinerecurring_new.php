<?php

/**
 * CiviCRM Offline Recurring Extension
 * Civi 4.5+ version
 * @package uk.co.circleinteractive.offlinerecurring
 */

/**
 * Implementation of hook_civicrm_links
 */
function offlinerecurring_civicrm_links($op, $objectName, $objectId, &$links, &$mask, &$values) {

    # rewrite links on contribution tab of contact view
    if ($objectName == 'Contribution' and $op == 'contribution.selector.recurring')
        $links = array_map(function($item) {
            if (isset($item['url']))
                $item['url'] = str_replace('contribute/updaterecur', 'recurring/add', $item['url']);
            return $item;
        }, $links);

}

/**
 * Implementation of hook_civicrm_pageRun
 */
function offlinerecurring_civicrm_pageRun(&$page) {
    watchdog('andyw', 'page = <pre>' . print_r($page, true) . '</pre>');
    if ($page instanceof CRM_Contribute_Page_Tab and implode('/', $page->urlPath) == 'civicrm/contact/view/contribution'
        && CRM_Core_Permission::check('add offline recurring payments')) {
        # template for this tab doesn't look for an .extra.tpl, 
        # so adding button via javascript
        $script = [
            '<div class="action-link">',
            '<a class="button" href="/civicrm/recurring/add?reset=1&action=add&cid=' . $_GET['cid'] . '&reset=1" accesskey="N">',
            '<span>',
            '<div class="icon add-icon add-recurring"></div>',
            'Add New Recurring Contribution',
            '</span>',
            '</a>',
            '</div>'
        ];
        CRM_Core_Resources::singleton()->addScript("if (!CRM.$('.add-recurring').length) CRM.$('.view-content').append('" . implode("", $script) . "');");     
        // Hide 'Edit' link if no permission to edit offline recurring payments
        if (!CRM_Core_Permission::check('edit offline recurring payments')) {
          CRM_Core_Resources::singleton()->addScript("if (CRM.$('a[title=\'Edit Recurring Payment\']').length) CRM.$('a[title=\'Edit Recurring Payment\']').hide();");
        }
    }

}

# cron job converted from standalone cron script to job api call
# todo: really need to rewrite this using the ContributionRecur api - that api didn't
# exist when the extension was first written
function civicrm_api3_job_process_offline_recurring_payments($params) {
                
    //$dtCurrentDay      = date("Ymd", mktime(0, 0, 0, date("m") , date("d") , date("Y")));
    //$dtCurrentDayStart = $dtCurrentDay."000000"; 
    //$dtCurrentDayEnd   = $dtCurrentDay."235959"; 
    
    // 7 day lookback - prevents contributions stopping if cron fails to run for up to 7 days
    $searchStart = date('Y-m-d H:i:s', strtotime('today') - (7 * 86400));
    $searchEnd   = date('Y-m-d H:i:s', strtotime('today') + 86399);

    // Select the recurring payment, where current date is equal to next scheduled date
    $sql = "
        SELECT * FROM civicrm_contribution_recur ccr
    INNER JOIN civicrm_contribution_recur_offline ccro ON ccro.recur_id = ccr.id
         WHERE (ccr.end_date IS NULL OR ccr.end_date > NOW())
           AND ccr.next_sched_contribution >= %1 
           AND ccr.next_sched_contribution <= %2
    ";
    
    if (_offlinerecurring_getCRMVersion() >= 4.4)
        $sql = str_replace('next_sched_contribution', 'next_sched_contribution_date', $sql);

    $dao = CRM_Core_DAO::executeQuery($sql, [
          1 => [$searchStart, 'String'],
          2 => [$searchEnd, 'String']
       ]
    );
    
    $counter = 0;
    $errors  = 0;
    $output  = [];
    
    while($dao->fetch()) {
                
        $contact_id                 = $dao->contact_id;
        $hash                       = md5(uniqid(rand(), true)); 
        $total_amount               = $dao->amount;
        $contribution_recur_id      = $dao->id;
        $contribution_type_id       = 1;
        $source                     = "Offline Recurring Contribution";
        $receive_date               = date("YmdHis");
        $contribution_status_id     = 2;    // Set to pending, must complete manually
        $payment_instrument_id      = 3;
        
        require_once 'api/api.php';
        $result = civicrm_api('contribution', 'create',
            [
                'version'                => 3,
                'contact_id'             => $contact_id,
                'receive_date'           => $receive_date,
                'total_amount'           => $total_amount,
                'payment_instrument_id'  => $payment_instrument_id,
                'trxn_id'                => $hash,
                'invoice_id'             => $hash,
                'source'                 => $source,
                'contribution_status_id' => $contribution_status_id,
                'contribution_type_id'   => $contribution_type_id,
                'contribution_recur_id'  => $contribution_recur_id,
                //'contribution_page_id'   => $entity_id
            ]
        );
        if ($result['is_error']) {
            $output[] = $result['error_message'];
            ++$errors;
            ++$counter;
            continue;
        } else {
            $contribution = reset($result['values']);
            $contribution_id = $contribution['id'];
            $output[] = ts('Created contribution record for contact id %1', [1 => $contact_id]);
        }
    
        //$mem_end_date = $member_dao->end_date;

        $next_sched_contribution = _offlinerecurring_getCRMVersion() >= 4.4 ? 
            $dao->next_sched_contribution_date : $dao->next_sched_contribution;
        
        $temp_date = strtotime($next_sched_contribution);
        
        $next_collectionDate = strtotime ("+$dao->frequency_interval $dao->frequency_unit", $temp_date);
        $next_collectionDate = date('YmdHis', $next_collectionDate);
        
        $sql = "
            UPDATE civicrm_contribution_recur 
               SET next_sched_contribution = %1 
             WHERE id = %2
        ";
        
        if (_offlinerecurring_getCRMVersion() >= 4.4)
            $sql = str_replace('next_sched_contribution', 'next_sched_contribution_date', $sql);

        CRM_Core_DAO::executeQuery($sql, [
               1 => [$next_collectionDate, 'String'],
               2 => [$dao->id, 'Integer']
           ]
        );


        $result = civicrm_api('activity', 'create',
            [
                'version'             => 3,
                'activity_type_id'    => 6,
                'source_record_id'    => $contribution_id,
                'source_contact_id'   => $contact_id,
                'assignee_contact_id' => $contact_id,
                'subject'             => "Offline Recurring Contribution - " . $total_amount,
                'status_id'           => 2,
                'activity_date_time'  => date("YmdHis"),            
            ]
        );
        if ($result['is_error']) {
            $output[] = ts(
                'An error occurred while creating activity record for contact id %1: %2',
                [
                    1 => $contact_id,
                    2 => $result['error_message']
                ]
            );
            ++$errors;
        } else {
            $output[] = ts('Created activity record for contact id %1', [1 => $contact_id]);

        }
        ++$counter;
    }
    
    // If errors ..
    if ($errors)
        return civicrm_api3_create_error(
            ts("Completed, but with %1 errors. %2 records processed.", 
                [
                    1 => $errors,
                    2 => $counter
                ]
            ) . "<br />" . implode("<br />", $output)
        );
    
    // If no errors and records processed ..
    if ($counter)
        return civicrm_api3_create_success(
            ts(
                '%1 contribution record(s) were processed.', 
                [
                    1 => $counter
                ]
            ) . "<br />" . implode("<br />", $output)
        );
   
    // No records processed
    return civicrm_api3_create_success(ts('No contribution records were processed.'));
    
}   

