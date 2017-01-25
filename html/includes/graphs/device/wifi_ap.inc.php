<?php

$ap = dbFetchRow("SELECT * FROM `wifi_aps` WHERE `device_id` = ? AND `id` = ? AND `essid` = ?", array($device['device_id'], $vars['id'], $vars['essid']));
$rrd_filename = rrd_name($device['hostname'], "unifi-ap-traffic-". $ap['id']. "-" . $ap['essid']);
$i=0;
        $rrd_list[$i]['filename']  = $rrd_filename;
        $rrd_list[$i]['descr_in']  = 'Rx Bytes';
        $rrd_list[$i]['descr_out'] = 'Tx Bytes';
        $rrd_list[$i]['ds_in']     = 'rx_bytes';
        $rrd_list[$i]['ds_out']    = 'tx_bytes';
$units       = 'b';
$total_units = 'B';
$colours_in  = 'greens';
$multiplier  = '1';
$colours_out = 'blues';

// $nototal = 1;
$ds_in  = 'rx_bytes';
$ds_out = 'tx_bytes';

$graph_title .= '::bits';

$colour_line_in  = '006600';
$colour_line_out = '000099';
$colour_area_in  = '91B13C';
$colour_area_out = '8080BD';

require 'includes/graphs/generic_multi_seperated.inc.php';
