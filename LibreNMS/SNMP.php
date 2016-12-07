<?php
/**
 * SNMP.php
 *
 * SNMP Wrapper class
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

namespace LibreNMS\SNMP;

use LibreNMS\SNMP\Contracts\SnmpEngine;

class SNMP
{
    private static $engine = null;

    //TODO use interface
    private static function getInstance(SnmpEngine $engine = null)
    {
        // Note, because this is static, there will only be one SnmpEngine instance
        if ($engine === null) {
            self::$engine = new NetSNMP(); // default engine
        } else {
            self::$engine = $engine;
        }

        return self::$engine;
    }

    // ---- Public Interface ----

    /**
     * @param array $device
     * @param string|array $oids single or array of oids to get
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return array array or object of results, format to be determined
     */
    public static function get($device, $oids, $mib = null, $mib_dir = null)
    {
        // return cached result or get from device
        // parse data
        // return formatted array or object
    }

    /**
     * @param array $device
     * @param string|array $oids single or array of oids to walk
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return string exact results from snmpget
     */
    public static function getRaw($device, $oids, $options = null, $mib = null, $mib_dir = null)
    {
        // snmpget data from device
    }


    /**
     * @param array $device
     * @param string|array $oids single or array of oids to walk
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return array array or object of results, format to be determined
     */
    public static function walk($device, $oids, $mib = null, $mib_dir = null)
    {
        // return cached result or get from device
        // parse data
        // return formatted array or object
    }

    /**
     * @param array $device
     * @param string $oid single oid to walk
     * @param string $options Options to send to snmpwalk
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return string exact results from snmpwalk
     */
    public static function walkRaw($device, $oid, $options = null, $mib = null, $mib_dir = null)
    {
        // snmpwalk data from device
    }
}
