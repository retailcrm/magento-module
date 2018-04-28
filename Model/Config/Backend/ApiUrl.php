<?php

namespace Retailcrm\Retailcrm\Model\Config\Backend;

use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class ApiUrl extends \Magento\Framework\App\Config\Value
{
    private $api;

    /**
     * ApiUrl constructor.
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $config
     * @param \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param ApiClient $api
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        ApiClient $api,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        $this->api = $api;
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * Call before save api url
     *
     * @throws \Magento\Framework\Exception\ValidatorException
     *
     * @return void
     */
    public function beforeSave()
    {
        $this->setParams([
            'url' => $this->getValue(),
            'apiKey' => $this->getFieldsetDataValue('api_key'),
            'version' => $this->getFieldsetDataValue('api_version')
        ]);

        if (!$this->isUrl($this->getValue())) {
            throw new \Magento\Framework\Exception\ValidatorException(__('Invalid CRM url'));
        }

        if (!$this->isHttps($this->getValue())) {
            $this->schemeEdit($this->getValue());
        }

        if ($this->validateApiUrl($this->api)) {
            $this->setValue($this->getValue());
        }

        parent::beforeSave();
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
    private function validateApiUrl(ApiClient $api)
    {
        $response = $api->availableVersions();

        if ($response === false) {
            throw new \Magento\Framework\Exception\ValidatorException(__('Verify that the data entered is correct'));
        } elseif (!$response->isSuccessful() && $response['errorMsg'] == $api->getErrorText('errorApiKey')) {
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
    private function isHttps($url)
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
    private function schemeEdit(&$url)
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

    /**
     * @param array $data
     */
    private function setParams(array $data)
    {
        $this->api->setUrl($data['url']);
        $this->api->setApiKey($data['apiKey']);
        $this->api->setVersion($data['version']);
        $this->api->init();
    }
}
