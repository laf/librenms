<?php

/*
 * dbFacile - A Database API that should have existed from the start
 * Version 0.4.3
 *
 * This code is covered by the MIT license http://en.wikipedia.org/wiki/MIT_License
 *
 * By Alan Szlosek from http://www.greaterscope.net/projects/dbFacile
 *
 * The non-OO version of dbFacile. It's a bit simplistic, but gives you the
 * really useful bits in non-class form.
 *
 * Usage
 * 1. Connect to MySQL as you normally would ... this code uses an existing connection
 * 2. Use dbFacile as you normally would, without the object context
 * 3. Oh, and dbFetchAll() is now dbFetchRows()
 */

use LibreNMS\Exceptions\DatabaseConnectException;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Driver\PDOException;

function dbIsConnected()
{
    global $db_conn;
    if (!$db_conn) {
        return false;
    }

    return $db_conn->ping();
}

/**
 * Connect to the database.
 * Will use global $config variables if they are not sent: db_host, db_user, db_pass, db_name, db_port, db_socket
 *
 * @param string $host
 * @param string $user
 * @param string $password
 * @param string $database
 * @param string $port
 * @param string $socket
 * @return mysqli
 * @throws DatabaseConnectException
 */
function dbConnect($host = null, $user = '', $password = '', $database = '', $port = null, $socket = null)
{
    global $config, $db_conn, $debug, $vdebug;

    if (dbIsConnected()) {
        return $db_conn;
    }

    $host = empty($host) ? $config['db_host'] : $host;
    $user = empty($user) ? $config['db_user'] : $user;
    $password = empty($password) ? $config['db_pass'] : $password;
    $database = empty($database) ? $config['db_name'] : $database;
    $port = empty($port) ? $config['db_port'] : $port;
    $socket = empty($socket) ? $config['db_socket'] : $socket;

    $db_config = new Doctrine\DBAL\Configuration();

    if ($debug || $vdebug) {
        $logger = new \Doctrine\DBAL\Logging\EchoSQLLogger();
    } else {
        $logger = null;
    }
    $db_config->setSQLLogger($logger);

    $connectionParams = array(
        'user' => $user,
        'password' => $password,
        'host' => $host,
        'port' => $port,
        'charset' => 'utf8',
        'driver' => 'pdo_mysql',
        'unix_socket' => $socket,
    );

    $db_conn = \Doctrine\DBAL\DriverManager::getConnection($connectionParams, $db_config);

    try {
        $db_conn->executeQuery("USE {$config['db_name']}");
    } catch (Exception $e) {
        if (preg_match('/ Unknown database/', $e->getMessage())) {
            try {
                $db_conn->executeQuery("CREATE DATABASE {$config['db_name']} CHARACTER SET utf8 COLLATE utf8_unicode_ci");
                $db_conn->executeQuery("USE {$config['db_name']}");
            } catch (Exception $e) {
                throw new DatabaseConnectException("Could not create database: $database. " . $e->getMessage());
            }
        } else {
            throw new DatabaseConnectException($e->getMessage());
        }
    }

    dbQuery("SET NAMES 'utf8'");
    dbQuery("SET CHARACTER SET 'utf8'");
    dbQuery("SET COLLATION_CONNECTION = 'utf8_unicode_ci'");

    return $db_conn;
}

/*
 * Performs a query using the given string.
 * Used by the other _query functions.
 * */


function dbQuery($sql, $parameters = array())
{
    global $fullSql, $debug, $sql_debug, $db_conn, $config;
    $fullSql = dbMakeQuery($sql, $parameters);
    if ($debug) {
        if (php_sapi_name() == 'cli' && empty($_SERVER['REMOTE_ADDR'])) {
            if (preg_match('/(INSERT INTO `alert_log`).*(details)/i', $fullSql)) {
                echo "\nINSERT INTO `alert_log` entry masked due to binary data\n";
            } else {
                c_echo('SQL[%y'.$fullSql."%n] \n");
            }
        } else {
            $sql_debug[] = $fullSql;
        }
    }

    try {
        $result = $db_conn->executeQuery($fullSql);
    } catch (\Doctrine\DBAL\DBALException $e) {
        $result = false;
        mysql_log_error($e->getMessage());
    }

    return $result;
}//end dbQuery()

