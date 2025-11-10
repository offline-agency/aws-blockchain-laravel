<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Services;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Models\BlockchainTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContractUpgrader
{
    protected ContractDeployer $deployer;

    protected ContractInteractor $interactor;

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        ContractDeployer $deployer,
        ContractInteractor $interactor,
        array $config = []
    ) {
        $this->deployer = $deployer;
        $this->interactor = $interactor;
        $this->config = $config;
    }

    /**
     * Upgrade a contract
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function upgrade(BlockchainContract $oldContract, string $newVersion, array $options = []): array
    {
        if (! $oldContract->isUpgradeable()) {
            throw new \RuntimeException(
                "Contract '{$oldContract->name}' is not upgradeable. ".
                'Deploy as a new contract or use proxy pattern.'
            );
        }

        return DB::transaction(function () use ($oldContract, $newVersion, $options) {
            // Deploy new implementation
            $newImplementation = $this->deployNewImplementation($oldContract, $newVersion, $options);

            // Update proxy to point to new implementation
            if ($options['preserve_state'] ?? true) {
                $this->updateProxy($oldContract, $newImplementation, $options);
            }

            // Run migration if specified
            if (isset($options['migration'])) {
                $this->runMigration($oldContract, $newImplementation, $options['migration']);
            }

            // Mark old contract as upgraded
            $oldContract->update(['status' => 'upgraded']);

            Log::info('Contract upgraded successfully', [
                'old_contract' => $oldContract->name,
                'old_version' => $oldContract->version,
                'new_version' => $newVersion,
                'new_address' => $newImplementation->address,
            ]);

            return [
                'old_contract' => $oldContract,
                'new_contract' => $newImplementation,
                'proxy_updated' => $options['preserve_state'] ?? true,
            ];
        });
    }

    /**
     * Rollback to a previous version
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function rollback(BlockchainContract $currentContract, ?string $targetVersion = null, array $options = []): array
    {
        if (! $currentContract->isUpgradeable()) {
            throw new \RuntimeException(
                "Contract '{$currentContract->name}' is not upgradeable and cannot be rolled back"
            );
        }

        // Find previous version
        $previousContract = $this->findPreviousVersion($currentContract, $targetVersion);

        if (! $previousContract) {
            throw new \RuntimeException('No previous version found for rollback');
        }

        return DB::transaction(function () use ($currentContract, $previousContract, $options) {
            // Update proxy to point back to old implementation
            $this->updateProxy($currentContract, $previousContract, $options);

            // Store rollback transaction
            $this->storeRollbackRecord($currentContract, $previousContract);

            // Update contract statuses
            $currentContract->update(['status' => 'deprecated']);
            $previousContract->update(['status' => 'deployed']);

            Log::info('Contract rolled back', [
                'contract' => $currentContract->name,
                'from_version' => $currentContract->version,
                'to_version' => $previousContract->version,
            ]);

            return [
                'current_contract' => $currentContract,
                'restored_contract' => $previousContract,
                'rollback_successful' => true,
            ];
        });
    }

    /**
     * Create upgradeable proxy contract
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function createUpgradeableContract(array $options): array
    {
        // Deploy implementation contract
        $implementation = $this->deployer->deploy($options);

        // Deploy proxy contract
        $proxyOptions = [
            'name' => $options['name'].'_Proxy',
            'version' => $options['version'] ?? '1.0.0',
            'network' => $options['network'] ?? $this->config['default_network'] ?? 'local',
            'constructor_params' => [
                $implementation['contract']->address, // Implementation address
            ],
            // In real implementation, would use a standard proxy contract
            'bytecode' => $this->getProxyBytecode(),
            'abi' => $this->getProxyAbi(),
        ];

        $proxy = $this->deployer->deploy($proxyOptions);

        // Update implementation to link to proxy
        $implementation['contract']->update([
            'proxy_contract_id' => $proxy['contract']->id,
            'is_upgradeable' => true,
        ]);

        $proxy['contract']->update([
            'implementation_of' => $implementation['contract']->id,
            'is_upgradeable' => true,
        ]);

        return [
            'proxy' => $proxy['contract'],
            'implementation' => $implementation['contract'],
        ];
    }

    /**
     * Deploy new implementation
     *
     * @param  array<string, mixed>  $options
     */
    protected function deployNewImplementation(
        BlockchainContract $oldContract,
        string $newVersion,
        array $options
    ): BlockchainContract {
        $deployOptions = [
            'name' => $oldContract->name,
            'version' => $newVersion,
            'network' => $oldContract->network,
            'from' => $options['from'] ?? null,
        ];

        // Add source code or artifacts if provided
        if (isset($options['source_code'])) {
            $deployOptions['source_code'] = $options['source_code'];
        } elseif (isset($options['source_file'])) {
            $deployOptions['source_file'] = $options['source_file'];
        }

        $result = $this->deployer->deploy($deployOptions);
        $newContract = $result['contract'];

        // Link to proxy if old contract had one
        if ($oldContract->proxy_contract_id) {
            $newContract->update([
                'proxy_contract_id' => $oldContract->proxy_contract_id,
                'is_upgradeable' => true,
            ]);
        }

        return $newContract;
    }

    /**
     * Update proxy to point to new implementation
     *
     * @param  array<string, mixed>  $options
     */
    protected function updateProxy(
        BlockchainContract $oldContract,
        BlockchainContract $newContract,
        array $options
    ): void {
        if (! $oldContract->proxy_contract_id) {
            throw new \RuntimeException('No proxy contract found');
        }

        $proxy = BlockchainContract::find($oldContract->proxy_contract_id);

        if (! $proxy) {
            throw new \RuntimeException('Proxy contract not found in database');
        }

        // Call upgrade function on proxy
        try {
            $this->interactor->call(
                $proxy,
                'upgradeTo',
                [$newContract->address],
                ['from' => $options['from'] ?? null, 'wait' => true]
            );

            Log::info('Proxy updated to new implementation', [
                'proxy' => $proxy->address,
                'new_implementation' => $newContract->address,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update proxy', [
                'error' => $e->getMessage(),
                'proxy' => $proxy->address,
            ]);
            throw $e;
        }
    }

    /**
     * Run data migration
     */
    protected function runMigration(
        BlockchainContract $oldContract,
        BlockchainContract $newContract,
        string $migrationName
    ): void {
        // This would run a custom migration script
        // Placeholder implementation
        Log::info('Running migration', [
            'migration' => $migrationName,
            'old_contract' => $oldContract->address,
            'new_contract' => $newContract->address,
        ]);
    }

    /**
     * Find previous version of contract
     */
    protected function findPreviousVersion(BlockchainContract $currentContract, ?string $targetVersion): ?BlockchainContract
    {
        $query = BlockchainContract::where('name', $currentContract->name)
            ->where('network', $currentContract->network)
            ->where('id', '!=', $currentContract->id);

        if ($targetVersion) {
            $query->where('version', $targetVersion);
        } else {
            $query->where('created_at', '<', $currentContract->created_at)
                ->orderBy('created_at', 'desc');
        }

        return $query->first();
    }

    /**
     * Store rollback record
     */
    protected function storeRollbackRecord(
        BlockchainContract $currentContract,
        BlockchainContract $previousContract
    ): void {
        // Find the upgrade transaction
        $upgradeTransaction = BlockchainTransaction::where('contract_id', $currentContract->id)
            ->where('method_name', 'upgradeTo')
            ->latest()
            ->first();

        if ($upgradeTransaction) {
            // Create rollback transaction record
            BlockchainTransaction::create([
                'transaction_hash' => '0x'.bin2hex(random_bytes(32)),
                'contract_id' => $previousContract->id,
                'method_name' => 'rollback',
                'parameters' => ['target_version' => $previousContract->version],
                'rollback_id' => $upgradeTransaction->id,
                'status' => 'success',
                'confirmed_at' => now(),
            ]);
        }
    }

    /**
     * Get proxy bytecode (simplified)
     */
    protected function getProxyBytecode(): string
    {
        // In real implementation, this would return actual proxy contract bytecode
        return '0x608060405234801561001057600080fd5b50';
    }

    /**
     * Get proxy ABI (simplified)
     *
     * @return array<int, mixed>
     */
    protected function getProxyAbi(): array
    {
        return [
            [
                'type' => 'function',
                'name' => 'upgradeTo',
                'inputs' => [
                    ['name' => 'newImplementation', 'type' => 'address'],
                ],
                'outputs' => [],
                'stateMutability' => 'nonpayable',
            ],
            [
                'type' => 'function',
                'name' => 'implementation',
                'inputs' => [],
                'outputs' => [
                    ['name' => '', 'type' => 'address'],
                ],
                'stateMutability' => 'view',
            ],
        ];
    }
}

