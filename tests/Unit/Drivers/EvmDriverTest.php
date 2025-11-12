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

    public function test_is_available_returns_true_when_rpc_works(): void
    {
        $rpcClientMock = Mockery::mock(EthereumJsonRpcClient::class);
        $rpcClientMock->shouldReceive('eth_blockNumber')
            ->andReturn('0x3039');

        $driver = new EvmDriver([
            'network' => 'testnet',
            'rpc_url' => 'http://localhost:8545',
        ], $rpcClientMock);

        $this->assertTrue($driver->isAvailable());
    }

    public function test_is_available_returns_false_when_rpc_fails(): void
    {
        $rpcClientMock = Mockery::mock(EthereumJsonRpcClient::class);
        $rpcClientMock->shouldReceive('eth_blockNumber')
            ->andThrow(new \RuntimeException('Connection failed'));

        $driver = new EvmDriver([
            'network' => 'testnet',
            'rpc_url' => 'http://localhost:8545',
        ], $rpcClientMock);

        $this->assertFalse($driver->isAvailable());
    }

    public function test_deploy_contract_without_from_address_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('From address is required');

        $driver = new EvmDriver([
            'network' => 'testnet',
            'rpc_url' => 'http://localhost:8545',
        ]);

        $driver->deployContract([
            'bytecode' => '0x6080604052',
        ]);
    }

    public function test_deploy_contract_with_constructor_params(): void
    {
        $rpcClientMock = Mockery::mock(EthereumJsonRpcClient::class);
        $abiEncoderMock = Mockery::mock(AbiEncoder::class);

        $rpcClientMock->shouldReceive('eth_gasPrice')
            ->andReturn('0x4a817c800');

        $rpcClientMock->shouldReceive('eth_estimateGas')
            ->andReturn('0x5208');

        $rpcClientMock->shouldReceive('eth_sendTransaction')
            ->andReturn('0x1234567890abcdef');

        $rpcClientMock->shouldReceive('eth_getTransactionReceipt')
            ->andReturn([
                'transactionHash' => '0x1234567890abcdef',
                'contractAddress' => '0x0987654321098765432109876543210987654321',
                'status' => true,
            ]);

        $abiEncoderMock->shouldReceive('encodeConstructorCall')
            ->andReturn('0x60806040520000000000000000000000000000000000000000000000000000000000000003e8');

        $driver = new EvmDriver([
            'network' => 'testnet',
            'rpc_url' => 'http://localhost:8545',
            'default_account' => '0x1234567890123456789012345678901234567890',
        ], $rpcClientMock, $abiEncoderMock);

        $result = $driver->deployContract([
            'bytecode' => '0x6080604052',
            'abi' => json_encode([
                [
                    'type' => 'constructor',
                    'inputs' => [['type' => 'uint256']],
                ],
            ]),
            'constructor_params' => [1000],
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('address', $result);
        $this->assertArrayHasKey('transaction_hash', $result);
    }

    public function test_call_contract(): void
    {
        $rpcClientMock = Mockery::mock(EthereumJsonRpcClient::class);
        $abiEncoderMock = Mockery::mock(AbiEncoder::class);

        $abiEncoderMock->shouldReceive('encodeFunctionCall')
            ->andReturn('0xa9059cbb00000000000000000000000012345678901234567890123456789012345678900000000000000000000000000000000000000000000000000000000000000003e8');

        $rpcClientMock->shouldReceive('eth_call')
            ->andReturn('0x0000000000000000000000000000000000000000000000000000000000000001');

        $abiEncoderMock->shouldReceive('decodeFunctionResult')
            ->andReturn(true);

        $driver = new EvmDriver([
            'network' => 'testnet',
            'rpc_url' => 'http://localhost:8545',
        ], $rpcClientMock, $abiEncoderMock);

        $result = $driver->callContract(
            '0x1234567890123456789012345678901234567890',
            json_encode([
                [
                    'type' => 'function',
                    'name' => 'transfer',
                    'inputs' => [
                        ['type' => 'address'],
                        ['type' => 'uint256'],
                    ],
                    'outputs' => [['type' => 'bool']],
                ],
            ]),
            'transfer',
            ['0x0987654321098765432109876543210987654321', 1000]
        );

        $this->assertNotNull($result);
    }

    public function test_estimate_gas(): void
    {
        $rpcClientMock = Mockery::mock(EthereumJsonRpcClient::class);

        $rpcClientMock->shouldReceive('eth_estimateGas')
            ->andReturn('0x5208');

        $driver = new EvmDriver([
            'network' => 'testnet',
            'rpc_url' => 'http://localhost:8545',
        ], $rpcClientMock);

        $gas = $driver->estimateGas([
            'from' => '0x1234567890123456789012345678901234567890',
            'to' => '0x0987654321098765432109876543210987654321',
            'data' => '0x1234',
        ]);

        $this->assertIsInt($gas);
        $this->assertEquals(21000, $gas);
    }

    public function test_get_transaction_receipt(): void
    {
        $rpcClientMock = Mockery::mock(EthereumJsonRpcClient::class);

        $rpcClientMock->shouldReceive('eth_getTransactionReceipt')
            ->andReturn([
                'transactionHash' => '0x1234567890abcdef',
                'blockNumber' => 12345,
                'contractAddress' => '0x0987654321098765432109876543210987654321',
                'status' => true,
            ]);

        $driver = new EvmDriver([
            'network' => 'testnet',
            'rpc_url' => 'http://localhost:8545',
        ], $rpcClientMock);

        $receipt = $driver->getTransactionReceipt('0x1234567890abcdef');

        $this->assertIsArray($receipt);
        $this->assertEquals('0x1234567890abcdef', $receipt['transactionHash']);
    }

    public function test_get_balance(): void
    {
        $rpcClientMock = Mockery::mock(EthereumJsonRpcClient::class);

        $rpcClientMock->shouldReceive('eth_getBalance')
            ->andReturn('0xde0b6b3a7640000');

        $driver = new EvmDriver([
            'network' => 'testnet',
            'rpc_url' => 'http://localhost:8545',
        ], $rpcClientMock);

        $balance = $driver->getBalance('0x1234567890123456789012345678901234567890');

        $this->assertIsString($balance);
        $this->assertEquals('0xde0b6b3a7640000', $balance);
    }

    public function test_get_driver_info_handles_rpc_errors(): void
    {
        $rpcClientMock = Mockery::mock(EthereumJsonRpcClient::class);

        $rpcClientMock->shouldReceive('eth_blockNumber')
            ->andThrow(new \RuntimeException('RPC error'));

        $rpcClientMock->shouldReceive('eth_chainId')
            ->andThrow(new \RuntimeException('RPC error'));

        $driver = new EvmDriver([
            'network' => 'testnet',
            'rpc_url' => 'http://localhost:8545',
        ], $rpcClientMock);

        $info = $driver->getDriverInfo();

        $this->assertIsArray($info);
        $this->assertEquals('evm', $info['type']);
        $this->assertNull($info['block_number']);
        $this->assertNull($info['chain_id']);
    }
}
