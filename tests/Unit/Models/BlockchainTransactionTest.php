<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Unit\Models;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Models\BlockchainTransaction;
use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BlockchainTransactionTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_blockchain_transaction(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        $transaction = BlockchainTransaction::create([
            'transaction_hash' => '0xabcd1234',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('blockchain_transactions', [
            'transaction_hash' => '0xabcd1234',
            'method_name' => 'transfer',
        ]);
    }

    public function test_can_mark_transaction_as_successful(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        $transaction = BlockchainTransaction::create([
            'transaction_hash' => '0xabcd1234',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'pending',
        ]);

        $transaction->markAsSuccessful();

        $this->assertEquals('success', $transaction->fresh()->status);
        $this->assertNotNull($transaction->fresh()->confirmed_at);
    }

    public function test_can_mark_transaction_as_failed(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        $transaction = BlockchainTransaction::create([
            'transaction_hash' => '0xabcd1234',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'pending',
        ]);

        $transaction->markAsFailed('Gas limit exceeded');

        $this->assertEquals('failed', $transaction->fresh()->status);
        $this->assertEquals('Gas limit exceeded', $transaction->fresh()->error_message);
    }

    public function test_can_calculate_total_cost(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        $transaction = BlockchainTransaction::create([
            'transaction_hash' => '0xabcd1234',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'gas_used' => 21000,
            'gas_price' => 1000000000,
            'status' => 'success',
        ]);

        $totalCost = $transaction->getTotalCost();

        $this->assertEquals(21000 * 1000000000, $totalCost);
    }

    public function test_total_cost_returns_null_when_gas_data_missing(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        $transaction = BlockchainTransaction::create([
            'transaction_hash' => '0xabcd1234',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'pending',
        ]);

        $this->assertNull($transaction->getTotalCost());
    }

    public function test_can_check_if_transaction_is_confirmed(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        $transaction = BlockchainTransaction::create([
            'transaction_hash' => '0xabcd1234',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'success',
            'confirmed_at' => now(),
        ]);

        $this->assertTrue($transaction->isConfirmed());
    }

    public function test_can_check_if_transaction_is_pending(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        $transaction = BlockchainTransaction::create([
            'transaction_hash' => '0xabcd1234',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'pending',
        ]);

        $this->assertTrue($transaction->isPending());
    }

    public function test_can_check_if_transaction_is_successful(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        $transaction = BlockchainTransaction::create([
            'transaction_hash' => '0xabcd1234',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'success',
        ]);

        $this->assertTrue($transaction->isSuccessful());
    }

    public function test_can_check_if_transaction_has_failed(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        $transaction = BlockchainTransaction::create([
            'transaction_hash' => '0xabcd1234',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'failed',
        ]);

        $this->assertTrue($transaction->hasFailed());
    }
}
