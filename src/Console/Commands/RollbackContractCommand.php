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

class RollbackContractCommand extends Command
{
    protected $signature = 'blockchain:rollback {contract : Contract name or address}
                            {--target-version= : Target version to rollback to}
                            {--from= : Address performing the rollback}
                            {--json : Output in JSON format}';

    protected $description = 'Rollback a contract to a previous version';

    public function handle(): int
    {
        $identifier = $this->argument('contract');

        if (! is_string($identifier)) {
            $this->error('Contract identifier must be a string');

            return Command::FAILURE;
        }

        try {
            $contract = $this->findContract($identifier);

            if (! $contract) {
                $this->error("Contract '{$identifier}' not found");

                return Command::FAILURE;
            }

            if (! $contract->isUpgradeable()) {
                $this->error('Contract is not upgradeable and cannot be rolled back');

                return Command::FAILURE;
            }

            // Initialize services
            $config = config('aws-blockchain-laravel.contracts', []);
            $blockchain = App::make('blockchain');
            $driver = $blockchain->driver();
            
            $compiler = new ContractCompiler($config['compiler'] ?? []);
            $deployer = new ContractDeployer($driver, $compiler, $config);
            $interactor = new ContractInteractor($driver, $config);
            $upgrader = new ContractUpgrader($deployer, $interactor, $config);

            // Confirm rollback
            if (! $this->confirm("Rollback contract '{$contract->name}' to previous version?", true)) {
                $this->info('Rollback cancelled');

                return Command::SUCCESS;
            }

            $options = ['from' => $this->option('from')];

            $this->info('Rolling back contract...');
            $versionOption = $this->option('target-version');
            $targetVersion = is_string($versionOption) ? $versionOption : null;
            $result = $upgrader->rollback($contract, $targetVersion, $options);

            if ($this->option('json')) {
                $jsonOutput = json_encode([
                    'success' => true,
                    'restored_version' => $result['restored_contract']->version,
                ], JSON_PRETTY_PRINT);
                if ($jsonOutput !== false) {
                    $this->line($jsonOutput);
                }
            } else {
                $this->info('✓ Contract rolled back successfully!');
                $this->line('  Restored version: '.$result['restored_contract']->version);
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Rollback failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    protected function findContract(string $identifier): ?BlockchainContract
    {
        return BlockchainContract::where('address', $identifier)
            ->orWhere('name', $identifier)
            ->first();
    }
}

