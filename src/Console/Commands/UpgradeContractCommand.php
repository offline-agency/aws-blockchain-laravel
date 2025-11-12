<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Console\Commands;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Services\ContractCompiler;
use AwsBlockchain\Laravel\Services\ContractDeployer;
use AwsBlockchain\Laravel\Services\ContractInteractor;
use AwsBlockchain\Laravel\Services\ContractUpgrader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class UpgradeContractCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blockchain:upgrade {identifier : Contract name or name@version}
                            {--preserve-state : Preserve contract state during upgrade}
                            {--migration= : Migration script to run}
                            {--source= : Path to new contract source file}
                            {--from= : Address performing the upgrade}
                            {--network= : Network the contract is on}
                            {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Upgrade an existing smart contract';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $identifier = $this->argument('identifier');

        if (! is_string($identifier)) {
            $this->error('Contract identifier must be a string');

            return Command::FAILURE;
        }

        [$contractName, $currentVersion] = $this->parseIdentifier($identifier);

        try {
            // Find existing contract
            $contract = $this->findContract($contractName, $currentVersion);

            if (! $contract) {
                $this->error("Contract '{$contractName}' not found");

                return Command::FAILURE;
            }

            // Get new version
            $newVersion = $this->askNewVersion($contract->version);

            // Initialize services
            $config = config('aws-blockchain-laravel.contracts', []);
            $blockchain = App::make('blockchain');
            $driver = $blockchain->driver();

            $compiler = new ContractCompiler($config['compiler'] ?? []);
            $deployer = new ContractDeployer($driver, $compiler, $config);
            $interactor = new ContractInteractor($driver, $config);
            $upgrader = new ContractUpgrader($deployer, $interactor, $config);

            // Confirm upgrade
            if (! $this->confirm("Upgrade '{$contractName}' from v{$contract->version} to v{$newVersion}?", true)) {
                $this->info('Upgrade cancelled');

                return Command::SUCCESS;
            }

            // Prepare upgrade options
            $options = [
                'preserve_state' => $this->option('preserve-state'),
                'from' => $this->option('from'),
            ];

            if ($this->option('source')) {
                $options['source_file'] = $this->option('source');
            }

            if ($this->option('migration')) {
                $options['migration'] = $this->option('migration');
            }

            // Perform upgrade
            $this->info('Upgrading contract...');
            $result = $upgrader->upgrade($contract, $newVersion, $options);

            // Display result
            return $this->displayResult($result);

        } catch (\Exception $e) {
            $this->error('Upgrade failed: '.$e->getMessage());

            if ($this->option('json')) {
                $jsonOutput = json_encode([
                    'success' => false,
                    'error' => $e->getMessage(),
                ], JSON_PRETTY_PRINT);
                if ($jsonOutput !== false) {
                    $this->line($jsonOutput);
                }
            }

            return Command::FAILURE;
        }
    }

    /**
     * Parse contract identifier
     *
     * @return array{0: string, 1: string|null}
     */
    protected function parseIdentifier(string $identifier): array
    {
        if (str_contains($identifier, '@')) {
            $parts = explode('@', $identifier, 2);

            return [$parts[0], $parts[1] ?? null];
        }

        return [$identifier, null];
    }

    /**
     * Find contract in database
     */
    protected function findContract(string $name, ?string $version): ?BlockchainContract
    {
        $query = BlockchainContract::where('name', $name);

        if ($this->option('network')) {
            $query->where('network', $this->option('network'));
        }

        if ($version) {
            $query->where('version', $version);
        }

        return $query->where('status', 'deployed')->latest()->first();
    }

    /**
     * Ask for new version
     */
    protected function askNewVersion(string $currentVersion): string
    {
        return $this->ask('New version', $this->incrementVersion($currentVersion));
    }

    /**
     * Increment version number
     */
    protected function incrementVersion(string $version): string
    {
        $parts = explode('.', $version);
        $parts[count($parts) - 1] = (int) end($parts) + 1;

        return implode('.', $parts);
    }

    /**
     * Display upgrade result
     *
     * @param  array<string, mixed>  $result
     */
    protected function displayResult(array $result): int
    {
        if ($this->option('json')) {
            $output = [
                'success' => true,
                'old_contract' => [
                    'name' => $result['old_contract']->name,
                    'version' => $result['old_contract']->version,
                    'address' => $result['old_contract']->address,
                ],
                'new_contract' => [
                    'name' => $result['new_contract']->name,
                    'version' => $result['new_contract']->version,
                    'address' => $result['new_contract']->address,
                ],
            ];

            $jsonOutput = json_encode($output, JSON_PRETTY_PRINT);
            if ($jsonOutput !== false) {
                $this->line($jsonOutput);
            }

            return Command::SUCCESS;
        }

        $this->info('✓ Contract upgraded successfully!');
        $this->newLine();

        $this->info('Old Contract:');
        $this->line('  Version: '.$result['old_contract']->version);
        $this->line('  Address: '.$result['old_contract']->address);
        $this->newLine();

        $this->info('New Contract:');
        $this->line('  Version: '.$result['new_contract']->version);
        $this->line('  Address: '.$result['new_contract']->address);

        return Command::SUCCESS;
    }
}
