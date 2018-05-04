<?php

namespace Retailcrm\Retailcrm\Test\Unit\Block\Adminhtml\System\Config\Form\Field;

class StatusTest extends \PHPUnit\Framework\TestCase
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

        $status = $this->objectManager->getObject(
            \Retailcrm\Retailcrm\Block\Adminhtml\System\Config\Form\Field\Status::class,
            [
                'client' => $client,
                'statusCollection' => $statusCollection
            ]
        );

        $html = $status->render($elementMock);

        if (!$isConfigured || !$isSuccessful) {
            $this->assertEquals($html, $this->getHtml(true));
        }

        if ($isConfigured && $isSuccessful) {
            $this->assertEquals($html, $this->getHtml(false));
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

    private function getHtml($error)
    {
        $html = '';
        $statuses = $this->getTestResponse();

        foreach ($this->getTestStatuses() as $code => $status) {
            $html .= '<table id="' . $this->testElementId . '_table">';
            $html .= '<tr id="row_retailcrm_status_' . $status['label'] . '">';
            $html .= '<td class="label">' . $status['label'] . '</td>';
            $html .= '<td>';
            $html .= '<select name="groups[Status][fields][' . $status['value'] . '][value]">';

            $html .= '<option value=""> Select status </option>';

            foreach ($statuses['statuses'] as $k => $value) {
                $html .= '<option value="' . $value['code'] . '"> ' . $value['name'] . '</option>';
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
