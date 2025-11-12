<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Contracts;

interface BlockchainDriverInterface
{
    /**
     * Record an event on the blockchain
     *
     * @param  array<string, mixed>  $data
     */
    public function recordEvent(array $data): string;

    /**
     * Get an event from the blockchain
     *
     * @return array<string, mixed>|null
     */
    public function getEvent(string $id): ?array;

    /**
     * Verify event integrity
     *
     * @param  array<string, mixed>  $data
     */
    public function verifyIntegrity(string $id, array $data): bool;

    /**
     * Check if driver is available
     */
    public function isAvailable(): bool;

    /**
     * Get driver type
     */
    public function getType(): string;

    /**
     * Get driver info
     *
     * @return array<string, mixed>
     */
    public function getDriverInfo(): array;

    /**
     * Deploy a smart contract
     *
     * @param  array<string, mixed>  $params  Contract deployment parameters
     * @return array<string, mixed> Deployment result with address and transaction hash
     */
    public function deployContract(array $params): array;

    /**
     * Call a contract method
     *
     * @param  string  $address  Contract address
     * @param  string  $abi  Contract ABI JSON
     * @param  string  $method  Method name to call
     * @param  array<int, mixed>  $params  Method parameters
     * @return mixed Method return value
     */
    public function callContract(string $address, string $abi, string $method, array $params = []): mixed;

    /**
     * Estimate gas for a transaction
     *
     * @param  array<string, mixed>  $transaction  Transaction parameters
     * @return int Estimated gas units
     */
    public function estimateGas(array $transaction): int;

    /**
     * Get transaction receipt
     *
     * @param  string  $hash  Transaction hash
     * @return array<string, mixed>|null Transaction receipt or null if not found
     */
    public function getTransactionReceipt(string $hash): ?array;

    /**
     * Get current gas price
     *
     * @return int Gas price in wei
     */
    public function getGasPrice(): int;

    /**
     * Send a transaction
     *
     * @param  array<string, mixed>  $transaction  Transaction parameters
     * @return string Transaction hash
     */
    public function sendTransaction(array $transaction): string;

    /**
     * Get account balance
     *
     * @param  string  $address  Account address
     * @return string Balance in wei as string
     */
    public function getBalance(string $address): string;
}
