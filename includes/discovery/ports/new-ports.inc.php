<?php

echo 'New ports disco' . PHP_EOL;

$data_oids = array(
    'ifName',
    'ifDescr',
    'ifAlias',
    'ifMtu',
    'ifSpeed',
    'ifHighSpeed',
    'ifType',
    'ifPhysAddress',
    'ifPromiscuousMode',
    'ifConnectorPresent',
);

$hc_mappings = array(
    'ifHCInOctets' => 'ifInOctets',
    'ifHCOutOctets' => 'ifOutOctets',
    'ifHCInUcastPkts' => 'ifInUcastPkts',
    'ifHCOutUcastPkts' => 'ifOutUcastPkts',
    'ifHCInBroadcastPkts' => 'ifInBroadcastPkts',
    'ifHCOutBroadcastPkts' => 'ifOutBroadcastPkts',
    'ifHCInMulticastPkts' => 'ifInMulticastPkts',
    'ifHCOutMulticastPkts' => 'ifOutMulticastPkts',
);

// Build SNMP Cache Array
$port_stats = array();
$port_stats = snmpwalk_cache_oid($device, 'ifEntry', $port_stats, 'IF-MIB');
$port_stats = snmpwalk_cache_oid($device, 'ifXEntry', $port_stats, 'IF-MIB');

// End Building SNMP Cache Array
d_echo($port_stats);

// By default libreNMS uses the ifIndex to associate ports on devices with ports discoverd/polled
// before and stored in the database. On Linux boxes this is a problem as ifIndexes may be
// unstable between reboots or (re)configuration of tunnel interfaces (think: GRE/OpenVPN/Tinc/...)
// The port association configuration allows to choose between association via ifIndex, ifName,
// or maybe other means in the future. The default port association mode still is ifIndex for
// compatibility reasons.
$port_association_mode = $config['default_port_association_mode'];
if ($device['port_association_mode']) {
    $port_association_mode = get_port_assoc_mode_name($device['port_association_mode']);
}

// Build array of ports in the database and an ifIndex/ifName -> port_id map
$ports_mapped = get_ports_mapped($device['device_id']);
$ports_db = $ports_mapped['ports'];
$ports    = $ports_db; // Create a copy of the ports in the DB

// New interface detection
foreach ($port_stats as $ifIndex => $port) {
    // Store ifIndex in port entry and prefetch ifName as we'll need it multiple times
    $port['ifIndex'] = $ifIndex;
    $ifName = $port['ifName'];
    $ifAlias = $port['ifAlias'];
    $ifDescr = $port['ifDescr'];

    // Get port_id according to port_association_mode used for this device
    $port_id = get_port_id($ports_mapped, $port, $port_association_mode);
    if (is_port_valid($port, $device)) {
        // Port newly discovered?
        if (! is_array($ports_db[$port_id])) {
            $port_id         = dbInsert(array('device_id' => $device['device_id'], 'ifIndex' => $ifIndex, 'ifName' => $ifName, 'ifAlias' => $ifAlias, 'ifDescr' => $ifDescr), 'ports');
            $ports[$port_id] = dbFetchRow('SELECT * FROM `ports` WHERE `device_id` = ? AND `port_id` = ?', array($device['device_id'], $port_id));
            echo 'Adding: '.$ifName.'('.$ifIndex.')('.$port_id.')';
        } // Port re-discovered after previous deletion?
        elseif ($ports_db[$port_id]['deleted'] == '1') {
            dbUpdate(array('deleted' => '0'), 'ports', '`port_id` = ?', array($port_id));
            $ports_db[$port_id]['deleted'] = '0';
            echo 'U';
        } else {
            echo '.';
        }

        // We've seen it. Remove it from the cache.
        unset($ports_db[$port_id]);
    } else {
        // Port vanished (mark as deleted)
        if (is_array($ports_db[$port_id])) {
            if ($ports_db[$port_id]['deleted'] != '1') {
                dbUpdate(array('deleted' => '1'), 'ports', '`port_id` = ?', array($port_id));
                $ports_db[$port_id]['deleted'] = '1';
                echo '-';
            }
        }

        echo 'X';
    }//end if
}//end foreach

unset(
    $ports_mapped,
    $port
);

// End New interface detection
// Interface Deletion
// If it's in our $ports_l list, that means it's not been seen. Mark it deleted.
foreach ($ports_db as $ifIndex => $port) {
    if ($ports_db[$port['port_id']]['deleted'] == '0') {
        dbUpdate(array('deleted' => '1'), 'ports', '`port_id` = ?', array($port['port_id']));
        echo '-'.$ifIndex;
    }
}

