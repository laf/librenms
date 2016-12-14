<?php
/**
 * OIDData.php
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

use Illuminate\Support\Collection;

class OIDData extends Collection
{
    public static function makeRaw($oid, $raw_value)
    {
        $new = new self(compact('oid', 'raw_value'));
        return $new->merge(Parse::rawOID($oid))
            ->merge(Parse::rawValue($raw_value));
    }

    public static function makeSnmprec($entry)
    {
        list($oid, $type, $data) = collect(explode('|', $entry));
        $value = self::getTypeString($type) . ": " . $data;

        return self::makeRaw($oid, $value);
    }

    private static function getTypeString($type)
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
}
