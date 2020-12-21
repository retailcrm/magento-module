<?php

namespace Retailcrm\Retailcrm\Test\Unit\Model\Service;

use Retailcrm\Retailcrm\Test\TestCase;

class CustomerTest extends TestCase
{
    private $mockData;
    private $mockCustomer;
    private $unit;
    private $mockOrder;
    private $mockBillingAddress;

    public function setUp()
    {
        $this->mockData = $this->getMockBuilder(\Retailcrm\Retailcrm\Helper\Data::class)
            ->disableOriginalConstructor()
            ->getMock();

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

        $this->mockOrder = $this->getMockBuilder(\Magento\Sales\Model\Order::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getBillingAddress',
            ])
            ->getMock();

        $this->mockBillingAddress = $this->getMockBuilder(\Magento\Customer\Model\Address\AddressModelInterface::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getTelephone',
                'getEmail',
                'getData',
                'getFirstname',
                'getMiddlename',
                'getLastname',
                'getCountryId',
                'getPostcode',
                'getRegion',
                'getCity',
                'getStreet'
            ])
            ->getMockForAbstractClass();

        $this->unit = new \Retailcrm\Retailcrm\Model\Service\Customer(
            $this->mockData
        );
    }

    public function testPrepareCustomerFromOrder()
    {
        $this->mockOrder->expects($this->any())
            ->method('getBillingAddress')
            ->willReturn($this->mockBillingAddress);

        $result = $this->unit->prepareCustomerFromOrder($this->mockOrder);

        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('externalId', $result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('firstName', $result);
        $this->assertArrayHasKey('lastName', $result);
        $this->assertArrayHasKey('patronymic', $result);
        $this->assertArrayHasKey('address', $result);
        $this->assertIsArray($result['address']);
        $this->assertArrayHasKey('countryIso', $result['address']);
        $this->assertArrayHasKey('index', $result['address']);
        $this->assertArrayHasKey('region', $result['address']);
        $this->assertArrayHasKey('city', $result['address']);
        $this->assertArrayHasKey('street', $result['address']);
        $this->assertArrayHasKey('text', $result['address']);
    }

    /**
     * @param $apiVersion
     *
     * @dataProvider dataProvider
     */
    public function testProcess($apiVersion)
    {
        $mockAddress = $this->getMockBuilder(\Magento\Customer\Model\Address::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getData',
                'getTelephone'
            ])
            ->getMock();

        $mockAddress->expects($this->any())
            ->method('getData')
            ->with(
                $this->logicalOr(
                    $this->equalTo('postcode'),
                    $this->equalTo('country_id'),
                    $this->equalTo('region'),
                    $this->equalTo('city'),
                    $this->equalTo('street')
                )
            )
            ->will($this->returnCallback([$this, 'getCallbackDataAddress']));

        $mockAddress->expects($this->any())
            ->method('getTelephone')
            ->willReturn('000-00-00');

        $this->mockData->expects($this->any())
            ->method('getGeneralSettings')
            ->with('api_version')
            ->willReturn($apiVersion);

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

        $this->mockCustomer->expects($this->any())
            ->method('getGender')
            ->willReturn($testData['gender']);

        $this->mockCustomer->expects($this->any())
            ->method('getDob')
            ->willReturn($testData['birthday']);

        $this->mockCustomer->expects($this->any())
            ->method('getDefaultBillingAddress')
            ->willReturn($mockAddress);

        $this->mockCustomer->expects($this->any())
            ->method('getAddressesCollection')
            ->willReturn([$mockAddress]);

        $result = $this->unit->process($this->mockCustomer);

        $this->assertNotEmpty($result);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('externalId', $result);
        $this->assertNotEmpty($result['externalId']);
        $this->assertEquals($this->getAfterSaveCustomerTestData()['id'], $result['externalId']);
        $this->assertArrayHasKey('email', $result);
        $this->assertNotEmpty($result['email']);
        $this->assertEquals($this->getAfterSaveCustomerTestData()['email'], $result['email']);
        $this->assertArrayHasKey('firstName', $result);
        $this->assertNotEmpty($result['firstName']);
        $this->assertEquals($this->getAfterSaveCustomerTestData()['firstname'], $result['firstName']);
        $this->assertArrayHasKey('lastName', $result);
        $this->assertNotEmpty($result['lastName']);
        $this->assertEquals($this->getAfterSaveCustomerTestData()['lastname'], $result['lastName']);
        $this->assertArrayHasKey('patronymic', $result);
        $this->assertNotEmpty($result['patronymic']);
        $this->assertEquals($this->getAfterSaveCustomerTestData()['middlename'], $result['patronymic']);

        if ($apiVersion == 'v5') {
            $this->assertArrayHasKey('birthday', $result);
            $this->assertNotEmpty($result['birthday']);
            $this->assertEquals($this->getAfterSaveCustomerTestData()['birthday'], $result['birthday']);
            $this->assertArrayHasKey('sex', $result);
            $this->assertNotEmpty($result['sex']);
            $this->assertEquals('male', $result['sex']);
        }
    }

    public function getCallbackDataAddress($dataKey)
    {
        $address = [
            'postcode' => 'test-index',
            'country_id' => 'US',
            'region' => 'test-region',
            'city' => 'test-city',
            'street' => 'test-street'
        ];

        return $address[$dataKey];
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
     * Data provider
     *
     * @return array
     */
    public function dataProvider()
    {
        return [
            [
                'api_version' => 'v4',
            ],
            [
                'api_version' => 'v5'
            ]
        ];
    }
}
