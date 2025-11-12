<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Console\Commands;

use AwsBlockchain\Laravel\Services\ContractCompiler;
use AwsBlockchain\Laravel\Services\ContractDeployer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

class WatchContractsCommand extends Command
{
    protected $signature = 'blockchain:watch {--network=local : Network to deploy to}
                            {--interval=1000 : Poll interval in milliseconds}';

    protected $description = 'Watch contract files for changes and auto-redeploy (hot reload)';

    /** @var array<string, string> */
    protected array $fileHashes = [];

    public function handle(): int
    {
        $config = config('aws-blockchain-laravel.contracts', []);

        if (! ($config['hot_reload']['enabled'] ?? false)) {
            $this->error('Hot reload is not enabled in configuration');

            return Command::FAILURE;
        }

        $watchPaths = $config['hot_reload']['watch_paths'] ?? [];
        $interval = (int) $this->option('interval');

        $this->info('Watching for contract changes...');
        $this->info('Press Ctrl+C to stop');
        $this->newLine();

        try {
            while (true) {
                $this->checkForChanges($watchPaths, $config);
                usleep($interval * 1000);
            }
        } catch (\Exception $e) {
            // Handle interruption gracefully
            return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }

    /**
     * Check for file changes
     *
     * @param  array<int, string>  $paths
     * @param  array<string, mixed>  $config
     */
    protected function checkForChanges(array $paths, array $config): void
    {
        foreach ($paths as $path) {
            if (! file_exists($path)) {
                continue;
            }

            $files = glob($path.'/*.sol') ?: [];

            foreach ($files as $file) {
                $currentHash = md5_file($file);
                if ($currentHash === false || ! is_string($currentHash)) {
                    continue;
                }
                $previousHash = $this->fileHashes[$file] ?? null;

                if ($previousHash !== null && $currentHash !== $previousHash) {
                    $this->info('Change detected in '.basename($file));
                    $this->redeployContract($file, $config);
                }

                $this->fileHashes[$file] = $currentHash;
            }
        }
    }

    /**
     * Redeploy contract after change
     *
     * @param  array<string, mixed>  $config
     */
    protected function redeployContract(string $file, array $config): void
    {
        try {
            $contractName = pathinfo($file, PATHINFO_FILENAME);

            $blockchain = App::make('blockchain');
            $driver = $blockchain->driver();
            $compiler = new ContractCompiler($config['compiler'] ?? []);
            $deployer = new ContractDeployer($driver, $compiler, $config);

            $this->line('  Redeploying...');

            $networkOption = $this->option('network');
            $network = is_string($networkOption) ? $networkOption : 'local';

            $result = $deployer->deploy([
                'name' => $contractName,
                'version' => 'dev-'.time(),
                'source_file' => $file,
                'network' => $network,
            ]);

            $this->info('  ✓ Deployed to '.$result['contract']->address);
        } catch (\Exception $e) {
            $this->error('  ✗ Deployment failed: '.$e->getMessage());
        }
    }
}
