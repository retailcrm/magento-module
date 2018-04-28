<?php

namespace Retailcrm\Retailcrm\Model\Config\Backend;

use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class ApiVersion extends \Magento\Framework\App\Config\Value
{
    private $api;

    /**
     * ApiVersion constructor.
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
     * Call before save api version
     *
     * @return void
     */
    public function beforeSave()
    {
        $this->setParams([
            'url' => $this->getFieldsetDataValue('api_url'),
            'apiKey' => $this->getFieldsetDataValue('api_key'),
            'version' => $this->getValue()
        ]);

        $this->validateApiVersion($this->api, $this->getValue());

        parent::beforeSave();
    }

    /**
     * Validate selected api version
     *
     * @param ApiClient $api
     * @param string $apiVersion
     *
     * @throws \Magento\Framework\Exception\ValidatorException
     *
     * @return void
     */
    private function validateApiVersion(ApiClient $api, $apiVersion)
    {
        $apiVersions = [
            'v4' => '4.0',
            'v5' => '5.0'
        ];

        $response = $api->availableVersions();

        if ($response->isSuccessful()) {
            $availableVersions = $response['versions'];
        } else {
            throw new \Magento\Framework\Exception\ValidatorException(__('Invalid CRM url or api key'));
        }

        if (isset($availableVersions)) {
            if (in_array($apiVersions[$apiVersion], $availableVersions)) {
                $this->setValue($this->getValue());
            } else {
                throw new \Magento\Framework\Exception\ValidatorException(__('Selected api version forbidden'));
            }
        }
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
