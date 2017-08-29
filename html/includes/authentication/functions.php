<?php
/**
 * functions.php
 *
 * authentication functions
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
 * @copyright  2017 Tony Murray
 * @author     Tony Murray <murraytony@gmail.com>
 */

use LibreNMS\Authentication\TwoFactor;
use LibreNMS\Exceptions\AuthenticationException;
use Phpass\PasswordHash;

/**
 * Log out the user, unset cookies, destroy the session
 *
 * @param string $message The logout message.
 */
function log_out_user($message = 'Logged Out')
{
    global $auth_message;

    dbInsert(array('user' => \Delight\Cookie\Session::get('username'), 'address' => get_client_ip(), 'result' => 'Logged Out'), 'authlog');

    clear_remember_me(\Delight\Cookie\Session::get('username'));

    \Delight\Cookie\Session::delete('username');

    $auth_message = $message; // global variable used to display a message to the user
}

/**
 * Log in the user and set up a few login tasks
 * $_SESSION['username'] must be set prior to calling this function
 * If twofactor authentication is enabled, it will be checked here.
 *
 * If everything goes well, $_SESSION['authenticated'] will be true after this function completes.
 * @return bool If the user was successfully logged in.
 * @throws AuthenticationException if anything failed why trying to log in
 */
function log_in_user()
{
    global $config;

    $tmp_username  = \Delight\Cookie\Session::get('username');
    $tmp_userid    = \Delight\Cookie\Session::get('user_id');
    // set up variables, but don't override existing ones (ad anonymous bind can only get user_id at login)
    if (!\Delight\Cookie\Session::has('userlevel')) {
        set_session('userlevel', get_userlevel($tmp_username));
    }

    if (!\Delight\Cookie\Session::has('user_id')) {
        set_session('user_id', get_userid($tmp_username));
    }

    // check for valid user_id
    if ($tmp_userid === false || $tmp_userid < 0) {
        throw new AuthenticationException('Invalid Credentials');
    }

    if (!session_authenticated()) {
        // check twofactor
        if ($config['twofactor'] === true && !\Delight\Cookie\Session::has('twofactor')) {
            if (TwoFactor::showForm()) {
                return false; // not done yet, one more cycle to show the 2fa form
            }
        }

        $tmp_twofactor = \Delight\Cookie\Session::get('twofactor', false);

        // if two factor isn't enabled or it has passed already ware are logged in
        if (!$config['twofactor'] || $tmp_twofactor) {
            set_session('authenticated', true);
            dbInsert(array('user' => $tmp_username, 'address' => get_client_ip(), 'result' => 'Logged In'), 'authlog');
        }
    }

    if (session_authenticated()) {
        set_remember_me();
    }

    return true;
}

/**
 * Check if the session is authenticated
 *
 * @return bool
 */
function session_authenticated()
{
    $tmp_authenticated = \Delight\Cookie\Session::get('authenticated', false);
    return isset($tmp_authenticated) && $tmp_authenticated;
}

/**
 * Set or update the remember me cookie if $_SESSION['remember'] is set
 * If setting a new cookie, $_SESSION['username'] must be set
 */
function set_remember_me()
{
    global $config;

    if (!\Delight\Cookie\Session::has('remember', false)) {
        return;
    }
    \Delight\Cookie\Session::delete('remember');

    $sess_id = session_id();
    $expiration = time() + 60 * 60 * 24 * $config['auth_remember'];

    $db_entry = array(
        'session_value' => $sess_id,
        'session_expiry' => $expiration,
    );

    if (isset($_COOKIE['token'], $_COOKIE['auth'])) {
        $token_id = $_COOKIE['token'];
        $auth = $_COOKIE['auth'];
        dbUpdate($db_entry, 'session', 'session_auth=?', array($_COOKIE['auth']));
    } else {
        $token = strgen();
        $auth = strgen();
        $hasher = new PasswordHash(8, false);
        $token_id = \Delight\Cookie\Session::get('username') . '|' . $hasher->HashPassword(\Delight\Cookie\Session::get('username') . $token);

        $db_entry['session_username'] = \Delight\Cookie\Session::get('username');
        $db_entry['session_token'] = $token;
        $db_entry['session_auth'] = $auth;
        dbInsert($db_entry, 'session');
    }

    \Delight\Cookie\Cookie::setcookie('sess_id', $sess_id, $expiration, '/', null, $config['secure_cookies'], true, 'Strict');
    \Delight\Cookie\Cookie::setcookie('token', $token_id, $expiration, '/', null, $config['secure_cookies'], true, 'Strict');
    \Delight\Cookie\Cookie::setcookie('auth', $auth, $expiration, '/', null, $config['secure_cookies'], true, 'Strict');
}

/**
 * Check the remember me cookie
 * If the cookie is valid, $_SESSION['username'] will be set
 *
 * @param string $sess_id sess_id cookie value
 * @param string $token token cookie value
 * @return bool is the remember me token valid
 * @throws AuthenticationException thrown if the cookie is invalid
 */
function check_remember_me($sess_id, $token)
{
    list($uname, $hash) = explode('|', $token);
    $session = dbFetchRow(
        "SELECT * FROM `session` WHERE `session_username`=? AND `session_value`=?",
        array($uname, $sess_id),
        true
    );

    $hasher = new PasswordHash(8, false);
    if ($hasher->CheckPassword($uname . $session['session_token'], $hash)) {
        set_session('username', $uname);
        return true;
    }

    clear_remember_me($uname);
    throw new AuthenticationException('Cookie invalid, please log in.');
}

/**
 * Clear remember cookie and remove our database record
 *
 * @param $username
 */
function clear_remember_me($username)
{
    global $config;

    dbDelete(
        'session',
        '`session_username` =  ? AND `session_value` = ?',
        array($username, $_COOKIE['sess_id'])
    );

    unset($_COOKIE);

    $time = time() - 60 * 60 * 24 * $config['auth_remember']; // time in the past to make sure

    \Delight\Cookie\Cookie::setcookie('sess_id', '', $time, '/', null, $config['secure_cookies'], false, 'Strict');
    \Delight\Cookie\Cookie::setcookie('token', '', $time, '/', null, $config['secure_cookies'], false, 'Strict');
    \Delight\Cookie\Cookie::setcookie('auth', '', $time, '/', null, $config['secure_cookies'], false, 'Strict');
}