function mysql_log_error($mysql_error)
{
    global $config;
    if (isset($config['mysql_log_level']) && ((in_array($config['mysql_log_level'], array('INFO', 'ERROR')) && !preg_match('/Duplicate entry/', $mysql_error)) || in_array($config['mysql_log_level'], array('DEBUG')))) {
        if (!empty($mysql_error)) {
            logfile(date($config['dateformat']['compact']) . " MySQL Error: $mysql_error");
        }
    }
}

/*
 * Passed an array and a table name, it attempts to insert the data into the table.
 * Check for boolean false to determine whether insert failed
 * */


function dbInsert($data, $table)
{
    global $db_conn;
    $time_start = microtime(true);

    // the following block swaps the parameters if they were given in the wrong order.
    // it allows the method to work for those that would rather it (or expect it to)
    // follow closer with SQL convention:
    // insert into the TABLE this DATA
    if (is_string($data) && is_array($table)) {
        $tmp   = $data;
        $data  = $table;
        $table = $tmp;
        // trigger_error('QDB - Parameters passed to insert() were in reverse order, but it has been allowed', E_USER_NOTICE);
    }

    try {
        $id = $db_conn->insert($table, $data);
    } catch (\Doctrine\DBAL\DBALException $e) {
        $id = null;
        mysql_log_error($e->getMessage());
    }

    recordDbStatistic('insert', $time_start);
    return $id;
}//end dbInsert()


/*
 * Passed an array and a table name, it attempts to insert the data into the table.
 * $data is an array (rows) of key value pairs.  keys are fields.  Rows need to have same fields.
 * Check for boolean false to determine whether insert failed
 * */


function dbBulkInsert($data, $table)
{
    $time_start = microtime(true);
    // the following block swaps the parameters if they were given in the wrong order.
    // it allows the method to work for those that would rather it (or expect it to)
    // follow closer with SQL convention:
    // insert into the TABLE this DATA
    if (is_string($data) && is_array($table)) {
        $tmp   = $data;
        $data  = $table;
        $table = $tmp;
    }
    if (count($data) === 0) {
        return false;
    }
    if (count($data[0]) === 0) {
        return false;
    }

    $sql = 'INSERT INTO `'.$table.'` (`'.implode('`,`', array_keys($data[0])).'`)  VALUES ';
    $values ='';

    foreach ($data as $row) {
        if ($values != '') {
            $values .= ',';
        }
        $rowvalues='';
        foreach ($row as $key => $value) {
            if ($rowvalues != '') {
                $rowvalues .= ',';
            }
            $rowvalues .= "'".mres($value)."'";
        }
        $values .= "(".$rowvalues.")";
    }

    $result = dbQuery($sql.$values);

    recordDbStatistic('insert', $time_start);
    return $result;
}//end dbBulkInsert()


/*
 * Passed an array, table name, WHERE clause, and placeholder parameters, it attempts to update a record.
 * Returns the number of affected rows
 * */


function dbUpdate($data, $table, $where)
{
    global $fullSql, $db_conn;
    $time_start = microtime(true);

    // the following block swaps the parameters if they were given in the wrong order.
    // it allows the method to work for those that would rather it (or expect it to)
    // follow closer with SQL convention:
    // update the TABLE with this DATA
    if (is_string($data) && is_array($table)) {
        $tmp   = $data;
        $data  = $table;
        $table = $tmp;
        // trigger_error('QDB - The first two parameters passed to update() were in reverse order, but it has been allowed', E_USER_NOTICE);
    }

    try {
        $count = $db_conn->update($table, $data, $where);
    } catch (\Doctrine\DBAL\DBALException $e) {
        $count = null;
        mysql_log_error($e->getMessage());
    }

    recordDbStatistic('update', $time_start);
    return $count;
}//end dbUpdate()


