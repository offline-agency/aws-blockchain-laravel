<?php

namespace AwsBlockchain\Laravel\Tests\Unit;

use AwsBlockchain\Laravel\BlockchainManager;
use AwsBlockchain\Laravel\Tests\TestCase;
use InvalidArgumentException;

class BlockchainManagerEdgeCasesTest extends TestCase
{
    public function test_throws_exception_for_unknown_driver()
    {
        $config = [
            'default_driver' => 'unknown',
            'drivers' => [
                'mock' => ['type' => 'mock'],
            ],
        ];

        $manager = new BlockchainManager($config);

        $this->expectException(InvalidArgumentException::class);
        $manager->driver('unknown');
    }

    public function test_uses_default_driver_when_none_specified()
    {
        $config = [
            'default_driver' => 'mock',
            'drivers' => [
                'mock' => ['type' => 'mock'],
            ],
        ];

        $manager = new BlockchainManager($config);
        $driver = $manager->driver();

        $this->assertNotNull($driver);
        $this->assertEquals('mock', $driver->getType());
    }

    public function test_handles_empty_drivers_config()
    {
        $config = [
            'default_driver' => 'mock',
            'drivers' => [],
        ];

        $manager = new BlockchainManager($config);

        $this->expectException(InvalidArgumentException::class);
        $manager->driver();
    }

    public function test_handles_missing_default_driver()
    {
        $config = [
            'drivers' => [
                'mock' => ['type' => 'mock'],
            ],
        ];

        $manager = new BlockchainManager($config);

        // Should use 'mock' as fallback
        $driver = $manager->driver();
        $this->assertNotNull($driver);
    }

    public function test_handles_missing_public_driver()
    {
        $config = [
            'default_driver' => 'mock',
            'drivers' => [
                'mock' => ['type' => 'mock'],
            ],
        ];

        $manager = new BlockchainManager($config);

        // Should use 'mock' as fallback
        $driver = $manager->publicDriver();
        $this->assertNotNull($driver);
        $this->assertEquals('mock', $driver->getType());
    }

    public function test_handles_missing_private_driver()
    {
        $config = [
            'default_driver' => 'mock',
            'drivers' => [
                'mock' => ['type' => 'mock'],
            ],
        ];

        $manager = new BlockchainManager($config);

        // Should use 'mock' as fallback
        $driver = $manager->privateDriver();
        $this->assertNotNull($driver);
        $this->assertEquals('mock', $driver->getType());
    }

    public function test_get_available_drivers_handles_exceptions()
    {
        $config = [
            'default_driver' => 'mock',
            'drivers' => [
                'mock' => ['type' => 'mock'],
                'invalid' => ['type' => 'invalid'],
            ],
        ];

        $manager = new BlockchainManager($config);
        $drivers = $manager->getAvailableDrivers();

        $this->assertIsArray($drivers);
        $this->assertArrayHasKey('mock', $drivers);
        $this->assertArrayHasKey('invalid', $drivers);

        // Mock driver should be available
        $this->assertTrue($drivers['mock']['available']);

        // Invalid driver should have error
        $this->assertArrayHasKey('error', $drivers['invalid']);
    }

    public function test_driver_caching_works_correctly()
    {
        $config = [
            'default_driver' => 'mock',
            'drivers' => [
                'mock' => ['type' => 'mock'],
            ],
        ];

        $manager = new BlockchainManager($config);

        $driver1 = $manager->driver('mock');
        $driver2 = $manager->driver('mock');
        $driver3 = $manager->driver(); // default driver

        // All should be the same instance (cached)
        $this->assertSame($driver1, $driver2);
        $this->assertSame($driver1, $driver3);
    }

    public function test_different_drivers_are_different_instances()
    {
        $config = [
            'default_driver' => 'mock',
            'drivers' => [
                'mock' => ['type' => 'mock'],
                'mock2' => ['type' => 'mock'],
            ],
        ];

        $manager = new BlockchainManager($config);

        $driver1 = $manager->driver('mock');
        $driver2 = $manager->driver('mock2');

        // Different drivers should be different instances
        $this->assertNotSame($driver1, $driver2);
    }

    public function test_set_default_driver_affects_subsequent_calls()
    {
        $config = [
            'default_driver' => 'mock',
            'drivers' => [
                'mock' => ['type' => 'mock'],
                'mock2' => ['type' => 'mock'],
            ],
        ];

        $manager = new BlockchainManager($config);

        $originalDriver = $manager->driver();
        $manager->setDefaultDriver('mock2');
        $newDriver = $manager->driver();

        $this->assertNotSame($originalDriver, $newDriver);
        $this->assertEquals('mock', $originalDriver->getType());
        $this->assertEquals('mock', $newDriver->getType()); // Both use 'mock' type from config, but different instances
    }

    public function test_config_preservation()
    {
        $config = [
            'default_driver' => 'mock',
            'public_driver' => 'mock',
            'private_driver' => 'mock',
            'drivers' => [
                'mock' => ['type' => 'mock'],
            ],
            'custom_setting' => 'test_value',
        ];

        $manager = new BlockchainManager($config);

        // The manager should preserve the config
        $reflection = new \ReflectionClass($manager);
        $configProperty = $reflection->getProperty('config');
        $configProperty->setAccessible(true);
        $storedConfig = $configProperty->getValue($manager);

        $this->assertEquals($config, $storedConfig);
    }

    public function test_handles_null_config_values()
    {
        $config = [
            'default_driver' => null,
            'public_driver' => null,
            'private_driver' => null,
            'drivers' => [
                'mock' => ['type' => 'mock'],
            ],
        ];

        $manager = new BlockchainManager($config);

        // Should handle null values gracefully
        $this->assertEquals('mock', $manager->getDefaultDriver()); // Should use fallback
        $this->assertNotNull($manager->publicDriver());
        $this->assertNotNull($manager->privateDriver());
    }
}
