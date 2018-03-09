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
use LibreNMS\DB\Schema;

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

    private $builder;
    private $schema;

    private function __construct(array $builder)
    {
        $this->builder = $builder;
        $this->schema = new Schema();
    }

    public function getTables($rules = null)
    {
        if (!isset($this->tables)) {
            $tables = [];

            if (is_null($rules)) {
                $rules = $this->builder['rules'];
            }

            foreach ($rules as $rule) {
                if (array_key_exists('rules', $rule)) {
                    $tables = array_merge($tables, $this->getTables($rule));
                } elseif (str_contains($rule['field'], '.')) {
                    list($table, $column) = explode('.', $rule['field']);

                    if ($table == 'macros') {
                        $tables = array_merge($tables, $this->expandMacro($rule['field'], true));
                    } else {
                        $tables[] = $table;
                    }
                }
            }

            // resolve glue tables (remove duplicates
            foreach (array_keys(array_flip($tables)) as $table) {
                $rp = $this->schema->findRelationshipPath($table);
                if (is_array($rp)) {
                    $tables = array_merge($tables, $rp);
                }
            }

            // remove duplicates
            $this->tables = array_keys(array_flip($tables));
        }

        return $this->tables;
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

                // replace regex placeholder, don't think we can safely convert to like operators
                if ($operator == 'regex' || $operator == 'not_regex') {
                    $value = str_replace('@', '.*', $value);
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

    public function toSql($expand = true)
    {
        if (empty($this->builder) || !array_key_exists('condition', $this->builder)) {
            return null;
        }

        $result = [];
        foreach ($this->builder['rules'] as $rule) {
            if (array_key_exists('condition', $rule)) {
                $result[] = $this->parseGroup($rule, $expand);
            } else {
                $result[] = $this->parseRule($rule, $expand);
            }
        }

        $sql = '';
        if ($expand) {
            $sql = 'SELECT * FROM ' . implode(',', $this->getTables());
            $sql .= ' WHERE ' . $this->generateGlue() . ' AND ';
        }
        return $sql . implode(" {$this->builder['condition']} ", $result);
    }

    private function parseGroup($rule, $expand = false)
    {
        $group_rules = [];

        foreach ($rule['rules'] as $group_rule) {
            if (array_key_exists('condition', $group_rule)) {
                $group_rules[] = $this->parseGroup($group_rule, $expand);
            } else {
                $group_rules[] = $this->parseRule($group_rule, $expand);
            }
        }

        $sql = implode(" {$rule['condition']} ", $group_rules);
        return "($sql)";
    }

    private function parseRule($rule, $expand = false)
    {
        $op = self::$operators[$rule['operator']];
        $value = $rule['value'];

        if (starts_with($value, '`') && ends_with($value, '`')) {
            // pass through value such as field
            $value = trim($value, '`');
            $value = $this->expandMacro($value); // check for macros
        } elseif ($rule['type'] != 'integer') {
            $value = "\"$value\"";
        }

        $field = $rule['field'];
        if ($expand) {
            $field = $this->expandMacro($field);
        }

        $sql = "$field $op $value";

        return $sql;
    }

    public function expandMacro($subject, $tables_only = false)
    {
        if (!str_contains($subject, 'macros.')) {
            return $subject;
        }

        $macros = Config::get('alert.macros.rule');

        $count = 0;
        $limit = 20; // replacement limit
        while ($count++ < $limit && str_contains($subject, 'macros.')) {
            $subject = preg_replace_callback('/%?macros.([^ =()]+)/', function ($matches) use ($macros) {
                $name = $matches[1];
                if (isset($macros[$name])) {
                    return $macros[$name];
                } else {
                    return $matches[0]; // this isn't a macro, don't replace
                }
            }, $subject);
        }

        if ($tables_only) {
            preg_match_all('/%([^%.]+)\./', $subject, $matches);
            return array_unique($matches[1]);
        }

        // clean leading %
        $subject = preg_replace('/%([^%.]+)\./', '$1.', $subject);

        // wrap entire macro result in parenthesis if needed
        if (!(starts_with($subject, '(') && ends_with($subject, ')'))) {
            $subject = "($subject)";
        }

        return $subject;
    }


    public function generateGlue($target = 'devices')
    {
        $tables = $this->getTables();  // get all tables in query

        $singles = [];
        $chains = [];
        foreach ($tables as $table) {
            $path = $this->schema->findRelationshipPath($table, $target);

            if ($path === true) {
                // just a single table
                $singles[] = $table;
            } elseif (is_array($path)) {
                // append glue to the glues array
                $chains[] = $path;
            }
        }

        // remove duplicate single tables
        $singles = array_unique($singles);
        $glue = [];

        // add the anchor
        if (!empty($singles)) {
            $anchor = array_shift($singles);
        } else {
            $anchor = $chains[0][0];
        }
        $glue[] = "$anchor.device_id = ?"; // start with anchor

        // add singles
        foreach ($singles as $single) {
            if ($single != $anchor) {
                $glue[] = "$anchor.device_id = $single.device_id";
            }
        }

        foreach ($chains as $chain) {
            $first = array_shift($chain);
            if ($first != $anchor) {
                $glue[] = "$anchor.device_id = $first.device_id"; // attach to anchor
            }

            foreach (array_pairs($chain) as $pair) {
                list($left, $right) = $pair;
                $glue[] = $this->getGlue($left, $right);
            }
        }

        // remove duplicates
        $glue = array_unique($glue);

        return '(' . implode(' AND ', $glue) . ')';
    }

    private function getGlue($table1, $table2)
    {
        $key2 = $this->schema->getPrimaryKey($table2);
        $key1 = $key2;

        if (!$this->schema->columnExists($table1, $key1)) {
            if (ends_with($table1, 'xes')) {
                $key1 = substr($table1, 0, -2) . '_id';
            } else {
                $key1 = preg_replace('/s$/', '_id', $table1);
            }

            if (!$this->schema->columnExists($table1, $key1)) {
                throw new \Exception("FIXME: Could not make glue from $table1 to $table2");
            }
        }

        return "$table1.$key1 = $table2.$key2";
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
