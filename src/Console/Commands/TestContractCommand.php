<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Console\Commands;

use AwsBlockchain\Laravel\Services\ContractCompiler;
use AwsBlockchain\Laravel\Services\ContractDeployer;
use AwsBlockchain\Laravel\Services\ContractInteractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class TestContractCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blockchain:test {name : Contract name}
                            {--network=local : Network to test on}
                            {--coverage : Generate code coverage report}
                            {--source= : Path to contract source file}
                            {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test a smart contract on a test network';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $contractName = $this->argument('name');
        $network = $this->option('network');

        try {
            $config = config('aws-blockchain-laravel.contracts', []);
            $blockchain = App::make('blockchain');
            $driver = $blockchain->driver();
            
            $compiler = new ContractCompiler($config['compiler'] ?? []);
            $deployer = new ContractDeployer($driver, $compiler, $config);
            $interactor = new ContractInteractor($driver, $config);

            $this->info("Testing contract '{$contractName}' on {$network}...");

            // Deploy to test network
            $deployParams = [
                'name' => $contractName,
                'version' => 'test-'.time(),
                'network' => $network,
            ];

            if ($this->option('source')) {
                $deployParams['source_file'] = $this->option('source');
            }

            $this->line('Deploying contract to test network...');
            $deployment = $deployer->deploy($deployParams);

            // Run basic tests
            $testResults = $this->runBasicTests($deployment['contract'], $interactor);

            // Clean up test deployment
            $this->line('Cleaning up test deployment...');
            $deployment['contract']->update(['status' => 'deprecated']);

            // Display results
            return $this->displayResults($testResults);

        } catch (\Exception $e) {
            $this->error('Testing failed: '.$e->getMessage());
            
            if ($this->option('json')) {
                $this->line(json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], JSON_PRETTY_PRINT));
            }

            return Command::FAILURE;
        }
    }

    /**
     * Run basic tests on contract
     *
     * @return array<string, mixed>
     */
    protected function runBasicTests($contract, ContractInteractor $interactor): array
    {
        $results = [
            'total' => 0,
            'passed' => 0,
            'failed' => 0,
            'tests' => [],
        ];

        $this->newLine();
        $this->info('Running tests...');

        // Test 1: Contract is deployed
        $results['total']++;
        if ($contract->address) {
            $this->line('  ✓ Contract deployed successfully');
            $results['passed']++;
            $results['tests'][] = [
                'name' => 'deployment',
                'status' => 'passed',
            ];
        } else {
            $this->line('  ✗ Contract deployment failed');
            $results['failed']++;
            $results['tests'][] = [
                'name' => 'deployment',
                'status' => 'failed',
            ];
        }

        // Test 2: Contract has valid ABI
        $results['total']++;
        $abi = $contract->getParsedAbi();
        if ($abi && is_array($abi) && count($abi) > 0) {
            $this->line('  ✓ Contract has valid ABI');
            $results['passed']++;
            $results['tests'][] = [
                'name' => 'abi_validation',
                'status' => 'passed',
            ];
        } else {
            $this->line('  ✗ Contract ABI is invalid');
            $results['failed']++;
            $results['tests'][] = [
                'name' => 'abi_validation',
                'status' => 'failed',
            ];
        }

        return $results;
    }

    /**
     * Display test results
     *
     * @param  array<string, mixed>  $results
     */
    protected function displayResults(array $results): int
    {
        if ($this->option('json')) {
            $this->line(json_encode($results, JSON_PRETTY_PRINT));

            return $results['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
        }

        $this->newLine();
        $this->info('=== Test Results ===');
        $this->line("Total: {$results['total']}");
        $this->line("Passed: {$results['passed']}");
        $this->line("Failed: {$results['failed']}");

        return $results['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}

