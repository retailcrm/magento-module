<?php

namespace Retailcrm\Retailcrm\Test\Unit\Model\Observer;

use Retailcrm\Retailcrm\Test\TestCase;

class CustomerTest extends TestCase
{
    private $mockApi;
    private $mockResponse;
    private $registry;
    private $mockObserver;
    private $mockEvent;
    private $mockCustomer;
    private $unit;
    private $helper;
    private $mockServiceCustomer;

    public function setUp(): void
    {
        $this->mockApi = $this->getMockBuilder(\Retailcrm\Retailcrm\Helper\Proxy::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'customersEdit',
                'customersCreate',
                'isConfigured',
                'setSite'
            ])
            ->getMock();

        $this->mockResponse = $this->getMockBuilder(\RetailCrm\Response\ApiResponse::class)
            ->disableOriginalConstructor()
            ->setMethods(['isSuccessful'])
            ->getMock();

        $this->registry = $this->getMockBuilder(\Magento\Framework\Registry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockObserver = $this->getMockBuilder(\Magento\Framework\Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockEvent = $this->getMockBuilder(\Magento\Framework\Event::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCustomer'])
            ->getMock();

        $this->mockCustomer = $this->getMockBuilder(\Magento\Customer\Model\Customer::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getStore'
            ])
            ->getMock();

        $this->mockServiceCustomer = $this->getMockBuilder(\Retailcrm\Retailcrm\Model\Service\Customer::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->mockServiceCustomer->expects($this->any())->method('process')->willReturn($this->getCustomerTestData());

        $this->helper = $this->createMock(\Retailcrm\Retailcrm\Helper\Data::class);

        $this->unit = new \Retailcrm\Retailcrm\Model\Observer\Customer(
            $this->registry,
            $this->helper,
            $this->mockApi,
            $this->mockServiceCustomer
        );
    }

    /**
     * @param boolean $isSuccessful
     * @param boolean $isConfigured
     * @dataProvider dataProviderCustomer
     */
    public function testExecute(
        $isSuccessful,
        $isConfigured
    ) {
        // mock Response
        $this->mockResponse->expects($this->any())
            ->method('isSuccessful')
            ->willReturn($isSuccessful);

        $this->mockResponse->errorMsg = 'Not found';

        // mock API
        $this->mockApi->expects($this->any())
            ->method('customersEdit')
            ->willReturn($this->mockResponse);

        $this->mockApi->expects($this->any())
            ->method('customersCreate')
            ->willReturn($this->mockResponse);

        $this->mockApi->expects($this->any())
            ->method('isConfigured')
            ->willReturn($isConfigured);

        $store = $this->createMock(\Magento\Store\Model\Store::class);

        $this->mockCustomer->expects($this->any())
            ->method('getStore')
            ->willReturn($store);

        // mock Event
        $this->mockEvent->expects($this->any())
            ->method('getCustomer')
            ->willReturn($this->mockCustomer);

        // mock Observer
        $this->mockObserver->expects($this->any())
            ->method('getEvent')
            ->willReturn($this->mockEvent);

        $customerObserver = $this->unit->execute($this->mockObserver);

        if ($isConfigured) {
            $this->assertNotEmpty($this->unit->getCustomer());
            $this->assertArrayHasKey('externalId', $this->unit->getCustomer());
            $this->assertArrayHasKey('email', $this->unit->getCustomer());
            $this->assertArrayHasKey('firstName', $this->unit->getCustomer());
            $this->assertArrayHasKey('lastName', $this->unit->getCustomer());
            $this->assertArrayHasKey('patronymic', $this->unit->getCustomer());
            $this->assertArrayHasKey('createdAt', $this->unit->getCustomer());
            $this->assertInstanceOf(\Retailcrm\Retailcrm\Model\Observer\Customer::class, $customerObserver);
        } else {
            $this->assertEmpty($this->unit->getCustomer());
        }
    }

    /**
     * @return array
     */
    private function getCustomerTestData()
    {
        return [
            'externalId' => 1,
            'email' => 'test@mail.com',
            'firstName' => 'TestFirstname',
            'lastName' => 'Testlastname',
            'patronymic' => 'Testmiddlename',
            'createdAt' => \date('Y-m-d H:i:s')
        ];
    }

    public function dataProviderCustomer()
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
                'is_successful' => false,
                'is_configured' => true
            ],
            [
                'is_successful' => true,
                'is_configured' => false
            ]
        ];
    }
}
