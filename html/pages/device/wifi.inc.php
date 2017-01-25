<?php

foreach (dbFetchRows("SELECT * FROM `wifi_aps` WHERE `device_id` = ?", array($device['device_id'])) as $ap) {
    $graph_title         = $ap['essid'];
    $graph_array['type'] = 'device_wifi_ap';
    $graph_array['id']   = $ap['id'];
    include 'includes/print-device-graph.php';
}
