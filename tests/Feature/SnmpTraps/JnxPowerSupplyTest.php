<?php

/**
 * JnxPowerSupplyTest.php
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 *
 * Tests JnxDomAlertSet and JnxDomAlertCleared traps from Juniper devices.
 *
 * @link       https://www.librenms.org
 *
 * @copyright  2019 KanREN, Inc
 * @author     Heath Barnhart <hbarnhart@kanren.net>
 */

namespace LibreNMS\Tests\Feature\SnmpTraps;

use LibreNMS\Enum\Severity;

class JnxPowerSupplyTest extends SnmpTrapTestCase
{
    public function testJnxPowerSupplyFailureTrap(): void
    {
        $this->assertTrapLogsMessage(<<<'TRAP'
{{ hostname }}
UDP: [{{ ip }}]:49716->[10.0.0.1]:162
DISMAN-EVENT-MIB::sysUpTimeInstance 470:23:25:41.21
SNMPv2-MIB::snmpTrapOID.0 JUNIPER-MIB::jnxPowerSupplyFailure
JUNIPER-MIB::jnxContentsContainerIndex.2.4.0.0 2
JUNIPER-MIB::jnxContentsL1Index.2.4.0.0 4
JUNIPER-MIB::jnxContentsL2Index.2.4.0.0 0
JUNIPER-MIB::jnxContentsL3Index.2.4.0.0 0
JUNIPER-MIB::jnxContentsDescr.2.4.0.0 PEM 3
JUNIPER-MIB::jnxOperatingState.2.4.0.0 down
SNMPv2-MIB::snmpTrapEnterprise.0 JUNIPER-CHASSIS-DEFINES-MIB::jnxProductNameMX960
TRAP,
            'Power Supply PEM 3 is down',
            'Could not handle JnxPowerSupplyFailure',
            [Severity::Error],
        );
    }

    public function testJnxPowerSupplyOkTrap(): void
    {
        $this->assertTrapLogsMessage(<<<'TRAP'
{{ hostname }}
UDP: [{{ ip }}]:49716->[10.0.0.1]:162
DISMAN-EVENT-MIB::sysUpTimeInstance 470:23:25:41.21
SNMPv2-MIB::snmpTrapOID.0 JUNIPER-MIB::jnxPowerSupplyOK
JUNIPER-MIB::jnxContentsContainerIndex.2.4.0.0 2
JUNIPER-MIB::jnxContentsL1Index.2.4.0.0 4
JUNIPER-MIB::jnxContentsL2Index.2.4.0.0 0
JUNIPER-MIB::jnxContentsL3Index.2.4.0.0 0
JUNIPER-MIB::jnxContentsDescr.2.4.0.0 PEM 4
JUNIPER-MIB::jnxOperatingState.2.4.0.0 ok
SNMPv2-MIB::snmpTrapEnterprise.0 JUNIPER-CHASSIS-DEFINES-MIB::jnxProductNameMX960
TRAP,
            'Power Supply PEM 4 is OK',
            'Could not handle JnxPowerSupplyOK',
            [Severity::Ok],
        );
    }
}
