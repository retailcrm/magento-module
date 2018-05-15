<?php

namespace Retailcrm\Retailcrm\Test\Unit\Observer;

/**
 * Order create observer test class
 */
class OrderCreateTest extends \PHPUnit\Framework\TestCase
{
    private $config;
    private $unit;
    private $mockEvent;
    private $mockObserver;
    private $registry;
    private $mockApi;
    private $mockOrder;
    private $mockItem;
    private $mockStore;
    private $mockBillingAddress;
    private $mockResponse;
    private $mockPayment;
    private $mockPaymentMethod;
    private $logger;

    public function setUp()
    {
        $this->mockApi = $this->getMockBuilder(\Retailcrm\Retailcrm\Helper\Proxy::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'ordersGet',
                'ordersCreate',
                'customersGet',
                'customersCreate',
                'customersList',
                'getVersion',
                'isConfigured',
                'setSite'
            ])
            ->getMock();

        $this->mockObserver = $this->getMockBuilder(\Magento\Framework\Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockEvent = $this->getMockBuilder(\Magento\Framework\Event::class)
            ->disableOriginalConstructor()
            ->setMethods(['getOrder'])
            ->getMock();
        
        $this->config = $this->getMockBuilder(\Magento\Framework\App\Config\ScopeConfigInterface::class)
            ->getMockForAbstractClass();

        $this->logger = $this->getMockBuilder(\Retailcrm\Retailcrm\Model\Logger\Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->registry = $this->getMockBuilder(\Magento\Framework\Registry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockOrder = $this->getMockBuilder(\Magento\Sales\Order::class)
            ->setMethods([
                'getId',
                'getRealOrderId',
                'getCreatedAt',
                'getStore',
                'getBillingAddress',
                'getShippingMethod',
                'getCustomerId',
                'getCustomerLastname',
                'getCustomerFirstname',
                'getCustomerMiddlename',
                'getCustomerEmail',
                'getShippingAmount',
                'getDiscountAmount',
                'getPayment',
                'getBaseTotalDue',
                'getCustomerIsGuest',
                'getAllItems',
                'getStatus'
            ])
            ->getMock();

        $this->mockPayment = $this->getMockBuilder(\Magento\Sales\Model\Order\Payment::class)
            ->setMethods(['getMethodInstance'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockPaymentMethod = $this->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->mockItem = $this->getMockBuilder(\Magento\Sales\Model\Order\Item::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getPrice',
                'getProductId',
                'getName',
                'getQtyOrdered',
                'getProductType'
            ])
            ->getMock();

        $this->mockStore = $this->getMockBuilder(\Magento\Store\Model\Store::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCode'])
            ->getMock();

        $this->mockBillingAddress = $this->getMockBuilder(\Magento\Customer\Model\Address\AddressModelInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getTelephone', 'getData'])
            ->getMockForAbstractClass();

        $this->mockResponse = $this->getMockBuilder(\RetailCrm\Response\ApiResponse::class)
            ->disableOriginalConstructor()
            ->setMethods(['isSuccessful'])
            ->getMock();

        $product = $this->getMockBuilder(\Magento\Catalog\Model\ProductRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $helper = $this->createMock(\Retailcrm\Retailcrm\Helper\Data::class);

        $this->unit = new \Retailcrm\Retailcrm\Model\Observer\OrderCreate(
            $this->config,
            $this->registry,
            $this->logger,
            $product,
            $helper,
            $this->mockApi
        );
    }

    /**
     * @param boolean $isSuccessful
     * @param string $errorMsg
     * @param int $customerIsGuest
     * @param string $apiVersion
     * @param boolean $isConfigured
     * @dataProvider dataProviderOrderCreate
     */
    public function testExecute(
        $isSuccessful,
        $errorMsg,
        $customerIsGuest,
        $apiVersion,
        $isConfigured
    ) {
        $testData = $this->getAfterSaveOrderTestData();

        // mock Response
        $this->mockResponse->expects($this->any())
            ->method('isSuccessful')
            ->willReturn($isSuccessful);

        $this->mockResponse->errorMsg = $errorMsg;

        // mock API
        $this->mockApi->expects($this->any())
            ->method('ordersGet')
            ->willReturn($this->mockResponse);

        $this->mockApi->expects($this->any())
            ->method('ordersCreate')
            ->willReturn($this->mockResponse);

        $this->mockApi->expects($this->any())
            ->method('customersGet')
            ->willReturn($this->mockResponse);

        $this->mockApi->expects($this->any())
            ->method('customersCreate')
            ->willReturn($this->mockResponse);

        $this->mockApi->expects($this->any())
            ->method('customersList')
            ->willReturn($this->mockResponse);

        $this->mockApi->expects($this->any())
            ->method('getVersion')
            ->willReturn($apiVersion);

        $this->mockApi->expects($this->any())
            ->method('isConfigured')
            ->willReturn($isConfigured);

        // billing address mock set data
        $this->mockBillingAddress->expects($this->any())
            ->method('getTelephone')
            ->willReturn($testData['order.billingAddress']['telephone']);

        $this->mockBillingAddress->expects($this->any())
            ->method('getData')
            ->with($this->logicalOr(
                $this->equalTo('city'),
                $this->equalTo('region'),
                $this->equalTo('street'),
                $this->equalTo('postcode'),
                $this->equalTo('country_id')
            ))
            ->will($this->returnCallback([$this, 'getCallbackDataAddress']));

        // store mock set data
        $this->mockStore->expects($this->any())
            ->method('getCode')
            ->willReturn(1);

        // order item mock set data
        $this->mockItem->expects($this->any())
            ->method('getProductType')
            ->willReturn('simple');

        $this->mockItem->expects($this->any())
            ->method('getPrice')
            ->willReturn(999.99);

        $this->mockItem->expects($this->any())
            ->method('getProductId')
            ->willReturn(10);

        $this->mockItem->expects($this->any())
            ->method('getName')
            ->willReturn('Product name');

        $this->mockItem->expects($this->any())
            ->method('getQtyOrdered')
            ->willReturn(3);

        // order mock set data
        $this->mockOrder->expects($this->any())
            ->method('getId')
            ->willReturn($testData['order.id']);

        $this->mockOrder->expects($this->any())
            ->method('getBillingAddress')
            ->willReturn($this->mockBillingAddress);

        $this->mockOrder->expects($this->any())
            ->method('getShippingMethod')
            ->willReturn($testData['order.shippingMethod']);

        $this->mockOrder->expects($this->any())
            ->method('getStore')
            ->willReturn($this->mockStore);

        $this->mockOrder->expects($this->any())
            ->method('getRealOrderId')
            ->willReturn($testData['order.realOrderId']);

        $this->mockOrder->expects($this->any())
            ->method('getCreatedAt')
            ->willReturn(date('Y-m-d H:i:s'));

        $this->mockOrder->expects($this->any())
            ->method('getCustomerLastname')
            ->willReturn($testData['order.customerLastname']);

        $this->mockOrder->expects($this->any())
            ->method('getCustomerFirstname')
            ->willReturn($testData['order.customerFirstname']);

        $this->mockOrder->expects($this->any())
            ->method('getCustomerMiddlename')
            ->willReturn($testData['order.customerMiddlename']);

        $this->mockOrder->expects($this->any())
            ->method('getCustomerEmail')
            ->willReturn($testData['order.customerEmail']);

        $this->mockOrder->expects($this->any())
            ->method('getAllItems')
            ->willReturn($testData['order.allItems']);

        $this->mockOrder->expects($this->any())
            ->method('getStatus')
            ->willReturn($testData['order.status']);

        $this->mockOrder->expects($this->any())
            ->method('getCustomerIsGuest')
            ->willReturn($customerIsGuest);

        $this->mockOrder->expects($this->any())
            ->method('getCustomerId')
            ->willReturn(1);

        $this->mockOrder->expects($this->any())
            ->method('getPayment')
            ->willReturn($this->mockPayment);

        // mock Payment Method
        $this->mockPaymentMethod->expects($this->any())
            ->method('getCode')
            ->willReturn($testData['order.paymentMethod']);

        // mock Payment
        $this->mockPayment->expects($this->any())
            ->method('getMethodInstance')
            ->willReturn($this->mockPaymentMethod);

        // mock Event
        $this->mockEvent->expects($this->any())
            ->method('getOrder')
            ->willReturn($this->mockOrder);

        // mock Observer
        $this->mockObserver->expects($this->any())
            ->method('getEvent')
            ->willReturn($this->mockEvent);

        $orderCreateObserver = $this->unit->execute($this->mockObserver);

        if ($isConfigured && !$isSuccessful) {
            $this->assertNotEmpty($this->unit->getOrder());
            $this->assertArrayHasKey('externalId', $this->unit->getOrder());
            $this->assertArrayHasKey('number', $this->unit->getOrder());
            $this->assertArrayHasKey('createdAt', $this->unit->getOrder());
            $this->assertArrayHasKey('lastName', $this->unit->getOrder());
            $this->assertArrayHasKey('firstName', $this->unit->getOrder());
            $this->assertArrayHasKey('patronymic', $this->unit->getOrder());
            $this->assertArrayHasKey('email', $this->unit->getOrder());
            $this->assertArrayHasKey('phone', $this->unit->getOrder());
//            $this->assertArrayHasKey('status', $this->unit->getOrder());
            $this->assertArrayHasKey('items', $this->unit->getOrder());
            $this->assertArrayHasKey('delivery', $this->unit->getOrder());

            if ($apiVersion == 'v5') {
                $this->assertArrayHasKey('payments', $this->unit->getOrder());
            } else {
                $this->assertArrayHasKey('paymentType', $this->unit->getOrder());
            }

            $this->assertInstanceOf(\Retailcrm\Retailcrm\Model\Observer\OrderCreate::class, $orderCreateObserver);
        } elseif (!$isConfigured || $isSuccessful) {
            $this->assertEmpty($this->unit->getOrder());
        }
    }

    /**
     * Get test order data
     *
     * @return array $testOrderData
     */
    private function getAfterSaveOrderTestData()
    {
        $testOrderData = [
            'order.id' => 1,
            'order.status' => 'processing',
            'order.realOrderId' => '000000001',
            'order.billingAddress' => [
                'telephone' => '890000000000',
                'data' => [
                    'city' => 'Moscow',
                    'region' => 'Moscow',
                    'street' => 'TestStreet',
                    'postcode' => '111111',
                    'country_id' => 'RU'
                ]
            ],
            'order.allItems' => [$this->mockItem],
            'order.shippingMethod' => 'flatrate_flatrate',
            'order.paymentMethod' => 'checkmo',
            'order.customerLastname' => 'Test',
            'order.customerFirstname' => 'Test',
            'order.customerMiddlename' => 'Test',
            'order.customerEmail' => 'test@gmail.com'
        ];

        return $testOrderData;
    }

    public function getCallbackDataAddress($dataKey)
    {
        $testData = $this->getAfterSaveOrderTestData();

        return $testData['order.billingAddress']['data'][$dataKey];
    }

    public function getCallbackDataClasses($class)
    {
        $helper = $this->getMockBuilder(\Retailcrm\Retailcrm\Helper\Data::class)
           ->disableOriginalConstructor()
           ->getMock();

        $logger = $this->getMockBuilder(\Retailcrm\Retailcrm\Model\Logger\Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        if ($class == '\Retailcrm\Retailcrm\Helper\Data') {
            return $helper;
        }

        if ($class == '\Retailcrm\Retailcrm\Model\Logger\Logger') {
            return $logger;
        }
    }

    public function dataProviderOrderCreate()
    {
        return [
            [
                'is_successful' => true,
                'error_msg' => 'Not found',
                'customer_is_guest' => 1,
                'api_version' => 'v4',
                'is_configured' => true
            ],
            [
                'is_successful' => true,
                'error_msg' => 'Not found',
                'customer_is_guest' => 0,
                'api_version' => 'v4',
                'is_configured' => false
            ],
            [
                'is_successful' => false,
                'error_msg' => 'Not found',
                'customer_is_guest' => 1,
                'api_version' => 'v4',
                'is_configured' => true
            ],
            [
                'is_successful' => false,
                'error_msg' => 'Not found',
                'customer_is_guest' => 0,
                'api_version' => 'v4',
                'is_configured' => false
            ],
            [
                'is_successful' => true,
                'error_msg' => 'Not found',
                'customer_is_guest' => 1,
                'api_version' => 'v5',
                'is_configured' => true
            ],
            [
                'is_successful' => true,
                'error_msg' => 'Not found',
                'customer_is_guest' => 0,
                'api_version' => 'v5',
                'is_configured' => false
            ],
            [
                'is_successful' => false,
                'error_msg' => 'Not found',
                'customer_is_guest' => 1,
                'api_version' => 'v5',
                'is_configured' => true
            ],
            [
                'is_successful' => false,
                'error_msg' => 'Not found',
                'customer_is_guest' => 0,
                'api_version' => 'v5',
                'is_configured' => false
            ]
        ];
    }
}
