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

    public function setUp()
    {
        $this->mockApi = $this->getMockBuilder(\Retailcrm\Retailcrm\Helper\Proxy::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'customersEdit',
                'customersCreate'
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
                'getLastname'
            ])
            ->getMock();

        $this->unit = new \Retailcrm\Retailcrm\Model\Observer\Customer(
            $this->registry,
            $this->mockApi
        );

        $reflection = new \ReflectionClass($this->unit);
        $reflection_property = $reflection->getProperty('api');
        $reflection_property->setAccessible(true);
        $reflection_property->setValue($this->unit, $this->mockApi);
    }

    /**
     * @param boolean $isSuccessful
     * @dataProvider dataProviderCustomer
     */
    public function testExecute(
        $isSuccessful
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

        // mock Customer
        $this->mockCustomer->expects($this->once())
            ->method('getId')
            ->willReturn($testData['id']);

        $this->mockCustomer->expects($this->once())
            ->method('getEmail')
            ->willReturn($testData['email']);

        $this->mockCustomer->expects($this->once())
            ->method('getFirstname')
            ->willReturn($testData['firstname']);

        $this->mockCustomer->expects($this->once())
            ->method('getMiddlename')
            ->willReturn($testData['middlename']);

        $this->mockCustomer->expects($this->once())
            ->method('getLastname')
            ->willReturn($testData['lastname']);

        // mock Event
        $this->mockEvent->expects($this->once())
            ->method('getCustomer')
            ->willReturn($this->mockCustomer);

        // mock Observer
        $this->mockObserver->expects($this->once())
            ->method('getEvent')
            ->willReturn($this->mockEvent);

        $this->unit->execute($this->mockObserver);
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
                'is_successful' => true
            ],
            [
                'is_successful' => false
            ]
        ];
    }
}
