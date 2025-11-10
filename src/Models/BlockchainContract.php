<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlockchainContract extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'blockchain_contracts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'version',
        'type',
        'address',
        'network',
        'deployer_address',
        'abi',
        'bytecode_hash',
        'constructor_params',
        'deployed_at',
        'transaction_hash',
        'gas_used',
        'status',
        'is_upgradeable',
        'proxy_contract_id',
        'implementation_of',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'constructor_params' => 'array',
        'deployed_at' => 'datetime',
        'gas_used' => 'integer',
        'is_upgradeable' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the transactions associated with this contract.
     *
     * @return HasMany<BlockchainTransaction>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(BlockchainTransaction::class, 'contract_id');
    }

    /**
     * Get the proxy contract if this is an implementation.
     *
     * @return BelongsTo<BlockchainContract, BlockchainContract>
     */
    public function proxyContract(): BelongsTo
    {
        return $this->belongsTo(BlockchainContract::class, 'proxy_contract_id');
    }

    /**
     * Get the implementation contract if this is a proxy.
     *
     * @return BelongsTo<BlockchainContract, BlockchainContract>
     */
    public function implementationContract(): BelongsTo
    {
        return $this->belongsTo(BlockchainContract::class, 'implementation_of');
    }

    /**
     * Get the implementations of this proxy.
     *
     * @return HasMany<BlockchainContract>
     */
    public function implementations(): HasMany
    {
        return $this->hasMany(BlockchainContract::class, 'proxy_contract_id');
    }

    /**
     * Scope a query to only include deployed contracts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<BlockchainContract>  $query
     * @return \Illuminate\Database\Eloquent\Builder<BlockchainContract>
     */
    public function scopeDeployed($query)
    {
        return $query->where('status', 'deployed');
    }

    /**
     * Scope a query to only include active (not deprecated) contracts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<BlockchainContract>  $query
     * @return \Illuminate\Database\Eloquent\Builder<BlockchainContract>
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', ['deployed', 'upgraded']);
    }

    /**
     * Scope a query to filter by network.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<BlockchainContract>  $query
     * @param  string  $network
     * @return \Illuminate\Database\Eloquent\Builder<BlockchainContract>
     */
    public function scopeOnNetwork($query, string $network)
    {
        return $query->where('network', $network);
    }

    /**
     * Get the parsed ABI as an array.
     *
     * @return array<int, mixed>|null
     */
    public function getParsedAbi(): ?array
    {
        if (empty($this->abi)) {
            return null;
        }

        $decoded = json_decode($this->abi, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Set the ABI from an array.
     *
     * @param  array<int, mixed>  $abi
     */
    public function setAbiFromArray(array $abi): void
    {
        $this->abi = json_encode($abi);
    }

    /**
     * Check if the contract is upgradeable.
     */
    public function isUpgradeable(): bool
    {
        return (bool) $this->is_upgradeable;
    }

    /**
     * Mark the contract as deprecated.
     */
    public function deprecate(): bool
    {
        $this->status = 'deprecated';

        return $this->save();
    }

    /**
     * Get the full contract identifier (name@version).
     */
    public function getFullIdentifier(): string
    {
        return "{$this->name}@{$this->version}";
    }
}

