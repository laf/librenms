<?php
/**
 * PhpSnmp.php
 *
 * -Description-
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

use LibreNMS\SNMP\DataSet;
use LibreNMS\SNMP\Format;
use LibreNMS\SNMP\OIDData;
use SNMP;

class PhpSnmp extends FormattedBase
{
    /** @var SNMP $snmp */
    private $snmp;
    private $versionTable = array(
        'v1'  => SNMP::VERSION_1,
        'v2c' => SNMP::VERSION_2C,
        'v3'  => SNMP::VERSION_3
    );

    private function initSnmp($device)
    {
        if (isset($this->snmp) &&
            ($this->snmp->info['hostname'] == gethostbyname($device['hostname']) . ':' . $device['port'])) {
            // already initialized to this device
            return;
        }

        $this->snmp = new SNMP(
            $this->versionTable[$device['snmpver']],
            $device['hostname'] . ':' . $device['port'],
            $device['community'],
            (prep_snmp_setting($device, 'timeout') ?: 1) * 1000000,
            prep_snmp_setting($device, 'retries') ?: 5
        );
    }

    //TODO: mibs/mib directories

    public function get($device, $oids, $mib = null, $mib_dir = null)
    {
        global $debug;
        $this->initSnmp($device);
        c_echo('SNMP[%c'.implode(' ', (array)$oids)."%n]\n", $debug);
        $result = @$this->snmp->get((array)$oids);
        d_echo($result);


        if ($result === false) {
            $error = $this->snmp->getError();
            d_echo("Error: $error\n");
            if (starts_with($error, 'No response from')) {
                return Format::unreachable($error);
            }
            throw new \Exception($this->snmp->getError());
        }
        return $this->formatDataSet($result);
    }

    public function walk($device, $oids, $mib = null, $mib_dir = null)
    {
        global $debug;
        $this->initSnmp($device);
        $output = DataSet::make();

        foreach ((array)$oids as $oid) {
            c_echo('SNMP[%c'.$oid."%n]\n", $debug);
            $data = @$this->snmp->walk($oid);
            d_echo($data);

            if ($data === false) {
                $error = $this->snmp->getError();
                d_echo("Error: $error\n");
                if (starts_with($error, 'No response from')) {
                    return Format::unreachable($error);
                }
                throw new \Exception($error);
            }
            $output = $output->merge($this->formatDataSet($data));
        }

        return $output;
    }

    /**
     * Collapse snmp output array into a NetSnmp compatible string
     *
     * @param array $data
     * @return string
     */
    private function formatString($data)
    {
        return collect($data)->mapWithKeys(function ($value, $oid) {
            return array("$oid = $value");
        })->implode(PHP_EOL);
    }

    /**
     * @param array $data
     * @return DataSet
     */
    private function formatDataSet($data)
    {
        return DataSet::make(collect($data)->mapWithKeys(function ($value, $oid) {
            return array(OIDData::makeRaw($oid, $value));
        }));
    }
}
