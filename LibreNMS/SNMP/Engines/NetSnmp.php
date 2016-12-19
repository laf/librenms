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

use Illuminate\Support\Collection;
use LibreNMS\SNMP\Contracts\SnmpTranslator;

class NetSnmp extends RawBase implements SnmpTranslator
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
        $oids = is_array($oids) ? implode(' ', $oids) : $oids;
        return $this->exec(gen_snmpget_cmd($device, $oids, $options, $mib, $mib_dir));
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
        return $this->exec(gen_snmpwalk_cmd($device, $oid, $options, $mib, $mib_dir));
    }

    /**
     * @param array $device
     * @param string|array $oids
     * @param string $options
     * @param string $mib
     * @param string $mib_dir
     * @return string
     * @internal param string $oid
     */
    public function translate($device, $oids, $options = null, $mib = null, $mib_dir = null)
    {
        $oids = collect((array)$oids);
        $cmd  = 'snmptranslate '.$this->getMibDir($mib_dir, $device);
        if (isset($mib)) {
            $cmd .= " -m $mib";
        }
        $cmd .= " $options ";
        $cmd .= $oids->implode(' ');
        $cmd .= ' 2>/dev/null';

        $output = collect(explode("\n\n", $this->exec($cmd)));

        $result = $oids->combine(array_pad($output->all(), $oids->count(), null));

        return $result->count() == 1 ? $result->first() : $result->all();
    }

    /**
     * @param array $device
     * @param string|array $oids
     * @param string $mib
     * @param string $mib_dir
     * @return Collection
     * @throws \Exception
     */
    public function translateNumeric($device, $oids, $mib = null, $mib_dir = null)
    {
        $self = $this; // php5.3 bs
        $oids = collect($oids)->map(function ($oid) use ($self, $mib) {
            return $self->formatOid($oid, $mib);
        });


        $result = collect();
        foreach ($oids as $oid) {
            if (self::isNumericOid($oid)) {
                $result[$oid] = $oid;
            } elseif ($this->oidIsCached($oid)) {
                $result[$oid] = $this->getCachedOid($oid);
            } else {
                $result[$oid] = null;
            }
        }

        $oids_to_translate = $result->filter(function ($item) {
            return is_null($item);
        })->keys();

        $translated = $this->translate($device, $oids_to_translate->all(), '-IR', $mib, $mib_dir);
//        $translated = $this->runTranslate($oids_to_translate, $mib, $mib_dir);

        $final = $result->merge($translated);
        var_dump($final);

        return $final->values();
    }

    private function oidIsCached($oid)
    {
        return array_key_exists($oid, self::$cached_translations);
    }

    private function getCachedOid($oid)
    {
        self::$cached_translations[$oid];
    }

    private function formatOid($oid, $mib)
    {
        if (!str_contains($oid, '::') && $mib !== null && !str_contains($mib, ':') && !self::isNumericOid($oid)) {
            return "$mib::$oid";
        }
        return $oid;
    }

    private function exec($cmd)
    {
        global $debug;
        c_echo('SNMP[%c'.$cmd."%n]\n", $debug);
        $output = rtrim(shell_exec($cmd));
        d_echo("[$output]" . PHP_EOL);
        return $output;
    }

    private static $cached_translations = array(
        'SNMPv2-MIB::sysDescr.0' => '.1.3.6.1.2.1.1.1.0',
        'SNMPv2-MIB::sysObjectID.0' => '.1.3.6.1.2.1.1.2.0',
        'ENTITY-MIB::entPhysicalDescr.1' => '.1.3.6.1.2.1.47.1.1.1.1.2.1',
        'ENTITY-MIB::entPhysicalMfgName.1' => '.1.3.6.1.2.1.47.1.1.1.1.12.1',
        'SML-MIB::product-Name.0' => '.1.3.6.1.4.1.2.6.182.3.3.1.0',
        'GAMATRONIC-MIB::psUnitManufacture.0' => '.1.3.6.1.4.1.6050.1.1.2.0',
    );

    /**
     * Generate the mib search directory argument for snmpcmd
     * If null return the default mib dir
     * If $mibdir is empty '', return an empty string
     *
     * @param string $mibdir should be the name of the directory within $config['mib_dir']
     * @param array $device
     * @return string The option string starting with -M
     */
    private function getMibDir($mibdir = null, $device = array())
    {
        global $config;

        // get mib directories from the device
        $extra_dir = '';
        if (file_exists($config['mib_dir'] . '/' . $device['os'])) {
            $extra_dir .= $config['mib_dir'] . '/' . $device['os'] . ':';
        }

        if (isset($device['os_group']) && file_exists($config['mib_dir'] . '/' . $device['os_group'])) {
            $extra_dir .= $config['mib_dir'] . '/' . $device['os_group'] . ':';
        }

        if (isset($config['os_groups'][$device['os_group']]['mib_dir'])) {
            if (is_array($config['os_groups'][$device['os_group']]['mib_dir'])) {
                foreach ($config['os_groups'][$device['os_group']]['mib_dir'] as $k => $dir) {
                    $extra_dir .= $config['mib_dir'] . '/' . $dir . ':';
                }
            }
        }

        if (isset($config['os'][$device['os']]['mib_dir'])) {
            if (is_array($config['os'][$device['os']]['mib_dir'])) {
                foreach ($config['os'][$device['os']]['mib_dir'] as $k => $dir) {
                    $extra_dir .= $config['mib_dir'] . '/' . $dir . ':';
                }
            }
        }

        if (is_null($mibdir)) {
            return " -M $extra_dir${config['mib_dir']}";
        }

        if (empty($mibdir)) {
            return '';
        }

        // automatically set up includes
        return " -M $extra_dir{$config['mib_dir']}/$mibdir:{$config['mib_dir']}";
    }
}
