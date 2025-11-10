<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Feature\Commands;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Models\BlockchainTransaction;
use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContractStatusCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_command_fails_when_contract_not_found(): void
    {
        $this->artisan('blockchain:status', [
            'contract' => 'NonExistent',
        ])
            ->expectsOutput("Contract 'NonExistent' not found")
            ->assertFailed();
    }

    public function test_status_command_shows_contract_details(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => '0x1234567890123456789012345678901234567890',
            'network' => 'local',
            'status' => 'deployed',
        ]);

        $this->artisan('blockchain:status', [
            'contract' => 'TestContract',
        ])
            ->expectsOutput('Contract: TestContract')
            ->assertSuccessful();
    }

    public function test_status_command_shows_recent_transactions(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => '0x1234567890123456789012345678901234567890',
            'network' => 'local',
            'status' => 'deployed',
        ]);

        BlockchainTransaction::create([
            'transaction_hash' => '0xabcd',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'success',
        ]);

        $this->artisan('blockchain:status', [
            'contract' => 'TestContract',
        ])
            ->assertSuccessful();
    }

    public function test_status_command_with_json_output(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => '0x1234567890123456789012345678901234567890',
            'network' => 'local',
            'status' => 'deployed',
        ]);

        $this->artisan('blockchain:status', [
            'contract' => 'TestContract',
            '--json' => true,
        ])
            ->assertSuccessful();
    }
}
