ALTER TABLE `applications` ADD INDEX(`device_id`);
ALTER TABLE `alert_schedule` ADD INDEX(`recurring`);
ALTER TABLE `alert_rules` ADD INDEX(`disabled`);
ALTER TABLE `ipv4_addresses` ADD INDEX(`ipv4_address`);
ALTER TABLE `notifications_attribs` ADD INDEX( `key`, `value`);
ALTER TABLE `loadbalancer_rservers` ADD INDEX(`device_id`);
ALTER TABLE `pseudowires` ADD INDEX(`device_id`);
