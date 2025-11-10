<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Feature\Commands;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VerifyContractCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_verify_command_fails_when_contract_not_found(): void
    {
        $this->artisan('blockchain:verify', [
            'contract' => 'NonExistent',
        ])
            ->expectsOutput("Contract 'NonExistent' not found")
            ->assertFailed();
    }

    public function test_verify_command_succeeds_with_valid_contract(): void
    {
        BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => '0x1234567890123456789012345678901234567890',
            'network' => 'local',
            'status' => 'deployed',
        ]);

        $this->artisan('blockchain:verify', [
            'contract' => 'TestContract',
        ])
            ->expectsOutput('✓ Contract verified successfully!')
            ->assertSuccessful();
    }

    public function test_verify_command_with_json_output(): void
    {
        BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => '0x1234567890123456789012345678901234567890',
            'network' => 'local',
            'status' => 'deployed',
        ]);

        $this->artisan('blockchain:verify', [
            'contract' => 'TestContract',
            '--json' => true,
        ])
            ->assertSuccessful();
    }
}
