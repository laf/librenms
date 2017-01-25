<?php

/*
 * LibreNMS Network Management and Monitoring System
 * Copyright (C) 2006-2011, Observium Developers - http://www.observium.org
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * See COPYING for more details.
 */

use LibreNMS\SNMP;

unset($poll_device);

$oids = array(
    'sysUpTime.0',
    'sysLocation.0',
    'sysContact.0 ',
    'sysName.0',
    'sysDescr.0',
    'sysObjectID.0',
);
$sys_data = SNMP::get($device, $oids, 'SNMPv2-MIB')->map(function($item) {
    // Remove leading & trailing backslashes added by VyOS/Vyatta/EdgeOS (still valid?)
    $item['value'] = trim($item['value'], '\\');
    return $item;
});

$poll_device = $sys_data->pluck('value', 'name')->all();  // legacy compat
$poll_device['sysName'] = strtolower($poll_device['sysName']);

if (!empty($agent_data['uptime'])) {
    list($uptime) = explode(' ', $agent_data['uptime']);
    $uptime = intval($uptime);
    echo "Using UNIX Agent Uptime ($uptime)\n";
}

if (empty($uptime)) {
    $uptime_oids = array(
        'SNMP-FRAMEWORK-MIB::snmpEngineTime.0',
        'HOST-RESOURCES-MIB:hrSystemUptime.0'
    );

    $uptime_data = SNMP::get($device, $uptime_oids)
        ->push($sys_data->where('name', 'sysUpTime')->first());


    d_echo($uptime_data->pluck('seconds', 'name')->all());

    if ($device['os'] == 'windows') {
        // Windows used to return invalid hrSystemUptime
        d_echo("Not using hrSystemUptime\n");
        $uptime_data = $uptime_data->reject(function ($item) {
            return $item['name'] == 'hrSystemUptime';
        });
    }

    if (!empty($config['os'][$device['os']]['bad_snmpEngineTime'])) {
        // some OS return bad snmpEngineTime, reject them
        d_echo("Not using snmpEngineTime\n");
        $uptime_data = $uptime_data->reject(function ($item) {
            return $item['name'] == 'snmpEngineTime';
        });
    }

    $uptime = $uptime_data->max('seconds');
}

if (empty($config['os'][$device['os']]['bad_uptime'])) {
    if ($uptime < $device['uptime']) {
        log_event('Device rebooted after ' . formatUptime($device['uptime']), $device, 'reboot', $device['uptime']);
    }

    $tags = array(
        'rrd_def' => 'DS:uptime:GAUGE:600:0:U',
    );
    data_update($device, 'uptime', $tags, $uptime);

    $graphs['uptime'] = true;

    echo 'Uptime: ' . formatUptime($uptime) . "\n";

    $update_array['uptime'] = $uptime;
}//end if

// Save results of various polled values to the database
foreach (array('sysContact', 'sysObjectID', 'sysName', 'sysDescr') as $elem) {
    if ($poll_device[$elem] && $poll_device[$elem] != $device[$elem]) {
        $update_array[$elem] = $poll_device[$elem];
        log_event("$elem -> " . $poll_device[$elem], $device, 'system');
    }
}

// Rewrite sysLocation if there is a mapping array
if (!empty($poll_device['sysLocation'])) {
    $poll_device['sysLocation'] = rewrite_location($poll_device['sysLocation']);
}

if ($poll_device['sysLocation'] && $device['location'] != $poll_device['sysLocation'] && $device['override_sysLocation'] == 0) {
    $update_array['location'] = $poll_device['sysLocation'];
    log_event('Location -> ' . $poll_device['sysLocation'], $device, 'system');
}

if ($config['geoloc']['latlng'] === true) {
    location_to_latlng($device);
}
