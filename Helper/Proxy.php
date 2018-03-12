<?php

namespace Retailcrm\Retailcrm\Helper;

use RetailCrm\ApiClient;
use Magento\Framework\App\ObjectManager;
use Retailcrm\Retailcrm\Model\Logger\Logger;

class Proxy
{
    protected $logger;
    protected $apiClient;

    private $errorAccount = 'Account does not exist.';
    private $errorNotFound = 'Not found';
    private $errorApiKey = 'Wrong "apiKey" value.';

    public function __construct ($url, $key, $apiVersion)
    {
        $objectManager = ObjectManager::getInstance();
        $this->logger = new Logger($objectManager);
        $this->apiClient = new ApiClient($url, $key, $apiVersion);
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
