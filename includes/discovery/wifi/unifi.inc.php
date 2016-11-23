<?php

use LibreNMS\Unifi;
$boom = new Unifi($config['unifi_user'], $config['unifi_pass'], $config['unifi_url']);
print_r($boom);
