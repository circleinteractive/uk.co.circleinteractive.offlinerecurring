<?php
use _ExtensionUtil as E;

/**
 * Job.processofflinerecurringpayments API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_job_processofflinerecurringpayments($params) {
  // 7 day lookback - prevents contributions stopping if cron fails to run for up to 7 days
  $searchStart = date('Y-m-d H:i:s', strtotime('today') - (7 * 86400));
  $searchEnd   = date('Y-m-d H:i:s', strtotime('today') + 86399);

  // Select the recurring payment, where current date is equal to next scheduled date
  $sql = "
    SELECT ccr.* FROM civicrm_contribution_recur ccr
      INNER JOIN civicrm_contribution_recur_offline ccro ON ccro.contribution_recur_id = ccr.id
    WHERE (ccr.end_date IS NULL OR ccr.end_date > NOW())
      AND ccr.next_sched_contribution_date >= %1
      AND ccr.next_sched_contribution_date <= %2
      AND ccr.cancel_date IS NULL
  ";

  $dao = CRM_Core_DAO::executeQuery($sql, [
    1 => [$searchStart, 'String'],
    2 => [$searchEnd, 'String']
  ]);

  $counter = $errors = 0;
  $output = [];

  while($dao->fetch()) {
    $hash = md5(uniqid(rand(), TRUE));
    $contributionParams = [
      'contact_id' => $dao->contact_id,
      'receive_date' => date("YmdHis"),
      'total_amount' => $dao->amount,
      'payment_instrument_id' => $dao->payment_instrument_id ? $dao->payment_instrument_id :'Cash',
      'trxn_id' => $hash,
      'invoice_id' => $hash,
      'source' => ts('Offline Recurring Contribution'),
      'contribution_status_id' => 'Pending',
      'contribution_type_id' => $dao->financial_type_id ? $dao->financial_type_id : 'Donation',
      'contribution_recur_id'  => $dao->id,
    ];
    try {
      $result = civicrm_api3('contribution', 'create', $contributionParams);
      $contribution = reset($result['values']);
      $output[] = ts('Created contribution record for contact id %1', [1 => $dao->contact_id]);
    }
    catch (CiviCRM_API3_Exception $e) {
      $output[] = $result['error_message'];
      ++$errors;
      ++$counter;
      continue;
    }

    $nextCollectionDate = strtotime (
      "+$dao->frequency_interval $dao->frequency_unit",
      strtotime($dao->next_sched_contribution_date)
    );
    $nextCollectionDate = date('YmdHis', $nextCollectionDate);
    $recurParams = [
      'id' => $dao->id,
      'next_sched_contribution_date' => $nextCollectionDate,
    ];
    civicrm_api3('ContributionRecur', 'create', $recurParams);
    $result = civicrm_api('activity', 'create', [
      'activity_type_id' => 'Contribution',
      'source_record_id' => $contribution['id'],
      'source_contact_id' => $dao->contact_id,
      'assignee_contact_id' => $dao->contact_id,
      'subject' => ts("Offline Recurring Contribution - ") . $dao->amount,
      'status_id' => 'Completed',
      'activity_date_time' => date("YmdHis"),
    ]);
    ++$counter;
  }

  // If errors ..
  if ($errors) {
    return civicrm_api3_create_error(
      ts("Completed, but with %1 errors. %2 records processed.",
        [
          1 => $errors,
          2 => $counter,
        ]
      ) . "<br />" . implode("<br />", $output)
    );
  }
  // If no errors and records processed ..
  if ($counter) {
    return civicrm_api3_create_success(
      ts('%1 contribution record(s) were processed.',
        [1 => $counter]
      ) . "<br />" . implode("<br />", $output)
    );
  }
  // No records processed
  return civicrm_api3_create_success(ts('No contribution records were processed.'));
}
