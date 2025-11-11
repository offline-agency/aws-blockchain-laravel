<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EthereumJsonRpcClient
{
    protected string $rpcUrl;

    protected int $timeout;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        $this->rpcUrl = $config['rpc_url'] ?? 'http://localhost:8545';
        $this->timeout = $config['timeout'] ?? 30;
    }

    /**
     * Make a JSON-RPC call
     *
     * @param  array<int, mixed>  $params
     * @return mixed
     */
    protected function call(string $method, array $params = []): mixed
    {
        $payload = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => 1,
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->post($this->rpcUrl, $payload);

            if (! $response->successful()) {
                throw new \RuntimeException(
                    "RPC request failed with status {$response->status()}: {$response->body()}"
                );
            }

            $data = $response->json();

            if (isset($data['error'])) {
                $error = $data['error'];
                throw new \RuntimeException(
                    "RPC error [{$error['code']}]: {$error['message']}"
                );
            }

            return $data['result'] ?? null;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('Ethereum RPC request failed', [
                'method' => $method,
                'url' => $this->rpcUrl,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException("Failed to connect to Ethereum RPC: {$e->getMessage()}", 0, $e);
        }
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

    /**
     * Convert decimal integer to hex string
     */
    protected function decToHex(int $dec): string
    {
        return '0x'.dechex($dec);
    }

    /**
     * Get the latest block number
     */
    public function eth_blockNumber(): string
    {
        return $this->call('eth_blockNumber');
    }

    /**
     * Get the chain ID
     */
    public function eth_chainId(): string
    {
        return $this->call('eth_chainId');
    }

    /**
     * Estimate gas for a transaction
     *
     * @param  array<string, mixed>  $transaction
     */
    public function eth_estimateGas(array $transaction): string
    {
        // Convert gas limit to hex if it's numeric
        if (isset($transaction['gas']) && is_numeric($transaction['gas'])) {
            $transaction['gas'] = $this->decToHex((int) $transaction['gas']);
        }

        return $this->call('eth_estimateGas', [$transaction]);
    }

    /**
     * Get transaction receipt
     *
     * @return array<string, mixed>|null
     */
    public function eth_getTransactionReceipt(string $hash): ?array
    {
        $result = $this->call('eth_getTransactionReceipt', [$hash]);

        if ($result === null) {
            return null;
        }

        // Convert hex values to integers where appropriate
        return [
            'transactionHash' => $result['transactionHash'] ?? $hash,
            'blockNumber' => isset($result['blockNumber']) ? $this->hexToDec($result['blockNumber']) : null,
            'blockHash' => $result['blockHash'] ?? null,
            'contractAddress' => $result['contractAddress'] ?? null,
            'gasUsed' => isset($result['gasUsed']) ? $this->hexToDec($result['gasUsed']) : null,
            'status' => isset($result['status']) ? ($this->hexToDec($result['status']) === 1) : true,
            'from' => $result['from'] ?? null,
            'to' => $result['to'] ?? null,
            'logs' => $result['logs'] ?? [],
        ];
    }

    /**
     * Get current gas price
     */
    public function eth_gasPrice(): string
    {
        return $this->call('eth_gasPrice');
    }

    /**
     * Send a transaction
     *
     * @param  array<string, mixed>  $transaction
     */
    public function eth_sendTransaction(array $transaction): string
    {
        // Convert numeric values to hex
        if (isset($transaction['gas']) && is_numeric($transaction['gas'])) {
            $transaction['gas'] = $this->decToHex((int) $transaction['gas']);
        }

        if (isset($transaction['gasPrice']) && is_numeric($transaction['gasPrice'])) {
            $transaction['gasPrice'] = $this->decToHex((int) $transaction['gasPrice']);
        }

        if (isset($transaction['value']) && is_numeric($transaction['value'])) {
            $transaction['value'] = $this->decToHex((int) $transaction['value']);
        }

        return $this->call('eth_sendTransaction', [$transaction]);
    }

    /**
     * Get account balance
     */
    public function eth_getBalance(string $address, string $block = 'latest'): string
    {
        return $this->call('eth_getBalance', [$address, $block]);
    }

    /**
     * Call a contract method (read-only)
     *
     * @param  array<string, mixed>  $transaction
     */
    public function eth_call(array $transaction, string $block = 'latest'): string
    {
        return $this->call('eth_call', [$transaction, $block]);
    }
}

