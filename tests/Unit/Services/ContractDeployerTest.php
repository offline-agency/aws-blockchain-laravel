<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Unit\Services;

use AwsBlockchain\Laravel\Drivers\MockDriver;
use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Services\ContractCompiler;
use AwsBlockchain\Laravel\Services\ContractDeployer;
use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContractDeployerTest extends TestCase
{
    use RefreshDatabase;

    protected ContractDeployer $deployer;

    protected MockDriver $driver;

    protected ContractCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->driver = new MockDriver('mock');
        $this->compiler = new ContractCompiler([
            'solc_path' => 'solc',
            'storage_path' => storage_path('app/contracts'),
        ]);

        $this->deployer = new ContractDeployer(
            $this->driver,
            $this->compiler,
            [
                'default_network' => 'local',
                'gas' => [
                    'default_limit' => 3000000,
                    'price_multiplier' => 1.1,
                ],
            ]
        );
    }

    public function test_can_deploy_contract_with_artifacts(): void
    {
        $result = $this->deployer->deploy([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'network' => 'local',
            'abi' => json_encode([['type' => 'function', 'name' => 'test']]),
            'bytecode' => '0x1234',
        ]);

        $this->assertArrayHasKey('contract', $result);
        $this->assertArrayHasKey('deployment', $result);
        $this->assertInstanceOf(BlockchainContract::class, $result['contract']);
        $this->assertEquals('TestContract', $result['contract']->name);
    }

    public function test_can_estimate_deployment_gas(): void
    {
        $estimate = $this->deployer->estimateDeploymentGas([
            'bytecode' => '0x1234',
            'from' => '0x1234567890123456789012345678901234567890',
        ]);

        $this->assertIsInt($estimate);
        $this->assertGreaterThan(0, $estimate);
    }

    public function test_can_preview_deployment(): void
    {
        $preview = $this->deployer->previewDeployment(
            'TestContract',
            [
                'bytecode' => '0x1234',
                'gas_limit' => 100000,
                'from' => '0x1234567890123456789012345678901234567890',
            ],
            'local'
        );

        $this->assertArrayHasKey('contract_name', $preview);
        $this->assertArrayHasKey('network', $preview);
        $this->assertArrayHasKey('gas_limit', $preview);
        $this->assertArrayHasKey('estimated_cost_wei', $preview);
        $this->assertEquals('TestContract', $preview['contract_name']);
    }

    public function test_deploy_stores_contract_in_database(): void
    {
        $result = $this->deployer->deploy([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'network' => 'local',
            'abi' => json_encode([['type' => 'function', 'name' => 'test']]),
            'bytecode' => '0x1234',
        ]);

        $this->assertDatabaseHas('blockchain_contracts', [
            'name' => 'TestContract',
            'version' => '1.0.0',
            'status' => 'deployed',
        ]);
    }

    public function test_deploy_creates_transaction_record(): void
    {
        $result = $this->deployer->deploy([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'network' => 'local',
            'abi' => json_encode([['type' => 'function', 'name' => 'test']]),
            'bytecode' => '0x1234',
        ]);

        $this->assertDatabaseHas('blockchain_transactions', [
            'contract_id' => $result['contract']->id,
            'method_name' => 'constructor',
            'status' => 'success',
        ]);
    }

    public function test_deploy_with_constructor_params(): void
    {
        $result = $this->deployer->deploy([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'network' => 'local',
            'abi' => json_encode([['type' => 'constructor']]),
            'bytecode' => '0x1234',
            'constructor_params' => [1000000],
        ]);

        $this->assertEquals([1000000], $result['contract']->constructor_params);
    }

    public function test_throws_exception_when_no_artifacts_available(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->deployer->deploy([
            'name' => 'NonExistentContract',
            'version' => '1.0.0',
            'network' => 'local',
        ]);
    }

    public function test_wait_for_confirmation_returns_receipt_on_success(): void
    {
        // Create a mock driver that returns a receipt
        $mockDriver = \Mockery::mock(MockDriver::class)->makePartial();
        $mockDriver->shouldReceive('getTransactionReceipt')
            ->once()
            ->andReturn([
                'transactionHash' => '0xabc123',
                'blockNumber' => 12345,
                'status' => true,
            ]);

        $deployer = new ContractDeployer(
            $mockDriver,
            $this->compiler,
            ['deployment' => ['confirmation_blocks' => 2]]
        );

        $receipt = $deployer->waitForConfirmation('0xabc123', 10);

        $this->assertNotNull($receipt);
        $this->assertEquals('0xabc123', $receipt['transactionHash']);
        $this->assertEquals(12345, $receipt['blockNumber']);
    }

    public function test_wait_for_confirmation_returns_null_on_timeout(): void
    {
        // Create a mock driver that never returns a receipt
        $mockDriver = \Mockery::mock(MockDriver::class)->makePartial();
        $mockDriver->shouldReceive('getTransactionReceipt')
            ->andReturn(null);

        $deployer = new ContractDeployer(
            $mockDriver,
            $this->compiler,
            ['deployment' => ['confirmation_blocks' => 2]]
        );

        $receipt = $deployer->waitForConfirmation('0xabc123', 1);

        $this->assertNull($receipt);
    }

    public function test_verify_contract_returns_true(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'address' => '0x1234567890123456789012345678901234567890',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $result = $this->deployer->verifyContract($contract);

        $this->assertTrue($result);
    }

    public function test_deploy_with_gas_estimation_failure_uses_default(): void
    {
        // Create a mock driver that fails gas estimation
        $mockDriver = \Mockery::mock(MockDriver::class)->makePartial();
        $mockDriver->shouldReceive('estimateGas')
            ->andThrow(new \Exception('Gas estimation failed'));

        $mockDriver->shouldReceive('deployContract')
            ->andReturn([
                'address' => '0x1234567890123456789012345678901234567890',
                'transaction_hash' => '0xabc123',
                'gas_used' => 21000,
            ]);

        $deployer = new ContractDeployer(
            $mockDriver,
            $this->compiler,
            [
                'default_network' => 'local',
                'gas' => [
                    'default_limit' => 5000000,
                    'price_multiplier' => 1.1,
                ],
            ]
        );

        $result = $deployer->deploy([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'network' => 'local',
            'abi' => json_encode([['type' => 'function', 'name' => 'test']]),
            'bytecode' => '0x1234',
        ]);

        $this->assertArrayHasKey('contract', $result);
        $this->assertEquals('TestContract', $result['contract']->name);
    }

    public function test_deploy_with_source_code_compiles_contract(): void
    {
        // Mock the compiler to avoid dependency on solc
        $mockCompiler = \Mockery::mock(\AwsBlockchain\Laravel\Services\ContractCompiler::class);
        
        // First, loadArtifacts will be called and return null (no pre-compiled artifacts)
        $mockCompiler->shouldReceive('loadArtifacts')
            ->once()
            ->with('TestContract', '1.0.0')
            ->andReturn(null);
        
        // Then compile will be called (not compileFromSource)
        $mockCompiler->shouldReceive('compile')
            ->once()
            ->with('pragma solidity ^0.8.0; contract TestContract {}', 'TestContract')
            ->andReturn([
                'bytecode' => '0x6080604052348015600f57600080fd5b50603f80601d6000396000f3fe6080604052600080fdfea264697066735822122012345678901234567890123456789012345678901234567890123456789064736f6c63430008000033',
                'abi' => json_encode([
                    [
                        'type' => 'constructor',
                        'inputs' => [],
                    ],
                ]),
            ]);

        // Create a new deployer with the mocked compiler
        $testDeployer = new \AwsBlockchain\Laravel\Services\ContractDeployer(
            $this->driver,
            $mockCompiler,
            [
                'gas' => [
                    'default_limit' => 3000000,
                    'price_multiplier' => 1.2,
                ],
            ]
        );

        $result = $testDeployer->deploy([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'network' => 'local',
            'source_code' => 'pragma solidity ^0.8.0; contract TestContract {}',
        ]);

        $this->assertArrayHasKey('contract', $result);
        $this->assertEquals('TestContract', $result['contract']->name);
        $this->assertEquals('1.0.0', $result['contract']->version);
    }

    public function test_estimate_deployment_gas_handles_exception(): void
    {
        // Create a mock driver that throws on estimateGas
        $mockDriver = \Mockery::mock(MockDriver::class)->makePartial();
        $mockDriver->shouldReceive('estimateGas')
            ->andThrow(new \Exception('Network error'));

        $deployer = new ContractDeployer(
            $mockDriver,
            $this->compiler,
            ['gas' => ['default_limit' => 4000000]]
        );

        $estimate = $deployer->estimateDeploymentGas([
            'bytecode' => '0x1234',
            'from' => '0x1234567890123456789012345678901234567890',
        ]);

        $this->assertEquals(4000000, $estimate);
    }

    public function test_preview_deployment_calculates_costs_correctly(): void
    {
        $preview = $this->deployer->previewDeployment(
            'TestContract',
            [
                'bytecode' => '0x'.str_repeat('00', 100),
                'gas_limit' => 200000,
                'from' => '0x1234567890123456789012345678901234567890',
                'constructor_params' => [1000, '0xabc'],
            ],
            'mainnet'
        );

        $this->assertArrayHasKey('contract_name', $preview);
        $this->assertArrayHasKey('network', $preview);
        $this->assertArrayHasKey('gas_limit', $preview);
        $this->assertArrayHasKey('gas_price', $preview);
        $this->assertArrayHasKey('estimated_cost_wei', $preview);
        $this->assertArrayHasKey('estimated_cost_eth', $preview);
        $this->assertArrayHasKey('constructor_params', $preview);
        $this->assertArrayHasKey('bytecode_size', $preview);
        $this->assertEquals('TestContract', $preview['contract_name']);
        $this->assertEquals('mainnet', $preview['network']);
        $this->assertEquals(200000, $preview['gas_limit']);
        $this->assertEquals([1000, '0xabc'], $preview['constructor_params']);
    }
}
