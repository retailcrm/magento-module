<?php

namespace Retailcrm\Retailcrm\Model\Config\Backend;

use Retailcrm\Retailcrm\Helper\Proxy as ApiClient;

class ApiVersion extends \Magento\Framework\App\Config\Value
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
     * Call before save api version
     * 
     * @return void
     */
    public function beforeSave()
    {
        $apiUrl = $this->getFieldsetDataValue('api_url');
        $apiKey = $this->getFieldsetDataValue('api_key');
        $apiVersion = $this->getValue();

        $api = new ApiClient($apiUrl, $apiKey, $apiVersion);

        $this->validateApiVersion($api, $apiVersion);

        parent::beforeSave();
    }

    /**
     * Call after save api version
     * 
     * @return void
     */
    public function afterSave()
    {
        return parent::afterSave();
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
    protected function validateApiVersion(ApiClient $api, $apiVersion)
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
}
