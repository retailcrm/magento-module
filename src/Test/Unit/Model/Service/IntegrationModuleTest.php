<?php

namespace Retailcrm\Retailcrm\Test\Unit\Model\Service;

use Retailcrm\Retailcrm\Test\TestCase;

class IntegrationModuleTest extends TestCase
{
    private $mockResourceConfig;
    private $mockApiClient;
    private $mockData;
    private $unit;

    const ACCOUNT_URL = 'test';

    public function setUp(): void
    {
        $this->mockData = $this->getMockBuilder(\Retailcrm\Retailcrm\Helper\Data::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockResourceConfig = $this->getMockBuilder(\Magento\Config\Model\ResourceModel\Config::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->mockApiClient = $this->getMockBuilder(\Retailcrm\Retailcrm\Helper\Proxy::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'marketplaceSettingsEdit',
                'integrationModulesEdit'
            ])
            ->getMock();

        $this->unit = new \Retailcrm\Retailcrm\Model\Service\IntegrationModule(
            $this->mockResourceConfig,
            $this->mockData
        );
    }

    /**
     * @param $active
     * @param $apiVersion
     * @param $isSuccessful
     *
     * @dataProvider dataProvider
     */
    public function testSendConfiguration($active, $apiVersion, $isSuccessful)
    {
        $response = $this->getMockBuilder(\RetailCrm\Response\ApiResponse::class)
            ->disableOriginalConstructor()
            ->getMock();
        $response->expects($this->any())->method('isSuccessful')->willReturn($isSuccessful);

        if ($apiVersion == 'v4') {
            $this->mockApiClient->expects($this->any())->method('marketplaceSettingsEdit')
                ->willReturn($response);
        } else {
            $this->mockApiClient->expects($this->any())->method('integrationModulesEdit')
                ->willReturn($response);
        }

        $this->unit->setAccountUrl(self::ACCOUNT_URL);
        $this->unit->setApiVersion($apiVersion);
        $this->unit->sendConfiguration($this->mockApiClient, $active);
        $configuration = $this->unit->getConfiguration();

        $this->assertNotEmpty($configuration);
        $this->assertArrayHasKey('name', $configuration);
        $this->assertEquals(
            \Retailcrm\Retailcrm\Model\Service\IntegrationModule::NAME,
            $configuration['name']
        );
        $this->assertArrayHasKey('logo', $configuration);
        $this->assertEquals(
            \Retailcrm\Retailcrm\Model\Service\IntegrationModule::LOGO,
            $configuration['logo']
        );
        $this->assertArrayHasKey('code', $configuration);
        $this->assertStringContainsString(
            \Retailcrm\Retailcrm\Model\Service\IntegrationModule::INTEGRATION_CODE,
            $configuration['code']
        );
        $this->assertArrayHasKey('active', $configuration);
        $this->assertEquals($active, $configuration['active']);

        if ($apiVersion == 'v4') {
            $this->assertArrayHasKey('configurationUrl', $configuration);
            $this->assertEquals(self::ACCOUNT_URL, $configuration['configurationUrl']);
        } else {
            $this->assertArrayHasKey('accountUrl', $configuration);
            $this->assertEquals(self::ACCOUNT_URL, $configuration['accountUrl']);
            $this->assertArrayHasKey('integrationCode', $configuration);
            $this->assertStringContainsString(
                \Retailcrm\Retailcrm\Model\Service\IntegrationModule::INTEGRATION_CODE,
                $configuration['integrationCode']
            );
            $this->assertArrayHasKey('clientId', $configuration);
            $this->assertNotEmpty($configuration['clientId']);
        }
    }

    public function dataProvider()
    {
        return [
            [
                'active' => true,
                'api_version' => 'v4',
                'is_successful' => true
            ],
            [
                'active' => false,
                'api_version' => 'v4',
                'is_successful' => true
            ],
            [
                'active' => true,
                'api_version' => 'v4',
                'is_successful' => false
            ],
            [
                'active' => false,
                'api_version' => 'v4',
                'is_successful' => false
            ],
            [
                'active' => true,
                'api_version' => 'v5',
                'is_successful' => true
            ],
            [
                'active' => false,
                'api_version' => 'v5',
                'is_successful' => true
            ],
            [
                'active' => true,
                'api_version' => 'v5',
                'is_successful' => false
            ],
            [
                'active' => false,
                'api_version' => 'v5',
                'is_successful' => false
            ]
        ];
    }
}
