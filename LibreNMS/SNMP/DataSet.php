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

class DataSet extends BaseDataSet
{
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

    public function getByName($name = null)
    {
        return $this->getByField('name', $name);
    }

    public function getByBaseOID($base_oid = null)
    {
        return $this->getByField('base_oid', $base_oid);
    }

    public function removeErrors() {
        return $this->reject(function ($oiddata) {
            return $oiddata->hasError();
        });
    }

    /**
     * Groups all items as a collection of OIDData objects by the specified field
     * Optionally filter by a specific value for that field
     *
     * @param string $field The name of the field to use for keys
     * @param mixed $field_value Only return OIDData entries that match this field value
     * @return DataSet The array of OIDData items under key values based on the specified field
     */
    public function getByField($field, $field_value = null)
    {
        return $this->optionalFilter($field, $field_value)->groupBy(function ($item) use ($field) {
            return $item[$field];
        });
    }

    public function getValuesByName() {
        return $this->getValuesByField('name');
    }

    /**
     * Gets the data in an array of keys and values
     * where the key is from the field and value is from the field value
     *
     * @param string $field The name of the field to use for keys
     * @return DataSet array of key-value pairs, key is $field, value is 'value'
     */
    public function getValuesByField($field)
    {
        //TODO worthwhile?
        return $this->pluck('value', $field);
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
