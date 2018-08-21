<?php

namespace Retailcrm\Retailcrm\Test\Unit\Block\Adminhtml\System\Config\Form\Fieldset;

class ShippingTest extends \Retailcrm\Retailcrm\Test\Helpers\FieldsetTest
{
    /**
     * @param $isSuccessful
     * @param $isConfigured
     * @dataProvider dataProvider
     */
    public function testRender($isSuccessful, $isConfigured)
    {
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

        $data = [
            'authSession' => $this->authSessionMock,
            'jsHelper' => $this->helperMock,
            'data' => ['group' => $this->groupMock],
            'client' => $client,
            'shippingConfig' => $shippingConfig,
            'context' => $this->context,
            'objectFactory' => $this->objectFactory
        ];

        $shipping = $this->objectManager->getObject(
            \Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Fieldset\Shipping::class,
            $data
        );

        $shipping->setForm($this->form);
        $shipping->setLayout($this->layoutMock);

        $html = $shipping->render($this->elementMock);

        $this->assertContains($this->testElementId, $html);
        $this->assertContains($this->testFieldSetCss, $html);

        if (!$isConfigured) {
            $expected = sprintf(
                '<div style="margin-left: 15px;"><b><i>%s</i></b></div>',
                __('Enter API of your URL and API key')
            );
            $this->assertContains($expected, $html);
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
