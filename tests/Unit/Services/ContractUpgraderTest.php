<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Unit\Services;

use AwsBlockchain\Laravel\Drivers\MockDriver;
use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Services\ContractCompiler;
use AwsBlockchain\Laravel\Services\ContractDeployer;
use AwsBlockchain\Laravel\Services\ContractInteractor;
use AwsBlockchain\Laravel\Services\ContractUpgrader;
use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContractUpgraderTest extends TestCase
{
    use RefreshDatabase;

    protected ContractUpgrader $upgrader;

    protected ContractDeployer $deployer;

    protected ContractInteractor $interactor;

    protected function setUp(): void
    {
        parent::setUp();

        $driver = new MockDriver('mock');
        $compiler = new ContractCompiler([]);

        $this->deployer = new ContractDeployer($driver, $compiler, []);
        $this->interactor = new ContractInteractor($driver, []);
        $this->upgrader = new ContractUpgrader($this->deployer, $this->interactor, []);
    }

    public function test_throws_exception_when_contract_not_upgradeable(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => false,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not upgradeable');

        $this->upgrader->upgrade($contract, '2.0.0');
    }

    public function test_can_create_upgradeable_contract(): void
    {
        $result = $this->upgrader->createUpgradeableContract([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'network' => 'local',
            'abi' => json_encode([]),
            'bytecode' => '0x1234',
        ]);

        $this->assertArrayHasKey('proxy', $result);
        $this->assertArrayHasKey('implementation', $result);
        $this->assertTrue($result['implementation']->is_upgradeable);
    }

    public function test_rollback_throws_exception_when_not_upgradeable(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => false,
        ]);

        $this->expectException(\RuntimeException::class);

        $this->upgrader->rollback($contract);
    }

    public function test_rollback_throws_exception_when_no_previous_version(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => true,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No previous version found');

        $this->upgrader->rollback($contract);
    }
}
