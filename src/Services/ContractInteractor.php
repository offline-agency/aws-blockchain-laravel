<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Services;

use AwsBlockchain\Laravel\Contracts\BlockchainDriverInterface;
use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Models\BlockchainTransaction;
use Illuminate\Support\Facades\Log;

class ContractInteractor
{
    protected BlockchainDriverInterface $driver;

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(BlockchainDriverInterface $driver, array $config = [])
    {
        $this->driver = $driver;
        $this->config = $config;
    }

    /**
     * Call a contract method
     *
     * @param  array<int, mixed>  $params
     * @param  array<string, mixed>  $options
     * @return mixed
     */
    public function call(
        BlockchainContract $contract,
        string $methodName,
        array $params = [],
        array $options = []
    ): mixed {
        $abi = $contract->getParsedAbi();

        if ($abi === null) {
            throw new \RuntimeException("Contract ABI not available for {$contract->name}");
        }

        // Find method in ABI
        $method = $this->findMethodInAbi($abi, $methodName);

        if ($method === null) {
            throw new \InvalidArgumentException("Method '{$methodName}' not found in contract ABI");
        }

        // Validate parameters
        $this->validateParameters($method, $params);

        $isStateChanging = ! in_array($method['stateMutability'] ?? 'nonpayable', ['pure', 'view']);

        try {
            if ($isStateChanging) {
                return $this->sendTransaction($contract, $methodName, $params, $options, $method);
            } else {
                return $this->callView($contract, $methodName, $params);
            }
        } catch (\Exception $e) {
            Log::error('Contract interaction failed', [
                'contract' => $contract->name,
                'method' => $methodName,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Call a view/pure function (no transaction)
     *
     * @param  array<int, mixed>  $params
     * @return mixed
     */
    protected function callView(
        BlockchainContract $contract,
        string $methodName,
        array $params
    ): mixed {
        $result = $this->driver->callContract(
            $contract->address,
            $contract->abi ?? '[]',
            $methodName,
            $params
        );

        Log::info('Contract view called', [
            'contract' => $contract->name,
            'method' => $methodName,
            'result' => $result,
        ]);

        return $result;
    }

    /**
     * Send a state-changing transaction
     *
     * @param  array<int, mixed>  $params
     * @param  array<string, mixed>  $options
     * @param  array<string, mixed>  $method
     * @return mixed
     */
    protected function sendTransaction(
        BlockchainContract $contract,
        string $methodName,
        array $params,
        array $options,
        array $method
    ): mixed {
        // Estimate gas if not provided
        if (! isset($options['gas_limit'])) {
            $options['gas_limit'] = $this->estimateGas($contract, $methodName, $params, $options);
        }

        // Build transaction
        $transaction = [
            'to' => $contract->address,
            'from' => $options['from'] ?? null,
            'data' => $this->encodeMethodCall($methodName, $params, $method),
            'gas' => $options['gas_limit'],
        ];

        // Send transaction
        $transactionHash = $this->driver->sendTransaction($transaction);

        // Store transaction record
        $txRecord = $this->storeTransactionRecord(
            $contract,
            $transactionHash,
            $methodName,
            $params,
            $options
        );

        Log::info('Transaction sent', [
            'contract' => $contract->name,
            'method' => $methodName,
            'hash' => $transactionHash,
        ]);

        // Wait for confirmation if requested
        if ($options['wait'] ?? false) {
            $receipt = $this->waitForConfirmation($transactionHash, $options['timeout'] ?? 300);
            
            if ($receipt) {
                $txRecord->update([
                    'status' => $receipt['status'] ? 'success' : 'failed',
                    'gas_used' => $receipt['gasUsed'] ?? null,
                    'block_number' => $receipt['blockNumber'] ?? null,
                    'confirmed_at' => now(),
                ]);
            }

            return $receipt;
        }

        return [
            'transaction_hash' => $transactionHash,
            'transaction_record' => $txRecord,
        ];
    }

    /**
     * Estimate gas for a method call
     *
     * @param  array<int, mixed>  $params
     * @param  array<string, mixed>  $options
     */
    public function estimateGas(
        BlockchainContract $contract,
        string $methodName,
        array $params,
        array $options
    ): int {
        try {
            $abi = $contract->getParsedAbi();
            $method = $this->findMethodInAbi($abi ?? [], $methodName);

            $transaction = [
                'to' => $contract->address,
                'from' => $options['from'] ?? '0x0000000000000000000000000000000000000000',
                'data' => $this->encodeMethodCall($methodName, $params, $method ?? []),
            ];

            $estimate = $this->driver->estimateGas($transaction);
            
            // Add safety margin
            $multiplier = $this->config['gas']['price_multiplier'] ?? 1.1;

            return (int) ($estimate * $multiplier);
        } catch (\Exception $e) {
            Log::warning('Gas estimation failed for method call', [
                'contract' => $contract->name,
                'method' => $methodName,
                'error' => $e->getMessage(),
            ]);

            return $this->config['gas']['default_limit'] ?? 100000;
        }
    }

    /**
     * Wait for transaction confirmation
     *
     * @return array<string, mixed>|null
     */
    public function waitForConfirmation(string $transactionHash, int $timeoutSeconds = 300): ?array
    {
        $startTime = time();

        while (time() - $startTime < $timeoutSeconds) {
            $receipt = $this->driver->getTransactionReceipt($transactionHash);

            if ($receipt !== null && isset($receipt['blockNumber'])) {
                return $receipt;
            }

            sleep(2);
        }

        return null;
    }

    /**
     * Parse parameters from string input
     *
     * @return array<int, mixed>
     */
    public function parseParameters(string $paramsString): array
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
     * Format return value for output
     *
     * @param  array<string, mixed>  $options
     */
    public function formatReturnValue(mixed $value, array $options = []): string
    {
        if ($options['json'] ?? false) {
            return json_encode($value, JSON_PRETTY_PRINT);
        }

        if (is_array($value)) {
            return print_r($value, true);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * Find method in ABI
     *
     * @param  array<int, mixed>  $abi
     * @return array<string, mixed>|null
     */
    protected function findMethodInAbi(array $abi, string $methodName): ?array
    {
        foreach ($abi as $item) {
            if (is_array($item) && 
                ($item['type'] ?? '') === 'function' && 
                ($item['name'] ?? '') === $methodName) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Validate parameters against method signature
     *
     * @param  array<string, mixed>  $method
     * @param  array<int, mixed>  $params
     */
    protected function validateParameters(array $method, array $params): void
    {
        $inputs = $method['inputs'] ?? [];
        $expectedCount = count($inputs);
        $actualCount = count($params);

        if ($actualCount !== $expectedCount) {
            throw new \InvalidArgumentException(
                "Method expects {$expectedCount} parameters, but {$actualCount} provided"
            );
        }
    }

    /**
     * Encode method call data
     *
     * @param  array<int, mixed>  $params
     * @param  array<string, mixed>  $method
     */
    protected function encodeMethodCall(string $methodName, array $params, array $method): string
    {
        // This is a simplified version
        // Real implementation would use proper ABI encoding
        $signature = $this->getMethodSignature($method);
        $selector = substr(hash('sha256', $signature), 0, 8);

        return '0x'.$selector;
    }

    /**
     * Get method signature
     *
     * @param  array<string, mixed>  $method
     */
    protected function getMethodSignature(array $method): string
    {
        $inputs = $method['inputs'] ?? [];
        $types = array_map(fn ($input) => $input['type'] ?? 'unknown', $inputs);

        return $method['name'].'('.implode(',', $types).')';
    }

    /**
     * Store transaction record
     *
     * @param  array<int, mixed>  $params
     * @param  array<string, mixed>  $options
     */
    protected function storeTransactionRecord(
        BlockchainContract $contract,
        string $transactionHash,
        string $methodName,
        array $params,
        array $options
    ): BlockchainTransaction {
        return BlockchainTransaction::create([
            'transaction_hash' => $transactionHash,
            'contract_id' => $contract->id,
            'method_name' => $methodName,
            'parameters' => $params,
            'from_address' => $options['from'] ?? null,
            'to_address' => $contract->address,
            'status' => 'pending',
        ]);
    }
}

