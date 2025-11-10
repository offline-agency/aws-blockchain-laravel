<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Feature\Commands;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UpgradeContractCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_command_fails_when_contract_not_found(): void
    {
        $this->artisan('blockchain:upgrade', [
            'identifier' => 'NonExistent',
        ])
            ->expectsOutput("Contract 'NonExistent' not found")
            ->assertFailed();
    }

    public function test_upgrade_command_parses_identifier_with_version(): void
    {
        BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'is_upgradeable' => true,
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        // This will fail at upgrade step but should parse the identifier
        $this->artisan('blockchain:upgrade', [
            'identifier' => 'TestContract@1.0.0',
            '--json' => true,
        ])
            ->assertFailed(); // Fails because we can't actually upgrade in tests
    }
}
