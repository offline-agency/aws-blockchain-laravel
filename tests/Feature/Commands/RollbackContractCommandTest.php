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

    public function test_rollback_command_find_contract_by_address(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'address' => '0x1234567890123456789012345678901234567890',
            'status' => 'deployed',
            'is_upgradeable' => true,
        ]);

        $command = $this->app->make(\AwsBlockchain\Laravel\Console\Commands\RollbackContractCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('findContract');
        $method->setAccessible(true);

        $result = $method->invoke($command, '0x1234567890123456789012345678901234567890');

        $this->assertNotNull($result);
        $this->assertEquals('TestContract', $result->name);
    }

    public function test_rollback_command_find_contract_by_name(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
            'is_upgradeable' => true,
        ]);

        $command = $this->app->make(\AwsBlockchain\Laravel\Console\Commands\RollbackContractCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('findContract');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'TestContract');

        $this->assertNotNull($result);
        $this->assertEquals('TestContract', $result->name);
    }
}
