<?php

use LibreNMS\Authentication\TwoFactor;
use LibreNMS\Exceptions\AuthenticationException;

// Pre-flight checks
if (!is_dir($config['rrd_dir'])) {
    echo "<div class='errorbox'>RRD Log Directory is missing ({$config['rrd_dir']}).  Graphing may fail.</div>";
}

if (!is_dir($config['temp_dir'])) {
    echo "<div class='errorbox'>Temp Directory is missing ({$config['temp_dir']}).  Graphing may fail.</div>";
}

if (!is_writable($config['temp_dir'])) {
    echo "<div class='errorbox'>Temp Directory is not writable ({$config['tmp_dir']}).  Graphing may fail.</div>";
}

// Clear up any old sessions
dbDelete('session', '`session_expiry` <  ?', array(time()));

\Delight\Cookie\Session::start('Strict');

$time = time() - 60 * 60 * 24 * $config['auth_remember'];
setcookie('sess_id', '', $time, '/', null, $config['secure_cookies']);
setcookie('PHPSESSID', '', $time, '/', null, $config['secure_cookies']);
setcookie('token', '', $time, '/', null, $config['secure_cookies']);
setcookie('auth', '', $time, '/', null, $config['secure_cookies']);

if ($vars['page'] == 'logout' && session_authenticated()) {
    log_out_user();
    header('Location: ' . $config['base_url']);
    exit;
}

try {
    if (session_authenticated()) {
        // session authenticated already
        log_in_user();
    } else {
        // try authentication methods

        if (isset($_POST['twofactor']) && TwoFactor::authenticate($_POST['twofactor'])) {
            // process two-factor auth tokens
            log_in_user();
        } elseif (isset($_COOKIE['sess_id'], $_COOKIE['token']) &&
            reauthenticate(clean($_COOKIE['sess_id']), clean($_COOKIE['token']))
        ) {
            set_session('remember', true);
            set_session('twofactor', true);
            // cookie authentication
            log_in_user();
        } else {
            // collect username and password
            $password = null;
            if (isset($_REQUEST['username']) && isset($_REQUEST['password'])) {
                $username = clean($_REQUEST['username']);
                $password = $_REQUEST['password'];
            } elseif (isset($_SERVER['REMOTE_USER'])) {
                $username = clean($_SERVER['REMOTE_USER']);
            } elseif (isset($_SERVER['PHP_AUTH_USER']) && $config['auth_mechanism'] === 'http-auth') {
                $username = clean($_SERVER['PHP_AUTH_USER']);
            }

            // form authentication
            if (isset($username) && authenticate($username, $password)) {
                set_session('username', $username);

                if (isset($_POST['remember'])) {
                    set_session('remember', $_POST['remember']);
                }

                if (log_in_user()) {
                    // redirect to original uri or home page.
                    header('Location: '.rtrim($config['base_url'], '/').$_SERVER['REQUEST_URI'], true, 303);
                }
            }
        }
    }
} catch (AuthenticationException $ae) {
    $auth_message = $ae->getMessage();
    if ($debug) {
        $auth_message .= '<br /> ' . $ae->getFile() . ': ' . $ae->getLine();
    }

    dbInsert(
        array('user' => \Delight\Cookie\Session::get('username'), 'address' => get_client_ip(), 'result' => $auth_message),
        'authlog'
    );
    log_out_user($auth_message);
}

session_write_close();

// populate the permissions cache
if (\Delight\Cookie\Session::has('user_id')) {
    $permissions = permissions_cache(\Delight\Cookie\Session::get('user_id'));
}

unset($username, $password);
