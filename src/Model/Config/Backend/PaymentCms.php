<?php

namespace Retailcrm\Retailcrm\Model\Config\Backend;

class PaymentCms extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * @var \Magento\Payment\Model\Config
     */
    private $paymentConfig;

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
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->paymentConfig = $paymentConfig;
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

            $paymentMethods = $this->paymentConfig->getActiveMethods();

            foreach ($paymentMethods as $code => $payment) {
                $this->addOption($payment->getCode(), $payment->getTitle());
            }
        }

        return parent::_toHtml();
    }
}
