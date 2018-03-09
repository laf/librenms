<?php
/**
 * Schema.php
 *
 * Class for querying the schema
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
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\DB;

use LibreNMS\Config;
use Symfony\Component\Yaml\Yaml;

class Schema
{
    private static $relationship_blacklist = [
        'devices_perms',
        'bill_perms',
        'ports_perms',
    ];

    /**
     * Get the primary key column(s) for a table
     *
     * @param string $table
     * @return string|array if a single column just the name is returned, otherwise an array of column names
     */
    public function getPrimaryKey($table)
    {
        $schema = $this->getSchema();

        $columns = $schema[$table]['Indexes']['PRIMARY']['Columns'];

        if (count($columns) == 1) {
            return reset($columns);
        }

        return $columns;
    }

    public function getSchema()
    {
        if (!isset($this->schema)) {
            $file = Config::get('install_dir') . '/misc/db_schema.yaml';
            $this->schema = Yaml::parse(file_get_contents($file));
        }

        return $this->schema;
    }

    public function findRelationshipPath($start, $target = 'devices')
    {
        d_echo("Searching for target: $target, starting with $start\n");

        if ($start == $target) {
            // um, yeah, we found it...
            return [$target];
        }

        $path = $this->findPathRecursive([$start], $target);

        if ($path === false) {
            return $path;
        }

        if (count($path) == 1) {
            return true;
        }

        return $path;
    }

    private function findPathRecursive(array $tables, $target, $history = [])
    {
        $relationships = $this->getTableRelationships();

        d_echo("Starting Tables: " . json_encode($tables) . PHP_EOL);
        if (!empty($history)) {
            $tables = array_diff($tables, $history);
            d_echo("Filtered Tables: " . json_encode($tables) . PHP_EOL);
        }

        foreach ($tables as $table) {
            $table_relations = $relationships[$table];
            $path = [$table];
            d_echo("Searching $table: " . json_encode($table_relations) . PHP_EOL);

            if (!empty($table_relations)) {
                if (in_array($target, $relationships[$table])) {
                    d_echo("Found in $table\n");
                    return $path; // found it
                } else {
                    $recurse = $this->findPathRecursive($relationships[$table], $target, array_merge($history, $tables));
                    if ($recurse) {
                        $path = array_merge($recurse, $path);
                        return $path;
                    }
                }
            } else {
                $relations = array_keys(array_filter($relationships, function ($related) use ($table) {
                    return in_array($table, $related);
                }));

                d_echo("Dead end at $table, searching for relationships " . json_encode($relations) . PHP_EOL);
                $recurse = $this->findPathRecursive($relations, $target, array_merge($history, $tables));
                if ($recurse) {
                    $path = array_merge($recurse, $path);
                    return $path;
                }
            }
        }

        return false;
    }

    public function getTableRelationships()
    {
        if (!isset($this->relationships)) {
            $schema = $this->getSchema();

            $relations = array_column(array_map(function ($table, $data) {
                $columns = array_column($data['Columns'], 'Field');

                $related = array_filter(array_map(function ($column) use ($table) {
                    $guess = $this->getTableFromKey($column);
                    if ($guess != $table) {
                        return $guess;
                    }

                    return null;
                }, $columns));

                return [$table, $related];
            }, array_keys($schema), $schema), 1, 0);

            // filter out blacklisted tables
            $this->relationships = array_diff_key($relations, array_flip(self::$relationship_blacklist));
        }

        return $this->relationships;
    }

    public function getTableFromKey($key)
    {
        if (ends_with($key, '_id')) {
            // hardcoded
            if ($key == 'app_id') {
                return 'applications';
            }

            // try to guess assuming key_id = keys table
            $guessed_table = substr($key, 0, -3);

            if (!ends_with($guessed_table, 's')) {
                if (ends_with($guessed_table, 'x')) {
                    $guessed_table .= 'es';
                } else {
                    $guessed_table .= 's';
                }
            }

            if (array_key_exists($guessed_table, $this->getSchema())) {
                return $guessed_table;
            }
        }

        return null;
    }

    public function columnExists($table, $column)
    {
        $schema = $this->getSchema();

        $fields = array_column($schema[$table]['Columns'], 'Field');

        return in_array($column, $fields);
    }
}
