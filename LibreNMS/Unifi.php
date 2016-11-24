<?php

namespace LibreNMS;

use Exception;

class Unifi
{

    public $user;
    public $pass;
    public $url;

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
        $this->curl->setHeader();
        $this->curl->setReferer();
        $data = json_encode(array('username' => $this->user, 'password' => $this->pass));
        $login = $this->curl->postUrl($this->url, $data);
        print_r($login);
    }

    public function logout()
    {
        //print_r($this->curl);
    }

}
