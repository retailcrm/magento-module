<?php
namespace Retailcrm\Retailcrm\Controller\Index;

use Magento\Framework\App\Helper\Context;
use Psr\Log\LoggerInterface;


class Test extends \Magento\Framework\App\Action\Action
{
    protected $logger;
	
    public function __construct(
        LoggerInterface $logger,
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Page\Config $pageConfig,
        \Magento\Framework\App\Config\ScopeConfigInterface $config
    ) {
        $this->logger = $logger;

        $api_url = $config->getValue('retailcrm/general/api_url');
        $api_key = $config->getValue('retailcrm/general/api_key');

        var_dump($api_key);
        var_dump($api_url);

        //$this->logger->debug($api_url);

        parent::__construct($context);
    }

    public function execute()
    {
        //
        exit;
    } 
}