function dbDelete($table, $where = null, $parameters = array())
{
    global $database_link;
    $time_start = microtime(true);

    $sql = 'DELETE FROM `'.$table.'`';
    if ($where) {
        $sql .= ' WHERE '.$where;
    }

    $result = dbQuery($sql, $parameters);

    recordDbStatistic('delete', $time_start);
    if ($result) {
        return mysqli_affected_rows($database_link);
    } else {
        return false;
    }
}//end dbDelete()


/*
 * Fetches all of the rows (associatively) from the last performed query.
 * Most other retrieval functions build off this
 * */


function dbFetchRows($sql, $parameters = array(), $nocache = false)
{
    global $config, $db_conn;

    if ($config['memcached']['enable'] && $nocache === false) {
        $result = $config['memcached']['resource']->get(hash('sha512', $sql.'|'.serialize($parameters)));
        if (!empty($result)) {
            return $result;
        }
    }

    $time_start = microtime(true);

    try {
        $rows = $db_conn->fetchAll($sql, $parameters);
    } catch (\Doctrine\DBAL\DBALException $e) {
        $rows = false;
        mysql_log_error($e->getMessage(), $sql);
    }

    if ($config['memcached']['enable'] && $nocache === false) {
        $config['memcached']['resource']->set(hash('sha512', $sql.'|'.serialize($parameters)), $rows, $config['memcached']['ttl']);
    }
    recordDbStatistic('fetchrows', $time_start);
    return $rows;
}//end dbFetchRows()


/*
 * This is intended to be the method used for large result sets.
 * It is intended to return an iterator, and act upon buffered data.
 * */


function dbFetch($sql, $parameters = array(), $nocache = false)
{
    return dbFetchRows($sql, $parameters, $nocache);
    /*
        // for now, don't do the iterator thing
        $result = dbQuery($sql, $parameters);
        if($result) {
        // return new iterator
        return new dbIterator($result);
        } else {
        return null; // ??
        }
     */
}//end dbFetch()


/*
 * Like fetch(), accepts any number of arguments
 * The first argument is an sprintf-ready query stringTypes
 * */


function dbFetchRow($sql = null, $parameters = array(), $nocache = false)
{
    global $config, $db_conn;

    if (isset($config['memcached']['enable']) && $config['memcached']['enable'] && $nocache === false) {
        $result = $config['memcached']['resource']->get(hash('sha512', $sql.'|'.serialize($parameters)));
        if (!empty($result)) {
            return $result;
        }
    }

    $time_start = microtime(true);
    $row = $db_conn->fetchAssoc($sql, $parameters);
    recordDbStatistic('fetchrow', $time_start);

    if (isset($config['memcached']['enable']) && $config['memcached']['enable'] && $nocache === false) {
        $config['memcached']['resource']->set(hash('sha512', $sql.'|'.serialize($parameters)), $row, $config['memcached']['ttl']);
    }
    return $row;
}//end dbFetchRow()


/*
 * Fetches the first call from the first row returned by the query
 * */


function dbFetchCell($sql, $parameters = array(), $nocache = false)
{
    global $db_conn;
    $time_start = microtime(true);
    $row = $db_conn->fetchColumn($sql, $parameters);
    recordDbStatistic('fetchcell', $time_start);
    return $row;
}//end dbFetchCell()


/*
 * This method is quite different from fetchCell(), actually
 * It fetches one cell from each row and places all the values in 1 array
 * */


function dbFetchColumn($sql, $parameters = array(), $nocache = false)
{
    $time_start = microtime(true);
    $cells          = array();
    foreach (dbFetch($sql, $parameters, $nocache) as $row) {
        $cells[] = array_shift($row);
    }

    recordDbStatistic('fetchcolumn', $time_start);
    return $cells;
}//end dbFetchColumn()


/*
 * Should be passed a query that fetches two fields
 * The first will become the array key
 * The second the key's value
 */


