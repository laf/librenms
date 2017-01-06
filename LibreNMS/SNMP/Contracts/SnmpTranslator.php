<?php
/**
 * SnmpTranslator.php
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
 * @copyright  2016 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\SNMP\Contracts;

interface SnmpTranslator
{
    /**
     * @param array $device
     * @param string|array $oids
     * @param string $options
     * @param string $mib
     * @param string $mib_dir
     * @return string|array
     */
    public function translate($device, $oids, $options = null, $mib = null, $mib_dir = null);

    /**
     * @param array $device
     * @param string|array $oids
     * @param string $mib
     * @param string $mib_dir
     * @return string|array
     */
    public function translateNumeric($device, $oids, $mib = null, $mib_dir = null);
}
