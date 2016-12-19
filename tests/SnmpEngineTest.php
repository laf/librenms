<?php
/**
 * SnmpEngineTest.php
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

namespace LibreNMS\Tests;

use LibreNMS\SNMP;
use LibreNMS\SNMP\Engines\Mock;

class SnmpEngineTest extends \PHPUnit_Framework_TestCase
{
    /**
     *
     */
    public function testSnmpTranslate()
    {
        $this->assertEquals('UCD-SNMP-MIB::prTable', SNMP::translate(Mock::genDevice(), '1.3.6.1.4.1.2021.2'));

        $oids = array('system', 'ifTable');
        $expected = array('system' => 'SNMPv2-MIB::system', 'ifTable' => 'IF-MIB::ifTable');
        $this->assertEquals($expected, SNMP::translate(Mock::genDevice(), $oids, '-IR'));

        $oids = array('system', 'ifTable', 'SNMPv2-MIB:sysName.0');
        $expected = array(
            'system' => '.1.3.6.1.2.1.1',
            'ifTable' => '.1.3.6.1.2.1.2.2',
            'SNMPv2-MIB:sysName.0' => '.1.3.6.1.2.1.1.5.0'
        );
        $this->assertEquals($expected, SNMP::translate(Mock::genDevice(), $oids, '-IR -On'));
    }

    public function testNetSnmpTranslateFailure()
    {
        $oids = array('SNMPv2-MIB::system', '.1.3.6.1.2.1.1', 'fldsmdfr', '.1.3.6.1.2.1.1.5.0');
        $expected = array(
            'SNMPv2-MIB::system' => 'SNMPv2-MIB::system',
            '.1.3.6.1.2.1.1' => 'SNMPv2-MIB::system',
            'fldsmdfr' => null,
            '.1.3.6.1.2.1.1.5.0' => null
        );

        $this->assertEquals($expected, SNMP::translate(Mock::genDevice(), $oids));

        $expected['.1.3.6.1.2.1.1'] = null;
        $this->assertEquals($expected, SNMP::translate(Mock::genDevice(), $oids, '-IR', null, ''));
    }
}
