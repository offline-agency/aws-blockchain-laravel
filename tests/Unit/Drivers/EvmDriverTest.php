<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Unit\Drivers;

use AwsBlockchain\Laravel\Drivers\EvmDriver;
use AwsBlockchain\Laravel\Services\AbiEncoder;
use AwsBlockchain\Laravel\Services\EthereumJsonRpcClient;
use AwsBlockchain\Laravel\Tests\TestCase;
use Mockery;

class EvmDriverTest extends TestCase
{
    protected EvmDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks for RPC client and ABI encoder
        $rpcClientMock = Mockery::mock(EthereumJsonRpcClient::class);
        $abiEncoderMock = Mockery::mock(AbiEncoder::class);

        // Mock RPC client methods
        $rpcClientMock->shouldReceive('eth_blockNumber')
            ->andReturn('0x3039'); // 12345 in hex

        $rpcClientMock->shouldReceive('eth_chainId')
            ->andReturn('0x1'); // 1 in hex

        $this->driver = new EvmDriver([
            'network' => 'testnet',
            'rpc_url' => 'http://localhost:8545',
            'default_account' => '0x1234567890123456789012345678901234567890',
        ], $rpcClientMock, $abiEncoderMock);
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
