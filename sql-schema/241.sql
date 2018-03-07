CREATE TABLE alert_group_map (id INT PRIMARY KEY AUTO_INCREMENT, rule_id INT NOT NULL, group_id INT NOT NULL);
CREATE INDEX alert_group_map_rule_id_group_id_index ON alert_group_map (rule_id, group_id);
INSERT INTO `alert_group_map` (`rule_id`, `group_id`) SELECT `rule`, SUBSTRING(`target`, 2) as `group_id` FROM `alert_map` WHERE `target` LIKE 'g%';
DELETE FROM `alert_map` WHERE `target` LIKE 'g%';
ALTER TABLE alert_map CHANGE rule rule_id INT(11) NOT NULL;
ALTER TABLE alert_map CHANGE target device_id INT(11) NOT NULL;
ALTER TABLE alert_map RENAME TO alert_device_map;
CREATE INDEX alert_device_map_rule_id_device_id_index ON alert_device_map (rule_id, device_id);


