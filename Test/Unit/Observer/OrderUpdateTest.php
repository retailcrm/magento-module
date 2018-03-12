<?php

namespace Retailcrm\Retailcrm\Test\Unit\Observer;

class OrderUpdateTest extends \PHPUnit\Framework\TestCase
{
    protected $unit;
    protected $objectManager;
    protected $config;
    protected $mockApi;
    protected $mockObserver;
    protected $mockEvent;
    protected $mockOrder;
    protected $mockPayment;
    protected $registry;

    protected function setUp()
    {
        $this->mockApi = $this->getMockBuilder(\Retailcrm\Retailcrm\Helper\Proxy::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'ordersEdit',
                'ordersPaymentsEdit',
                'getVersion'
            ])
            ->getMock();

        $this->mockObserver = $this->getMockBuilder(\Magento\Framework\Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockEvent = $this->getMockBuilder(\Magento\Framework\Event::class)
            ->disableOriginalConstructor()
            ->setMethods(['getOrder'])
            ->getMock();

        $this->objectManager = $this->getMockBuilder(\Magento\Framework\ObjectManagerInterface::class)
            ->getMockForAbstractClass();

        $helper = $this->getMockBuilder(\Retailcrm\Retailcrm\Helper\Data::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockOrder = $this->getMockBuilder(\Magento\Sales\Order::class)
            ->setMethods([
                'getId',
                'getPayment',
                'getBaseTotalDue',
                'getStatus'
            ])
            ->getMock();

        $this->mockPayment = $this->getMockBuilder(\Magento\Sales\Model\Order\Payment::class)
            ->setMethods(['getId'])
            ->disableOriginalConstructor()
            ->getMock();

        // mock Object Manager
        $this->objectManager->expects($this->any())
            ->method('get')
            ->with('\Retailcrm\Retailcrm\Helper\Data')
            ->willReturn($helper);

        $this->config = $this->getMockBuilder(\Magento\Framework\App\Config\ScopeConfigInterface::class)
            ->getMockForAbstractClass();

        $this->registry = $this->getMockBuilder(\Magento\Framework\Registry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->unit = new \Retailcrm\Retailcrm\Model\Observer\OrderUpdate(
            $this->objectManager,
            $this->config,
            $this->registry
        );

        $reflection = new \ReflectionClass($this->unit);
        $reflection_property = $reflection->getProperty('_api');
        $reflection_property->setAccessible(true);
        $reflection_property->setValue($this->unit, $this->mockApi);
    }

    /**
     * @param int $getBaseTotalDue
     * @param string $apiVersion
     * @dataProvider dataProviderOrderUpdate
     */
    public function testExecute(
        $getBaseTotalDue,
        $apiVersion
    ) {
        $testData = $this->getAfterUpdateOrderTestData();

        // mock Payment
        $this->mockPayment->expects($this->any())
            ->method('getId')
            ->willReturn(1);

        // mock Order
        $this->mockOrder->expects($this->once())
            ->method('getId')
            ->willReturn($testData['order.id']);

        $this->mockOrder->expects($this->once())
            ->method('getStatus')
            ->willReturn($testData['order.status']);

        $this->mockOrder->expects($this->once())
            ->method('getBaseTotalDue')
            ->willReturn($getBaseTotalDue);

        $this->mockOrder->expects($this->any())
            ->method('getPayment')
            ->willReturn($this->mockPayment);

        // mock Api
        $this->mockApi->expects($this->any())
            ->method('getVersion')
            ->willReturn($apiVersion);

        // mock Event
        $this->mockEvent->expects($this->once())
            ->method('getOrder')
            ->willReturn($this->mockOrder);

        // mock Observer
        $this->mockObserver->expects($this->once())
            ->method('getEvent')
            ->willReturn($this->mockEvent);

        $this->unit->execute($this->mockObserver);
    }

    /**
     * Get test order data
     * 
     * @return array $testOrderData
     */
    protected function getAfterUpdateOrderTestData()
    {
        $testOrderData = [
            'order.id' => 1,
            'order.status' => 'processing',
            'order.paymentMethod' => 'checkmo'
        ];

        return $testOrderData;
    }

    public function dataProviderOrderUpdate()
    {
        return [
            [
                'get_base_total_due' => 0,
                'api_version' => 'v4'
            ],
            [
                'get_base_total_due' => 1,
                'api_version' => 'v4'
            ],
            [
                'get_base_total_due' => 0,
                'api_version' => 'v5'
            ],
            [
                'get_base_total_due' => 1,
                'api_version' => 'v5'
            ]
        ];
    }
}
