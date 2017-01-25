<?php

if (is_file($config['install_dir'] . '/includes/discovery/wifi/' . $device['os'] . '.inc.php')) {
    require_once $config['install_dir'] . '/includes/discovery/wifi/' . $device['os'] . '.inc.php';
}

