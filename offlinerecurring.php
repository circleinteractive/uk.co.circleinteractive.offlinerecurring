<?php

require_once 'offlinerecurring.civix.php';
use CRM_OfflineRecurring_ExtensionUtil as E;

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function offlinerecurring_civicrm_config(&$config) {
  _offlinerecurring_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function offlinerecurring_civicrm_xmlMenu(&$files) {
  _offlinerecurring_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function offlinerecurring_civicrm_install() {
  _offlinerecurring_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function offlinerecurring_civicrm_postInstall() {
  _offlinerecurring_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function offlinerecurring_civicrm_uninstall() {
  _offlinerecurring_civix_civicrm_uninstall();
  CRM_Core_DAO::executeQuery("DROP TABLE IF EXISTS civicrm_contribution_recur_offline");
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function offlinerecurring_civicrm_enable() {
  _offlinerecurring_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function offlinerecurring_civicrm_disable() {
  _offlinerecurring_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function offlinerecurring_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _offlinerecurring_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function offlinerecurring_civicrm_managed(&$entities) {
  _offlinerecurring_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function offlinerecurring_civicrm_caseTypes(&$caseTypes) {
  _offlinerecurring_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function offlinerecurring_civicrm_angularModules(&$angularModules) {
  _offlinerecurring_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function offlinerecurring_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _offlinerecurring_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_entityTypes
 */
function offlinerecurring_civicrm_entityTypes(&$entityTypes) {
  _offlinerecurring_civix_civicrm_entityTypes($entityTypes);
  $entityTypes[] = [
    'name'  => 'OfflineRecurringContribution',
    'class' => 'CRM_OfflineRecurring_DAO_RecurringContribution',
    'table' => 'civicrm_contribution_recur_offline',
  ];
}

/**
 * Implementation of hook_civicrm_permission()
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_permission
 */
function offlinerecurring_civicrm_permission(&$permissions) {
  $prefix = ts('CiviCRM') . ': ';
  $permissions['add offline recurring payments'] = [
    $prefix . ts('add offline recurring payments'),
    ts('Add Offline Recurring Contribution(s)'),
  ];
  $permissions['edit offline recurring payments'] = [
    $prefix . ts('edit offline recurring payments'),
    ts('Update Offline Recurring Contribution(s)'),
  ];
}

/**
 * Implementation of hook_civicrm_links()
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_links
 */
function offlinerecurring_civicrm_links($op, $objectName, $objectId, &$links, &$mask, &$values) {
  if ($objectName == 'Contribution' and $op == 'contribution.selector.recurring') {
    if (CRM_OfflineRecurring_BAO_RecurringContribution::isOfflineRecur($values['crid'])) {
      $links[0]['title'] = ts('View Offline Recurring Payment');
      $links[1]['title'] = ts('Edit Offline Recurring Payment');
      $links[1]['url'] = 'civicrm/offlinerecurring/add';
    }
  }
}