// End interface deletion
echo "\n";

foreach ($ports as $port) {
    $port_id = $port['port_id'];
    $ifIndex = $port['ifIndex'];
    $port_info_string = 'Port ' . $port['ifName'] . ': ' . $port['ifDescr'] . " ($ifIndex / #$port_id) ";
    /* We don't care for disabled ports, go on */
    if ($port['disabled'] == 1) {
        echo "$port_info_string disabled.\n";
        continue;
    }

    echo $port_info_string;
    if ($port_stats[$ifIndex]) {
        $this_port = &$port_stats[$ifIndex];

        if ($device['os'] == 'vmware' && preg_match('/Device ([a-z0-9]+) at .*/', $this_port['ifDescr'], $matches)) {
            $this_port['ifDescr'] = $matches[1];
        }

        // When devices do not provide ifAlias data, populate with ifDescr data if configured
        if ($this_port['ifAlias'] == '' || $this_port['ifAlias'] == null) {
            $this_port['ifAlias'] = $this_port['ifDescr'];
            d_echo('Using ifDescr as ifAlias');
        }

        if ($this_port['ifName'] == '' || $this_port['ifName'] == null) {
            $this_port['ifName'] = $this_port['ifDescr'];
            d_echo('Using ifDescr as ifName');
        }

        // rewrite the ifPhysAddress
        if (strpos($this_port['ifPhysAddress'], ':')) {
            list($a_a, $a_b, $a_c, $a_d, $a_e, $a_f) = explode(':', $this_port['ifPhysAddress']);
            $this_port['ifPhysAddress']              = zeropad($a_a).zeropad($a_b).zeropad($a_c).zeropad($a_d).zeropad($a_e).zeropad($a_f);
        }
        
        if (isset($this_port['ifHighSpeed']) && is_numeric($this_port['ifHighSpeed'])) {
            d_echo('ifHighSpeed ');
            $this_port['ifSpeed'] = ($this_port['ifHighSpeed'] * 1000000);
        } elseif (isset($this_port['ifSpeed']) && is_numeric($this_port['ifSpeed'])) {
            d_echo('ifSpeed ');
        } else {
            d_echo('No ifSpeed ');
            $this_port['ifSpeed'] = 0;
        }

        // Update IF-MIB data
        $tune_port = false;
        foreach ($data_oids as $oid) {
            if ($oid == 'ifAlias') {
                if ($attribs['ifName:'.$port['ifName']]) {
                    $this_port['ifAlias'] = $port['ifAlias'];
                }
            }
            if ($oid == 'ifSpeed' || $oid == 'ifHighSpeed') {
                if ($attribs['ifSpeed:'.$port['ifName']]) {
                    $this_port[$oid] = $port[$oid];
                }
            }

            if ($port[$oid] != $this_port[$oid] && !isset($this_port[$oid])) {
                $port['update'][$oid] = array('NULL');
                log_event($oid . ': ' . $port[$oid] . ' -> NULL', $device, 'interface', 4, $port['port_id']);
                if ($debug) {
                    d_echo($oid.': '.$port[$oid].' -> NULL ');
                } else {
                    echo $oid.' ';
                }
            } elseif ($port[$oid] != $this_port[$oid]) {
                // if the value is different, update it
                // set the update data
                $port['update'][$oid] = $this_port[$oid];

                log_event($oid . ': ' . $port[$oid] . ' -> ' . $this_port[$oid], $device, 'interface', 3, $port['port_id']);
                if ($debug) {
                    d_echo($oid.': '.$port[$oid].' -> '.$this_port[$oid].' ');
                } else {
                    echo $oid.' ';
                }
            }
        }//end foreach

        $port['update'] = array();
        $port['update_extended'] = array();
        $port['state']  = array();

        // use HC values if they are available
        foreach ($hc_mappings as $hc_oid => $if_oid) {
            if (isset($this_port[$hc_oid]) && $this_port[$hc_oid]) {
                d_echo("$hc_oid ");
                $this_port[$if_oid] = $this_port[$hc_oid];
            } else {
                d_echo("$if_oid ");
            }
        }

        // Update Database
        if (count($port['update'])) {
            $updated = dbUpdate($port['update'], 'ports', '`port_id` = ?', array($port_id));
            d_echo("$updated updated");
        }

    }
}

// Clear Variables Here
unset($port_stats);
unset($ports_db);
