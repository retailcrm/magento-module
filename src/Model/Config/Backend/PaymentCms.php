<?php

namespace Retailcrm\Retailcrm\Model\Config\Backend;

class PaymentCms extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * @var \Magento\Payment\Model\Config
     */
    private $paymentConfig;

    /**
     * @var \Retailcrm\Retailcrm\Model\Logger\Logger
     */
    private $logger;

    /**
     * PaymentCms constructor.
     *
     * @param \Magento\Framework\View\Element\Context $context
     * @param \Magento\Payment\Model\Config           $paymentConfig
     * @param array                                   $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Magento\Payment\Model\Config $paymentConfig,
        \Retailcrm\Retailcrm\Model\Logger\Logger $logger,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->paymentConfig = $paymentConfig;
        $this->logger = $logger;
    }

    /**
     * @param string $value
     * @return Magently\Tutorial\Block\Adminhtml\Form\Field\Activation
     */
    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Parse to html.
     *
     * @return mixed
     */
    public function _toHtml()
    {
        if (!$this->getOptions()) {
            $paymentMethods = array();

            try {
                $paymentMethods = $this->paymentConfig->getActiveMethods();
            } catch (\Exception $exception) {
                $this->logger->writeRow($exception->getMessage());
            }

            $this->addOption( 'null',  "not selected");
            if ($paymentMethods) {
                foreach ($paymentMethods as $code => $payment) {
                    $this->addOption($payment->getCode(), $payment->getTitle());
                }
            }
        }

        return parent::_toHtml();
    }
}
