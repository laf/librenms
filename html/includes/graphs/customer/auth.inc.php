<?php

// FIXME - wtfbbq
if (is_read() || is_admin() || $auth) {
    $id    = mres($vars['id']);
    $title = generate_device_link($device);
    $auth  = true;
}
