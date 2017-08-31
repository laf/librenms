<?php

use Delight\Cookie\Session;

$init_modules = array('web', 'auth');
require realpath(__DIR__ . '/..') . '/includes/init.php';

//availability-map mode view
if (isset($_REQUEST['map_view'])) {
    Session::set('map_view', $_REQUEST['map_view']);
    $map_view = array('map_view' => Session::get('map_view'));
    header('Content-type: text/plain');
    echo json_encode($map_view);
}

//availability-map device group view
if (isset($_REQUEST['group_view'])) {
    Session::set('group_view', $_REQUEST['group_view']);
    $group_view = array('group_view' => Session::get('group_view'));
    header('Content-type: text/plain');
    echo json_encode($group_view);
}
