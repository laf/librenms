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

namespace LibreNMS;

use LibreNMS\SNMP\Contracts\SnmpEngine;
use LibreNMS\SNMP\Contracts\SnmpTranslator;
use LibreNMS\SNMP\DataSet;
use LibreNMS\SNMP\Engines\NetSnmp;

class SNMP
{
    /** @var SnmpEngine */
    private static $engine;
    private static $translator;

    /**
     * Get the SnmpEngine instance.  This is called automatically by \LibreNMS\SNMP.
     * NetSNMP is currently the default implementation.
     * The private instance will be set to any passed engine,
     * discarding the old engine and any data it might have cached.
     *
     * @param SnmpEngine|null $engine
     * @return SnmpEngine|NetSnmp
     */
    public static function getInstance(SnmpEngine $engine = null)
    {
        if ($engine !== null) {
            self::$engine = $engine;
        }

        if (self::$engine === null) {
            self::$engine = new NetSNMP(); // default engine
        }

        return self::$engine;
    }

    /**
     * @param SnmpTranslator|null $translator
     * @return SnmpTranslator
     */
    public static function getTranslator(SnmpTranslator $translator = null)
    {
        if ($translator !== null) {
            self::$translator = $translator;
        }

        if (self::$translator === null) {
            if (self::getInstance() instanceof SnmpTranslator) {
                // try to use the SnmpEngine
                self::$translator = self::$engine;
            } else {
                self::$translator = new NetSnmp();
            }
        }

        return self::$translator;
    }

    // ---- Public Interface ----

    /**
     * @param array $device
     * @param string|array $oids single or array of oids to get
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return DataSet collection of results
     */
    public static function get($device, $oids, $mib = null, $mib_dir = null)
    {
        // return cached result or get from device
        return self::getInstance()->get($device, $oids, $mib, $mib_dir);
    }

    /**
     * @param array $device
     * @param string|array $oids single or array of oids to walk
     * @param null $options Options to send to snmpget
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return string exact results from snmpget
     */
    public static function getRaw($device, $oids, $options = null, $mib = null, $mib_dir = null)
    {
        return self::getInstance()->getRaw($device, $oids, $options, $mib, $mib_dir);
    }


    /**
     * @param array $device
     * @param string|array $oids single or array of oids to walk
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return DataSet collection of results
     */
    public static function walk($device, $oids, $mib = null, $mib_dir = null)
    {
        // return cached result or get from device
        return self::getInstance()->walk($device, $oids, $mib, $mib_dir);
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
        return self::getInstance()->walkRaw($device, $oid, $options, $mib, $mib_dir);
    }


    /**
     * @param array $device
     * @param string $oid
     * @param string $options
     * @param string $mib
     * @param string $mib_dir
     * @return string
     */
    public static function translate($device, $oid, $options = null, $mib = null, $mib_dir = null)
    {
        return self::getTranslator()->translate($device, $oid, $options, $mib, $mib_dir);
    }

    /**
     * @param array $device
     * @param string|array $oids
     * @param string $mib
     * @param string $mib_dir
     * @return string|array
     */
    public static function translateNumeric($device, $oids, $mib = null, $mib_dir = null)
    {
        return self::getTranslator()->translateNumeric($device, $oids, $mib, $mib_dir);
    }
}
