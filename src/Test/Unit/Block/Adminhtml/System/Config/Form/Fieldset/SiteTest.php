<?php

namespace Retailcrm\Retailcrm\Test\Unit\Block\Adminhtml\System\Config\Form\Fieldset;

class SiteTest extends \Retailcrm\Retailcrm\Test\Helpers\FieldsetTest
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

        $data = [
            'authSession' => $this->authSessionMock,
            'jsHelper' => $this->helperMock,
            'data' => ['group' => $this->groupMock],
            'client' => $client,
            'context' => $this->context,
            'objectFactory' => $this->objectFactory
        ];

        $site = $this->objectManager->getObject(
            \Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Fieldset\Site::class,
            $data
        );

        $site->setForm($this->form);
        $site->setLayout($this->layoutMock);

        $html = $site->render($this->elementMock);

        $this->assertContains($this->testElementId, $html);
        $this->assertContains($this->testFieldSetCss, $html);

        if (!$isConfigured) {
            $expected = '
                <div style="margin-left: 15px;"><b><i>' . __('Enter API of your URL and API key') . '</i></b></div>
            ';
            $this->assertContains($expected, $html);
        }
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
