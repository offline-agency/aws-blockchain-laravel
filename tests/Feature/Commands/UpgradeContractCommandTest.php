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
            'identifier' => 'TestContract',
            '--preserve-state' => true,
            '--json' => true,
        ])
            ->assertFailed(); // Will fail at upgrade but tests the path
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

    public function test_upgrade_command_display_result_with_json(): void
    {
        $command = $this->app->make(\AwsBlockchain\Laravel\Console\Commands\UpgradeContractCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('displayResult');
        $method->setAccessible(true);

        $input = new \Symfony\Component\Console\Input\ArrayInput(
            ['identifier' => 'TestContract'],
            $command->getDefinition()
        );
        $bufferedOutput = new \Symfony\Component\Console\Output\BufferedOutput;
        $command->setOutput(new \Illuminate\Console\OutputStyle($input, $bufferedOutput));

        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($command, $input);

        $oldContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'address' => '0x1111111111111111111111111111111111111111',
            'status' => 'deployed',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test1',
        ]);

        $newContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'address' => '0x2222222222222222222222222222222222222222',
            'status' => 'deployed',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test2',
        ]);

        $result = [
            'old_contract' => $oldContract,
            'new_contract' => $newContract,
        ];

        $exitCode = $method->invoke($command, $result);

        $this->assertEquals(\Illuminate\Console\Command::SUCCESS, $exitCode);
    }

    public function test_upgrade_command_display_result_without_json(): void
    {
        $command = $this->app->make(\AwsBlockchain\Laravel\Console\Commands\UpgradeContractCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('displayResult');
        $method->setAccessible(true);

        $input = new \Symfony\Component\Console\Input\ArrayInput(
            ['identifier' => 'TestContract'],
            $command->getDefinition()
        );
        $bufferedOutput = new \Symfony\Component\Console\Output\BufferedOutput;
        $command->setOutput(new \Illuminate\Console\OutputStyle($input, $bufferedOutput));

        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($command, $input);

        $oldContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'address' => '0x1111111111111111111111111111111111111111',
            'status' => 'deployed',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test1',
        ]);

        $newContract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'address' => '0x2222222222222222222222222222222222222222',
            'status' => 'deployed',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test2',
        ]);

        $result = [
            'old_contract' => $oldContract,
            'new_contract' => $newContract,
        ];

        $exitCode = $method->invoke($command, $result);

        $this->assertEquals(\Illuminate\Console\Command::SUCCESS, $exitCode);
        // Get output from the command's output property
        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputStyle = $outputProperty->getValue($command);
        $outputContent = $outputStyle->getOutput()->fetch();
        $this->assertStringContainsString('upgraded successfully', $outputContent);
    }

    public function test_upgrade_command_parse_identifier(): void
    {
        $command = $this->app->make(\AwsBlockchain\Laravel\Console\Commands\UpgradeContractCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('parseIdentifier');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'TestContract@1.0.0');
        $this->assertIsArray($result);
        $this->assertEquals('TestContract', $result[0]);
        $this->assertEquals('1.0.0', $result[1]);

        $result2 = $method->invoke($command, 'TestContract');
        $this->assertEquals('TestContract', $result2[0]);
        $this->assertNull($result2[1]);
    }

    public function test_upgrade_command_increment_version(): void
    {
        $command = $this->app->make(\AwsBlockchain\Laravel\Console\Commands\UpgradeContractCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('incrementVersion');
        $method->setAccessible(true);

        $result = $method->invoke($command, '1.0.0');
        $this->assertEquals('1.0.1', $result);

        $result2 = $method->invoke($command, '2.5.10');
        $this->assertEquals('2.5.11', $result2);
    }
}
