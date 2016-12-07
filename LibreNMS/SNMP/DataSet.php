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

class DataSet extends Collection
{
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
