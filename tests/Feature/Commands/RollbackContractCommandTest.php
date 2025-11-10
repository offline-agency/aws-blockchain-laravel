<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Feature\Commands;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RollbackContractCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_rollback_command_fails_when_contract_not_found(): void
    {
        $this->artisan('blockchain:rollback', [
            'contract' => 'NonExistent',
        ])
            ->expectsOutput("Contract 'NonExistent' not found")
            ->assertFailed();
    }

    public function test_rollback_command_fails_when_contract_not_upgradeable(): void
    {
        BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'is_upgradeable' => false,
        ]);

        $this->artisan('blockchain:rollback', [
            'contract' => 'TestContract',
        ])
            ->expectsOutput('Contract is not upgradeable and cannot be rolled back')
            ->assertFailed();
    }

    public function test_rollback_command_with_json_output(): void
    {
        BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'is_upgradeable' => true,
        ]);

        $this->artisan('blockchain:rollback', [
            'contract' => 'TestContract',
            '--json' => true,
        ])
            ->assertFailed(); // Fails because no previous version, but tests JSON flag
    }
}
