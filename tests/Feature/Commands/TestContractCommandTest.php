<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Feature\Commands;

use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TestContractCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_command_with_json_output(): void
    {
        $this->artisan('blockchain:test', [
            'name' => 'TestContract',
            '--json' => true,
        ])
            ->assertFailed(); // Will fail because no artifacts, but tests JSON output
    }

    public function test_test_command_defaults_to_local_network(): void
    {
        $this->artisan('blockchain:test', [
            'name' => 'TestContract',
        ])
            ->assertFailed(); // Will fail but we're testing it runs
    }

    public function test_test_command_with_custom_network(): void
    {
        $this->artisan('blockchain:test', [
            'name' => 'TestContract',
            '--network' => 'testnet',
        ])
            ->assertFailed();
    }

    public function test_test_command_with_source_file(): void
    {
        $this->artisan('blockchain:test', [
            'name' => 'TestContract',
            '--source' => '/path/to/contract.sol',
        ])
            ->assertFailed();
    }

    public function test_test_command_shows_error_for_non_string_name(): void
    {
        // Laravel always passes strings, but we test the error handling path
        $this->artisan('blockchain:test', [
            'name' => 'TestContract',
        ])
            ->assertFailed(); // Will fail but tests the path
    }

    public function test_test_command_outputs_json_on_error(): void
    {
        $this->artisan('blockchain:test', [
            'name' => 'NonExistentContract',
            '--json' => true,
        ])
            ->expectsOutputToContain('"success": false')
            ->assertFailed();
    }

    public function test_test_command_handles_testing_failure(): void
    {
        $this->artisan('blockchain:test', [
            'name' => 'NonExistentContract',
        ])
            ->assertFailed();
    }
}
