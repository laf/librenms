<?php
/**
 * NetSnmp.php
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

namespace LibreNMS\SNMP\Engines;

use LibreNMS\SNMP\Contracts\SnmpEngine;

class NetSnmp extends Base
{

    /**
     * @param array $device
     * @param string|array $oids single or array of oids to walk
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return string exact results from snmpget
     */
    public function getRaw($device, $oids, $options = null, $mib = null, $mib_dir = null)
    {
        var_dump($mib);
        $cmd = gen_snmpget_cmd($device, $oids, $options, $mib, $mib_dir);
        d_echo($cmd . PHP_EOL);
        $output = shell_exec($cmd);

        d_echo($output . PHP_EOL);

        return $output;
    }

    /**
     * @param array $device
     * @param string $oid single oid to walk
     * @param string $options Options to send to snmpwalk
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return string exact results from snmpwalk
     */
    public function walkRaw($device, $oid, $options = null, $mib = null, $mib_dir = null)
    {
        return shell_exec(gen_snmpwalk_cmd($device, $oid, $options, $mib, $mib_dir));
    }
}
