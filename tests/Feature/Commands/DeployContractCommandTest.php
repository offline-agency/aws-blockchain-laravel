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
            ->expectsOutput(fn ($output) => str_contains($output, '"contract_name"'))
            ->assertSuccessful();
    }
}

