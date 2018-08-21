<?php

namespace Retailcrm\Retailcrm\Test\Unit\Block\Adminhtml\System\Config\Form\Fieldset;

class StatusTest extends \Retailcrm\Retailcrm\Test\Helpers\FieldsetTest
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
                    'statusesList'
                ]
            )
            ->getMock();
        $client->expects($this->any())
            ->method('isConfigured')
            ->willReturn($isConfigured);
        $client->expects($this->any())
            ->method('statusesList')
            ->willReturn($response);

        // status collection mock
        $statusCollection = $this->getMockBuilder(\Magento\Sales\Model\ResourceModel\Order\Status\Collection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $statusCollection->expects($this->any())
            ->method('toOptionArray')
            ->willReturn($this->getTestStatuses());

        $data = [
            'authSession' => $this->authSessionMock,
            'jsHelper' => $this->helperMock,
            'data' => ['group' => $this->groupMock],
            'client' => $client,
            'statusCollection' => $statusCollection,
            'context' => $this->context,
            'objectFactory' => $this->objectFactory
        ];

        $status = $this->objectManager->getObject(
            \Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Fieldset\Status::class,
            $data
        );

        $status->setForm($this->form);
        $status->setLayout($this->layoutMock);

        $html = $status->render($this->elementMock);

        $this->assertContains($this->testElementId, $html);
        $this->assertContains($this->testFieldSetCss, $html);

        if (!$isConfigured) {
            $expected = '
                <div style="margin-left: 15px;"><b><i>' . __('Enter API of your URL and API key') . '</i></b></div>
            ';
            $this->assertContains($expected, $html);
        }
    }

    private function getTestStatuses()
    {
        $status = [
            'label' => 'Test Status',
            'value' => 'Test status'
        ];

        return ['test_status' => $status];
    }

    private function getTestResponse()
    {
        return [
            'success' => true,
            'statuses' => [
                [
                    'code' => 'status',
                    'name' => 'Test status'
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
