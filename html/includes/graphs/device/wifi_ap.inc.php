<?php

$ap = dbFetchRow("SELECT * FROM `wifi_aps` WHERE `device_id` = ? AND `id` = ? AND `essid` = ?", array($device['device_id'], $vars['id'], $vars['essid']));
$rrd_filename = rrd_name($device['hostname'], "unifi-ap-traffic-". $ap['id']. "-" . $ap['essid']);
$rrd_list[0] =
        array(
            'filename' => $rrd_filename,
            'ds' => 'rx_bytes',
            'descr' => "Rx Bytes",
        );
$rrd_list[1] =
        array(
            'filename' => $rrd_filename,
            'ds' => 'tx_bytes',
            'descr' => "Tx Bytes",
        );
require 'includes/graphs/generic_multi_line.inc.php';
