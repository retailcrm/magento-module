<?php

namespace Retailcrm\Retailcrm\Test\Unit\Block\Adminhtml\System\Config\Form\Fieldset;

class PaymentTest extends \Retailcrm\Retailcrm\Test\Helpers\FieldsetTest
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

        $data = [
            'authSession' => $this->authSessionMock,
            'jsHelper' => $this->helperMock,
            'data' => ['group' => $this->groupMock],
            'client' => $client,
            'paymentConfig' => $paymentConfig,
            'context' => $this->context,
            'objectFactory' => $this->objectFactory
        ];

        $payment = $this->objectManager->getObject(
            \Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Fieldset\Payment::class,
            $data
        );

        $payment->setForm($this->form);
        $payment->setLayout($this->layoutMock);

        $html = $payment->render($this->elementMock);

        $this->assertContains($this->testElementId, $html);
        $this->assertContains($this->testFieldSetCss, $html);

        if (!$isConfigured) {
            $expected = '
                <div style="margin-left: 15px;"><b><i>' . __('Enter API of your URL and API key') . '</i></b></div>
            ';
            $this->assertContains($expected, $html);
        }
    }

    protected function getTestActiveMethods()
    {
        $payment = $this->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->setMethods(['getData'])
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $payment->expects($this->any())
            ->method('getData')
            ->with('title')
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
