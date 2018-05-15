<?php

namespace Retailcrm\Retailcrm\Test\Unit\Observer;

class CustomerTest extends \PHPUnit\Framework\TestCase
{
    private $mockApi;
    private $mockResponse;
    private $registry;
    private $mockObserver;
    private $mockEvent;
    private $mockCustomer;
    private $unit;
    private $helper;

    public function setUp()
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
                'getId',
                'getEmail',
                'getFirstname',
                'getMiddlename',
                'getLastname',
                'getStore'
            ])
            ->getMock();

        $this->helper = $this->createMock(\Retailcrm\Retailcrm\Helper\Data::class);

        $this->unit = new \Retailcrm\Retailcrm\Model\Observer\Customer(
            $this->registry,
            $this->helper,
            $this->mockApi
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
        $testData = $this->getAfterSaveCustomerTestData();

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

        // mock Customer
        $this->mockCustomer->expects($this->any())
            ->method('getId')
            ->willReturn($testData['id']);

        $this->mockCustomer->expects($this->any())
            ->method('getEmail')
            ->willReturn($testData['email']);

        $this->mockCustomer->expects($this->any())
            ->method('getFirstname')
            ->willReturn($testData['firstname']);

        $this->mockCustomer->expects($this->any())
            ->method('getMiddlename')
            ->willReturn($testData['middlename']);

        $this->mockCustomer->expects($this->any())
            ->method('getLastname')
            ->willReturn($testData['lastname']);

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
            $this->assertInstanceOf(\RetailCrm\Retailcrm\Model\Observer\Customer::class, $customerObserver);
        } else {
            $this->assertEmpty($this->unit->getCustomer());
        }
    }

    /**
     * Get test customer data
     *
     * @return array
     */
    private function getAfterSaveCustomerTestData()
    {
        return [
            'id' => 1,
            'email' => 'test@mail.com',
            'firstname' => 'TestFirstname',
            'lastname' => 'Testlastname',
            'middlename' => 'Testmiddlename'
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
