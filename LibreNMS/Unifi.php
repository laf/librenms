<?php

namespace LibreNMS;

use Exception;

class Unifi
{

    public $user;
    public $pass;
    public $url;
    public $curl;
    public $auth;
    public $cookies;

    public function __construct($user, $pass, $url)
    {
        $this->user = isset($user) ? $user : '';
        $this->pass = isset($pass) ? $pass : '';
        $this->url  = isset($url)  ? $url  : '';
        $this->auth = false;
        $this->curl = new Curl();
        $this->login();
    }

    public function __destruct()
    {
        $this->logout();
    }

    public function login()
    {
        $this->curl->setHeader(true);
        $this->curl->setReferer($this->url . '/api/login');
        $data = json_encode(array('username' => $this->user, 'password' => $this->pass));
        $output = $this->curl->postUrl($this->url . '/api/login', $data);
        $header_size = $this->curl->getHeaderSize();
        $body = trim(substr($output, $header_size));
        $code = $this->curl->getHTTPCode();
        preg_match_all('|Set-Cookie: (.*);|U', substr($output, 0, $header_size), $results);
        if (isset($results[1])) {
            $this->cookies = implode(';', $results[1]);
            if (!empty($body)) {
                if (($code >= 200) && ($code < 400)) {
                    if (strpos($this->cookies,'unifises') !== false) {
                        $this->auth = true;
                        $this->curl->setCookies($this->cookies);
                        $this->curl->setHeader(false);
                    }
                }
                if ($code === 400) {
                    return $code;
                }
            }
        }
    }

    public function logout()
    {
        $this->curl->getUrl($this->url . '/logout');
    }

    public function sites()
    {
        if ($this->auth !== true) {
            return false;
        }
        $data = json_decode($this->curl->getUrl($this->url . '/api/self/sites'));
        if ($data->meta->rc === 'ok') {
            return $data->data;
        }
        return false;
    }

    public function aps($site)
    {
        if ($this->auth !== true) {
            return false;
        }
        $data = json_decode($this->curl->getUrl($this->url . '/api/s/'.$site.'/stat/device/'));
        if ($data->meta->rc === 'ok') {
            return $data->data;
        }
        return false;
    }

}
