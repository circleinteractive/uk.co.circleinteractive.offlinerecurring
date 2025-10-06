CREATE TABLE IF NOT EXISTS `civicrm_contribution_recur_offline` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary ID',
  `contribution_recur_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Conditional foreign key to civicrm_contribution_recur id. Each contribution made in connection with a recurring contribution carries a foreign key to the recurring contribution record. This assumes we can track these processor initiated events.',
  PRIMARY KEY (`id`),
  KEY `FK_civicrm_contribution_recur_offline_contribution_recur_id` (`contribution_recur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Constraints for table `civicrm_contribution_recur_offline`
--
ALTER TABLE `civicrm_contribution_recur_offline`
 ADD CONSTRAINT `FK_civicrm_contribution_recur_offline_contribution_recur_id` FOREIGN KEY (`contribution_recur_id`) REFERENCES `civicrm_contribution_recur` (`id`) ON DELETE CASCADE;
