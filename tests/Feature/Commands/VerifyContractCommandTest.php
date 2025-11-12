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

    public function test_verify_command_find_contract_by_address(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => '0x1234567890123456789012345678901234567890',
            'network' => 'local',
            'status' => 'deployed',
        ]);

        $command = $this->app->make(\AwsBlockchain\Laravel\Console\Commands\VerifyContractCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('findContract');
        $method->setAccessible(true);

        $input = new \Symfony\Component\Console\Input\ArrayInput(
            ['contract' => 'TestContract'],
            $command->getDefinition()
        );
        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($command, $input);

        $result = $method->invoke($command, '0x1234567890123456789012345678901234567890');

        $this->assertNotNull($result);
        $this->assertEquals('TestContract', $result->name);
    }

    public function test_verify_command_find_contract_by_name(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => '0x1234567890123456789012345678901234567890',
            'network' => 'local',
            'status' => 'deployed',
        ]);

        $command = $this->app->make(\AwsBlockchain\Laravel\Console\Commands\VerifyContractCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('findContract');
        $method->setAccessible(true);

        $input = new \Symfony\Component\Console\Input\ArrayInput(
            ['contract' => 'TestContract'],
            $command->getDefinition()
        );
        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($command, $input);

        $result = $method->invoke($command, 'TestContract');

        $this->assertNotNull($result);
        $this->assertEquals('TestContract', $result->name);
    }
}
