<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Console\Commands;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use Illuminate\Console\Command;

class ListContractsCommand extends Command
{
    protected $signature = 'blockchain:list
                            {--network= : Filter by network}
                            {--status= : Filter by status}
                            {--json : Output in JSON format}';

    protected $description = 'List all deployed contracts';

    public function handle(): int
    {
        $query = BlockchainContract::query();

        if ($this->option('network')) {
            $query->where('network', $this->option('network'));
        }

        if ($this->option('status')) {
            $query->where('status', $this->option('status'));
        }

        $contracts = $query->orderBy('created_at', 'desc')->get();

        if ($contracts->isEmpty()) {
            $this->info('No contracts found');

            return Command::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line($contracts->toJson(JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $this->table(
            ['Name', 'Version', 'Address', 'Network', 'Status', 'Deployed'],
            $contracts->map(fn ($c) => [
                $c->name,
                $c->version,
                substr($c->address ?? '', 0, 10).'...',
                $c->network,
                $c->status,
                $c->deployed_at?->diffForHumans(),
            ])->toArray()
        );

        return Command::SUCCESS;
    }
}

