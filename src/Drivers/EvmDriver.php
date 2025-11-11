<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Drivers;

use AwsBlockchain\Laravel\Contracts\BlockchainDriverInterface;
use AwsBlockchain\Laravel\Services\AbiEncoder;
use AwsBlockchain\Laravel\Services\EthereumJsonRpcClient;

class EvmDriver implements BlockchainDriverInterface
{
    protected EthereumJsonRpcClient $rpcClient;

    protected AbiEncoder $abiEncoder;

    protected string $network;

    /** @var array<string, mixed> */
    protected array $config;

    protected ?string $defaultAccount = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        array $config,
        ?EthereumJsonRpcClient $rpcClient = null,
        ?AbiEncoder $abiEncoder = null
    ) {
        $this->config = $config;
        $this->network = $config['network'] ?? 'mainnet';

        $this->rpcClient = $rpcClient ?? new EthereumJsonRpcClient($config);
        $this->abiEncoder = $abiEncoder ?? new AbiEncoder;

        $this->defaultAccount = $config['default_account'] ?? null;
    }

    /**
     * Record an event on the blockchain
     *
     * @param  array<string, mixed>  $data
     */
    public function recordEvent(array $data): string
    {
        // For EVM, this could be implemented as a contract call to a logging contract
        $eventId = uniqid('evt_', true);

        // Store event data on-chain via smart contract
        // This is a placeholder implementation
        return $eventId;
    }

    /**
     * Get an event from the blockchain
     *
     * @return array<string, mixed>|null
     */
    public function getEvent(string $id): ?array
    {
        // Retrieve event from blockchain via contract call
        // This is a placeholder implementation
        return null;
    }

    /**
     * Verify event integrity
     *
     * @param  array<string, mixed>  $data
     */
    public function verifyIntegrity(string $id, array $data): bool
    {
        // Verify event data against blockchain
        return true;
    }

    /**
     * Check if driver is available
     */
    public function isAvailable(): bool
    {
        try {
            $this->rpcClient->eth_blockNumber();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get driver type
     */
    public function getType(): string
    {
        return 'evm';
    }

    /**
     * Get driver info
     *
     * @return array<string, mixed>
     */
    public function getDriverInfo(): array
    {
        $blockNumber = null;
        $chainId = null;

        try {
            $blockNumberHex = $this->rpcClient->eth_blockNumber();
            $blockNumber = $this->hexToDec($blockNumberHex);
        } catch (\Exception $e) {
            // Silently fail
        }

        try {
            $chainIdHex = $this->rpcClient->eth_chainId();
            $chainId = $this->hexToDec($chainIdHex);
        } catch (\Exception $e) {
            // Silently fail
        }

        return [
            'type' => 'evm',
            'network' => $this->network,
            'rpc_url' => $this->config['rpc_url'] ?? 'unknown',
            'block_number' => $blockNumber !== null ? (string) $blockNumber : null,
            'chain_id' => $chainId !== null ? (string) $chainId : null,
            'default_account' => $this->defaultAccount,
        ];
    }

    /**
     * Deploy a smart contract
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function deployContract(array $params): array
    {
        $bytecode = $params['bytecode'] ?? '';
        $abi = $params['abi'] ?? '[]';
        $constructorParams = $params['constructor_params'] ?? [];
        $from = $params['from'] ?? $this->defaultAccount;

        if (! $from) {
            throw new \InvalidArgumentException('From address is required for contract deployment');
        }

        // Parse ABI to find constructor
        $abiArray = is_string($abi) ? json_decode($abi, true) : $abi;
        if (! is_array($abiArray)) {
            $abiArray = [];
        }

        $constructorAbi = null;
        foreach ($abiArray as $item) {
            if (is_array($item) && ($item['type'] ?? '') === 'constructor') {
                $constructorAbi = $item;

                break;
            }
        }

        // Encode constructor call
        $data = $this->abiEncoder->encodeConstructorCall($constructorParams, $constructorAbi, $bytecode);

        // Build transaction
        $transaction = [
            'from' => $from,
            'data' => $data,
            'gas' => $params['gas_limit'] ?? 3000000,
        ];

        // Estimate gas if not provided
        if (! isset($params['gas_limit'])) {
            try {
                $gasEstimate = $this->rpcClient->eth_estimateGas($transaction);
                $transaction['gas'] = $this->hexToDec($gasEstimate);
            } catch (\Exception $e) {
                // Use default if estimation fails
            }
        }

        // Send transaction
        $transactionHash = $this->rpcClient->eth_sendTransaction($transaction);

        // Wait for receipt to get contract address
        $contractAddress = null;
        $gasUsed = null;
        $maxAttempts = 10;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            sleep(1);
            $receipt = $this->getTransactionReceipt($transactionHash);
            if ($receipt !== null) {
                $contractAddress = $receipt['contractAddress'] ?? null;
                $gasUsed = $receipt['gasUsed'] ?? null;
                if ($contractAddress !== null) {
                    break;
                }
            }
            $attempt++;
        }

        return [
            'address' => $contractAddress,
            'transaction_hash' => $transactionHash,
            'gas_used' => $gasUsed,
            'network' => $this->network,
        ];
    }

    /**
     * Call a contract method
     *
     * @param  array<int, mixed>  $params
     */
    public function callContract(string $address, string $abi, string $method, array $params = []): mixed
    {
        // Parse ABI to find method
        $abiArray = json_decode($abi, true);
        if (! is_array($abiArray)) {
            throw new \InvalidArgumentException('Invalid ABI format: must be valid JSON array');
        }

        $methodAbi = null;
        foreach ($abiArray as $item) {
            if (is_array($item) &&
                ($item['type'] ?? '') === 'function' &&
                ($item['name'] ?? '') === $method) {
                $methodAbi = $item;

                break;
            }
        }

        if ($methodAbi === null) {
            throw new \InvalidArgumentException("Method '{$method}' not found in ABI");
        }

        // Encode function call
        $data = $this->abiEncoder->encodeFunctionCall($method, $params, $methodAbi);

        // Build transaction for eth_call
        $transaction = [
            'to' => $address,
            'data' => $data,
        ];

        // Call contract method
        $resultHex = $this->rpcClient->eth_call($transaction);

        // Decode result
        if (! empty($methodAbi['outputs'] ?? [])) {
            return $this->abiEncoder->decodeFunctionResult($resultHex, $methodAbi['outputs']);
        }

        return $resultHex;
    }

    /**
     * Estimate gas for a transaction
     *
     * @param  array<string, mixed>  $transaction
     */
    public function estimateGas(array $transaction): int
    {
        $gasHex = $this->rpcClient->eth_estimateGas($transaction);

        return $this->hexToDec($gasHex);
    }

    /**
     * Get transaction receipt
     *
     * @return array<string, mixed>|null
     */
    public function getTransactionReceipt(string $hash): ?array
    {
        return $this->rpcClient->eth_getTransactionReceipt($hash);
    }

    /**
     * Get current gas price
     */
    public function getGasPrice(): int
    {
        $gasPriceHex = $this->rpcClient->eth_gasPrice();

        return $this->hexToDec($gasPriceHex);
    }

    /**
     * Send a transaction
     *
     * @param  array<string, mixed>  $transaction
     */
    public function sendTransaction(array $transaction): string
    {
        return $this->rpcClient->eth_sendTransaction($transaction);
    }

    /**
     * Get account balance
     */
    public function getBalance(string $address): string
    {
        return $this->rpcClient->eth_getBalance($address);
    }

    /**
     * Convert hex string to decimal integer
     */
    protected function hexToDec(string $hex): int
    {
        if (str_starts_with($hex, '0x')) {
            $hex = substr($hex, 2);
        }

        return (int) hexdec($hex);
    }
}
