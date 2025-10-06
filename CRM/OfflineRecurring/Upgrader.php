<?php
use CRM_OfflineRecurring_ExtensionUtil as E;

/**
 * Collection of upgrade steps.
 */
class CRM_OfflineRecurring_Upgrader extends CRM_Extension_Upgrader_Base {

  /**
   * Example: Run an upgrade with a query that touches many (potentially
   * millions) of records by breaking it up into smaller chunks.
   *
   * @return TRUE on success
   * @throws Exception
   */
  public function upgrade_1700() {
    $this->ctx->log->info('Upgrade to 1.7');
    $this->addTask(ts('Delete old schedule job for offline recurring.'), 'deleteJob');
    $this->addTask(ts('Rename column name.'), 'alterTable');
    $this->addTask(ts('Update all contribution recur status to In Progress for empty status_id.'), 'updateStatus');
    return TRUE;
  }

  /**
   * Delete previous schedule job.
   *
   * @param \CRM_Queue_TaskContext $ctx
   *
   * @return bool
   */
  public function deleteJob(CRM_Queue_TaskContext $ctx) {
    $sql = "DELETE * FROM civicrm_job WHERE api_action = 'process_offline_recurring_payments'";
    CRM_Core_DAO::executeQuery($sql);
    return TRUE;
  }

  /**
  * Alter civicrm_contribution_recur_offline.
  *
  * @param \CRM_Queue_TaskContext $ctx
  *
  * @return bool
  */
  public function alterTable(CRM_Queue_TaskContext $ctx) {
    $sqls = [
      "ALTER TABLE civicrm_contribution_recur_offline DROP PRIMARY KEY",
      "ALTER TABLE civicrm_contribution_recur_offline
        ADD `id` int(10) UNSIGNED COMMENT 'Primary ID',
        CHANGE `recur_id` `contribution_recur_id` int(10) UNSIGNED DEFAULT NULL,
        ADD PRIMARY KEY (`id`),
        ADD KEY `FK_civicrm_contribution_recur_offline_contribution_recur_id` (`contribution_recur_id`)",
      "ALTER TABLE civicrm_contribution_recur_offline
        CHANGE `id` `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT",
      "ALTER TABLE `civicrm_contribution_recur_offline`
        ADD CONSTRAINT `FK_civicrm_contribution_recur_offline_contribution_recur_id` FOREIGN KEY (`contribution_recur_id`) REFERENCES `civicrm_contribution_recur` (`id`) ON DELETE CASCADE",
    ];
    foreach ($sqls as $sql) {
      CRM_Core_DAO::executeQuery($sql);
    }
    return TRUE;
  }

  /**
  * Update contribution recur status.
  *
  * @param \CRM_Queue_TaskContext $ctx
  *
  * @return bool
  */
  public function updateStatus(CRM_Queue_TaskContext $ctx) {
    $inProgressStatusId = CRM_Core_PseudoConstant::getKey(
      'CRM_Contribute_BAO_Contribution',
      'contribution_status_id',
      'In Progress'
    );
    $sql = "UPDATE civicrm_contribution_recur ccr
      INNER JOIN civicrm_contribution_recur_offline ccro
        ON ccro.contribution_recur_id = ccr.id
      SET ccr.contribution_status_id = {$inProgressStatusId}
      WHERE ccr.contribution_status_id IS NULL
    ";
    CRM_Core_DAO::executeQuery($sql);
    return TRUE;
  }

}
