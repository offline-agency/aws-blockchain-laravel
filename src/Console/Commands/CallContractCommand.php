<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Console\Commands;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Services\ContractInteractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class CallContractCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blockchain:call {contract : Contract name or address}
                            {method : Method name to call}
                            {--params= : Method parameters (JSON or comma-separated)}
                            {--from= : Sender address (for transactions)}
                            {--network= : Network the contract is on}
                            {--wait : Wait for transaction confirmation}
                            {--gas-limit= : Gas limit for transaction}
                            {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Call a method on a deployed smart contract';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $contractIdentifier = $this->argument('contract');
        $methodName = $this->argument('method');

        try {
            // Find contract
            $contract = $this->findContract($contractIdentifier);

            if (! $contract) {
                $this->error("Contract '{$contractIdentifier}' not found");

                return Command::FAILURE;
            }

            // Initialize service
            $config = config('aws-blockchain-laravel.contracts', []);
            $blockchain = App::make('blockchain');
            $driver = $blockchain->driver();
            $interactor = new ContractInteractor($driver, $config);

            // Parse parameters
            $params = [];
            if ($this->option('params')) {
                $params = $interactor->parseParameters($this->option('params'));
            }

            // Prepare options
            $options = [
                'from' => $this->option('from'),
                'wait' => $this->option('wait'),
                'json' => $this->option('json'),
            ];

            if ($this->option('gas-limit')) {
                $options['gas_limit'] = (int) $this->option('gas-limit');
            }

            // Call method
            $this->info("Calling {$methodName} on {$contract->name}...");
            $result = $interactor->call($contract, $methodName, $params, $options);

            // Display result
            return $this->displayResult($methodName, $result, $options);

        } catch (\Exception $e) {
            $this->error('Contract call failed: '.$e->getMessage());
            
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
     * Find contract by name or address
     */
    protected function findContract(string $identifier): ?BlockchainContract
    {
        // Try to find by address first
        $query = BlockchainContract::where('address', $identifier);

        if ($this->option('network')) {
            $query->where('network', $this->option('network'));
        }

        $contract = $query->first();

        // If not found, try by name
        if (! $contract) {
            $query = BlockchainContract::where('name', $identifier);
            
            if ($this->option('network')) {
                $query->where('network', $this->option('network'));
            }
            
            $contract = $query->where('status', 'deployed')->latest()->first();
        }

        return $contract;
    }

    /**
     * Display call result
     *
     * @param  array<string, mixed>  $options
     */
    protected function displayResult(string $methodName, mixed $result, array $options): int
    {
        if ($options['json']) {
            $output = [
                'success' => true,
                'method' => $methodName,
                'result' => $result,
            ];

            $this->line(json_encode($output, JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->info("✓ Method '{$methodName}' executed successfully!");
        $this->newLine();
        
        if (is_array($result) && isset($result['transaction_hash'])) {
            $this->info('Transaction sent:');
            $this->line('  Hash: '.$result['transaction_hash']);
            
            if (isset($result['transaction_record'])) {
                $this->line('  Status: '.$result['transaction_record']->status);
            }
        } else {
            $this->info('Return value:');
            $this->line('  '.print_r($result, true));
        }

        return Command::SUCCESS;
    }
}

