<?php
/**
 * QueryBuilderParser.php
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
 * @copyright  2018 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Alerting;

use LibreNMS\Config;
use Symfony\Component\Yaml\Yaml;

class QueryBuilderParser implements \JsonSerializable
{
    private static $legacy_operators = [
        '=' => 'equal',
        '!=' => 'not_equal',
        '~' => 'regex',
        '!~' => 'not_regex',
        '<' => 'less',
        '>' => 'greater',
        '<=' => 'less_or_equal',
        '>=' => 'greater_or_equal',
    ];
    private static $operators = [
        'equal' => "=",
        'not_equal' => "!=",
        'in' => "IN (?)",
        'not_in' => "NOT IN (_REP_)",
        'less' => "<",
        'less_or_equal' => "<=",
        'greater' => ">",
        'greater_or_equal' => ">=",
        'begins_with' => "ILIKE",
        'not_begins_with' => "NOT ILIKE",
        'contains' => "ILIKE",
        'not_contains' => "NOT ILIKE",
        'ends_with' => "ILIKE",
        'not_ends_with' => "NOT ILIKE",
        'is_empty' => "=''",
        'is_not_empty' => "!=''",
        'is_null' => "IS NULL",
        'is_not_null' => "IS NOT NULL",
        'regex' => 'REGEXP',
        'not_regex' => 'NOT REGEXP',
    ];
    private static $like_operators = [
        'begins_with',
        'not_begins_with',
        'contains',
        'not_contains',
        'ends_with',
        'not_ends_with',
    ];

    private $builder = [];
    private $tables = [];

    private function __construct(array $builder)
    {
        $this->builder = $builder;
        $this->tables = $this->findTables($builder);
    }

    // FIXME macros
    public function findTables($rules)
    {
        $tables = [];

        foreach ($rules['rules'] as $rule) {
            if (array_key_exists('rules', $rule)) {
                $tables = array_merge($tables, $this->findTables($rule));
            } elseif (str_contains($rule['field'], '.')) {
                list($table, $column) = explode('.', $rule['field']);
                $tables[] = $table;
            }
        }

        return array_keys(array_flip($tables));
    }

    public static function fromJson($json)
    {
        if (!is_array($json)) {
            $json = json_decode($json, true);
        }

        return new static($json);
    }

    public static function fromOld($query)
    {
        $condition = null;
        $rules = [];
        $filter = new QueryBuilderFilter();

        $split = array_chunk(preg_split('/(&&|\|\|)/', $query, -1, PREG_SPLIT_DELIM_CAPTURE), 2);

        foreach ($split as $chunk) {
            list($rule_text, $rule_operator) = $chunk;
            if (!isset($condition)) {
                // only allow one condition.  Since old rules had no grouping, this should hold logically
                $condition = ($rule_operator == '||' ? 'OR' : 'AND');
            }

            list($field, $op, $value) = preg_split('/ *([!=<>~]{1,2}) */', trim($rule_text), 2,
                PREG_SPLIT_DELIM_CAPTURE);
            $field = ltrim($field, '%');

            // for rules missing values just use '= 1'
            $operator = isset(self::$legacy_operators[$op]) ? self::$legacy_operators[$op] : 'equal';
            if (is_null($value)) {
                $value = '1';
            } else {
                $value = trim($value, '"');

                // value is a field, mark it with backticks
                if (starts_with($value, '%')) {
                    $value = '`' . ltrim($value, '%') . '`';
                }
            }

            $filter_item = $filter->getFilter($field);

            $type = $filter_item['type'];
            $input = isset($filter_item['input']) ? $filter_item['input'] : 'text';

            $rules[] = [
                'id' => $field,
                'field' => $field,
                'type' => $type,
                'input' => $input,
                'operator' => $operator,
                'value' => $value,
            ];
        }

        $builder = [
            'condition' => $condition,
            'rules' => $rules,
            'valid' => true,
        ];

        return new static($builder);
    }

    public function getRules()
    {

    }

    public function toSql($expand = false)
    {
        if (empty($this->builder) || !array_key_exists('condition', $this->builder)) {
            return null;
        }

        $result = [];
        foreach ($this->builder['rules'] as $rule) {
            if (array_key_exists('condition', $rule)) {
                $result[] = $this->parseGroup($rule);
            } else {
                $result[] = $this->parseRule($rule);
            }
        }

        return implode(" {$this->builder['condition']} ", $result);
    }

    private function parseGroup($rule)
    {
        $group_rules = [];

        foreach ($rule['rules'] as $group_rule) {
            if (array_key_exists('condition', $group_rule)) {
                $group_rules[] = $this->parseGroup($group_rule);
            } else {
                $group_rules[] = $this->parseRule($group_rule);
            }
        }

        $sql = implode(" {$rule['condition']} ", $group_rules);
        return "($sql)";
    }

    private function parseRule($rule)
    {
        $op = self::$operators[$rule['operator']];
        $value = $rule['value'];

        if (starts_with($value, '`') && ends_with($value, '`')) {
            // pass through value such as field
            $value = trim($value, '`');

        } elseif ($rule['type'] != 'integer') {
            $value = "\"$value\"";
        }

        $sql = "{$rule['field']} $op $value";

        return $sql;
    }

    public function generateGlue($target = 'device_id')
    {
        if (array_key_exists('devices', $this->tables)) {
            return 'devices.device_id = ?';
        }

        $schema = Yaml::parse(file_get_contents(Config::get('install_dir') . '/misc/db_schema.yaml'));
        $schema = array_map(function ($data) {
            return array_column($data['Columns'], 'Field');
        }, $schema);

        $glues = [];
        $possible_id_fields = [];

        $glues = $this->recursiveGlue($target, array_keys($this->tables), $schema);

        return $glues;
    }

    private function recursiveGlue($target = 'device_id', $tables, $schema, $depth = 0, $limit = 30)
    {
        if ($depth >= $limit) {
            return false;
        }

        $glues = [];

        // breadth first
        foreach ($tables as $table) {
            if (in_array($target, $schema[$table])) {
                $glues[] = [$table, $target];
                return $glues;
            }
        }

        // TODO track searched keys
        // find keys to go deeper
        foreach ($tables as $table) {
            foreach ($schema[$table] as $column) {
                if (ends_with($column, '_id')) {
                    $result = $this->recursiveGlue($column, $this->tables, $schema, $glues, $depth + 1);
                    if ($result !== false) {
                        return array_merge($glues, $result);
                    }
                }
            }
        }

        return false;
    }


    public function toArray()
    {
        return $this->builder;
    }

    /**
     * Specify data which should be serialized to JSON
     * @link http://php.net/manual/en/jsonserializable.jsonserialize.php
     * @return mixed data which can be serialized by <b>json_encode</b>,
     * which is a value of any type other than a resource.
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        return $this->builder;
    }
}
