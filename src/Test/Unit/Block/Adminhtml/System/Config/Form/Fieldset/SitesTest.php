<?php

namespace Retailcrm\Retailcrm\Test\Unit\Block\Adminhtml\System\Config\Form\Fieldset;

class SitesTest extends \Retailcrm\Retailcrm\Test\Helpers\FieldsetTest
{
    /**
     * @param boolean $isSuccessful
     * @param boolean $isConfigured
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
                    'sitesList'
                ]
            )
            ->getMock();
        $client->expects($this->any())
            ->method('isConfigured')
            ->willReturn($isConfigured);
        $client->expects($this->any())
            ->method('sitesList')
            ->willReturn($response);

        $websiteMock = $this->createMock(\Magento\Store\Model\Website::class);
        $websiteMock->expects($this->any())->method('getStores')->willReturn($this->getTestStores());

        // payment config mock
        $storeManager = $this->getMockBuilder(\Magento\Store\Model\StoreManagerInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $storeManager->expects($this->any())
            ->method('getWebsite')
            ->willReturn($websiteMock);

        $data = [
            'authSession' => $this->authSessionMock,
            'jsHelper' => $this->helperMock,
            'data' => ['group' => $this->groupMock],
            'client' => $client,
            'storeManager' => $storeManager,
            'context' => $this->context,
            'objectFactory' => $this->objectFactory,
            'secureRenderer' => $this->secureRenderer
        ];

        $sites = $this->objectManager->getObject(
            \Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Fieldset\Sites::class,
            $data
        );

        $sites->setForm($this->form);
        $sites->setLayout($this->layoutMock);

        $html = $sites->render($this->elementMock);

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

    private function getTestStores()
    {
        $store = $this->getMockBuilder(\Magento\Store\Model\Store::class)
            ->disableOriginalConstructor()
            ->getMock();

        $store->expects($this->any())
            ->method('getName')
            ->willReturn('Test Store');

        $store->expects($this->any())
            ->method('getCode')
            ->willReturn('test_store_code');

        return ['test_site' => $store];
    }

    private function getTestResponse()
    {
        return [
            'success' => true,
            'sites' => [
                [
                    'code' => 'payment',
                    'name' => 'Test site'
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
