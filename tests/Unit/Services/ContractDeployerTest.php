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
}
