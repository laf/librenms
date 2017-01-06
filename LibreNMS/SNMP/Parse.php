<?php
/**
 * Parse.php
 *
 * Helpers for parsing SNMP data
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

use LibreNMS\SNMP;

class Parse
{

    public static function rawOID($oid)
    {
        // TODO: extract format
        $parts = collect(explode('.', $oid));
        if (count($parts) > 1) {
            // if the oid contains a name, index is the first thing after that
            if (str_contains($parts->first(), '::')) {
                return OIDData::make(array(
                    'oid' => $oid,
                    'base_oid' => $parts->first(),
                    'index' => $parts[1],
                    'error' => SNMP::ERROR_NONE,
                    'extra_oid' => $parts->slice(2)->values()->map(function ($item) {
                        return trim($item, '"');
                    })->all()
                ));
            }
            // otherwise, assume index is the last item
            return OIDData::make(array(
                'oid' => $oid,
                'base_oid' => implode('.', $parts->slice(0, count($parts) - 1)->all()),
                'index' => $parts->last(),
                'error' => SNMP::ERROR_NONE
            ));
        } else {
            // there are no segments in this oid
            return OIDData::make(array(
                'oid' => $oid,
                'base_oid' => $oid,
                'error' => SNMP::ERROR_NONE
            ));
        }
    }

    /**
     * @param string $message message to parse to error code
     * @return int LibreNMS\SNMP error code
     */
    public static function errorMessage($message)
    {
        if (starts_with($message, 'Timeout: No Response from ')) {
            return SNMP::ERROR_UNREACHABLE;
        }
        return -1;
    }

    /**
     * @param string $rawData
     * @return DataSet
     */
    public static function rawOutput($rawData)
    {
        $result = array();
        $separator = "\r\n";
        $line = strtok($rawData, $separator);

        $unreachable = array(
            ': Unknown host (',
            'Timeout: No Response from '
        );
        if (str_contains($line, $unreachable)) {
            return Format::unreachable($line);
        }
        $tmp_oid = '';
        $tmp_value = '';
        while ($line !== false) {
            // if line contains =, parse oid and value, otherwise append value
            if (str_contains($line, ' = ')) {
                list($tmp_oid, $tmp_value) = explode(' = ', $line, 2);
            } else {
                $tmp_value .= "\n" . $line;
            }

            // get the next line
            $line = strtok($separator);

            // if the next line is parsable or we reached the end, append OIDData to results
            // skip invalid lines that don't contain : in the value
            if (($line === false || str_contains($line, ' = '))) {
                if ($tmp_value != 'No more variables left in this MIB View (It is past the end of the MIB tree)') {
                    $result[] = OIDData::makeRaw($tmp_oid, $tmp_value);
                }
            }
        }

        return DataSet::make($result);
    }

    public static function rawValue($raw_value)
    {
        if (!str_contains($raw_value, ': ')) {
            if ($raw_value == 'No Such Instance currently exists at this OID') {
                return OIDData::make(array(
                    'raw_value' => $raw_value,
                    'error' => SNMP::ERROR_NO_SUCH_OID
                ));
            }
            return OIDData::make(array(
                'raw_value' => $raw_value,
                'error' => SNMP::ERROR_PARSE_ERROR
            ));
        }

        list($type, $value) = explode(': ', $raw_value, 2);
        return Parse::value($type, $value);
    }

    public static function value($type, $value)
    {

        $type = strtolower($type);
        $function = $type . 'Type';
        if (method_exists(__CLASS__, $function)) {
            return forward_static_call(array(__CLASS__, $function), $value);
        }

        return Format::generic($type, $value);
    }

    public static function snmprec($entry)
    {
        list($oid, $type, $data) = explode('|', $entry, 3);
        return OIDData::makeType($oid, self::getSnmprecTypeString($type), $data);
    }

    private static function getSnmprecTypeString($type)
    {
        // FIXME: dos this belong here?
        // FIXME: strings here might be wrong for some types
        static $types = array(
            2 => 'integer32',
            4 => 'string',
            '4x' => 'hex-string',
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

    /**
     * @param $input
     * @return OIDData
     */
    public static function integerType($input)
    {
        if (is_numeric($input)) {
            Format::integerType(intval($input));
        }

        if (preg_match('/(.+)\(([0-9]+)\)/', $input, $matches)) {
            $descr = $matches[1];
            $int = $matches[2];
            return Format::integerType($int, $descr);
        }

        return Format::integerType(null);
    }

    /**
     * @param $input
     * @return OIDData
     */
    public static function stringType($input)
    {
        return Format::stringType(trim($input, "\""));
    }

    /**
     * @param $input
     * @return OIDData
     */
    public static function timeticksType($input)
    {
        if (is_numeric($input)) {
            return Format::timeticksType($input);
        } else {
            $matched = preg_match('/\(([0-9]+)\) (.+)/', $input, $matches);
            if ($matched) {
                return Format::timeticksType($matches[1], $matches[2]);
            }
        }
        return Format::timeticksType(null);
    }
}
