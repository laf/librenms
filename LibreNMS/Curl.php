<?php

namespace LibreNMS;

use Exception;

class Curl
{

    public $curl;
    public $url;
    public $data;
    public $response;
    public $cookies;

    public function __construct($boom)
    {
        $this->getOpts();
    }

    public function __destruct()
    {
        curl_close($this->curl);
        $this->url = null;
        $this->data = null;
        $this->response = null;
    }

    public function getUrl($url)
    {
        $this->url = $url;
        $this->type = 'get';
        $this->response = $this->execCurl();
        return $this->response;
    }

    public function postUrl($url, $data)
    {
        $this->url = $url;
        $this->data = $data;
        $this->type = 'post';
        $this->response = $this->execCurl();
        return $this->response;
    }

    public function execCurl()
    {
        curl_setopt($this->curl, CURLOPT_URL, $this->url);

        if ($this->type === 'post' && !empty($this->data)) {
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->data);
        } else {
            curl_setopt($this->curl, CURLOPT_POST, FALSE);
        }

        if (($content = curl_exec($this->curl)) === FALSE) {
            print_r(curl_error($this->curl));
        }
        return $content;
    }

    public function getOpts()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        //curl_setopt($curl, CURLOPT_VERBOSE, TRUE);
        $this->curl = $curl;
    }

    public function setHeader($set)
    {
        curl_setopt($this->curl, CURLOPT_HEADER, $set);
    }

    public function setReferer($referer)
    {
        curl_setopt($this->curl, CURLOPT_REFERER, $referer);
    }

    public function setCookies($cookies)
    {   
        curl_setopt($this->curl, CURLOPT_COOKIESESSION, TRUE);
        curl_setopt($this->curl, CURLOPT_COOKIE, $cookies);
    }

    public function getHeaderSize()
    {
        return curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
    }

    public function getHTTPCode()
    {
        return curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
    }

}
