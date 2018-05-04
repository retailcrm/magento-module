<?php

namespace Retailcrm\Retailcrm\Helper;

use Retailcrm\Retailcrm\Model\Logger\Logger;
use Retailcrm\Retailcrm\Model\Service\ConfigManager;
use Retailcrm\Retailcrm\ApiClient\ApiClientFactory;

class Proxy
{
    private $logger;
    private $apiClient;
    private $url;
    private $apiKey;
    private $version;
    private $apiClientFactory;
    private $errorAccount = 'Account does not exist.';
    private $errorNotFound = 'Not found';
    private $errorApiKey = 'Wrong "apiKey" value.';

    /**
     * Proxy constructor.
     * @param string $pathUrl
     * @param string $pathKey
     * @param string $pathVersion
     * @param ConfigManager $config
     * @param Logger $logger
     * @param ApiClientFactory $apiClientFactory
     */
    public function __construct(
        $pathUrl,
        $pathKey,
        $pathVersion,
        ConfigManager $config,
        Logger $logger,
        ApiClientFactory $apiClientFactory
    ) {
        $this->logger = $logger;
        $this->url = $config->getConfigValue($pathUrl);
        $this->apiKey = $config->getConfigValue($pathKey);
        $this->version = $config->getConfigValue($pathVersion);
        $this->apiClientFactory = $apiClientFactory;

        if ($this->isConfigured()) {
            $this->init();
        }
    }

    public function __call($method, $arguments)
    {
        try {
            $response = call_user_func_array([$this->apiClient->request, $method], $arguments);

            if (!$response->isSuccessful()) {
                $this->logger->writeRow(
                    sprintf(
                        "[HTTP status %s] %s",
                        $response->getStatusCode(),
                        $response->getErrorMsg()
                    )
                );

                if (isset($response['errors'])) {
                    $this->logger->writeRow(implode(' :: ', $response['errors']));
                }
            }
        } catch (\RetailCrm\Exception\CurlException $exception) {
            $this->logger->writeRow($exception->getMessage());
            return false;
        } catch (\RetailCrm\Exception\InvalidJsonException $exception) {
            $this->logger->writeRow($exception->getMessage());
            return false;
        } catch (\InvalidArgumentException $exception) {
            $this->logger->writeRow($exception->getMessage());
        }

        return $response;
    }

    /**
     * Init retailcrm api client
     */
    public function init()
    {
        $this->apiClient = $this->apiClientFactory->create(
            $this->url,
            $this->apiKey,
            $this->version
        );
    }

    /**
     * @param $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * @param $apiKey
     */
    public function setApiKey($apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return $this->url && $this->apiKey;
    }

    /**
     * Get API version
     *
     * @return string
     */
    public function getVersion()
    {
        if (!is_object($this->apiClient)) {
            return false;
        }

        return $this->apiClient->getVersion();
    }

    /**
     * Get error text message
     *
     * @param string $property
     *
     * @return string
     */
    public function getErrorText($property)
    {
        return $this->{$property};
    }
}
