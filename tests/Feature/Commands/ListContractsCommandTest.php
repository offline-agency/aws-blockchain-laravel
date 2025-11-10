<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Feature\Commands;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ListContractsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_command_shows_no_contracts_message(): void
    {
        $this->artisan('blockchain:list')
            ->expectsOutput('No contracts found')
            ->assertSuccessful();
    }

    public function test_list_command_shows_contracts(): void
    {
        BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => '0x1234567890123456789012345678901234567890',
            'network' => 'local',
            'status' => 'deployed',
        ]);

        $this->artisan('blockchain:list')
            ->assertSuccessful();
    }

    public function test_list_command_with_json_output(): void
    {
        BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => '0x1234567890123456789012345678901234567890',
            'network' => 'local',
            'status' => 'deployed',
        ]);

        $this->artisan('blockchain:list', ['--json' => true])
            ->assertSuccessful();
    }
}

