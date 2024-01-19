<?php

class CRM_OfflineRecurring_BAO_RecurringContribution extends CRM_OfflineRecurring_DAO_RecurringContribution {

  /**
   * Class constructor.
   */
  public function __construct() {
    parent::__construct();
  }

  /**
   * The function creates entry in civicrm offline recur table.
   *
   * @param int $recurId
   *
   */
  public static function add($recurId) {
    $dao = new CRM_OfflineRecurring_BAO_RecurringContribution();
    $dao->contribution_recur_id = $recurId;
    $dao->find(TRUE);
    $dao->save();
  }

  /**
  * The function creates entry in civicrm offline recur table.
  *
  * @param int $recurId
  *
  * @return boolean
  */
  public static function isOfflineRecur($recurId) {
    $dao = new CRM_OfflineRecurring_BAO_RecurringContribution();
    $dao->contribution_recur_id = $recurId;
    if ($dao->find(TRUE)) {
      return TRUE;
    }
    return FALSE;
  }
}
