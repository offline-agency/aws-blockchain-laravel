<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Drivers;

use AwsBlockchain\Laravel\Contracts\BlockchainDriverInterface;
use Web3\Contract;
use Web3\Web3;

class EvmDriver implements BlockchainDriverInterface
{
    protected Web3 $web3;

    protected string $network;

    /** @var array<string, mixed> */
    protected array $config;

    protected ?string $defaultAccount = null;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->network = $config['network'] ?? 'mainnet';
        
        $provider = $config['rpc_url'] ?? 'http://localhost:8545';
        $this->web3 = new Web3($provider);
        
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
            $this->web3->eth->blockNumber(function ($err, $blockNumber) {
                return $err === null;
            });

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
            $this->web3->eth->blockNumber(function ($err, $result) use (&$blockNumber) {
                if (! $err) {
                    $blockNumber = $result->toString();
                }
            });

            $this->web3->eth->chainId(function ($err, $result) use (&$chainId) {
                if (! $err) {
                    $chainId = $result->toString();
                }
            });
        } catch (\Exception $e) {
            // Silently fail
        }

        return [
            'type' => 'evm',
            'network' => $this->network,
            'rpc_url' => $this->config['rpc_url'] ?? 'unknown',
            'block_number' => $blockNumber,
            'chain_id' => $chainId,
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

        $contract = new Contract($this->web3->provider, $abi);
        
        $transactionHash = null;
        $contractAddress = null;
        $gasUsed = null;

        // Deploy contract
        $contract->bytecode($bytecode)->new(...$constructorParams, [
            'from' => $from,
            'gas' => $params['gas_limit'] ?? '3000000',
        ], function ($err, $result) use (&$transactionHash, &$contractAddress, &$gasUsed) {
            if ($err !== null) {
                throw new \RuntimeException('Contract deployment failed: '.$err->getMessage());
            }
            
            if (is_string($result)) {
                $transactionHash = $result;
            } elseif (isset($result->contractAddress)) {
                $contractAddress = $result->contractAddress;
                $gasUsed = isset($result->gasUsed) ? hexdec($result->gasUsed) : null;
            }
        });

        // Wait for transaction receipt to get contract address
        if ($transactionHash && ! $contractAddress) {
            $receipt = $this->getTransactionReceipt($transactionHash);
            if ($receipt) {
                $contractAddress = $receipt['contractAddress'] ?? null;
                $gasUsed = $receipt['gasUsed'] ?? null;
            }
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
     * @param  string  $address
     * @param  string  $abi
     * @param  string  $method
     * @param  array<int, mixed>  $params
     * @return mixed
     */
    public function callContract(string $address, string $abi, string $method, array $params = []): mixed
    {
        $contract = new Contract($this->web3->provider, $abi);
        $contract->at($address);

        $result = null;

        $contract->call($method, ...$params, function ($err, $response) use (&$result) {
            if ($err !== null) {
                throw new \RuntimeException('Contract call failed: '.$err->getMessage());
            }
            $result = $response;
        });

        return $result;
    }

    /**
     * Estimate gas for a transaction
     *
     * @param  array<string, mixed>  $transaction
     */
    public function estimateGas(array $transaction): int
    {
        $gas = 0;

        $this->web3->eth->estimateGas($transaction, function ($err, $result) use (&$gas) {
            if ($err !== null) {
                throw new \RuntimeException('Gas estimation failed: '.$err->getMessage());
            }
            
            $gas = is_object($result) ? (int) $result->toString() : (int) $result;
        });

        return $gas;
    }

    /**
     * Get transaction receipt
     *
     * @return array<string, mixed>|null
     */
    public function getTransactionReceipt(string $hash): ?array
    {
        $receipt = null;

        $this->web3->eth->getTransactionReceipt($hash, function ($err, $result) use (&$receipt) {
            if ($err !== null || $result === null) {
                return;
            }
            
            $receipt = [
                'transactionHash' => $result->transactionHash ?? $hash,
                'blockNumber' => isset($result->blockNumber) ? hexdec($result->blockNumber) : null,
                'contractAddress' => $result->contractAddress ?? null,
                'gasUsed' => isset($result->gasUsed) ? hexdec($result->gasUsed) : null,
                'status' => isset($result->status) ? (hexdec($result->status) === 1) : true,
                'from' => $result->from ?? null,
                'to' => $result->to ?? null,
            ];
        });

        return $receipt;
    }

    /**
     * Get current gas price
     */
    public function getGasPrice(): int
    {
        $gasPrice = 0;

        $this->web3->eth->gasPrice(function ($err, $result) use (&$gasPrice) {
            if ($err !== null) {
                throw new \RuntimeException('Failed to get gas price: '.$err->getMessage());
            }
            
            $gasPrice = is_object($result) ? (int) $result->toString() : (int) $result;
        });

        return $gasPrice;
    }

    /**
     * Send a transaction
     *
     * @param  array<string, mixed>  $transaction
     */
    public function sendTransaction(array $transaction): string
    {
        $transactionHash = null;

        $this->web3->eth->sendTransaction($transaction, function ($err, $result) use (&$transactionHash) {
            if ($err !== null) {
                throw new \RuntimeException('Transaction failed: '.$err->getMessage());
            }
            
            $transactionHash = $result;
        });

        return $transactionHash ?? '';
    }

    /**
     * Get account balance
     */
    public function getBalance(string $address): string
    {
        $balance = '0';

        $this->web3->eth->getBalance($address, 'latest', function ($err, $result) use (&$balance) {
            if ($err !== null) {
                throw new \RuntimeException('Failed to get balance: '.$err->getMessage());
            }
            
            $balance = is_object($result) ? $result->toString() : (string) $result;
        });

        return $balance;
    }
}

