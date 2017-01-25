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

class OIDData extends BaseDataSet
{

    /** @property string     oid         the full oid */
    /** @property string     base_oid    the base oid, the first part of the oid */
    /** @property int        index       the index for this object */
    /** @preperty array      extra_oid   any oid parts after the index, split by the . */
    /** @property string     type        the type for of the value (string, oid, integer32, etc) */
    /** @property string|int value       the value of this object */
    /** @property string     description if the value is an enum, this is the description of that value. Valid for type integer32 */
    /** @property int        seconds     the value of this OID in seconds. Valid for type timeticks and some integer32 */
    /** @preoprty string     readable    human readable time value as return by netsnmp.  Valid for type timeticks */

    public static function makeRaw($oid, $raw_value)
    {
        return Parse::rawValue($raw_value)
            ->merge(Parse::rawOID($oid));
    }

    public static function makeType($oid, $type, $value)
    {
        return Parse::value($type, $value)
            ->merge(Parse::rawOID($oid));
    }

    public static function makeError($error, $message = null)
    {
        $result = parent::makeError($error, $message);
        $result->put('value', null);
        return $result;
    }

    /**
     * Magic getter function
     * Get array values as though they were properties
     *
     * @param $key
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }
}
