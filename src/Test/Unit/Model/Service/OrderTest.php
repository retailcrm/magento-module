<?php

namespace Retailcrm\Retailcrm\Test\Unit\Model\Service;

// backward compatibility with phpunit < v.6
if (!class_exists('\PHPUnit\Framework\TestCase') && class_exists('\PHPUnit_Framework_TestCase')) {
    class_alias('\PHPUnit_Framework_TestCase', '\PHPUnit\Framework\TestCase');
}

class OrderTest extends \PHPUnit\Framework\TestCase
{
    private $mockProductRepository;
    private $mockHelper;
    private $mockConfig;
    private $mockConfigurableProduct;
    private $mockOrder;
    private $unit;

    public function setUp()
    {
        $this->mockProductRepository = $this->getMockBuilder(\Magento\Catalog\Model\ProductRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockConfigurableProduct = $this->getMockBuilder(
            \Magento\ConfigurableProduct\Model\Product\Type\Configurable::class
        )->disableOriginalConstructor()->getMock();

        $this->mockHelper = $this->createMock(\Retailcrm\Retailcrm\Helper\Data::class);

        $this->mockConfig = $this->getMockBuilder(\Magento\Framework\App\Config\ScopeConfigInterface::class)
            ->getMockForAbstractClass();

        $this->mockOrder = $this->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->unit = new \Retailcrm\Retailcrm\Model\Service\Order(
            $this->mockProductRepository,
            $this->mockConfig,
            $this->mockConfigurableProduct,
            $this->mockHelper
        );
    }

    /**
     * @dataProvider dataProvider
     *
     * @param $productType
     * @param $customerIsGuest
     * @param $apiVersion
     */
    public function testProcess($productType, $customerIsGuest, $apiVersion)
    {
        $this->mockHelper->expects($this->any())->method('getGeneralSettings')
            ->with('api_version')->willReturn($apiVersion);

        $this->mockHelper->expects($this->any())->method('getConfigPayments')->willReturn(['checkmo'=>'test']);
        $this->mockHelper->expects($this->any())->method('getCongigStatus')->willReturn(['processing'=>'test']);
        $this->mockHelper->expects($this->any())->method('getCongigShipping')->willReturn(['flatrate'=>'test']);

        $this->mockConfig->expects($this->any())->method('getValue')
            ->with($this->logicalOr(
                $this->equalTo('retailcrm/retailcrm_site/default')
            ))->will($this->returnCallback([$this, 'getCallbackDataConfig']));

        $mockProduct = $this->getMockBuilder(\Magento\Catalog\Model\Product::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mockProduct->expects($this->any())->method('getId')->willReturn(1);

        $mockItem = $this->getMockBuilder(\Magento\Sales\Model\Order\Item::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockItem->expects($this->any())->method('getProductType')->willReturn($productType);
        $mockItem->expects($this->any())->method('getProductOptions')
            ->willReturn([
                'attributes_info' => [
                    [
                        'option_id' => 1,
                        'option_value' => 1
                    ]
                ]
            ]);
        $mockItem->expects($this->any())->method('getProduct')->willReturn($mockProduct);
        $mockItem->expects($this->any())->method('getPrice')->willReturn(100.000);
        $mockItem->expects($this->any())->method('getName')->willReturn('Test product');
        $mockItem->expects($this->any())->method('getQtyOrdered')->willReturn(2);

        $mockConfigurableProduct = $this->getMockBuilder(
            \Magento\ConfigurableProduct\Model\Product\Type\Configurable::class
        )
            ->disableOriginalConstructor()
            ->getMock();
        $mockConfigurableProduct->expects($this->any())->method('getProductByAttributes')->willReturn($mockProduct);

        $mockShippingAddress = $this->getMockBuilder(\Magento\Sales\Model\Order\Address::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getData',
                'getTelephone',
                'getFirstname',
                'getLastname',
                'getMiddlename',
                'getEmail'
            ])
            ->getMock();
        $mockShippingAddress->expects($this->any())
            ->method('getData')
            ->with($this->logicalOr(
                $this->equalTo('city'),
                $this->equalTo('region'),
                $this->equalTo('street'),
                $this->equalTo('postcode'),
                $this->equalTo('country_id')
            ))
            ->will($this->returnCallback([$this, 'getCallbackDataAddress']));

        $mockShippingAddress->expects($this->any())->method('getTelephone')->willReturn('89000000000');
        $mockShippingAddress->expects($this->any())->method('getFirstname')->willReturn('Test');
        $mockShippingAddress->expects($this->any())->method('getLastname')->willReturn('Test');
        $mockShippingAddress->expects($this->any())->method('getMiddlename')->willReturn('Test');
        $mockShippingAddress->expects($this->any())->method('getEmail')->willReturn('test@mail.com');

        $mockPaymentMethod = $this->getMockBuilder(\Magento\Payment\Model\MethodInterface::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $mockPaymentMethod->expects($this->any())->method('getCode')->willReturn('checkmo');

        $mockPayment = $this->getMockBuilder(\Magento\Sales\Model\Order\Payment::class)
            ->setMethods(['getMethodInstance'])
            ->disableOriginalConstructor()
            ->getMock();
        $mockPayment->expects($this->any())->method('getMethodInstance')->willReturn($mockPaymentMethod);

        $this->mockOrder->expects($this->any())->method('getAllItems')->willReturn([$mockItem]);
        $this->mockOrder->expects($this->any())->method('getShippingAddress')->willReturn($mockShippingAddress);
        $this->mockOrder->expects($this->any())->method('getShippingMethod')->willReturn('flatrate_flatrate');
        $this->mockOrder->expects($this->any())->method('getId')->willReturn(1);
        $this->mockOrder->expects($this->any())->method('getRealOrderId')->willReturn('000000001');
        $this->mockOrder->expects($this->any())->method('getCreatedAt')->willReturn(date('Y-m-d H:i:s'));
        $this->mockOrder->expects($this->any())->method('getStatus')->willReturn('processing');
        $this->mockOrder->expects($this->any())->method('getShippingAmount')->willReturn(100);
        $this->mockOrder->expects($this->any())->method('getDiscountAmount')->willReturn(0);
        $this->mockOrder->expects($this->any())->method('getPayment')->willReturn($mockPayment);
        $this->mockOrder->expects($this->any())->method('getBaseTotalDue')->willReturn(0);
        $this->mockOrder->expects($this->any())->method('getCustomerIsGuest')->willReturn($customerIsGuest);
        $this->mockOrder->expects($this->any())->method('getCustomerId')->willReturn(1);

        $resultOrder = $this->unit->process($this->mockOrder);

        $this->assertNotEmpty($resultOrder);
        $this->assertArrayHasKey('externalId', $resultOrder);
        $this->assertArrayHasKey('number', $resultOrder);
        $this->assertArrayHasKey('createdAt', $resultOrder);
        $this->assertArrayHasKey('lastName', $resultOrder);
        $this->assertArrayHasKey('firstName', $resultOrder);
        $this->assertArrayHasKey('patronymic', $resultOrder);
        $this->assertArrayHasKey('email', $resultOrder);
        $this->assertArrayHasKey('phone', $resultOrder);
        $this->assertArrayHasKey('items', $resultOrder);
        $this->assertArrayHasKey('delivery', $resultOrder);

        if ($apiVersion == 'v5') {
            $this->assertArrayHasKey('payments', $resultOrder);
        } else {
            $this->assertArrayHasKey('paymentType', $resultOrder);
        }
    }

    public function getCallbackDataConfig($key)
    {
        $data = [
            'retailcrm/retailcrm_site/default' => 'test'
        ];

        return $data[$key];
    }

    public function dataProvider()
    {
        return [
            [
                'product_type' => 'simple',
                'customer_is_guest' => 1,
                'api_version' => 'v4'
            ],
            [
                'product_type' => 'configurable',
                'customer_is_guest' => 1,
                'api_version' => 'v4'
            ],
            [
                'product_type' => 'simple',
                'customer_is_guest' => 0,
                'api_version' => 'v4'
            ],
            [
                'product_type' => 'configurable',
                'customer_is_guest' => 0,
                'api_version' => 'v4'
            ],
            [
                'product_type' => 'simple',
                'customer_is_guest' => 1,
                'api_version' => 'v5'
            ],
            [
                'product_type' => 'configurable',
                'customer_is_guest' => 1,
                'api_version' => 'v5'
            ],
            [
                'product_type' => 'simple',
                'customer_is_guest' => 0,
                'api_version' => 'v5'
            ],
            [
                'product_type' => 'configurable',
                'customer_is_guest' => 0,
                'api_version' => 'v5'
            ]
        ];
    }

    public function getCallbackDataAddress($dataKey)
    {
        $address = [
            'city' => 'Moscow',
            'region' => 'Moscow',
            'street' => 'Test street',
            'postcode' => '111111',
            'country_id' => 'RU'
        ];

        return $address[$dataKey];
    }
}
