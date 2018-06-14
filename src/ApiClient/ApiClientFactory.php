<?php

namespace Retailcrm\Retailcrm\ApiClient;

class ApiClientFactory
{
    /**
     * @param $url
     * @param $api_key
     * @param null $version
     *
     * @return \RetailCrm\ApiClient
     */
    public function create($url, $api_key, $version = null)
    {
        return new \RetailCrm\ApiClient($url, $api_key, $version);
    }
}
