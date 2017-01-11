<?php
/**
 * bootstrap.php
 *
 * Initialize the Autoloader and includes for phpunit to be able to run tests
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

// get the current LibreNMS install directory
$install_dir = realpath(__DIR__ . '/..');
$init_modules = array('alerts');
if (!getenv('SNMPSIM')) {
    $init_modules[] = 'mock_snmp';
}

require $install_dir . '/includes/init.php';
require $install_dir . '/html/includes/functions.inc.php';

if (getenv('SNMPSIM')) {
    $config['snmp']['port'] = getenv('SNMPSIM');
}

\phpFastCache\CacheManager::getInstance()->clean();

ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_WARNING);
//error_reporting(E_ALL);
$debug=true;
//$vdebug=true;
