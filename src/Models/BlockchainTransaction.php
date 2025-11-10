<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $transaction_hash
 * @property int $contract_id
 * @property string $method_name
 * @property array|null $parameters
 * @property array|null $return_values
 * @property int|null $gas_used
 * @property int|null $gas_price
 * @property string|null $from_address
 * @property string|null $to_address
 * @property string $status
 * @property string|null $error_message
 * @property int|null $rollback_id
 * @property int|null $block_number
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class BlockchainTransaction extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'blockchain_transactions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_hash',
        'contract_id',
        'method_name',
        'parameters',
        'return_values',
        'gas_used',
        'gas_price',
        'from_address',
        'to_address',
        'status',
        'error_message',
        'rollback_id',
        'block_number',
        'confirmed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'parameters' => 'array',
        'return_values' => 'array',
        'gas_used' => 'integer',
        'gas_price' => 'integer',
        'block_number' => 'integer',
        'confirmed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the contract associated with this transaction.
     *
     * @return BelongsTo<BlockchainContract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(BlockchainContract::class, 'contract_id');
    }

    /**
     * Get the rollback transaction if this is a rollback.
     *
     * @return BelongsTo<BlockchainTransaction, $this>
     */
    public function rollbackTransaction(): BelongsTo
    {
        return $this->belongsTo(BlockchainTransaction::class, 'rollback_id');
    }

    /**
     * Scope a query to only include successful transactions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<BlockchainTransaction>  $query
     * @return \Illuminate\Database\Eloquent\Builder<BlockchainTransaction>
     */
    public function scopeSuccessful($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope a query to only include failed transactions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<BlockchainTransaction>  $query
     * @return \Illuminate\Database\Eloquent\Builder<BlockchainTransaction>
     */
    public function scopeFailed($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->whereIn('status', ['failed', 'reverted']);
    }

    /**
     * Scope a query to only include pending transactions.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<BlockchainTransaction>  $query
     * @return \Illuminate\Database\Eloquent\Builder<BlockchainTransaction>
     */
    public function scopePending($query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Mark the transaction as successful.
     */
    public function markAsSuccessful(): bool
    {
        $this->status = 'success';
        $this->confirmed_at = now();

        return $this->save();
    }

    /**
     * Mark the transaction as failed.
     */
    public function markAsFailed(?string $errorMessage = null): bool
    {
        $this->status = 'failed';
        $this->error_message = $errorMessage;
        $this->confirmed_at = now();

        return $this->save();
    }

    /**
     * Calculate the total transaction cost.
     */
    public function getTotalCost(): ?int
    {
        if ($this->gas_used === null || $this->gas_price === null) {
            return null;
        }

        return $this->gas_used * $this->gas_price;
    }

    /**
     * Check if the transaction is confirmed.
     */
    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    /**
     * Check if the transaction is pending.
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if the transaction was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if the transaction failed.
     */
    public function hasFailed(): bool
    {
        return in_array($this->status, ['failed', 'reverted']);
    }
}