function dbFetchKeyValue($sql, $parameters = array(), $nocache = false)
{
    $data = array();
    foreach (dbFetch($sql, $parameters, $nocache) as $row) {
        $key = array_shift($row);
        if (sizeof($row) == 1) {
            // if there were only 2 fields in the result
            // use the second for the value
            $data[$key] = array_shift($row);
        } else {
            // if more than 2 fields were fetched
            // use the array of the rest as the value
            $data[$key] = $row;
        }
    }

    return $data;
}//end dbFetchKeyValue()


/*
 * This combines a query and parameter array into a final query string for execution
 * PDO drivers don't need to use this
 */


function dbMakeQuery($sql, $parameters)
{
    // bypass extra logic if we have no parameters
    if (sizeof($parameters) == 0) {
        return $sql;
    }

    $parameters = dbPrepareData($parameters);
    // separate the two types of parameters for easier handling
    $questionParams = array();
    $namedParams    = array();
    foreach ($parameters as $key => $value) {
        if (is_numeric($key)) {
            $questionParams[] = $value;
        } else {
            $namedParams[':'.$key] = $value;
        }
    }

    // sort namedParams in reverse to stop substring squashing
    krsort($namedParams);

    // split on question-mark and named placeholders
    if (preg_match('/(\[\[:[\w]+:\]\])/', $sql)) {
        $result = preg_split('/(\?[a-zA-Z0-9_-]*)/', $sql, -1, (PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE));
    } else {
        $result = preg_split('/(\?|:[a-zA-Z0-9_-]+)/', $sql, -1, (PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE));
    }

    // every-other item in $result will be the placeholder that was found
    $query            = '';
    $res_size = sizeof($result);
    for ($i = 0; $i < $res_size; $i += 2) {
        $query .= $result[$i];

        $j = ($i + 1);
        if (array_key_exists($j, $result)) {
            $test = $result[$j];
            if ($test == '?') {
                $query .= array_shift($questionParams);
            } else {
                $query .= $namedParams[$test];
            }
        }
    }

    return $query;
}//end dbMakeQuery()


function dbPrepareData($data)
{
    global $database_link;
    $values = array();

    foreach ($data as $key => $value) {
        $escape = true;
        // don't quote or esc if value is an array, we treat it
        // as a "decorator" that tells us not to escape the
        // value contained in the array
        if (is_array($value) && !is_object($value)) {
            $escape = false;
            $value  = array_shift($value);
        }

        // it's not right to worry about invalid fields in this method because we may be operating on fields
        // that are aliases, or part of other tables through joins
        // if(!in_array($key, $columns)) // skip invalid fields
        // continue;
        if ($escape) {
            $values[$key] = "'".mysqli_real_escape_string($database_link, $value)."'";
        } else {
            $values[$key] = $value;
        }
    }

    return $values;
}//end dbPrepareData()

/**
 * Given a data array, this returns an array of placeholders
 * These may be question marks, or ":email" type
 *
 * @param array $values
 * @return array
 */
function dbPlaceHolders($values)
{
    $data = array();
    foreach ($values as $key => $value) {
        if (is_numeric($key)) {
            $data[] = '?';
        } else {
            $data[] = ':'.$key;
        }
    }

    return $data;
}//end dbPlaceHolders()


function dbBeginTransaction()
{
    global $database_link;
    mysqli_query($database_link, 'begin');
}//end dbBeginTransaction()


function dbCommitTransaction()
{
    global $database_link;
    mysqli_query($database_link, 'commit');
}//end dbCommitTransaction()


function dbRollbackTransaction()
{
    global $database_link;
    mysqli_query($database_link, 'rollback');
}//end dbRollbackTransaction()

/**
 * Generate a string of placeholders to pass to fill in a list
 * result will look like this: (?, ?, ?, ?)
 *
 * @param $count
 * @return string placholder list
 */
function dbGenPlaceholders($count)
{
    return '(' . implode(',', array_fill(0, $count, '?')) . ')';
}
