<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Services;

use AwsBlockchain\Laravel\Contracts\BlockchainDriverInterface;
use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Models\BlockchainTransaction;
use Illuminate\Support\Facades\Log;

class ContractDeployer
{
    protected BlockchainDriverInterface $driver;

    protected ContractCompiler $compiler;

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        BlockchainDriverInterface $driver,
        ContractCompiler $compiler,
        array $config = []
    ) {
        $this->driver = $driver;
        $this->compiler = $compiler;
        $this->config = $config;
    }

    /**
     * Deploy a smart contract
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed> Deployment result
     */
    public function deploy(array $params): array
    {
        $contractName = $params['name'];
        $version = $params['version'] ?? '1.0.0';
        $network = $params['network'] ?? $this->config['default_network'] ?? 'local';

        // For preview mode, use placeholder artifacts if not available
        $artifacts = null;
        try {
            $artifacts = $this->getContractArtifacts($contractName, $version, $params);
        } catch (\InvalidArgumentException $e) {
            // For preview, use placeholder artifacts
            if ($params['preview'] ?? false) {
                $artifacts = [
                    'bytecode' => '0x' . str_repeat('00', 100), // Placeholder bytecode
                    'abi' => [],
                ];
            } else {
                throw $e;
            }
        }

        // Prepare deployment parameters
        $deployParams = [
            'bytecode' => $artifacts['bytecode'] ?? '',
            'abi' => json_encode($artifacts['abi'] ?? []),
            'constructor_params' => $params['constructor_params'] ?? [],
            'from' => $params['from'] ?? null,
            'gas_limit' => $params['gas_limit'] ?? $this->config['gas']['default_limit'] ?? 3000000,
        ];

        // Estimate gas if not provided
        if (! isset($params['gas_limit'])) {
            try {
                $gasEstimate = $this->estimateDeploymentGas($deployParams);
                $deployParams['gas_limit'] = $gasEstimate;
                
                Log::info('Gas estimated for deployment', [
                    'contract' => $contractName,
                    'estimate' => $gasEstimate,
                ]);
            } catch (\Exception $e) {
                // Use default if estimation fails
                $deployParams['gas_limit'] = $this->config['gas']['default_limit'] ?? 3000000;
            }
        }

        // Preview transaction if requested
        if ($params['preview'] ?? false) {
            return $this->previewDeployment($contractName, $deployParams, $network);
        }

        // Deploy contract
        $result = $this->driver->deployContract($deployParams);

        // Store deployment record
        $contract = $this->storeDeploymentRecord(
            $contractName,
            $version,
            $network,
            $deployParams,
            $result,
            $artifacts
        );

        Log::info('Contract deployed successfully', [
            'contract' => $contractName,
            'address' => $result['address'],
            'network' => $network,
        ]);

        return [
            'contract' => $contract,
            'deployment' => $result,
            'artifacts' => $artifacts,
        ];
    }

    /**
     * Estimate gas for deployment
     *
     * @param  array<string, mixed>  $params
     */
    public function estimateDeploymentGas(array $params): int
    {
        try {
            $transaction = [
                'data' => $params['bytecode'],
                'from' => $params['from'] ?? '0x0000000000000000000000000000000000000000',
            ];

            $estimate = $this->driver->estimateGas($transaction);
            
            // Add safety margin
            $multiplier = $this->config['gas']['price_multiplier'] ?? 1.1;

            return (int) ($estimate * $multiplier);
        } catch (\Exception $e) {
            Log::warning('Gas estimation failed, using default', [
                'error' => $e->getMessage(),
            ]);

            return $this->config['gas']['default_limit'] ?? 3000000;
        }
    }

    /**
     * Preview deployment
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function previewDeployment(string $contractName, array $params, string $network): array
    {
        $gasPrice = $this->driver->getGasPrice();
        $gasLimit = $params['gas_limit'] ?? ($this->config['gas']['default_limit'] ?? 3000000);
        $estimatedCost = $gasPrice * $gasLimit;

        return [
            'contract_name' => $contractName,
            'network' => $network,
            'from' => $params['from'] ?? 'not set',
            'gas_limit' => $gasLimit,
            'gas_price' => $gasPrice,
            'estimated_cost_wei' => $estimatedCost,
            'estimated_cost_eth' => $estimatedCost / 1e18,
            'constructor_params' => $params['constructor_params'] ?? [],
            'bytecode_size' => strlen($params['bytecode'] ?? ''),
        ];
    }

    /**
     * Wait for deployment confirmation
     *
     * @return array<string, mixed>|null
     */
    public function waitForConfirmation(string $transactionHash, int $timeoutSeconds = 300): ?array
    {
        $startTime = time();
        $confirmationBlocks = $this->config['deployment']['confirmation_blocks'] ?? 2;

        while (time() - $startTime < $timeoutSeconds) {
            $receipt = $this->driver->getTransactionReceipt($transactionHash);

            if ($receipt !== null && isset($receipt['blockNumber'])) {
                // Check if we have enough confirmations
                // This is a simplified version; real implementation would check current block
                return $receipt;
            }

            sleep(2);
        }

        return null;
    }

    /**
     * Verify contract on block explorer
     */
    public function verifyContract(BlockchainContract $contract): bool
    {
        // This would integrate with Etherscan or other block explorer APIs
        // Placeholder implementation
        Log::info('Contract verification requested', [
            'contract' => $contract->name,
            'address' => $contract->address,
        ]);

        return true;
    }

    /**
     * Get contract artifacts (load or compile)
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function getContractArtifacts(string $contractName, string $version, array $params): array
    {
        // Check if artifacts are provided directly
        if (isset($params['abi']) && isset($params['bytecode'])) {
            return [
                'abi' => is_string($params['abi']) ? json_decode($params['abi'], true) : $params['abi'],
                'bytecode' => $params['bytecode'],
            ];
        }

        // Try to load pre-compiled artifacts
        $artifacts = $this->compiler->loadArtifacts($contractName, $version);

        if ($artifacts !== null) {
            return $artifacts;
        }

        // Compile from source if available
        if (isset($params['source_code'])) {
            return $this->compiler->compile($params['source_code'], $contractName);
        }

        if (isset($params['source_file'])) {
            return $this->compiler->compileFromFile($params['source_file'], $contractName);
        }

        throw new \InvalidArgumentException(
            "No artifacts found for contract '{$contractName}'. ".
            'Please provide source code, source file, or pre-compiled artifacts.'
        );
    }

    /**
     * Store deployment record in database
     *
     * @param  array<string, mixed>  $deployParams
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $artifacts
     */
    protected function storeDeploymentRecord(
        string $contractName,
        string $version,
        string $network,
        array $deployParams,
        array $result,
        array $artifacts
    ): BlockchainContract {
        $contract = BlockchainContract::create([
            'name' => $contractName,
            'version' => $version,
            'type' => 'evm',
            'address' => $result['address'] ?? null,
            'network' => $network,
            'deployer_address' => $deployParams['from'] ?? null,
            'abi' => json_encode($artifacts['abi']),
            'bytecode_hash' => hash('sha256', $artifacts['bytecode']),
            'constructor_params' => $deployParams['constructor_params'] ?? null,
            'deployed_at' => now(),
            'transaction_hash' => $result['transaction_hash'] ?? null,
            'gas_used' => $result['gas_used'] ?? null,
            'status' => 'deployed',
            'is_upgradeable' => false,
        ]);

        // Store transaction record if we have a transaction hash
        if (isset($result['transaction_hash'])) {
            BlockchainTransaction::create([
                'transaction_hash' => $result['transaction_hash'],
                'contract_id' => $contract->id,
                'method_name' => 'constructor',
                'parameters' => $deployParams['constructor_params'] ?? [],
                'gas_used' => $result['gas_used'] ?? null,
                'status' => 'success',
                'confirmed_at' => now(),
            ]);
        }

        return $contract;
    }
}

