<?php
/**
 * Mock.php
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

use Illuminate\Support\Collection;
use LibreNMS\SNMP\Contracts\SnmpEngine;
use LibreNMS\SNMP\DataSet;
use LibreNMS\SNMP\Format;
use LibreNMS\SNMP\OIDData;
use LibreNMS\SNMP\Parse;

class Mock extends FormattedBase
{
    /** @var Collection  */
    private $snmpRecData;

    public function __construct()
    {
        $this->snmpRecData = new Collection;
    }

    private function getSnmpRec($community)
    {
        global $config;

        if ($this->snmpRecData->has($community)) {
            return $this->snmpRecData[$community];
        }

        $data = DataSet::make();

        $contents = file_get_contents($config['install_dir'] . "/tests/snmpsim/$community.snmprec");
        $line = strtok($contents, "\r\n");
        while ($line !== false) {
            $entry = Parse::snmprec($line);
            $data[$entry['oid']] = $entry;

            $line = strtok("\r\n");
        }

        $this->snmpRecData[$community] = $data;
        return $data;
    }

    /**
     * @param array $device
     * @param string|array $oids single or array of oids to get
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return DataSet collection of results
     */
    public function get($device, $oids, $mib = null, $mib_dir = null)
    {
        $oids = collect(is_string($oids) ? explode(' ', $oids) : $oids);

        $numeric_oids = $oids->map(array(__CLASS__, 'translate'));
//        var_dump($oids, $numeric_oids);

        $data = $this->getSnmpRec($device['community']);

        $result = $data->only($numeric_oids->all())->values();

        return $result;
    }

    public static function genDevice($community)
    {
        return array(
            'device_id' => 1,
            'hostname' => '127.0.0.1',
            'snmpver' => 'v2c',
            'port' => 11161,
            'timeout' => 3,
            'retries' => 0,
            'snmp_max_repeaters' => 10,
            'community' => $community,
            'os' => 'generic',
            'os_group' => '',
        );
    }

    private function getTypeString($type)
    {
        // FIXME: strings here might be wrong for some types
        static $types = array(
            2 => 'integer32',
            4 => 'string',
            5 => 'null',
            6 => 'oid',
            64 => 'ipaddress',
            65 => 'counter32',
            66 => 'gauge32',
            67 => 'timeticks',
            68 => 'opaque',
            70 => 'counter64'
        );
         // FIXME: is the default right here?
        return $types[$type];
    }

    private static $cached_translations = array(
        'SNMPv2-MIB::sysDescr.0' => '1.3.6.1.2.1.1.1.0',
        'SNMPv2-MIB::sysObjectID.0' => '1.3.6.1.2.1.1.2.0',
        'ENTITY-MIB::entPhysicalDescr.1' => '1.3.6.1.2.1.47.1.1.1.1.2.1',
        'ENTITY-MIB::entPhysicalMfgName.1' => '1.3.6.1.2.1.47.1.1.1.1.12.1',
        'SML-MIB::product-Name.0' => '1.3.6.1.4.1.2.6.182.3.3.1.0',
        'GAMATRONIC-MIB::psUnitManufacture.0' => '1.3.6.1.4.1.6050.1.1.2.0',
    );

    public function translate($device, $oid, $mib = null, $mibdir = null)
    {
        global $config;

        // check cache
        if (isset(self::$cached_translations[$oid])) {
            return self::$cached_translations[$oid];
        }

        // check if is numeric oid
        if ($this->isNumericOid($oid)) {
            return ltrim($oid, '.');
        }

        // translate
        $cmd = "snmptranslate -IR -On $oid";
        $cmd .= ' -M ' . (isset($mibdir) ? $config['mib_dir'] . ":".$config['mib_dir']."/$mibdir" : $config['mib_dir']);
        if (isset($mib) && $mib) {
            $cmd .= " -m $mib";
        }

        $number = shell_exec($cmd);

        if (empty($number)) {
            throw new \Exception('Could not translate oid: ' . $oid . PHP_EOL . 'Tried: ' . $cmd);
        }

        return trim($number, ". \n\r");
    }

    /**
     * @param array $device
     * @param string|array $oids single or array of oids to walk
     * @param string $mib Additional mibs to search, optionally you can specify full oid names
     * @param string $mib_dir Additional mib directory, should be rarely needed, see definitions to add per os mib dirs
     * @return DataSet collection of results
     */
    public function walk($device, $oids, $mib = null, $mib_dir = null)
    {
        $oids = collect((array)$oids);
        $data = $this->getSnmpRec($device['community']);

        $self = $this; // ugliness for php 5.3
        $numeric_oids = $oids->map(function ($oid) use ($self, $device, $mib, $mib_dir) {
            return $self->translateNumeric($device, $oid, $mib, $mib_dir);
        });

        $output = $data->filter(function ($_, $key) use ($numeric_oids) {
            return starts_with($key, $numeric_oids);
        });


        return $output;
    }

    /**
     * @param array $device
     * @param string $oid
     * @param string $mib
     * @param string $mib_dir
     * @return string
     */
    public function translateNumeric($device, $oid, $mib = null, $mib_dir = null)
    {
        // TODO: Implement translateNumeric() method.
    }


}
