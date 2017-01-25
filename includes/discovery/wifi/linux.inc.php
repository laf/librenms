<?php

use LibreNMS\Unifi;
print_r($device);exit;
$unifi = new Unifi($config['unifi_user'], $config['unifi_pass'], $config['unifi_url']);
$keep_site = array();
$keep_ap = array();
$ap_stats = array(
                  'id', 'ap_mac', 'bssid', 'channel', 'essid', 'radio', 'rx_bytes', 'tx_bytes', 
                  'rx_dropped', 'tx_dropped', 'rx_errors', 'tx_errors', 'rx_packets', 'tx_packets', 
                  'tx_power', 'tx_retries', 'state', 'up'
                 );

foreach ($unifi->sites() as $site) {
    $site_id = dbFetchCell('SELECT `wifi_site_id` FROM `wifi_sites` WHERE `device_id`=? AND `site_desc`=?', array($device['device_id'], $site->desc));
    if ($site_id < 1) {
        if ($site_id = dbInsert(array('device_id' => $device['device_id'], 'site_desc' => $site->desc, 'site_name' => $site->name), 'wifi_sites')) {
            d_echo('Added site ' . $site->desc, '+');
        }
    } else {
        d_echo('Existing site ' . $site->desc, '.');
    }
    $keep_site[] = $site->desc;
    $aps = $unifi->aps($site->name);
    $aps = $aps[0]->vap_table;
    foreach ($aps as $ap) {
        if (dbFetchCell('SELECT count(*) FROM `wifi_aps` WHERE `device_id`=? AND `id`=? AND `essid`=?', array($device['device_id'], $ap->id, $ap->essid)) < 1) {
            $insert = array('wifi_site_id' => $site_id, 'device_id' => $device['device_id']);
            foreach ($ap_stats as $stats) {
                $insert[$stats] = $ap->$stats;
            }
            if (dbInsert($insert, 'wifi_aps')) {
                d_echo('Added AP ' . $ap->essid, '+');
            }
        } else {
            d_echo('Existing AP ' . $ap->essid, '.');
        }
        $keep_ap[] = $ap->id;
    }
    dbDelete('wifi_aps', "`device_id` = ? AND `wifi_site_id` = ? AND `id` NOT IN ('".implode("','", $keep_ap)."')", array($device['device_id'], $site_id));
}

dbDelete('wifi_clients', "`device_id` = ? AND `site_desc` NOT IN ('".implode("','", $keep_site)."')", array($device['device_id']));
