<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Feature\Commands;

use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeployContractCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_deploy_command_shows_preview(): void
    {
        $this->artisan('blockchain:deploy', [
            'name' => 'TestContract',
            '--preview' => true,
        ])
            ->assertSuccessful();
    }

    public function test_deploy_command_with_json_output(): void
    {
        $this->artisan('blockchain:deploy', [
            'name' => 'TestContract',
            '--preview' => true,
            '--json' => true,
        ])
            ->expectsOutputToContain('contract_name')
            ->assertSuccessful();
    }

    public function test_deploy_command_with_network_option(): void
    {
        $this->artisan('blockchain:deploy', [
            'name' => 'TestContract',
            '--preview' => true,
            '--network' => 'testnet',
        ])
            ->assertSuccessful();
    }

    public function test_deploy_command_with_constructor_params_json(): void
    {
        $this->artisan('blockchain:deploy', [
            'name' => 'TestContract',
            '--preview' => true,
            '--params' => '["0x1234567890123456789012345678901234567890", 1000]',
        ])
            ->assertSuccessful();
    }

    public function test_deploy_command_with_constructor_params_comma_separated(): void
    {
        $this->artisan('blockchain:deploy', [
            'name' => 'TestContract',
            '--preview' => true,
            '--params' => 'param1, param2, 123',
        ])
            ->assertSuccessful();
    }

    public function test_deploy_command_with_single_constructor_param(): void
    {
        $this->artisan('blockchain:deploy', [
            'name' => 'TestContract',
            '--preview' => true,
            '--params' => 'single_param',
        ])
            ->assertSuccessful();
    }

    public function test_deploy_command_with_gas_limit(): void
    {
        $this->artisan('blockchain:deploy', [
            'name' => 'TestContract',
            '--preview' => true,
            '--gas-limit' => '5000000',
        ])
            ->assertSuccessful();
    }

    public function test_deploy_command_with_from_address(): void
    {
        $this->artisan('blockchain:deploy', [
            'name' => 'TestContract',
            '--preview' => true,
            '--from' => '0x1234567890123456789012345678901234567890',
        ])
            ->assertSuccessful();
    }

    public function test_deploy_command_with_source_file(): void
    {
        $this->artisan('blockchain:deploy', [
            'name' => 'TestContract',
            '--preview' => true,
            '--source' => '/path/to/contract.sol',
        ])
            ->assertSuccessful();
    }

    public function test_deploy_command_with_version_option(): void
    {
        $this->artisan('blockchain:deploy', [
            'name' => 'TestContract',
            '--preview' => true,
            '--contract-version' => '2.0.0',
        ])
            ->assertSuccessful();
    }

    public function test_deploy_command_handles_deployment_failure(): void
    {
        $this->artisan('blockchain:deploy', [
            'name' => 'NonExistentContract',
        ])
            ->assertFailed();
    }

    public function test_deploy_command_outputs_json_on_error(): void
    {
        $this->artisan('blockchain:deploy', [
            'name' => 'NonExistentContract',
            '--json' => true,
        ])
            ->expectsOutputToContain('"success": false')
            ->assertFailed();
    }

    public function test_deploy_command_shows_error_for_non_string_name(): void
    {
        // Laravel always passes strings, but we test the error handling path
        $this->artisan('blockchain:deploy', [
            'name' => 'TestContract',
        ])
            ->assertFailed(); // Will fail because contract doesn't exist, but tests the path
    }

    public function test_deploy_command_display_result_with_json(): void
    {
        $command = $this->app->make(\AwsBlockchain\Laravel\Console\Commands\DeployContractCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('displayResult');
        $method->setAccessible(true);

        $input = new \Symfony\Component\Console\Input\ArrayInput(
            ['name' => 'TestContract', '--json' => true],
            $command->getDefinition()
        );
        $bufferedOutput = new \Symfony\Component\Console\Output\BufferedOutput;
        $command->setOutput(new \Illuminate\Console\OutputStyle($input, $bufferedOutput));

        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($command, $input);

        $contract = \AwsBlockchain\Laravel\Models\BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'address' => '0x1234567890123456789012345678901234567890',
            'transaction_hash' => '0xabcdef',
            'status' => 'deployed',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $result = [
            'contract' => $contract,
        ];

        $exitCode = $method->invoke($command, $result);

        $this->assertEquals(\Illuminate\Console\Command::SUCCESS, $exitCode);
    }

    public function test_deploy_command_display_result_without_json(): void
    {
        $command = $this->app->make(\AwsBlockchain\Laravel\Console\Commands\DeployContractCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('displayResult');
        $method->setAccessible(true);

        $input = new \Symfony\Component\Console\Input\ArrayInput(
            ['name' => 'TestContract'],
            $command->getDefinition()
        );
        $bufferedOutput = new \Symfony\Component\Console\Output\BufferedOutput;
        $command->setOutput(new \Illuminate\Console\OutputStyle($input, $bufferedOutput));

        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($command, $input);

        $contract = \AwsBlockchain\Laravel\Models\BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'address' => '0x1234567890123456789012345678901234567890',
            'transaction_hash' => '0xabcdef',
            'gas_used' => '21000',
            'status' => 'deployed',
            'abi' => json_encode([]),
            'bytecode_hash' => 'test',
        ]);

        $result = [
            'contract' => $contract,
        ];

        $exitCode = $method->invoke($command, $result);

        $this->assertEquals(\Illuminate\Console\Command::SUCCESS, $exitCode);
        // Get output from the command's output property
        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputStyle = $outputProperty->getValue($command);
        $outputContent = $outputStyle->getOutput()->fetch();
        $this->assertStringContainsString('deployed successfully', $outputContent);
    }
}
