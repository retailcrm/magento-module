<?php

namespace Retailcrm\Retailcrm\Test\Unit\Block\Adminhtml\System\Config\Form\Field;

class PaymentTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $testElementId = 'test_element_id';

    public function setUp()
    {
        $this->objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
    }

    /**
     * @param boolean $isSuccessful
     * @param boolean $isConfigured
     * @dataProvider dataProvider
     */
    public function testRender($isSuccessful, $isConfigured)
    {
        // element mock
        $elementMock = $this->getMockBuilder(\Magento\Framework\Data\Form\Element\AbstractElement::class)
            ->disableOriginalConstructor()
            ->setMethods(['getId'])
            ->getMock();
        $elementMock->expects($this->any())
            ->method('getId')
            ->willReturn($this->testElementId);

        // response
        $response = $this->objectManager->getObject(
            \RetailCrm\Response\ApiResponse::class,
            [
                'statusCode' => $isSuccessful ? 200 : 404,
                'responseBody' => json_encode($this->getTestResponse())
            ]
        );

        // api client mock
        $client = $this->getMockBuilder(\Retailcrm\Retailcrm\Helper\Proxy::class)
            ->disableOriginalConstructor()
            ->setMethods(
                [
                    'isConfigured',
                    'paymentTypesList'
                ]
            )
            ->getMock();
        $client->expects($this->any())
            ->method('isConfigured')
            ->willReturn($isConfigured);
        $client->expects($this->any())
            ->method('paymentTypesList')
            ->willReturn($response);

        // payment config mock
        $paymentConfig = $this->getMockBuilder(\Magento\Payment\Model\Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $paymentConfig->expects($this->any())
            ->method('getActiveMethods')
            ->willReturn($this->getTestActiveMethods());

        $payment = $this->objectManager->getObject(
            \Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Field\Payment::class,
            [
                'client' => $client,
                'paymentConfig' => $paymentConfig
            ]
        );

        $html = $payment->render($elementMock);

        if (!$isConfigured || !$isSuccessful) {
            $this->assertEquals($html, $this->getHtml(true));
        }

        if ($isConfigured && $isSuccessful) {
            $this->assertEquals($html, $this->getHtml(false));
        }
    }

    private function getTestActiveMethods()
    {
        $payment = $this->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $payment->expects($this->any())
            ->method('getTitle')
            ->willReturn('Test Payment');

        return ['test_payment' => $payment];
    }

    private function getTestResponse()
    {
        return [
            'success' => true,
            'paymentTypes' => [
                [
                    'code' => 'payment',
                    'name' => 'Test payment type'
                ]
            ]
        ];
    }

    private function getHtml($error)
    {
        $html = '';
        $paymentTypes = $this->getTestResponse();

        foreach ($this->getTestActiveMethods() as $code => $paymentType) {
            $html .= '<table id="' . $this->testElementId . '_table">';
            $html .= '<tr id="row_retailcrm_payment_' . $code . '">';
            $html .= '<td class="label">' . $paymentType->getTitle() . '</td>';
            $html .= '<td>';
            $html .= '<select id="1" name="groups[Payment][fields][' . $code . '][value]">';

            foreach ($paymentTypes['paymentTypes'] as $k => $value) {
                $html .= '<option  value="' . $value['code'] . '"> ' . $value['name'] . '</option>';
            }

            $html .= '</select>';
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';
        }

        if ($error) {
            return '<div style="margin-left: 15px;"><b><i>Please check your API Url & API Key</i></b></div>';
        }

        return $html;
    }

    public function dataProvider()
    {
        return [
            [
                'is_successful' => true,
                'is_configured' => true
            ],
            [
                'is_successful' => false,
                'is_configured' => false
            ],
            [
                'is_successful' => true,
                'is_configured' => false
            ],
            [
                'is_successful' => false,
                'is_configured' => true
            ]
        ];
    }
}
