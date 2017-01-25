<?php

use LibreNMS\Unifi;
$sites = dbFetchRows('SELECT * FROM `wifi_sites` WHERE `device_id`=?', array($device['device_id']));
d_echo($sites);
$ap_stats = array(
                  'ap_mac', 'bssid', 'channel', 'essid', 'radio', 'rx_bytes', 'tx_bytes',
                  'rx_dropped', 'tx_dropped', 'rx_errors', 'tx_errors', 'rx_packets', 'tx_packets',
                  'tx_power', 'tx_retries', 'state', 'up'
                 );
$rrd_def = array(
    'DS:rx_bytes:COUNTER:600:0:U',
    'DS:tx_bytes:COUNTER:600:0:U',
    'DS:rx_dropped:COUNTER:600:0:U',
    'DS:tx_dropped:COUNTER:600:0:U',
    'DS:rx_errors:COUNTER:600:0:U',
    'DS:tx_errors:COUNTER:600:0:U',
    'DS:rx_packets:COUNTER:600:0:U',
    'DS:tx_packets:COUNTER:600:0:U',
);

if (is_array($sites)) {

    $unifi = new Unifi($device['attribs']['override_Unifi_user'], $device['attribs']['override_Unifi_pass'], $device['attribs']['override_Unifi_url']);

    foreach ($sites as $site) {
        $aps = $unifi->aps($site['site_name']);
        $aps = $aps[0]->vap_table;
        $update = array();
        foreach ($aps as $ap) {
            foreach ($ap_stats as $index => $stats) {
                $update[$stats] = $ap->$stats;
            }
            if (is_array($update)) {
                dbUpdate($update, 'wifi_aps', '`id` = ? AND `essid` = ?', array($ap->id, $ap->essid));
                $fields = array(
                    'rx_bytes'   => $ap->rx_bytes,
                    'tx_bytes'   => $ap->tx_bytes,
                    'rx_dropped'   => $ap->rx_dropped,
                    'tx_dropped'   => $ap->tx_dropped,
                    'rx_errors'   => $ap->rx_errors,
                    'tx_errors'   => $ap->tx_errors,
                    'rx_packets'   => $ap->rx_packets,
                    'tx_packets'   => $ap->tx_packets,
                );

                $tags = compact('rrd_def');
                data_update($device, 'unifi-ap-traffic-'.$ap->id.'-'.$ap->essid, $tags, $fields);
            }
        }
    }

}
