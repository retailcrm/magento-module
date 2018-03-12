<?php

namespace Retailcrm\Retailcrm\Test\Unit\Observer;

/**
 * Order create observer test class
 */
class OrderCreateTest extends \PHPUnit\Framework\TestCase
{
    protected $objectManager;
    protected $_config;
    protected $_unit;
    protected $_mockEvent;
    protected $_mockObserver;
    protected $_registry;
    protected $_mockApi;
    protected $_mockOrder;
    protected $_mockItem;
    protected $_mockStore;
    protected $_mockBillingAddress;
    protected $_mockResponse;
    protected $_mockPayment;
    protected $_mockPaymentMethod;

    protected function setUp()
    {
        $this->_mockApi = $this->getMockBuilder(\Retailcrm\Retailcrm\Helper\Proxy::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'ordersGet',
                'ordersCreate',
                'customersGet',
                'customersCreate',
                'customersList',
                'getVersion'
            ])
            ->getMock();

        $this->_mockObserver = $this->getMockBuilder(\Magento\Framework\Event\Observer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->_mockEvent = $this->getMockBuilder(\Magento\Framework\Event::class)
            ->disableOriginalConstructor()
            ->setMethods(['getOrder'])
            ->getMock();

        $this->objectManager = $this->getMockBuilder(\Magento\Framework\ObjectManagerInterface::class)
            ->getMockForAbstractClass();

        // mock Object Manager
        $this->objectManager->expects($this->any())
            ->method('get')
            ->with($this->logicalOr(
                $this->equalTo('\Retailcrm\Retailcrm\Helper\Data'),
                $this->equalTo('\Retailcrm\Retailcrm\Model\Logger\Logger')
            ))
            ->will($this->returnCallback([$this, 'getCallbackDataClasses']));
        
        $this->_config = $this->getMockBuilder(\Magento\Framework\App\Config\ScopeConfigInterface::class)
            ->getMockForAbstractClass();

        $this->_logger = $this->getMockBuilder(\Retailcrm\Retailcrm\Model\Logger\Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->_registry = $this->getMockBuilder(\Magento\Framework\Registry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->_mockOrder = $this->getMockBuilder(\Magento\Sales\Order::class)
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

        $this->_mockPayment = $this->getMockBuilder(\Magento\Sales\Model\Order\Payment::class)
            ->setMethods(['getMethodInstance'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->_mockPaymentMethod = $this->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();

        $this->_mockItem = $this->getMockBuilder(\Magento\Sales\Model\Order\Item::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getPrice',
                'getProductId',
                'getName',
                'getQtyOrdered',
                'getProductType'
            ])
            ->getMock();

        $this->_mockStore = $this->getMockBuilder(\Magento\Store\Model\Store::class)
            ->disableOriginalConstructor()
            ->setMethods(['getCode'])
            ->getMock();

        $this->_mockBillingAddress = $this->getMockBuilder(\Magento\Customer\Model\Address\AddressModelInterface::class)
            ->disableOriginalConstructor()
            ->setMethods(['getTelephone', 'getData'])
            ->getMockForAbstractClass();

        $this->_mockResponse = $this->getMockBuilder(\RetailCrm\Response\ApiResponse::class)
            ->disableOriginalConstructor()
            ->setMethods(['isSuccessful'])
            ->getMock();

        $this->_unit = new \Retailcrm\Retailcrm\Model\Observer\OrderCreate(
            $this->objectManager,
            $this->_config,
            $this->_registry
        );

        $reflection = new \ReflectionClass($this->_unit);
        $reflection_property = $reflection->getProperty('_api');
        $reflection_property->setAccessible(true);
        $reflection_property->setValue($this->_unit, $this->_mockApi);
    }

    /**
     * @param boolean $isSuccessful
     * @param string $errorMsg
     * @param int $customerIsGuest
     * @param string $apiVersion
     * @dataProvider dataProviderOrderCreate
     */
    public function testExecute(
        $isSuccessful,
        $errorMsg,
        $customerIsGuest,
        $apiVersion
    ) {
        $testData = $this->getAfterSaveOrderTestData();

        // mock Response
        $this->_mockResponse->expects($this->any())
            ->method('isSuccessful')
            ->willReturn($isSuccessful);

        $this->_mockResponse->errorMsg = $errorMsg;

        // mock API
        $this->_mockApi->expects($this->any())
            ->method('ordersGet')
            ->willReturn($this->_mockResponse);

        $this->_mockApi->expects($this->any())
            ->method('ordersCreate')
            ->willReturn($this->_mockResponse);

        $this->_mockApi->expects($this->any())
            ->method('customersGet')
            ->willReturn($this->_mockResponse);

        $this->_mockApi->expects($this->any())
            ->method('customersCreate')
            ->willReturn($this->_mockResponse);

        $this->_mockApi->expects($this->any())
            ->method('customersList')
            ->willReturn($this->_mockResponse);

        $this->_mockApi->expects($this->any())
            ->method('getVersion')
            ->willReturn($apiVersion);

        // billing address mock set data
        $this->_mockBillingAddress->expects($this->any())
            ->method('getTelephone')
            ->willReturn($testData['order.billingAddress']['telephone']);

        $this->_mockBillingAddress->expects($this->any())
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
        $this->_mockStore->expects($this->any())
            ->method('getCode')
            ->willReturn(1);

        // order item mock set data
        $this->_mockItem->expects($this->any())
            ->method('getProductType')
            ->willReturn('simple');

        $this->_mockItem->expects($this->any())
            ->method('getPrice')
            ->willReturn(999.99);

        $this->_mockItem->expects($this->any())
            ->method('getProductId')
            ->willReturn(10);

        $this->_mockItem->expects($this->any())
            ->method('getName')
            ->willReturn('Product name');

        $this->_mockItem->expects($this->any())
            ->method('getQtyOrdered')
            ->willReturn(3);

        // order mock set data
        $this->_mockOrder->expects($this->any())
            ->method('getId')
            ->willReturn($testData['order.id']);

        $this->_mockOrder->expects($this->any())
            ->method('getBillingAddress')
            ->willReturn($this->_mockBillingAddress);

        $this->_mockOrder->expects($this->any())
            ->method('getShippingMethod')
            ->willReturn($testData['order.shippingMethod']);

        $this->_mockOrder->expects($this->any())
            ->method('getStore')
            ->willReturn($this->_mockStore);

        $this->_mockOrder->expects($this->any())
            ->method('getRealOrderId')
            ->willReturn($testData['order.realOrderId']);

        $this->_mockOrder->expects($this->any())
            ->method('getCreatedAt')
            ->willReturn(date('Y-m-d H:i:s'));

        $this->_mockOrder->expects($this->any())
            ->method('getCustomerLastname')
            ->willReturn($testData['order.customerLastname']);

        $this->_mockOrder->expects($this->any())
            ->method('getCustomerFirstname')
            ->willReturn($testData['order.customerFirstname']);

        $this->_mockOrder->expects($this->any())
            ->method('getCustomerMiddlename')
            ->willReturn($testData['order.customerMiddlename']);

        $this->_mockOrder->expects($this->any())
            ->method('getCustomerEmail')
            ->willReturn($testData['order.customerEmail']);

        $this->_mockOrder->expects($this->any())
            ->method('getAllItems')
            ->willReturn($testData['order.allItems']);

        $this->_mockOrder->expects($this->any())
            ->method('getStatus')
            ->willReturn($testData['order.status']);

        $this->_mockOrder->expects($this->any())
            ->method('getCustomerIsGuest')
            ->willReturn($customerIsGuest);

        $this->_mockOrder->expects($this->any())
            ->method('getCustomerId')
            ->willReturn(1);

        $this->_mockOrder->expects($this->any())
            ->method('getPayment')
            ->willReturn($this->_mockPayment);

        // mock Payment Method
        $this->_mockPaymentMethod->expects($this->any())
            ->method('getCode')
            ->willReturn($testData['order.paymentMethod']);

        // mock Payment
        $this->_mockPayment->expects($this->any())
            ->method('getMethodInstance')
            ->willReturn($this->_mockPaymentMethod);

        // mock Event
        $this->_mockEvent->expects($this->once())
            ->method('getOrder')
            ->willReturn($this->_mockOrder);

        // mock Observer
        $this->_mockObserver->expects($this->once())
            ->method('getEvent')
            ->willReturn($this->_mockEvent);

        $this->_unit->execute($this->_mockObserver);
    }

    /**
     * Get test order data
     * 
     * @return array $testOrderData
     */
    protected function getAfterSaveOrderTestData()
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
            'order.allItems' => [$this->_mockItem],
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
                'api_version' => 'v4'
            ],
            [
                'is_successful' => true,
                'error_msg' => 'Not found',
                'customer_is_guest' => 0,
                'api_version' => 'v4'
            ],
            [
                'is_successful' => false,
                'error_msg' => 'Not found',
                'customer_is_guest' => 1,
                'api_version' => 'v4'
            ],
            [
                'is_successful' => false,
                'error_msg' => 'Not found',
                'customer_is_guest' => 0,
                'api_version' => 'v4'
            ],
            [
                'is_successful' => true,
                'error_msg' => 'Not found',
                'customer_is_guest' => 1,
                'api_version' => 'v5'
            ],
            [
                'is_successful' => true,
                'error_msg' => 'Not found',
                'customer_is_guest' => 0,
                'api_version' => 'v5'
            ],
            [
                'is_successful' => false,
                'error_msg' => 'Not found',
                'customer_is_guest' => 1,
                'api_version' => 'v5'
            ],
            [
                'is_successful' => false,
                'error_msg' => 'Not found',
                'customer_is_guest' => 0,
                'api_version' => 'v5'
            ]
        ];
    }
}
