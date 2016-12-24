<?php
/**
 * DataSet.php
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
use LibreNMS\SNMP;

class DataSet extends Collection
{
    private $error = SNMP::ERROR_NONE;

    /**
     * Create an empty DataSet with an SNMP error code
     * likely SNMP::ERROR_UNREACHABLE
     *
     * @param int $error
     * @return DataSet
     */
    public static function makeError($error)
    {
        $new = self::make();
        $new->error = $error;
        return $new;
    }

    /**
     * Check if this DataSet has an error
     *
     * @return bool
     */
    public function hasError()
    {
        return $this->error != SNMP::ERROR_NONE;
    }

    /**
     * Get the error code, see SNMP
     *
     * @return int
     */
    public function getError()
    {
        return $this->error;
    }

    public function toRawString()
    {
        return $this->reduce(function ($entry, $output) {
            return $output . $entry['oid'] . ' = ' . strtoupper($entry['type']) . ': '. $entry['value'] . PHP_EOL;
        }, '');
    }

    public function getByIndex($index = null)
    {
        return $this->getByField('index', $index);
    }

    public function getByBaseOID($base_oid = null)
    {
        return $this->getByField('base_oid', $base_oid);
    }

    public function getByField($field, $index = null)
    {
        return $this->optionalFilter($field, $index)->groupBy(function ($item) use ($field) {
            return $item[$field];
        });
    }

    public function filterBaseOID($base_oid)
    {
        return $this->optionalFilter('base_oid', $base_oid);
    }


    /**
     * Optionally filter this object.
     * Do nothing if $index is null
     *
     * @param string|null $index
     * @return Collection
     */
    private function optionalFilter($field, $index)
    {
        if ($index === null) {
            return $this;
        }

        return $this->filter(function ($item) use ($field, $index) {
            return $item[$field] === $index;
        });
    }
}
