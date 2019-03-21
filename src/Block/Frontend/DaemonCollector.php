<?php

namespace Retailcrm\Retailcrm\Block\Frontend;

class DaemonCollector extends \Magento\Framework\View\Element\Template
{
    private $customer;
    private $helper;
    private $storeResolver;
    private $js = '';

    private $template = <<<EOT
<script type="text/javascript">
    (function(_,r,e,t,a,i,l){_['retailCRMObject']=a;_[a]=_[a]||function(){(_[a].q=_[a].q||[]).push(arguments)};_[a].l=1*new Date();l=r.getElementsByTagName(e)[0];i=r.createElement(e);i.async=!0;i.src=t;l.parentNode.insertBefore(i,l)})(window,document,'script','https://collector.retailcrm.pro/w.js','_rc');
    {{ code }}
    _rc('send', 'pageView');
</script>
EOT;

    /**
     * DaemonCollector constructor.
     *
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Store\Api\StoreResolverInterface $storeResolver
     * @param \Retailcrm\Retailcrm\Helper\Data $helper
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Store\Api\StoreResolverInterface $storeResolver,
        \Retailcrm\Retailcrm\Helper\Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->customer = $customerSession->getCustomer();
        $this->storeResolver = $storeResolver;
        $this->helper = $helper;
    }

    /**
     * @return string
     */
    public function getJs()
    {
        return $this->js;
    }

    /**
     * @return $this
     */
    public function buildScript()
    {
        $params = array();

        if ($this->customer->getId()) {
            $params['customerId'] = $this->customer->getId();
        }

        try {
            $siteKey = $this->helper->getSiteKey(
                $this->_storeManager->getStore(
                    $this->storeResolver->getCurrentStoreId()
                )->getWebsiteId()
            );
        } catch (\Magento\Framework\Exception\NoSuchEntityException $entityException) {
            return $this;
        }

        if ($siteKey) {
            $this->js = preg_replace(
                '/{{ code }}/',
                sprintf(
                    "\t_rc('create', '%s', %s);\n",
                    $siteKey,
                    json_encode((object) $params)
                ),
                $this->template
            );
        }

        return $this;
    }
}
