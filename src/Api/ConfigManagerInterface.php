<?php

namespace Retailcrm\Retailcrm\Api;

interface ConfigManagerInterface
{
    const URL_PATH = 'retailcrm/general/api_url';
    const KEY_PATH = 'retailcrm/general/api_key';
    const API_VERSION_PATH = 'retailcrm/general/api_version';

    public function getConfigValue($path);
}
