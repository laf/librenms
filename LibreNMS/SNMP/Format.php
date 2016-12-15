<?php
/**
 * Format.php
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

namespace LibreNMS\SNMP;

class Format
{

    public static function oid($oid, $base_oid, $index, $extra_oid)
    {
        return OIDData::make(compact('oid', 'base_oid', 'index', 'extra_oid'));
    }

    /**
     * @param string $type
     * @param mixed $value
     * @return OIDData
     */
    public static function generic($type, $value)
    {
        return OIDData::make(array(
            'type' => $type,
            'value' => $value
        ));
    }


    /**
     * @param int $integer
     * @param string $description
     * @return OIDData
     */
    public static function integerType($integer, $description = null)
    {
        return OIDData::make(array(
            'type' => 'integer',
            'value' => intval($integer),
            'description' => $description
        ));
    }

    /**
     * @param string $string
     * @return OIDData
     */
    public static function stringType($string)
    {
        return OIDData::make(array(
            'type'   => 'string',
            'value'  => $string
        ));
    }

    /**
     * @param int $seconds
     * @param string $readable
     * @return OIDData
     */
    public static function timeticksType($seconds, $readable = null)
    {
        return OIDData::make(array(
            'type' => 'timeticks',
            'value' => intval($seconds),
            'readable' => $readable
        ));
    }
}
