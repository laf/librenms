<?php
/**
 * arp-table.php
 *
 * Collect arp table entries from devices and update the database
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package    LibreNMS
 * @link       http://librenms.org
 * @copyright  2016 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

use LibreNMS\SNMP;

if (key_exists('vrf_lite_cisco', $device) && (count($device['vrf_lite_cisco'])!=0)) {
    $vrfs_lite_cisco = $device['vrf_lite_cisco'];
} else {
    $vrfs_lite_cisco = array(array('context_name'=>null));
}

foreach ($vrfs_lite_cisco as $vrf) {
    $device['context_name'] = $vrf['context_name'];

    // collect data from device and database
    $arp_data = SNMP::walk($device, array('ipNetToMediaPhysAddress', 'ipNetToPhysicalPhysAddress'), 'IP-MIB')
        ->map(function ($entry) use ($device) {
            return parse_arp_data($entry, $device);
        })->filter(null);


    $sql = "SELECT M.* from ipv4_mac AS M, ports AS I WHERE M.port_id=I.port_id AND I.device_id=? AND M.context_name=?";
    $params = array($device['device_id'], $device['context_name']);
    $existing_data = collect(dbFetchRows($sql, $params));


    // group data
    $live_ips = $arp_data->pluck('ipv4_address')->all();

    $arp_data = $arp_data->groupBy(function ($entry) use ($existing_data) {
        if ($existing_data->contains($entry)) {
            return 'Unchanged';
        } elseif ($existing_data->contains('ipv4_address', $entry['ipv4_address'])) {
            return 'Changed';
        }
        return 'New';
    });

    $removed_entries = $existing_data->reject(function ($entry) use ($live_ips) {
        return in_array($entry['ipv4_address'], $live_ips);
    });
    if (!$removed_entries->isEmpty()) {
        $arp_data->put('Removed', $removed_entries);
    }


    // Update database
    $arp_data->each(function ($group, $key) use ($device, $existing_data) {
        print "$key: " . count($group) . PHP_EOL;

        if ($key == 'Changed') {
            $group->each(function ($entry) use ($device, $existing_data) {
                extract($entry);
                $old_mac = $existing_data->where('ipv4_address', $ipv4_address)->collapse()->get('mac_address');
                log_event("MAC change: $ipv4_address : " . mac_clean_to_readable($old_mac) . ' -> '
                    . mac_clean_to_readable($mac_address), $device, 'interface', $port_id);
                dbUpdate(
                    array('mac_address' => $mac_address),
                    'ipv4_mac',
                    'port_id=? AND ipv4_address=? AND context_name=?',
                    array($port_id, $ipv4_address, $context_name)
                );
            });
        } elseif ($key == 'Removed') {
            $group->each(function ($entry) {
                dbDelete(
                    'ipv4_mac',
                    '`port_id` = ? AND `mac_address`=? AND `ipv4_address`=? AND `context_name`=?',
                    array_values($entry)
                );
            });
        } elseif ($key == 'New') {
            dbBulkInsert($group, 'ipv4_mac');
        }
    });

    unset($arp_data, $existing_data, $removed_entries, $live_ips);
    unset($device['context_name']);
}
unset($vrfs_lite_cisco);
