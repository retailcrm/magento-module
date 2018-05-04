<?php

namespace Retailcrm\Retailcrm\Test\Unit\Block\Adminhtml\System\Config\Form\Field;

class ShippingTest extends \PHPUnit\Framework\TestCase
{
    private $objectManager;
    private $testElementId = 'test_element_id';

    public function setUp()
    {
        $this->objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);
    }

    /**
     * @param $isSuccessful
     * @param $isConfigured
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
                    'deliveryTypesList'
                ]
            )
            ->getMock();
        $client->expects($this->any())
            ->method('isConfigured')
            ->willReturn($isConfigured);
        $client->expects($this->any())
            ->method('deliveryTypesList')
            ->willReturn($response);

        // shipping config mock
        $shippingConfig = $this->getMockBuilder(\Magento\Shipping\Model\Config::class)
            ->disableOriginalConstructor()
            ->getMock();
        $shippingConfig->expects($this->any())
            ->method('getActiveCarriers')
            ->willReturn($this->getTestActiveCarriers());

        $shipping = $this->objectManager->getObject(
            \Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Field\Shipping::class,
            [
                'client' => $client,
                'shippingConfig' => $shippingConfig
            ]
        );

        $html = $shipping->render($elementMock);

        if (!$isConfigured || !$isSuccessful) {
            $this->assertEquals($html, $this->getHtml(true));
        }

        if ($isConfigured && $isSuccessful) {
            $this->assertEquals($html, $this->getHtml(false));
        }
    }

    private function getTestActiveCarriers()
    {
        $shipping = $this->getMockBuilder(\Magento\Shipping\Model\Carrier\AbstractCarrierInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $shipping->expects($this->any())
            ->method('getConfigData')
            ->with('title')
            ->willReturn('Test Shipping');

        return ['test_shipping' => $shipping];
    }

    private function getTestResponse()
    {
        return [
            'success' => true,
            'deliveryTypes' => [
                [
                    'code' => 'delivery',
                    'name' => 'Test delivery type'
                ]
            ]
        ];
    }

    private function getHtml($error)
    {
        $html = '';
        $deliveryTypes = $this->getTestResponse();

        foreach ($this->getTestActiveCarriers() as $code => $deliveryType) {
            $html .= '<table id="' . $this->testElementId . '_table">';
            $html .= '<tr id="row_retailcrm_shipping_' . $code . '">';
            $html .= '<td class="label">' . $deliveryType->getConfigData('title') . '</td>';
            $html .= '<td>';
            $html .= '<select id="1" name="groups[Shipping][fields][' . $code . '][value]">';

            foreach ($deliveryTypes['deliveryTypes'] as $k => $value) {
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
