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

    public function test_has_contract_relationship(): void
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
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $transaction->contract());
    }

    public function test_has_rollback_transaction_relationship(): void
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
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $transaction->rollbackTransaction());
    }

    public function test_scope_successful_filters_correctly(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        BlockchainTransaction::create([
            'transaction_hash' => '0xsuccess',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'success',
        ]);

        BlockchainTransaction::create([
            'transaction_hash' => '0xfailed',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'failed',
        ]);

        $successful = BlockchainTransaction::successful()->get();

        $this->assertCount(1, $successful);
        $this->assertEquals('0xsuccess', $successful->first()->transaction_hash);
    }

    public function test_scope_failed_filters_correctly(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        BlockchainTransaction::create([
            'transaction_hash' => '0xfailed1',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'failed',
        ]);

        BlockchainTransaction::create([
            'transaction_hash' => '0xreverted',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'reverted',
        ]);

        BlockchainTransaction::create([
            'transaction_hash' => '0xsuccess',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'success',
        ]);

        $failed = BlockchainTransaction::failed()->get();

        $this->assertCount(2, $failed);
        $this->assertTrue($failed->pluck('status')->contains('failed'));
        $this->assertTrue($failed->pluck('status')->contains('reverted'));
    }

    public function test_scope_pending_filters_correctly(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        BlockchainTransaction::create([
            'transaction_hash' => '0xpending',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'pending',
        ]);

        BlockchainTransaction::create([
            'transaction_hash' => '0xsuccess',
            'contract_id' => $contract->id,
            'method_name' => 'transfer',
            'status' => 'success',
        ]);

        $pending = BlockchainTransaction::pending()->get();

        $this->assertCount(1, $pending);
        $this->assertEquals('0xpending', $pending->first()->transaction_hash);
    }

    public function test_is_confirmed_returns_false_when_not_confirmed(): void
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
            'confirmed_at' => null,
        ]);

        $this->assertFalse($transaction->isConfirmed());
    }

    public function test_has_failed_returns_true_for_reverted(): void
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
            'status' => 'reverted',
        ]);

        $this->assertTrue($transaction->hasFailed());
    }
}
