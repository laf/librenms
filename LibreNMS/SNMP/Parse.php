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

class Parse
{

    public static function rawOID($oid)
    {
        $result = collect();
        $parts = collect(explode('.', $oid));
        if (count($parts) > 1) {
            $result->put('index', $parts->last());
        }
        $result->put('base_oid', implode('.', $parts->slice(0, count($parts)-1)->all()));

        return $result;
    }

    public static function rawValue($data)
    {
        $data = explode(': ', $data, 2);

        $func = strtolower($data[0]) . 'Type';
        if (method_exists(__CLASS__, $func)) {
            return forward_static_call(array(__CLASS__, $func), $data[1]);
        }

        return OIDData::make();
    }

    /**
     * Get a typed number from a string
     *
     * @param string $number
     * @return float|int
     */
    public static function number($number)
    {
        return ctype_digit($number) ? intval($number) : floatval($number);
    }

    /**
     * @param string $rawData
     * @return DataSet
     */
    public static function rawResult($rawData)
    {
        $result = array();
        $separator = "\r\n";
        $line = strtok($rawData, $separator);

//        var_dump($rawData);

        $tmp_oid = '';
        $tmp_value = '';
        while ($line !== false) {
            if (str_contains($line, ' = ')) {
                list($tmp_oid, $tmp_value) = explode(' = ', $line, 2);
            } else {
                $tmp_value .= $line;
            }
            echo "Parsed: $tmp_oid <> $tmp_value\n";
            $line = strtok($separator);

            if ($line === false || str_contains($line, ' = ')) {
                $result[] = OIDData::makeRaw($tmp_oid, $tmp_value);
            }
        }

        return DataSet::make($result);
    }

    /**
     * @param $value
     * @return \Illuminate\Support\Collection
     */
    public static function stringType($value)
    {
        $value = trim($value, "\"");
        return collect(array(
            'type'   => 'string',
            'string' => $value,
            'value'  => $value
        ));
    }

    /**
     * @param $value
     * @return \Illuminate\Support\Collection
     */
    public static function timeticksType($value)
    {
        $matched = preg_match('/\(([0-9]+)\) ([0-9:\.]+)/', $value, $matches);
        if ($matched) {
            return collect(array(
                'type'    => 'timeticks',
                'seconds' => $matches[1],
                'time'    => $matches[2],
                'value'   => $matches[1]
            ));
        }
        $seconds = self::number($value);
        return collect(array(
            'type'    => 'timeticks',
            'seconds' => $seconds,
            'value'   => $seconds
        ));
    }

    /**
     * @param $value
     * @return \Illuminate\Support\Collection
     */
    public static function oidType($value)
    {
        return collect(array(
            'type'   => 'oid',
            'string' => $value,
            'value'  => $value
        ));
    }
}
