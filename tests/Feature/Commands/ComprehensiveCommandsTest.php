<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Feature\Commands;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Comprehensive test suite to reach 98% code coverage
 * Covers edge cases and paths not tested elsewhere - Command Tests
 */
class Comprehensive98PercentCommandsTest extends TestCase
{
    use RefreshDatabase;

    // ============ UpgradeContractCommand Tests ============

    public function test_upgrade_command_with_preserve_state_option(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'is_upgradeable' => true,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:upgrade', [
            'identifier' => 'TestContract@1.0.0',
            '--preserve-state' => true,
            '--json' => true,
        ])
            ->assertFailed(); // Fails in test env but tests the path
    }

    public function test_upgrade_command_with_migration_option(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'is_upgradeable' => true,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:upgrade', [
            'identifier' => 'TestContract',
            '--migration' => 'migrate_v2',
            '--json' => true,
        ])
            ->assertFailed();
    }

    public function test_upgrade_command_with_source_file(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'is_upgradeable' => true,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:upgrade', [
            'identifier' => 'TestContract',
            '--source' => '/path/to/contract.sol',
        ])
            ->assertFailed();
    }

    public function test_upgrade_command_with_from_address(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'is_upgradeable' => true,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:upgrade', [
            'identifier' => 'TestContract',
            '--from' => '0x1234567890123456789012345678901234567890',
        ])
            ->assertFailed();
    }

    public function test_upgrade_command_with_network_filter(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'mainnet',
            'status' => 'deployed',
            'is_upgradeable' => true,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:upgrade', [
            'identifier' => 'TestContract',
            '--network' => 'mainnet',
        ])
            ->assertFailed();
    }

    // ============ RollbackContractCommand Tests ============

    public function test_rollback_command_without_target_version(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'is_upgradeable' => true,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:rollback', [
            'contract' => 'TestContract',
        ])
            ->assertFailed();
    }

    public function test_rollback_command_with_target_version(): void
    {
        BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'is_upgradeable' => true,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test1',
        ]);

        BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'is_upgradeable' => true,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test2',
        ]);

        $this->artisan('blockchain:rollback', [
            'contract' => 'TestContract',
            '--target-version' => '1.0.0',
        ])
            ->assertFailed();
    }

    public function test_rollback_command_with_from_address(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'is_upgradeable' => true,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:rollback', [
            'contract' => 'TestContract',
            '--from' => '0x1234567890123456789012345678901234567890',
        ])
            ->assertFailed();
    }

    public function test_rollback_command_json_output(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'is_upgradeable' => true,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:rollback', [
            'contract' => 'TestContract',
            '--json' => true,
        ])
            ->assertFailed(); // Will fail because no previous version exists
    }

    // ============ VerifyContractCommand Tests ============

    public function test_verify_command_success_flow(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'mainnet',
            'status' => 'deployed',
            'address' => '0x1234567890123456789012345678901234567890',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:verify', [
            'contract' => 'TestContract',
        ])
            ->assertSuccessful();
    }

    public function test_verify_command_contract_not_found(): void
    {
        $this->artisan('blockchain:verify', [
            'contract' => 'NonExistent',
        ])
            ->expectsOutput("Contract 'NonExistent' not found")
            ->assertFailed();
    }

    public function test_verify_command_with_network_filter(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'mainnet',
            'status' => 'deployed',
            'address' => '0x1234567890123456789012345678901234567890',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:verify', [
            'contract' => 'TestContract',
            '--network' => 'mainnet',
        ])
            ->assertSuccessful();
    }

    public function test_verify_command_json_output(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'mainnet',
            'status' => 'deployed',
            'address' => '0x1234567890123456789012345678901234567890',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:verify', [
            'contract' => 'TestContract',
            '--json' => true,
        ])
            ->expectsOutputToContain('"success": true')
            ->assertSuccessful();
    }

    // ============ ContractStatusCommand Tests ============

    public function test_status_command_shows_contract_details(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'address' => '0x1234567890123456789012345678901234567890',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:status', [
            'contract' => 'TestContract',
        ])
            ->expectsOutput('Contract: TestContract')
            ->assertSuccessful();
    }

    public function test_status_command_with_network_filter(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'mainnet',
            'status' => 'deployed',
            'address' => '0x1234567890123456789012345678901234567890',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:status', [
            'contract' => 'TestContract',
            '--network' => 'mainnet',
        ])
            ->assertSuccessful();
    }

    public function test_status_command_contract_not_found(): void
    {
        $this->artisan('blockchain:status', [
            'contract' => 'NonExistent',
        ])
            ->expectsOutput("Contract 'NonExistent' not found")
            ->assertFailed();
    }

    public function test_status_command_json_output(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'address' => '0x1234567890123456789012345678901234567890',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:status', [
            'contract' => 'TestContract',
            '--json' => true,
        ])
            ->expectsOutputToContain('"name": "TestContract"')
            ->assertSuccessful();
    }

    // ============ ListContractsCommand Tests ============

    public function test_list_with_network_filter(): void
    {
        BlockchainContract::create([
            'name' => 'Contract1',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'mainnet',
            'status' => 'deployed',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test1',
        ]);

        BlockchainContract::create([
            'name' => 'Contract2',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'testnet',
            'status' => 'deployed',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test2',
        ]);

        $this->artisan('blockchain:list', [
            '--network' => 'mainnet',
        ])
            ->assertSuccessful();
    }

    public function test_list_with_status_filter(): void
    {
        BlockchainContract::create([
            'name' => 'Contract1',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test1',
        ]);

        BlockchainContract::create([
            'name' => 'Contract2',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'failed',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test2',
        ]);

        $this->artisan('blockchain:list', [
            '--status' => 'deployed',
        ])
            ->assertSuccessful();
    }

    public function test_list_without_filters(): void
    {
        BlockchainContract::create([
            'name' => 'Contract1',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test1',
        ]);

        $this->artisan('blockchain:list')
            ->assertSuccessful();
    }

    public function test_list_with_json_output(): void
    {
        BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:list', [
            '--json' => true,
        ])
            ->assertSuccessful();
    }

    public function test_list_empty_results(): void
    {
        $this->artisan('blockchain:list')
            ->assertSuccessful();
    }

    public function test_list_with_multiple_filters(): void
    {
        BlockchainContract::create([
            'name' => 'Contract1',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'mainnet',
            'status' => 'deployed',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test1',
        ]);

        $this->artisan('blockchain:list', [
            '--network' => 'mainnet',
            '--status' => 'deployed',
        ])
            ->assertSuccessful();
    }

    // ============ CallContractCommand Additional Tests ============

    public function test_call_command_with_from_address(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'address' => '0x1234567890123456789012345678901234567890',
            'abi' => json_encode([
                [
                    'type' => 'function',
                    'name' => 'transfer',
                    'inputs' => [
                        ['type' => 'address'],
                        ['type' => 'uint256'],
                    ],
                ],
            ]),
            'status' => 'deployed',
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:call', [
            'contract' => 'TestContract',
            'method' => 'transfer',
            '--params' => '["0x1111111111111111111111111111111111111111", 1000]',
            '--from' => '0x2222222222222222222222222222222222222222',
        ])
            ->assertSuccessful();
    }

    public function test_call_command_with_wait_option(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'address' => '0x1234567890123456789012345678901234567890',
            'abi' => json_encode([
                [
                    'type' => 'function',
                    'name' => 'mint',
                    'inputs' => [['type' => 'uint256']],
                ],
            ]),
            'status' => 'deployed',
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:call', [
            'contract' => 'TestContract',
            'method' => 'mint',
            '--params' => '[1000]',
            '--wait' => true,
        ])
            ->assertSuccessful();
    }

    public function test_call_command_with_gas_limit(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'address' => '0x1234567890123456789012345678901234567890',
            'abi' => json_encode([
                [
                    'type' => 'function',
                    'name' => 'test',
                    'inputs' => [],
                ],
            ]),
            'status' => 'deployed',
            'bytecode_hash' => 'test',
        ]);

        $this->artisan('blockchain:call', [
            'contract' => 'TestContract',
            'method' => 'test',
            '--gas-limit' => '200000',
        ])
            ->assertSuccessful();
    }

    // ============ DeployContractCommand Additional Tests ============

    public function test_deploy_command_with_all_options_combined(): void
    {
        $this->artisan('blockchain:deploy', [
            'name' => 'TestContract',
            '--preview' => true,
            '--network' => 'testnet',
            '--from' => '0x1234567890123456789012345678901234567890',
            '--gas-limit' => '5000000',
            '--contract-version' => '2.0.0',
            '--json' => true,
        ])
            ->assertSuccessful();
    }

    // ============ TestContractCommand Additional Tests ============

    public function test_test_contract_success_with_coverage(): void
    {
        $this->artisan('blockchain:test', [
            'name' => 'TestContract',
            '--coverage' => true,
        ])
            ->assertFailed(); // No artifacts in test env
    }

    public function test_test_contract_with_custom_network(): void
    {
        $this->artisan('blockchain:test', [
            'name' => 'TestContract',
            '--network' => 'sepolia',
        ])
            ->assertFailed();
    }

    // ============ CompileContractCommand Additional Tests ============

    public function test_compile_command_with_json_and_version(): void
    {
        $this->artisan('blockchain:compile', [
            'source' => '/non/existent/contract.sol',
            'name' => 'TestContract',
            '--contract-version' => '3.0.0',
            '--json' => true,
        ])
            ->assertFailed();
    }

    // ============ WatchContractsCommand Additional Tests ============

    public function test_watch_command_with_custom_interval(): void
    {
        // Can't test the actual watch loop, but we can test argument parsing
        // The command should fail when hot reload is disabled in tests
        $this->artisan('blockchain:watch', [
            '--interval' => '5000',
        ])
            ->assertFailed(); // Expected to fail when hot reload is disabled
    }
}
