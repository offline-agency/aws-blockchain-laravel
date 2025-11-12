<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Unit;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Models\BlockchainTransaction;
use AwsBlockchain\Laravel\Services\AbiEncoder;
use AwsBlockchain\Laravel\Services\ContractCompiler;
use AwsBlockchain\Laravel\Services\EthereumJsonRpcClient;
use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

/**
 * Edge case tests for critical components
 * Tests boundary conditions, error paths, and exceptional scenarios
 * across services, models, and infrastructure components
 */
class EdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    // ============ AbiEncoder Edge Cases ============

    public function test_abi_encoder_encode_parameter_with_various_types(): void
    {
        $encoder = new AbiEncoder;

        $reflection = new \ReflectionClass($encoder);
        $method = $reflection->getMethod('encodeParameter');
        $method->setAccessible(true);

        // Test uint256
        $result = $method->invoke($encoder, 100, 'uint256');
        $this->assertIsString($result);
        $this->assertEquals(64, strlen($result));

        // Test address
        $result = $method->invoke($encoder, '0x1234567890123456789012345678901234567890', 'address');
        $this->assertIsString($result);
        $this->assertEquals(64, strlen($result));
    }

    public function test_abi_encoder_get_method_signature(): void
    {
        $encoder = new AbiEncoder;

        $reflection = new \ReflectionClass($encoder);
        $method = $reflection->getMethod('getMethodSignature');
        $method->setAccessible(true);

        $signature = $method->invoke($encoder, 'transfer', [
            ['type' => 'address'],
            ['type' => 'uint256'],
        ]);

        $this->assertEquals('transfer(address,uint256)', $signature);
    }

    public function test_abi_encoder_hex_to_dec(): void
    {
        $encoder = new AbiEncoder;

        $reflection = new \ReflectionClass($encoder);
        $method = $reflection->getMethod('hexToDec');
        $method->setAccessible(true);

        $result = $method->invoke($encoder, 'ff');
        $this->assertEquals(255, $result);
    }

    public function test_abi_encoder_function_selector(): void
    {
        $encoder = new AbiEncoder;

        $selector = $encoder->getFunctionSelector('transfer', [
            ['type' => 'address'],
            ['type' => 'uint256'],
        ]);

        // Function selector is the first 4 bytes (8 hex chars) plus 0x prefix = 10 chars
        $this->assertIsString($selector);
        $this->assertEquals(10, strlen($selector)); // 0x + 4 bytes = 10 chars
        $this->assertStringStartsWith('0x', $selector);
    }

    public function test_abi_encoder_encode_uint(): void
    {
        $encoder = new AbiEncoder;

        $reflection = new \ReflectionClass($encoder);
        $method = $reflection->getMethod('encodeUint');
        $method->setAccessible(true);

        $result = $method->invoke($encoder, 255, 256);
        $this->assertIsString($result);
        $this->assertEquals(64, strlen($result)); // 32 bytes = 64 hex chars
    }

    public function test_abi_encoder_encode_address(): void
    {
        $encoder = new AbiEncoder;

        $reflection = new \ReflectionClass($encoder);
        $method = $reflection->getMethod('encodeAddress');
        $method->setAccessible(true);

        $result = $method->invoke($encoder, '0x1234567890123456789012345678901234567890');
        $this->assertIsString($result);
        $this->assertEquals(64, strlen($result)); // 32 bytes = 64 hex chars
    }

    public function test_abi_encoder_encode_bool(): void
    {
        $encoder = new AbiEncoder;

        $reflection = new \ReflectionClass($encoder);
        $method = $reflection->getMethod('encodeBool');
        $method->setAccessible(true);

        $resultTrue = $method->invoke($encoder, true);
        $resultFalse = $method->invoke($encoder, false);

        $this->assertStringContainsString('1', $resultTrue);
        $this->assertStringContainsString('0', $resultFalse);
    }

    // ============ EthereumJsonRpcClient Edge Cases ============

    public function test_ethereum_rpc_client_handles_null_result(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => null,
            ]),
        ]);

        $client = new EthereumJsonRpcClient(['rpc_url' => 'http://localhost:8545']);

        try {
            $result = $client->eth_blockNumber();
            // If this doesn't throw, the return type is wrong
            $this->fail('Expected TypeError for null return value');
        } catch (\TypeError $e) {
            // Expected - eth_blockNumber should return string but got null
            $this->assertStringContainsString('must be of type string', $e->getMessage());
        }
    }

    public function test_ethereum_rpc_client_handles_error_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'jsonrpc' => '2.0',
                'id' => 1,
                'error' => [
                    'code' => -32600,
                    'message' => 'Invalid Request',
                ],
            ]),
        ]);

        $client = new EthereumJsonRpcClient(['rpc_url' => 'http://localhost:8545']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('RPC error');

        $client->eth_blockNumber();
    }

    // ============ ContractCompiler Edge Cases ============

    public function test_contract_compiler_throws_for_invalid_json_output(): void
    {
        $compiler = new ContractCompiler([
            'solc_path' => 'echo',
            'storage_path' => storage_path('app/contracts'),
        ]);

        try {
            // This will fail because echo doesn't produce valid compiler output
            $compiler->compile('contract Test {}', 'Test');
            $this->fail('Expected RuntimeException for invalid compiler output');
        } catch (\RuntimeException $e) {
            // Expected - no valid JSON output
            $this->assertStringContainsString('No JSON output found', $e->getMessage());
        }
    }

    public function test_contract_compiler_validate_abi_returns_false_for_invalid(): void
    {
        $compiler = new ContractCompiler([]);

        $invalidAbi = [
            ['invalid' => 'structure'], // Missing required 'type' field
        ];

        $this->assertFalse($compiler->validateAbi($invalidAbi));
    }

    public function test_contract_compiler_validate_abi_with_empty_array(): void
    {
        $compiler = new ContractCompiler([]);

        // Empty ABI is valid
        $this->assertTrue($compiler->validateAbi([]));
    }

    public function test_contract_compiler_get_method_returns_null_for_missing_method(): void
    {
        $compiler = new ContractCompiler([]);

        $abi = [
            ['type' => 'function', 'name' => 'transfer'],
        ];

        $method = $compiler->getMethod($abi, 'nonExistent');

        $this->assertNull($method);
    }

    public function test_contract_compiler_get_constructor_returns_null_for_no_constructor(): void
    {
        $compiler = new ContractCompiler([]);

        $abi = [
            ['type' => 'function', 'name' => 'transfer'],
        ];

        $constructor = $compiler->getConstructor($abi);

        $this->assertNull($constructor);
    }

    // ============ Model Edge Cases ============

    public function test_blockchain_contract_is_upgradeable_with_explicit_false(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => false,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->assertFalse($contract->isUpgradeable());
    }

    public function test_blockchain_contract_is_upgradeable_defaults_to_false(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->assertFalse($contract->isUpgradeable());
    }

    public function test_blockchain_transaction_stores_parameters_as_json(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $params = ['0x1234567890123456789012345678901234567890', 1000];
        $transaction = BlockchainTransaction::create([
            'transaction_hash' => '0xtest',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'parameters' => json_encode($params),
        ]);

        $this->assertIsString($transaction->parameters);
        $this->assertJson($transaction->parameters);
    }

    public function test_blockchain_transaction_has_gas_used_attribute(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $transaction = BlockchainTransaction::create([
            'transaction_hash' => '0xtest',
            'contract_id' => $contract->id,
            'method_name' => 'test',
            'gas_used' => '21000',
        ]);

        $this->assertEquals('21000', $transaction->gas_used);
    }

    public function test_blockchain_contract_get_parsed_abi_handles_valid_json(): void
    {
        $abi = [
            ['type' => 'function', 'name' => 'test'],
        ];

        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'abi' => json_encode($abi),
            'bytecode_hash' => 'test',
        ]);

        $parsed = $contract->getParsedAbi();
        $this->assertIsArray($parsed);
        $this->assertEquals('test', $parsed[0]['name']);
    }

    public function test_blockchain_contract_has_version_attribute(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.5.0',
            'type' => 'evm',
            'network' => 'local',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->assertEquals('2.5.0', $contract->version);
    }

    // ============ Facade Edge Cases ============

    public function test_blockchain_facade_get_facade_accessor(): void
    {
        // Use reflection to access protected method
        $reflection = new \ReflectionClass(\AwsBlockchain\Laravel\Facades\Blockchain::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);

        $accessor = $method->invoke(null);

        $this->assertEquals('blockchain', $accessor);
    }

    public function test_blockchain_facade_throws_on_missing_method(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Call to undefined method');

        // Try to call a non-existent method through the facade
        \AwsBlockchain\Laravel\Facades\Blockchain::nonExistentMethod();
    }
}
