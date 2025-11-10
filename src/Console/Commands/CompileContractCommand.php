<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Console\Commands;

use AwsBlockchain\Laravel\Services\ContractCompiler;
use Illuminate\Console\Command;

class CompileContractCommand extends Command
{
    protected $signature = 'blockchain:compile {source : Path to contract source file}
                            {name : Contract name}
                            {--contract-version=1.0.0 : Contract version}
                            {--json : Output in JSON format}';

    protected $description = 'Compile a Solidity contract without deploying';

    public function handle(): int
    {
        $source = $this->argument('source');
        $name = $this->argument('name');
        $version = $this->option('contract-version');

        if (! is_string($source)) {
            $this->error('Source path must be a string');

            return Command::FAILURE;
        }

        if (! is_string($name)) {
            $this->error('Contract name must be a string');

            return Command::FAILURE;
        }

        if (! is_string($version)) {
            $this->error('Version must be a string');

            return Command::FAILURE;
        }

        try {
            $config = config('aws-blockchain-laravel.contracts.compiler', []);
            $compiler = new ContractCompiler($config);

            $this->info("Compiling contract '{$name}'...");
            
            $result = $compiler->compileFromFile($source, $name);
            
            // Store artifacts
            $compiler->storeArtifacts($name, $version, $result);

            if ($this->option('json')) {
                $jsonOutput = json_encode([
                    'success' => true,
                    'contract' => $name,
                    'version' => $version,
                    'abi_functions' => count($result['abi']),
                ], JSON_PRETTY_PRINT);
                if ($jsonOutput !== false) {
                    $this->line($jsonOutput);
                }
            } else {
                $this->info('✓ Contract compiled successfully!');
                $this->line("  ABI functions: ".count($result['abi']));
                $this->line("  Bytecode size: ".strlen($result['bytecode'])." bytes");
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Compilation failed: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}

