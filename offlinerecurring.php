<?php

/** 
 * CiviCRM Offline Recurring Payment Extension for CiviCRM - Circle Interactive 2013
 * Original author: rajesh
 * http://sourceforge.net/projects/civicrmoffline/
 * 
 * Ported to CiviCRM extension by andyw@circle, 2013
 * Rewritten for CiviCRM 4.5 - andyw@circle, Jan 2015
 */

/**
 * Get CRM version as floating point number - eg: 4.5
 * so we can do less than / greater than comparison
 * @return float
 */
function _offlinerecurring_getCRMVersion() {
    $crmversion = explode('.', ereg_replace('[^0-9\.]','', CRM_Utils_System::version()));
    return floatval($crmversion[0] . '.' . $crmversion[1]);
}

/**
 * Implementation of hook_civicrm_config
 */
function offlinerecurring_civicrm_config(&$config) {

    $template = &CRM_Core_Smarty::singleton();
    $ddRoot   = dirname(__FILE__);
    $subDir   = _offlinerecurring_getCRMVersion() >= 4.5 ? 'new' : 'legacy';
    $ddDir    = "$ddRoot/$subDir/templates";
    
    if (is_array($template->template_dir)) {
        array_unshift($template->template_dir, $ddDir);
    } else {
        $template->template_dir = [$ddDir, $template->template_dir];
    }
    
    # also fix php include path
    set_include_path("$ddRoot/$subDir/php" . PATH_SEPARATOR . get_include_path());
    
}

/**
 * Implementation of hook_civicrm_disable
 */
function offlinerecurring_civicrm_disable() {
    CRM_Core_DAO::executeQuery("
        DELETE FROM civicrm_job WHERE api_action = 'process_offline_recurring_payments'
    ");
}

/**
 * Implementation of hook_civicrm_enable
 */
function offlinerecurring_civicrm_enable() {

    // Create entry in civicrm_job table for cron call
    $version = _offlinerecurring_getCRMVersion();

    if ($version >= 4.3) {
        // looks like someone finally wrote an api ..
        civicrm_api('job', 'create', [
            'version'       => 3,
            'name'          => ts('Process Offline Recurring Payments'),
            'description'   => ts('Processes any offline recurring payments that are due'),
            'run_frequency' => 'Hourly',
            'api_entity'    => 'job',
            'api_action'    => 'process_offline_recurring_payments',
            'is_active'     => 0
        ]);
    } else {
        // otherwise, this ..
        CRM_Core_DAO::executeQuery("
            INSERT INTO civicrm_job (
               id, domain_id, run_frequency, last_run, name, description, 
               api_prefix, api_entity, api_action, parameters, is_active
            ) VALUES (
               NULL, %1, 'Hourly', NULL, 'Process Offline Recurring Payments', 
               'Processes any offline recurring payments that are due',
               'civicrm_api3', 'job', 'process_offline_recurring_payments', '', 0
            )
            ", [
                1 => [CIVICRM_DOMAIN_ID, 'Integer']
            ]
        );
    }

    // We need some way of keeping track of which contribution_recurs were created by us.
    // I'm creating a second table for that for now, maybe not the best way, but we
    // can revisit this later perhaps
    CRM_Core_DAO::executeQuery("
        CREATE TABLE IF NOT EXISTS `civicrm_contribution_recur_offline` (
          `recur_id` int(10) unsigned NOT NULL,
          PRIMARY KEY (`recur_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=latin1;       
    ");

}

/**
 * Implementation of hook_civicrm_uninstall
 */
function offlinerecurring_civicrm_uninstall() {
    CRM_Core_DAO::executeQuery("DROP TABLE civicrm_contribution_recur_offline");   
}

/**
 * Implementation of hook_civicrm_xmlMenu
 */
function offlinerecurring_civicrm_xmlMenu(&$files) {
    $subDir  = _offlinerecurring_getCRMVersion() >= 4.5 ? 'new' : 'legacy';
    $files[] = dirname(__FILE__) . "/$subDir/php/Recurring/xml/Menu/RecurringPayment.xml";
}

/**
 * Implementation of hook_civicrm_permission
 */
function offlinerecurring_civicrm_permission( &$permissions ) {
  $prefix = ts('CiviCRM') . ': '; // name of extension or module
  $permissions['add offline recurring payments'] = $prefix . ts('add offline recurring payments');
  $permissions['edit offline recurring payments'] = $prefix . ts('edit offline recurring payments');
}

# conditionally include other hooks / functions, depending
# on which version of CiviCRM we're running on
require_once dirname(__FILE__) . (
    _offlinerecurring_getCRMVersion() >= 4.5 ? 
        '/offlinerecurring_new.php' : 
        '/offlinerecurring_legacy.php'
);

