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
            '--params' => '0x1234567890123456789012345678901234567890',
            '--network' => 'local',
        ])
            ->assertSuccessful();
    }

    public function test_call_command_handles_exception_with_json(): void
    {
        $this->artisan('blockchain:call', [
            'contract' => 'TestContract',
            'method' => 'nonExistentMethod',
            '--json' => true,
        ])
            ->expectsOutputToContain('"success": false')
            ->assertFailed();
    }

    public function test_call_command_display_result_with_json(): void
    {
        $command = $this->app->make(\AwsBlockchain\Laravel\Console\Commands\CallContractCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('displayResult');
        $method->setAccessible(true);

        $input = new \Symfony\Component\Console\Input\ArrayInput(
            ['contract' => 'TestContract', 'method' => 'testMethod'],
            $command->getDefinition()
        );
        $bufferedOutput = new \Symfony\Component\Console\Output\BufferedOutput;
        $command->setOutput(new \Illuminate\Console\OutputStyle($input, $bufferedOutput));

        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($command, $input);

        $result = ['test' => 'value'];

        $exitCode = $method->invoke($command, 'testMethod', $result, ['json' => true]);

        $this->assertEquals(\Illuminate\Console\Command::SUCCESS, $exitCode);
    }

    public function test_call_command_display_result_without_json(): void
    {
        $command = $this->app->make(\AwsBlockchain\Laravel\Console\Commands\CallContractCommand::class);
        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('displayResult');
        $method->setAccessible(true);

        $input = new \Symfony\Component\Console\Input\ArrayInput(
            ['contract' => 'TestContract', 'method' => 'testMethod'],
            $command->getDefinition()
        );
        $bufferedOutput = new \Symfony\Component\Console\Output\BufferedOutput;
        $command->setOutput(new \Illuminate\Console\OutputStyle($input, $bufferedOutput));

        $inputProperty = $reflection->getProperty('input');
        $inputProperty->setAccessible(true);
        $inputProperty->setValue($command, $input);

        $result = 'test result';

        $exitCode = $method->invoke($command, 'testMethod', $result, []);

        $this->assertEquals(\Illuminate\Console\Command::SUCCESS, $exitCode);
        // Get output from the command's output property
        $outputProperty = $reflection->getProperty('output');
        $outputProperty->setAccessible(true);
        $outputStyle = $outputProperty->getValue($command);
        $outputContent = $outputStyle->getOutput()->fetch();
        $this->assertStringContainsString('testMethod', $outputContent);
    }
}
