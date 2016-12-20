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
use LibreNMS\SNMP;
use LibreNMS\SNMP\DataSet;
use LibreNMS\SNMP\Parse;

class Mock extends FormattedBase
{
    /** @var Collection */
    private $snmpRecData;

    public function __construct()
    {
        $this->snmpRecData = new Collection;
    }

    /**
     * Generate fake device for testing.
     *
     * @param string $community name of the snmprec file to load
     * @param int $port port for snmpsim, should be defined by SNMPSIM
     * @return array
     */
    public static function genDevice($community = null, $port = 11161)
    {
        return array(
            'device_id' => 1,
            'hostname' => '127.0.0.1',
            'snmpver' => 'v2c',
            'port' => $port,
            'timeout' => 3,
            'retries' => 0,
            'snmp_max_repeaters' => 10,
            'community' => $community,
            'os' => 'generic',
            'os_group' => '',
        );
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

//        $self = $this;
//        $numeric_oids = $oids->map(function ($oid) use ($self, $device, $mib, $mib_dir) {
//            return $self->translateNumeric($device, $oid, $mib, $mib_dir);
//        });
        $numeric_oids = SNMP::translateNumeric($device, $oids);
        var_dump($numeric_oids);

        $data = $this->getSnmpRec($device['community']);

        $result = $data->only($numeric_oids)->values();

        return $result;
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

        $numeric_oids = array_map(function ($oid) {
            return ltrim($oid, '.');
        }, (array)SNMP::translateNumeric($device, $oids, $mib, $mib_dir));

        $output = $data->filter(function ($oid) use ($numeric_oids) {
            return starts_with($oid['oid'], $numeric_oids);
        });


        return $output;
    }
}
