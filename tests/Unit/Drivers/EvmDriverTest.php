<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Unit\Drivers;

use AwsBlockchain\Laravel\Drivers\EvmDriver;
use AwsBlockchain\Laravel\Tests\TestCase;

class EvmDriverTest extends TestCase
{
    protected EvmDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->driver = new EvmDriver([
            'network' => 'testnet',
            'rpc_url' => 'http://localhost:8545',
            'default_account' => '0x1234567890123456789012345678901234567890',
        ]);
    }

    public function test_get_type_returns_evm(): void
    {
        $this->assertEquals('evm', $this->driver->getType());
    }

    public function test_get_driver_info_returns_array(): void
    {
        $info = $this->driver->getDriverInfo();

        $this->assertIsArray($info);
        $this->assertArrayHasKey('type', $info);
        $this->assertArrayHasKey('network', $info);
        $this->assertEquals('evm', $info['type']);
        $this->assertEquals('testnet', $info['network']);
    }

    public function test_record_event_returns_string(): void
    {
        $eventId = $this->driver->recordEvent(['test' => 'data']);

        $this->assertIsString($eventId);
        $this->assertNotEmpty($eventId);
    }

    public function test_verify_integrity_returns_boolean(): void
    {
        $result = $this->driver->verifyIntegrity('test_id', ['data' => 'test']);

        $this->assertIsBool($result);
    }
}
