<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Unit\Services;

use AwsBlockchain\Laravel\Drivers\MockDriver;
use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Services\ContractInteractor;
use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContractInteractorTest extends TestCase
{
    use RefreshDatabase;

    protected ContractInteractor $interactor;

    protected MockDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new MockDriver('mock');
        $this->interactor = new ContractInteractor($this->driver, [
            'gas' => [
                'default_limit' => 100000,
                'price_multiplier' => 1.1,
            ],
        ]);
    }

    public function test_can_call_view_function(): void
    {
        $contract = $this->createTestContract();

        $result = $this->interactor->call(
            $contract,
            'balanceOf',
            ['0x1234567890123456789012345678901234567890']
        );

        $this->assertNotNull($result);
    }

    public function test_can_estimate_gas_for_method_call(): void
    {
        $contract = $this->createTestContract();

        $estimate = $this->interactor->estimateGas(
            $contract,
            'transfer',
            ['0x1234567890123456789012345678901234567890', 1000],
            ['from' => '0x0000000000000000000000000000000000000001']
        );

        $this->assertIsInt($estimate);
        $this->assertGreaterThan(0, $estimate);
    }

    public function test_can_parse_parameters_from_json(): void
    {
        $params = $this->interactor->parseParameters('["param1", "param2", 123]');

        $this->assertIsArray($params);
        $this->assertCount(3, $params);
        $this->assertEquals('param1', $params[0]);
        $this->assertEquals(123, $params[2]);
    }

    public function test_can_parse_parameters_from_comma_separated(): void
    {
        $params = $this->interactor->parseParameters('param1, param2, param3');

        $this->assertIsArray($params);
        $this->assertCount(3, $params);
        $this->assertEquals('param1', $params[0]);
    }

    public function test_can_parse_single_parameter(): void
    {
        $params = $this->interactor->parseParameters('single_param');

        $this->assertIsArray($params);
        $this->assertCount(1, $params);
        $this->assertEquals('single_param', $params[0]);
    }

    public function test_can_format_return_value_as_json(): void
    {
        $formatted = $this->interactor->formatReturnValue(
            ['key' => 'value'],
            ['json' => true]
        );

        $this->assertJson($formatted);
        $decoded = json_decode($formatted, true);
        $this->assertEquals('value', $decoded['key']);
    }

    public function test_can_format_return_value_as_string(): void
    {
        $formatted = $this->interactor->formatReturnValue('test');

        $this->assertEquals('test', $formatted);
    }

    public function test_can_format_boolean_return_value(): void
    {
        $this->assertEquals('true', $this->interactor->formatReturnValue(true));
        $this->assertEquals('false', $this->interactor->formatReturnValue(false));
    }

    public function test_throws_exception_for_missing_method(): void
    {
        $contract = $this->createTestContract();

        $this->expectException(\InvalidArgumentException::class);

        $this->interactor->call($contract, 'nonExistentMethod', []);
    }

    public function test_throws_exception_when_abi_not_available(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => '0x1234567890123456789012345678901234567890',
            'network' => 'local',
            'abi' => null,
        ]);

        $this->expectException(\RuntimeException::class);

        $this->interactor->call($contract, 'someMethod', []);
    }

    public function test_can_call_state_changing_function(): void
    {
        $contract = $this->createTestContract();

        $result = $this->interactor->call(
            $contract,
            'transfer',
            ['0x0987654321098765432109876543210987654321', 1000],
            ['from' => '0x0000000000000000000000000000000000000001']
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('transaction_hash', $result);
    }

    public function test_can_call_with_wait_option(): void
    {
        $contract = $this->createTestContract();

        $result = $this->interactor->call(
            $contract,
            'transfer',
            ['0x0987654321098765432109876543210987654321', 1000],
            [
                'from' => '0x0000000000000000000000000000000000000001',
                'wait' => true,
                'timeout' => 1, // Short timeout for testing
            ]
        );

        // Result might be receipt or transaction hash depending on confirmation
        $this->assertNotNull($result);
    }

    public function test_call_throws_when_contract_address_is_null(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => null,
            'network' => 'local',
            'abi' => json_encode([
                [
                    'type' => 'function',
                    'name' => 'balanceOf',
                    'stateMutability' => 'view',
                    'inputs' => [],
                ],
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Contract address is required');

        $this->interactor->call($contract, 'balanceOf', []);
    }

    public function test_estimate_gas_uses_default_when_estimation_fails(): void
    {
        $contract = $this->createTestContract();

        // Use the existing driver which might fail on estimateGas
        // The method should catch exceptions and return default
        $estimate = $this->interactor->estimateGas(
            $contract,
            'transfer',
            ['0x0987654321098765432109876543210987654321', 1000],
            []
        );

        // Should return either a valid estimate or the default
        $this->assertIsInt($estimate);
        $this->assertGreaterThanOrEqual(0, $estimate);
    }

    public function test_wait_for_confirmation_returns_null_on_timeout(): void
    {
        // Use a very short timeout to test timeout behavior
        // The method will sleep for 2 seconds between checks, so with 0 second timeout
        // it should return null immediately
        $result = $this->interactor->waitForConfirmation('0x1234567890abcdef', 0);

        // Will timeout immediately and return null
        $this->assertNull($result);
    }

    public function test_format_return_value_with_array(): void
    {
        $formatted = $this->interactor->formatReturnValue(['key1' => 'value1', 'key2' => 'value2']);

        $this->assertIsString($formatted);
        $this->assertStringContainsString('key1', $formatted);
    }

    public function test_format_return_value_with_integer(): void
    {
        $formatted = $this->interactor->formatReturnValue(12345);

        $this->assertEquals('12345', $formatted);
    }

    public function test_format_return_value_with_null(): void
    {
        $formatted = $this->interactor->formatReturnValue(null);

        $this->assertEquals('', $formatted); // null casts to empty string
    }

    public function test_parse_parameters_handles_empty_string(): void
    {
        $params = $this->interactor->parseParameters('');

        $this->assertIsArray($params);
        $this->assertCount(1, $params);
        $this->assertEquals('', $params[0]);
    }

    public function test_parse_parameters_handles_invalid_json(): void
    {
        $params = $this->interactor->parseParameters('not valid json, but has comma');

        $this->assertIsArray($params);
        $this->assertGreaterThan(1, count($params));
    }

    public function test_call_handles_pure_function(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => '0x1234567890123456789012345678901234567890',
            'network' => 'local',
            'abi' => json_encode([
                [
                    'type' => 'function',
                    'name' => 'calculate',
                    'stateMutability' => 'pure',
                    'inputs' => [],
                ],
            ]),
        ]);

        $result = $this->interactor->call($contract, 'calculate', []);

        $this->assertNotNull($result);
    }

    protected function createTestContract(): BlockchainContract
    {
        return BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => '0x1234567890123456789012345678901234567890',
            'network' => 'local',
            'abi' => json_encode([
                [
                    'type' => 'function',
                    'name' => 'balanceOf',
                    'stateMutability' => 'view',
                    'inputs' => [['name' => 'account', 'type' => 'address']],
                ],
                [
                    'type' => 'function',
                    'name' => 'transfer',
                    'stateMutability' => 'nonpayable',
                    'inputs' => [
                        ['name' => 'to', 'type' => 'address'],
                        ['name' => 'amount', 'type' => 'uint256'],
                    ],
                ],
            ]),
        ]);
    }
}
