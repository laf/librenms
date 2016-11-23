<?php

namespace LibreNMS;

use Exception;

class Curl
{

    public $curl;
    public $url;
    public $data;

    public function __construct()
    {
        $this->curl = $this->getOpts();
    }

    public function __destruct()
    {
        curl_close($this->curl);
    }

    public function getUrl($url)
    {

    }

    public function postUrl($url, $data)
    {
        $this->url = $url;
        $this->data = $data;
        $this->type = 'post';
        return $this->execCurl();
    }

    public function execCurl()
    {
        $curl = $this->getOpts();
        curl_setopt($curl, CURLOPT_URL, $this->url);

        if ($this->type === 'post' && !empty($this->data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        } else {
            curl_setopt($curl, CURLOPT_POST, FALSE);
        }

        if (($content = curl_exec($curl)) === FALSE) {
            throw new Exception('Curl error');
        }
        curl_close ($curl);
        return $content;
    }

    public function getOpts()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, TRUE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);

        if (!empty($this->cookies)) {
            curl_setopt($curl, CURLOPT_COOKIESESSION, TRUE);
            curl_setopt($curl, CURLOPT_COOKIE, $this->cookies);
        }
        return $curl;
    }

    public function setHeader()
    {
        curl_setopt($this->curl, CURLOPT_HEADER, 1);
    }

    public function setReferer()
    {
        curl_setopt($this->curl, CURLOPT_REFERER, $this->url.'/login');
    }

}
