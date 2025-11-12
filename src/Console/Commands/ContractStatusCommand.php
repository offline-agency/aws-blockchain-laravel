<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Console\Commands;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use Illuminate\Console\Command;

class ContractStatusCommand extends Command
{
    protected $signature = 'blockchain:status {contract : Contract name or address}
                            {--network= : Network filter}
                            {--json : Output in JSON format}';

    protected $description = 'Show detailed information about a contract';

    public function handle(): int
    {
        $identifier = $this->argument('contract');

        if (! is_string($identifier)) {
            $this->error('Contract identifier must be a string');

            return Command::FAILURE;
        }

        $contract = $this->findContract($identifier);

        if (! $contract) {
            $this->error("Contract '{$identifier}' not found");

            return Command::FAILURE;
        }

        if ($this->option('json')) {
            $jsonOutput = $contract->toJson(JSON_PRETTY_PRINT);
            if ($jsonOutput !== false) {
                $this->line($jsonOutput);
            }

            return Command::SUCCESS;
        }

        $this->info("Contract: {$contract->name}");
        $this->newLine();

        $this->table(
            ['Property', 'Value'],
            [
                ['Name', $contract->name],
                ['Version', $contract->version],
                ['Type', $contract->type],
                ['Address', $contract->address],
                ['Network', $contract->network],
                ['Status', $contract->status],
                ['Deployed At', $contract->deployed_at],
                ['Transaction Hash', $contract->transaction_hash],
                ['Gas Used', number_format($contract->gas_used ?? 0)],
                ['Upgradeable', $contract->is_upgradeable ? 'Yes' : 'No'],
            ]
        );

        // Show transactions
        $transactions = $contract->transactions()->latest()->limit(5)->get();
        if ($transactions->isNotEmpty()) {
            $this->newLine();
            $this->info('Recent Transactions:');
            $this->table(
                ['Method', 'Hash', 'Status', 'Date'],
                $transactions->map(fn ($t) => [
                    $t->method_name,
                    substr($t->transaction_hash, 0, 10).'...',
                    $t->status,
                    $t->created_at->diffForHumans(),
                ])->toArray()
            );
        }

        return Command::SUCCESS;
    }

    protected function findContract(string $identifier): ?BlockchainContract
    {
        $query = BlockchainContract::where('address', $identifier)
            ->orWhere('name', $identifier);

        if ($this->option('network')) {
            $query->where('network', $this->option('network'));
        }

        return $query->first();
    }
}
