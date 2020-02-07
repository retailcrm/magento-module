<?php

namespace Retailcrm\Retailcrm\Test\Unit\Observer;

// backward compatibility with phpunit < v.6
if (!class_exists('\PHPUnit\Framework\TestCase') && class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', '\PHPUnit\Framework\TestCase');
}

/**
 * Order create observer test class
 */
class OrderCreateTest extends \PHPUnit\Framework\TestCase
{
    private $unit;
    private $mockEvent;
    private $mockObserver;
    private $mockRegistry;
    private $mockApi;
    private $mockOrder;
    private $mockItem;
    private $mockStore;
    private $mockBillingAddress;
    private $mockResponse;
    private $mockLogger;
    private $mockServiceOrder;
    private $mockHelper;
    private $mockServiceCustomer;
    private $mockCustomer;

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

        $this->mockLogger = $this->getMockBuilder(\Retailcrm\Retailcrm\Model\Logger\Logger::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockRegistry = $this->getMockBuilder(\Magento\Framework\Registry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockOrder = $this->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getCustomer',
                'getId',
                'getBillingAddress',
                'getStore'
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

        $this->mockServiceOrder = $this->getMockBuilder(\Retailcrm\Retailcrm\Model\Service\Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockHelper = $this->getMockBuilder(\Retailcrm\Retailcrm\Helper\Data::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockServiceCustomer = $this->getMockBuilder(\Retailcrm\Retailcrm\Model\Service\Customer::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockServiceCustomer->expects($this->any())->method('process')->willReturn($this->getCustomerTestData());
        $this->mockServiceCustomer
            ->expects($this->any())
            ->method('prepareCustomerFromOrder')
            ->willReturn(
                $this->getCustomerTestData()
            );

        $this->mockCustomer = $this->getMockBuilder(\Magento\Customer\Model\Customer::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getId',
                'getEmail',
                'getFirstname',
                'getMiddlename',
                'getLastname',
                'getStore',
                'getGender',
                'getDob',
                'getDefaultBillingAddress',
                'getAddressesCollection'
            ])
            ->getMock();

        $testData = $this->getAfterSaveCustomerTestData();

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

        $this->unit = new \Retailcrm\Retailcrm\Model\Observer\OrderCreate(
            $this->mockRegistry,
            $this->mockLogger,
            $this->mockServiceOrder,
            $this->mockServiceCustomer,
            $this->mockHelper,
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

        // order mock set data
        $this->mockOrder->expects($this->any())
            ->method('getId')
            ->willReturn($testData['order.id']);

        $this->mockOrder->expects($this->any())
            ->method('getBillingAddress')
            ->willReturn($this->mockBillingAddress);

        $this->mockOrder->expects($this->any())
            ->method('getStore')
            ->willReturn($this->mockStore);

        $this->mockOrder->expects($this->any())
            ->method('getCustomer')
            ->willReturn($this->mockCustomer);

        // mock Event
        $this->mockEvent->expects($this->any())
            ->method('getOrder')
            ->willReturn($this->mockOrder);

        // mock Observer
        $this->mockObserver->expects($this->any())
            ->method('getEvent')
            ->willReturn($this->mockEvent);

        $this->mockServiceOrder->expects($this->any())->method('process')
            ->willReturn($this->getOrderTestData($apiVersion, $customerIsGuest));
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
            $this->assertArrayHasKey('status', $this->unit->getOrder());
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

    private function getOrderTestData($apiVersion, $customerIsGuest)
    {
        $order = [
            'countryIso' => 'RU',
            'externalId' => 1,
            'number' => '000000001',
            'status' => 'new',
            'phone' => '890000000000',
            'email' => 'test@gmail.com',
            'createdAt' => date('Y-m-d H:i:s'),
            'lastName' => 'Test',
            'firstName' => 'Test',
            'patronymic' => 'Tests',
            'items' => [
                [
                    'productName' => 'Test product',
                    'quantity' => 2,
                    'initialPrice' => 1.000,
                    'offer' => [
                        'externalId' => 1
                    ]
                ]
            ],
            'delivery' => [
                'code' => 'test',
                'cost' => '100',
                'address' => [
                    'index' => '111111',
                    'city' => 'Moscow',
                    'countryIso' => 'RU',
                    'street' => 'Test street',
                    'region' => 'Test region',
                    'text' => '111111, Moscow, Test region, Test street'
                ]
            ]
        ];

        if ($apiVersion == 'v5') {
            $order['discountManualAmount'] = 0;
            $payment = [
                'type' => 'test',
                'externalId' => 1,
                'order' => [
                    'externalId' => 1,
                ],
                'status' => 'paid'
            ];

            $order['payments'][] = $payment;
        } else {
            $order['paymentType'] = 'test';
            $order['discount'] = 0;
        }

        if ($customerIsGuest == 0) {
            $order['customer']['externalId'] = 1;
        }

        return $order;
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
            'middlename' => 'Testmiddlename',
            'birthday' => '1990-01-01',
            'gender' => 1
        ];
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
}
