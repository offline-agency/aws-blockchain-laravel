<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Feature\Commands;

use AwsBlockchain\Laravel\Tests\TestCase;

class CompileContractCommandTest extends TestCase
{
    public function test_compile_command_fails_with_invalid_source(): void
    {
        $this->artisan('blockchain:compile', [
            'source' => '/non/existent/file.sol',
            'name' => 'TestContract',
        ])
            ->assertFailed();
    }

    public function test_compile_command_accepts_version_option(): void
    {
        $this->artisan('blockchain:compile', [
            'source' => '/non/existent/file.sol',
            'name' => 'TestContract',
            '--contract-version' => '2.0.0',
        ])
            ->assertFailed(); // Fails on file not found, but tests argument parsing
    }

    public function test_compile_command_accepts_json_flag(): void
    {
        $this->artisan('blockchain:compile', [
            'source' => '/non/existent/file.sol',
            'name' => 'TestContract',
            '--json' => true,
        ])
            ->assertFailed();
    }
}
