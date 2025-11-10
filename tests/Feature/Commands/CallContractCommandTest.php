<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Feature\Commands;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CallContractCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => '0x1234567890123456789012345678901234567890',
            'network' => 'local',
            'status' => 'deployed',
            'abi' => json_encode([
                [
                    'type' => 'function',
                    'name' => 'balanceOf',
                    'stateMutability' => 'view',
                    'inputs' => [['name' => 'account', 'type' => 'address']],
                ],
            ]),
        ]);
    }

    public function test_call_command_fails_when_contract_not_found(): void
    {
        $this->artisan('blockchain:call', [
            'contract' => 'NonExistent',
            'method' => 'test',
        ])
            ->expectsOutput("Contract 'NonExistent' not found")
            ->assertFailed();
    }

    public function test_call_command_with_json_output(): void
    {
        $this->artisan('blockchain:call', [
            'contract' => 'TestContract',
            'method' => 'balanceOf',
            '--params' => '0x1234567890123456789012345678901234567890',
            '--json' => true,
        ])
            ->assertSuccessful();
    }

    public function test_call_command_with_network_filter(): void
    {
        $this->artisan('blockchain:call', [
            'contract' => 'TestContract',
            'method' => 'balanceOf',
            '--network' => 'local',
        ])
            ->assertSuccessful();
    }
}
