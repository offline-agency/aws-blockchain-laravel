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
