<?php

namespace Retailcrm\Retailcrm\Model\Config\Backend;

use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class ApiUrl extends \Magento\Framework\App\Config\Value
{
    /**
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\App\Config\ValueFactory $configValueFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param string $runModelPath
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Call before save api url
     * 
     * @return void
     */
    public function beforeSave()
    {
        $apiUrl = $this->getValue();
        $apiKey = $this->getFieldsetDataValue('api_key');
        $apiVersion = $this->getFieldsetDataValue('api_version');

        if (!$this->isUrl($apiUrl)) {
            throw new \Magento\Framework\Exception\ValidatorException(__('Invalid CRM url'));
        }

        if (!$this->isHttps($apiUrl)) {
           $this->schemeEdit($apiUrl);
        }

        $api = new ApiClient($apiUrl, $apiKey, $apiVersion);

        if ($this->validateApiUrl($api)) {
            $this->setValue($apiUrl);
        }

        parent::beforeSave();
    }

    /**
     * Call after save api url
     * 
     * @return void
     */
    public function afterSave()
    {
        return parent::afterSave();
    }

    /**
     * Validate selected api url
     * 
     * @param ApiClient $api
     * @param string $apiVersion
     * 
     * @throws \Magento\Framework\Exception\ValidatorException
     * 
     * @return boolean
     */
    protected function validateApiUrl(ApiClient $api)
    {
        $response = $api->availableVersions();

        if ($response === false) {
            throw new \Magento\Framework\Exception\ValidatorException(__('Verify that the data entered is correct'));
        } elseif (!$response->isSuccessful() && $response['errorMsg'] == $api->getErrorText ('errorApiKey')) {
            throw new \Magento\Framework\Exception\ValidatorException(__('Invalid CRM api key'));
        } elseif (isset($response['errorMsg']) && $response['errorMsg'] == $api->getErrorText('errorAccount')) {
            throw new \Magento\Framework\Exception\ValidatorException(__('Invalid CRM api url'));
        }

        return true;
    }

    /**
     * Check url scheme
     * 
     * @param string $url
     * 
     * @return boolean
     */
    protected function isHttps($url)
    {
        $url_array = parse_url($url);

        if ($url_array['scheme'] === 'https') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Edit scheme from http to https
     * 
     * @param string $url
     * 
     * @return string
     */
    protected function schemeEdit(&$url)
    {
        $url_array = parse_url($url);

        $url = 'https://' . $url_array['host'];
    }

    /**
     * Check url
     * 
     * @param string $url
     * 
     * @return type
     */
    public function isUrl($url)
    {
        return preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url);
    }
}
