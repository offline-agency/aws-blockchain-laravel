<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Drivers;

use Aws\QLDB\QLDBClient;
use Aws\QLDBSession\QLDBSessionClient;
use AwsBlockchain\Laravel\Contracts\BlockchainDriverInterface;
use Illuminate\Support\Facades\Log;

class QldbDriver implements BlockchainDriverInterface
{
    /** @var QLDBClient|object */
    protected $client;

    /** @var QLDBSessionClient|object */
    protected $sessionClient;

    protected string $ledgerName;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config, ?QLDBClient $client = null, ?QLDBSessionClient $sessionClient = null)
    {
        $this->ledgerName = $config['ledger_name'] ?? 'supply-chain-ledger';

        if ($client !== null) {
            $this->client = $client;
        } elseif (class_exists(QLDBClient::class)) {
            $this->client = new QLDBClient([
                'version' => 'latest',
                'region' => $config['region'] ?? 'us-east-1',
                'credentials' => [
                    'key' => $config['access_key_id'],
                    'secret' => $config['secret_access_key'],
                ],
            ]);
        } else {
            // In test environments where AWS SDK is not available, create a mock-like object
            /** @phpstan-ignore-next-line */
            $this->client = new class {
                public function describeLedger(array $args): void
                {
                    throw new \RuntimeException('QLDB client not available in test environment');
                }
            };
        }

        if ($sessionClient !== null) {
            $this->sessionClient = $sessionClient;
        } elseif (class_exists(QLDBSessionClient::class)) {
            $this->sessionClient = new QLDBSessionClient([
                'version' => 'latest',
                'region' => $config['region'] ?? 'us-east-1',
                'credentials' => [
                    'key' => $config['access_key_id'],
                    'secret' => $config['secret_access_key'],
                ],
            ]);
        } else {
            // In test environments where AWS SDK is not available, create a mock-like object
            /** @phpstan-ignore-next-line */
            $this->sessionClient = new class {
                public function sendCommand(array $args): void
                {
                    throw new \RuntimeException('QLDB session client not available in test environment');
                }
            };
        }
    }

    /**
     * Record an event on the blockchain
     */
    public function recordEvent(array $data): string
    {
        try {
            $documentId = 'doc_'.uniqid().'_'.time();

            $result = $this->sessionClient->sendCommand([
                'SessionToken' => $this->getSessionToken(),
                'ExecuteStatement' => [
                    'TransactionId' => $this->startTransaction(),
                    'Statement' => "INSERT INTO SupplyChainEvents VALUE {'id': ?, 'data': ?, 'timestamp': ?, 'hash': ?}",
                    'Parameters' => [
                        ['StringValue' => $documentId],
                        ['StringValue' => json_encode($data)],
                        ['StringValue' => now()->toIso8601String()],
                        ['StringValue' => $this->generateHash($data)],
                    ],
                ],
            ]);

            Log::info('Event recorded on QLDB', [
                'document_id' => $documentId,
                'data' => $data,
            ]);

            return $documentId;
        } catch (\Exception $e) {
            Log::error('Failed to record event on QLDB', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    /**
     * Get an event from the blockchain
     */
    public function getEvent(string $id): ?array
    {
        try {
            $result = $this->sessionClient->sendCommand([
                'SessionToken' => $this->getSessionToken(),
                'ExecuteStatement' => [
                    'TransactionId' => $this->startTransaction(),
                    'Statement' => 'SELECT * FROM SupplyChainEvents WHERE id = ?',
                    'Parameters' => [
                        ['StringValue' => $id],
                    ],
                ],
            ]);

            $items = $result['ExecuteStatement']['FirstPage']['Values'] ?? [];
            if (empty($items)) {
                return null;
            }

            return json_decode($items[0]['Document']['data'], true);
        } catch (\Exception $e) {
            Log::error('Failed to get event from QLDB', [
                'error' => $e->getMessage(),
                'event_id' => $id,
            ]);

            return null;
        }
    }

    /**
     * Verify event integrity
     */
    public function verifyIntegrity(string $id, array $data): bool
    {
        try {
            $result = $this->sessionClient->sendCommand([
                'SessionToken' => $this->getSessionToken(),
                'ExecuteStatement' => [
                    'TransactionId' => $this->startTransaction(),
                    'Statement' => 'SELECT hash FROM SupplyChainEvents WHERE id = ?',
                    'Parameters' => [
                        ['StringValue' => $id],
                    ],
                ],
            ]);

            $items = $result['ExecuteStatement']['FirstPage']['Values'] ?? [];
            if (empty($items)) {
                return false;
            }

            $storedHash = $items[0]['Document']['hash'];
            $expectedHash = $this->generateHash($data);

            return $storedHash === $expectedHash;
        } catch (\Exception $e) {
            Log::error('Failed to verify event integrity on QLDB', [
                'error' => $e->getMessage(),
                'event_id' => $id,
            ]);

            return false;
        }
    }

    /**
     * Check if driver is available
     */
    public function isAvailable(): bool
    {
        try {
            // Try to call describeLedger - this works for both real clients and mocks
            // Mocks will handle the call through Mockery's expectations
            // Real clients will either succeed or throw an exception
            $this->client->describeLedger([
                'Name' => $this->ledgerName,
            ]);
            return true;
        } catch (\Exception $e) {
            Log::warning('QLDB not available', [
                'error' => $e->getMessage(),
            ]);

            return false;
        } catch (\Error $e) {
            // Handle fatal errors (like method doesn't exist)
            Log::warning('QLDB not available', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get driver type
     */
    public function getType(): string
    {
        return 'qldb';
    }

    /**
     * Get driver info
     */
    public function getDriverInfo(): array
    {
        return [
            'type' => $this->getType(),
            'available' => $this->isAvailable(),
            'ledger_name' => $this->ledgerName,
            'driver' => 'QldbDriver',
        ];
    }

    /**
     * Get session token for QLDB operations
     */
    protected function getSessionToken(): string
    {
        $result = $this->client->sendCommand([
            'StartSession' => [
                'LedgerName' => $this->ledgerName,
            ],
        ]);

        return $result['StartSession']['SessionToken'];
    }

    /**
     * Start a transaction
     */
    protected function startTransaction(): string
    {
        $result = $this->sessionClient->sendCommand([
            'SessionToken' => $this->getSessionToken(),
            'StartTransaction' => [],
        ]);

        return $result['StartTransaction']['TransactionId'];
    }

    /**
     * Generate hash for data integrity
     */
    /**
     * @param  array<string, mixed>  $data
     */
    protected function generateHash(array $data): string
    {
        return hash('sha256', json_encode($data).$this->ledgerName);
    }

    /**
     * Deploy a smart contract (not applicable for QLDB)
     *
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function deployContract(array $params): array
    {
        // QLDB doesn't support smart contracts
        // Return a placeholder response
        $transactionId = 'contract_deploy_'.uniqid().'_'.time();

        Log::warning('Contract deployment not supported in QLDB', [
            'params' => $params,
            'transaction_id' => $transactionId,
        ]);

        return [
            'address' => null,
            'transaction_hash' => $transactionId,
            'gas_used' => 0,
            'network' => $this->ledgerName,
            'status' => 'not_supported',
        ];
    }

    /**
     * Call a contract method (not applicable for QLDB)
     *
     * @param  array<int, mixed>  $params
     */
    public function callContract(string $address, string $abi, string $method, array $params = []): mixed
    {
        // QLDB doesn't support smart contracts
        Log::warning('Contract calls not supported in QLDB', [
            'address' => $address,
            'method' => $method,
        ]);

        throw new \RuntimeException('Contract calls are not supported in QLDB. QLDB is a ledger database, not an EVM-compatible blockchain.');
    }

    /**
     * Estimate gas for a transaction (not applicable for QLDB)
     *
     * @param  array<string, mixed>  $transaction
     */
    public function estimateGas(array $transaction): int
    {
        // QLDB doesn't use gas
        return 0;
    }

    /**
     * Get transaction receipt
     *
     * @return array<string, mixed>|null
     */
    public function getTransactionReceipt(string $hash): ?array
    {
        try {
            // Query QLDB for transaction history
            // Note: QLDB doesn't have a direct transaction receipt API
            // This is a simplified implementation
            $result = $this->sessionClient->sendCommand([
                'SessionToken' => $this->getSessionToken(),
                'ExecuteStatement' => [
                    'TransactionId' => $this->startTransaction(),
                    'Statement' => 'SELECT * FROM _ql_committed_transactions WHERE txId = ?',
                    'Parameters' => [
                        ['StringValue' => $hash],
                    ],
                ],
            ]);

            $items = $result['ExecuteStatement']['FirstPage']['Values'] ?? [];
            if (empty($items)) {
                return null;
            }

            return [
                'transactionHash' => $hash,
                'blockNumber' => null,
                'status' => true,
                'timestamp' => $items[0]['Document']['timestamp'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get transaction receipt from QLDB', [
                'error' => $e->getMessage(),
                'transaction_hash' => $hash,
            ]);

            return null;
        }
    }

    /**
     * Get current gas price (not applicable for QLDB)
     */
    public function getGasPrice(): int
    {
        // QLDB doesn't use gas
        return 0;
    }

    /**
     * Send a transaction
     *
     * @param  array<string, mixed>  $transaction
     */
    public function sendTransaction(array $transaction): string
    {
        try {
            // Map transaction to QLDB's recordEvent functionality
            $data = $transaction['data'] ?? $transaction;
            $documentId = $this->recordEvent($data);

            Log::info('Transaction sent to QLDB', [
                'document_id' => $documentId,
                'transaction' => $transaction,
            ]);

            return $documentId;
        } catch (\Exception $e) {
            Log::error('Failed to send transaction to QLDB', [
                'error' => $e->getMessage(),
                'transaction' => $transaction,
            ]);

            throw $e;
        }
    }

    /**
     * Get account balance (not applicable for QLDB)
     */
    public function getBalance(string $address): string
    {
        // QLDB doesn't have account balances like EVM blockchains
        Log::warning('Account balance not applicable for QLDB', [
            'address' => $address,
        ]);

        return '0';
    }
}
