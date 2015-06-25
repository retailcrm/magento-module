<?php

/**
 * HTTP client
 */
class Retailcrm_Retailcrm_Model_Http_Client
{
    const METHOD_GET = 'GET';
    const METHOD_POST = 'POST';

    protected $url;
    protected $defaultParameters;

    public function __construct($url, array $defaultParameters = array())
    {
        if (false === stripos($url, 'https://')) {
            throw new Retailcrm_Retailcrm_Model_Exception_InvalidJsonException('API schema requires HTTPS protocol');
        }

        $this->url = $url;
        $this->defaultParameters = $defaultParameters;
        $this->retry = 0;
    }

    /**
     * Make HTTP request
     *
     * @param string $path
     * @param string $method (default: 'GET')
     * @param array $parameters (default: array())
     * @param int $timeout
     * @return Retailcrm_Retailcrm_Model_Response_ApiResponse
     */
    public function makeRequest($path, $method, array $parameters = array(), $timeout = 90)
    {
        $allowedMethods = array(self::METHOD_GET, self::METHOD_POST);
        if (!in_array($method, $allowedMethods)) {
            throw new Retailcrm_Retailcrm_Model_Exception_InvalidJsonException(sprintf(
                'Method "%s" is not valid. Allowed methods are %s',
                $method,
                implode(', ', $allowedMethods)
            ));
        }

        $parameters = array_merge($this->defaultParameters, $parameters);

        $url = $this->url . $path;

        if (self::METHOD_GET === $method && sizeof($parameters)) {
            $url .= '?' . http_build_query($parameters);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, (int) $timeout);

        if (self::METHOD_POST === $method) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
        }

        $responseBody = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);

        curl_close($ch);

        if ($errno && in_array($errno, array(6, 7, 28, 34, 35)) && $this->retry < 3) {
            $errno = null;
            $error = null;
            $this->retry += 1;
            $this->makeRequest(
                $path,
                $method,
                $parameters,
                $timeout
            );
        }

        if ($errno) {
            throw new Retailcrm_Retailcrm_Model_Exception_CurlException($error, $errno);
        }

        return new Retailcrm_Retailcrm_Model_Response_ApiResponse($statusCode, $responseBody);
    }
}
