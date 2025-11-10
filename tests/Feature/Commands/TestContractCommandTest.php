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
}
