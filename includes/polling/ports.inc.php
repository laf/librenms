<?php

if ($config['port_beta']) {
    include_once 'ports/new-ports.inc.php';
} else {
    include_once 'ports/old-ports.inc.php';
}