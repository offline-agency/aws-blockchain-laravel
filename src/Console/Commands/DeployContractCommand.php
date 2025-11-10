<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Console\Commands;

use AwsBlockchain\Laravel\Services\ContractCompiler;
use AwsBlockchain\Laravel\Services\ContractDeployer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class DeployContractCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blockchain:deploy {name : Contract name}
                            {--constructor= : Constructor signature}
                            {--params= : Constructor parameters (JSON or comma-separated)}
                            {--network= : Network to deploy to}
                            {--from= : Deployer address}
                            {--source= : Path to contract source file}
                            {--contract-version=1.0.0 : Contract version}
                            {--verify : Verify contract on block explorer}
                            {--preview : Preview deployment without executing}
                            {--gas-limit= : Gas limit for deployment}
                            {--json : Output in JSON format}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deploy a smart contract to the blockchain';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $contractName = $this->argument('name');
        
        if (! is_string($contractName)) {
            $this->error('Contract name must be a string');

            return Command::FAILURE;
        }

        $config = config('aws-blockchain-laravel.contracts', []);

        // Get blockchain driver
        $blockchain = App::make('blockchain');
        $networkOption = $this->option('network');
        $network = is_string($networkOption) ? $networkOption : ($config['default_network'] ?? 'local');
        $networkConfig = $config['networks'][$network] ?? [];
        
        $driver = $this->getDriverForNetwork($blockchain, $networkConfig);
        
        // Initialize services
        $compiler = new ContractCompiler($config['compiler'] ?? []);
        $deployer = new ContractDeployer($driver, $compiler, $config);

        try {
            // Prepare deployment parameters
            $params = $this->prepareDeploymentParams($contractName, $config);

            // Preview mode
            if ($this->option('preview')) {
                $preview = $deployer->previewDeployment(
                    $contractName,
                    $params,
                    $network
                );

                return $this->displayPreview($preview);
            }

            // Deploy contract
            $this->info("Deploying contract '{$contractName}' to {$network}...");
            
            $result = $deployer->deploy($params);

            // Verify if requested
            if ($this->option('verify') && ($config['deployment']['auto_verify'] ?? false)) {
                $this->info('Verifying contract on block explorer...');
                $deployer->verifyContract($result['contract']);
            }

            // Display result
            return $this->displayResult($result);

        } catch (\Exception $e) {
            $this->error('Deployment failed: '.$e->getMessage());
            
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
     * Prepare deployment parameters
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function prepareDeploymentParams(string $contractName, array $config): array
    {
        $params = [
            'name' => $contractName,
            'version' => $this->option('contract-version'),
            'network' => $this->option('network') ?? $config['default_network'] ?? 'local',
            'from' => $this->option('from'),
            'preview' => $this->option('preview'),
        ];

        // Add source file if provided
        if ($this->option('source')) {
            $params['source_file'] = $this->option('source');
        }

        // Parse constructor parameters
        $paramsOption = $this->option('params');
        if ($paramsOption && is_string($paramsOption)) {
            $params['constructor_params'] = $this->parseParameters($paramsOption);
        }

        // Gas limit
        if ($this->option('gas-limit')) {
            $params['gas_limit'] = (int) $this->option('gas-limit');
        }

        return $params;
    }

    /**
     * Parse parameters from string
     *
     * @return array<int, mixed>
     */
    protected function parseParameters(string $paramsString): array
    {
        // Try JSON first
        $decoded = json_decode($paramsString, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Try comma-separated values
        if (str_contains($paramsString, ',')) {
            return array_map('trim', explode(',', $paramsString));
        }

        // Single parameter
        return [$paramsString];
    }

    /**
     * Display deployment preview
     *
     * @param  array<string, mixed>  $preview
     */
    protected function displayPreview(array $preview): int
    {
        if ($this->option('json')) {
            $jsonOutput = json_encode($preview, JSON_PRETTY_PRINT);
            if ($jsonOutput !== false) {
                $this->line($jsonOutput);
            }

            return Command::SUCCESS;
        }

        $this->info('=== Deployment Preview ===');
        $this->table(
            ['Property', 'Value'],
            [
                ['Contract Name', $preview['contract_name']],
                ['Network', $preview['network']],
                ['From Address', $preview['from']],
                ['Gas Limit', number_format($preview['gas_limit'])],
                ['Gas Price (wei)', number_format($preview['gas_price'])],
                ['Estimated Cost (ETH)', number_format($preview['estimated_cost_eth'], 6)],
                ['Bytecode Size', number_format($preview['bytecode_size']).' bytes'],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Display deployment result
     *
     * @param  array<string, mixed>  $result
     */
    protected function displayResult(array $result): int
    {
        if ($this->option('json')) {
            $output = [
                'success' => true,
                'contract' => [
                    'name' => $result['contract']->name,
                    'address' => $result['contract']->address,
                    'version' => $result['contract']->version,
                    'network' => $result['contract']->network,
                    'transaction_hash' => $result['contract']->transaction_hash,
                ],
            ];

            $jsonOutput = json_encode($output, JSON_PRETTY_PRINT);
            if ($jsonOutput !== false) {
                $this->line($jsonOutput);
            }

            return Command::SUCCESS;
        }

        $this->info('✓ Contract deployed successfully!');
        $this->newLine();
        
        $this->table(
            ['Property', 'Value'],
            [
                ['Contract Name', $result['contract']->name],
                ['Version', $result['contract']->version],
                ['Address', $result['contract']->address],
                ['Network', $result['contract']->network],
                ['Transaction Hash', $result['contract']->transaction_hash],
                ['Gas Used', number_format($result['contract']->gas_used ?? 0)],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Get driver for network
     *
     * @param  mixed  $blockchain
     * @param  array<string, mixed>  $networkConfig
     * @return \AwsBlockchain\Laravel\Contracts\BlockchainDriverInterface
     */
    protected function getDriverForNetwork($blockchain, array $networkConfig): \AwsBlockchain\Laravel\Contracts\BlockchainDriverInterface
    {
        $type = $networkConfig['type'] ?? 'evm';
        
        // For EVM networks, we'd need to configure the driver with network-specific settings
        // This is a simplified version
        return $blockchain->driver();
    }
}

