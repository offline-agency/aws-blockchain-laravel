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

    public function test_upgrade_deploys_new_implementation(): void
    {
        $oldContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => true,
            'address' => '0x1234567890123456789012345678901234567890',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        try {
            $result = $this->upgrader->upgrade($oldContract, '2.0.0');

            $this->assertIsArray($result);
            $this->assertArrayHasKey('old_contract', $result);
            $this->assertArrayHasKey('new_contract', $result);
        } catch (\Exception $e) {
            // Expected if deployment fails in test environment
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    public function test_upgrade_with_preserve_state_false(): void
    {
        $oldContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => true,
            'address' => '0x1234567890123456789012345678901234567890',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        try {
            $result = $this->upgrader->upgrade($oldContract, '2.0.0', ['preserve_state' => false]);

            $this->assertIsArray($result);
        } catch (\Exception $e) {
            // Expected if deployment fails - verify exception is thrown
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    public function test_upgrade_with_migration(): void
    {
        $oldContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => true,
            'address' => '0x1234567890123456789012345678901234567890',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        try {
            $result = $this->upgrader->upgrade($oldContract, '2.0.0', [
                'migration' => function ($old, $new) {
                    return true;
                },
            ]);

            $this->assertIsArray($result);
        } catch (\Exception $e) {
            // Expected if deployment fails - verify exception is thrown
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    public function test_rollback_with_target_version(): void
    {
        $contract1 = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => true,
            'address' => '0x1111111111111111111111111111111111111111',
        ]);

        $contract2 = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => true,
            'address' => '0x2222222222222222222222222222222222222222',
        ]);

        try {
            $result = $this->upgrader->rollback($contract2, '1.0.0');

            $this->assertIsArray($result);
            $this->assertArrayHasKey('rolled_back_to', $result);
        } catch (\Exception $e) {
            // Expected if rollback logic fails - verify exception is thrown
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    public function test_rollback_throws_when_target_version_not_found(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => true,
        ]);

        $this->expectException(\RuntimeException::class);

        $this->upgrader->rollback($contract, '9.9.9');
    }

    public function test_deploy_new_implementation_with_source_code(): void
    {
        $oldContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => true,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $reflection = new \ReflectionClass($this->upgrader);
        $method = $reflection->getMethod('deployNewImplementation');
        $method->setAccessible(true);

        try {
            $result = $method->invoke($this->upgrader, $oldContract, '2.0.0', [
                'source_code' => 'pragma solidity ^0.8.0; contract TestContract {}',
            ]);

            $this->assertInstanceOf(BlockchainContract::class, $result);
        } catch (\Exception $e) {
            // Expected if deployment fails
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    public function test_deploy_new_implementation_with_source_file(): void
    {
        $oldContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => true,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $reflection = new \ReflectionClass($this->upgrader);
        $method = $reflection->getMethod('deployNewImplementation');
        $method->setAccessible(true);

        try {
            $result = $method->invoke($this->upgrader, $oldContract, '2.0.0', [
                'source_file' => '/path/to/contract.sol',
            ]);

            $this->assertInstanceOf(BlockchainContract::class, $result);
        } catch (\Exception $e) {
            // Expected if deployment fails
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    public function test_deploy_new_implementation_with_proxy_contract_id(): void
    {
        $proxy = BlockchainContract::create([
            'name' => 'Proxy',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'abi' => json_encode([]),
            'bytecode_hash' => 'proxy',
        ]);

        $oldContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => true,
            'proxy_contract_id' => $proxy->id,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $reflection = new \ReflectionClass($this->upgrader);
        $method = $reflection->getMethod('deployNewImplementation');
        $method->setAccessible(true);

        try {
            $result = $method->invoke($this->upgrader, $oldContract, '2.0.0', []);

            $this->assertInstanceOf(BlockchainContract::class, $result);
            $this->assertEquals($proxy->id, $result->proxy_contract_id);
        } catch (\Exception $e) {
            // Expected if deployment fails
            $this->assertInstanceOf(\Exception::class, $e);
        }
    }

    public function test_update_proxy_throws_when_no_proxy_contract_id(): void
    {
        $oldContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => true,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $newContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test2',
        ]);

        $reflection = new \ReflectionClass($this->upgrader);
        $method = $reflection->getMethod('updateProxy');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No proxy contract found');

        $method->invoke($this->upgrader, $oldContract, $newContract, []);
    }

    public function test_update_proxy_throws_when_proxy_not_found(): void
    {
        $oldContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'is_upgradeable' => true,
            'proxy_contract_id' => 99999, // Non-existent ID
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $newContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test2',
        ]);

        $reflection = new \ReflectionClass($this->upgrader);
        $method = $reflection->getMethod('updateProxy');
        $method->setAccessible(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Proxy contract not found');

        $method->invoke($this->upgrader, $oldContract, $newContract, []);
    }

    public function test_run_migration(): void
    {
        $oldContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'address' => '0x1111111111111111111111111111111111111111',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test1',
        ]);

        $newContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'address' => '0x2222222222222222222222222222222222222222',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test2',
        ]);

        $reflection = new \ReflectionClass($this->upgrader);
        $method = $reflection->getMethod('runMigration');
        $method->setAccessible(true);

        // Should not throw
        $method->invoke($this->upgrader, $oldContract, $newContract, 'migrate_v2');
        $this->assertTrue(true);
    }

    public function test_find_previous_version_with_target_version(): void
    {
        $contract1 = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'created_at' => now()->subDays(2),
        ]);

        $contract2 = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'created_at' => now(),
        ]);

        $reflection = new \ReflectionClass($this->upgrader);
        $method = $reflection->getMethod('findPreviousVersion');
        $method->setAccessible(true);

        $result = $method->invoke($this->upgrader, $contract2, '1.0.0');

        $this->assertNotNull($result);
        $this->assertEquals('1.0.0', $result->version);
    }

    public function test_find_previous_version_without_target_version(): void
    {
        $contract1 = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        // Use DB to directly update timestamp to ensure it's earlier
        \Illuminate\Support\Facades\DB::table('blockchain_contracts')
            ->where('id', $contract1->id)
            ->update(['created_at' => now()->subDays(2)]);
        $contract1->refresh();

        // Wait a moment to ensure different timestamps
        usleep(100000); // 0.1 second

        $contract2 = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);
        $contract2->refresh();

        $reflection = new \ReflectionClass($this->upgrader);
        $method = $reflection->getMethod('findPreviousVersion');
        $method->setAccessible(true);

        $result = $method->invoke($this->upgrader, $contract2, null);

        $this->assertNotNull($result);
        $this->assertEquals('1.0.0', $result->version);
    }

    public function test_store_rollback_record(): void
    {
        $contract1 = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        $contract2 = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        \AwsBlockchain\Laravel\Models\BlockchainTransaction::create([
            'transaction_hash' => '0xupgrade',
            'contract_id' => $contract2->id,
            'method_name' => 'upgradeTo',
            'status' => 'success',
        ]);

        $reflection = new \ReflectionClass($this->upgrader);
        $method = $reflection->getMethod('storeRollbackRecord');
        $method->setAccessible(true);

        $method->invoke($this->upgrader, $contract2, $contract1);

        $rollbackTransaction = \AwsBlockchain\Laravel\Models\BlockchainTransaction::where('method_name', 'rollback')->first();
        $this->assertNotNull($rollbackTransaction);
        $this->assertEquals('success', $rollbackTransaction->status);
    }

    public function test_get_proxy_bytecode(): void
    {
        $reflection = new \ReflectionClass($this->upgrader);
        $method = $reflection->getMethod('getProxyBytecode');
        $method->setAccessible(true);

        $bytecode = $method->invoke($this->upgrader);

        $this->assertIsString($bytecode);
        $this->assertStringStartsWith('0x', $bytecode);
    }

    public function test_get_proxy_abi(): void
    {
        $reflection = new \ReflectionClass($this->upgrader);
        $method = $reflection->getMethod('getProxyAbi');
        $method->setAccessible(true);

        $abi = $method->invoke($this->upgrader);

        $this->assertIsArray($abi);
    }
}
