<?php

use Delight\Cookie\Session;

$init_modules = array('web', 'auth');
require realpath(__DIR__ . '/..') . '/includes/init.php';

if (isset($_REQUEST['width'], $_REQUEST['height'])) {
    Session::set('screen_width', $_REQUEST['width']);
    Session::set('screen_height', $_REQUEST['height']);
}

header('Content-type: text/plain');
echo Session::get('screen_width') . 'x' . Session::get('screen_height');
