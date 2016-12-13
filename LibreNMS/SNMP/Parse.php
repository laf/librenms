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
        $parts = collect(explode('.', $oid));
        if (count($parts) > 1) {
            // if the oid contains a name, index is the first thing after that
            if (str_contains($parts->first(), '::')) {
                return collect(array(
                    'base_oid' => $parts->first(),
                    'index' => $parts[1],
                    'extra_oid' => $parts->slice(2)->values()->map(function ($item) {
                        return trim($item, '"');
                    })->all()
                ));
            }
            // otherwise, assume index is the last item
            return collect(array(
                'index' => $parts->last(),
                'base_oid' => implode('.', $parts->slice(0, count($parts) - 1)->all())
            ));
        } else {
            // there are no segments in this oid
            return collect(array(
                'base_oid' => $oid
            ));
        }
    }

    public static function rawValue($data)
    {
        list($type, $value) = explode(': ', $data, 2);
        return Parse::value($type, $value);
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

        $tmp_oid = '';
        $tmp_value = '';
        while ($line !== false) {
            if (str_contains($line, ' = ')) {
                list($tmp_oid, $tmp_value) = explode(' = ', $line, 2);
            } else {
                $tmp_value .= "\n" . $line;
            }
//            echo "Parsed: $tmp_oid <> $tmp_value\n";
            $line = strtok($separator);

            if ($line === false || str_contains($line, ' = ')) {
                $result[] = OIDData::makeRaw($tmp_oid, $tmp_value);
            }
        }

        return DataSet::make($result);
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
