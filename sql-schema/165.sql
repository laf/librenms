ALTER TABLE `applications` ADD INDEX(`device_id`);
ALTER TABLE `alert_schedule` ADD INDEX(`recurring`);
ALTER TABLE `alert_rules` ADD INDEX(`disabled`);
ALTER TABLE `alert_map` ADD INDEX(`target`);
ALTER TABLE `alert_map` ADD INDEX(`rule`);
