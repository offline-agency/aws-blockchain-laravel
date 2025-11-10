<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ContractCompiler
{
    protected string $solcPath;

    protected string $storagePath;

    protected bool $optimize;

    protected int $optimizeRuns;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config = [])
    {
        $this->solcPath = $config['solc_path'] ?? 'solc';
        $this->storagePath = $config['storage_path'] ?? storage_path('app/contracts');
        $this->optimize = $config['optimize'] ?? true;
        $this->optimizeRuns = $config['optimize_runs'] ?? 200;
    }

    /**
     * Compile a Solidity contract from source
     *
     * @return array<string, mixed> Compilation result with ABI and bytecode
     */
    public function compile(string $sourceCode, string $contractName): array
    {
        // Create temporary file for source code
        $tempFile = tempnam(sys_get_temp_dir(), 'solidity_');
        file_put_contents($tempFile, $sourceCode);

        try {
            // Build solc command
            $command = $this->buildCompileCommand($tempFile, $contractName);

            // Execute compilation
            $output = [];
            $returnCode = 0;
            exec($command.' 2>&1', $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \RuntimeException('Compilation failed: '.implode("\n", $output));
            }

            // Parse compilation output
            $result = $this->parseCompilationOutput(implode("\n", $output));

            // Store artifacts
            $this->storeArtifacts($contractName, '1.0.0', $result);

            return $result;
        } finally {
            // Clean up temporary file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Compile from a file path
     *
     * @return array<string, mixed>
     */
    public function compileFromFile(string $filePath, string $contractName): array
    {
        if (! file_exists($filePath)) {
            throw new \InvalidArgumentException("Contract file not found: {$filePath}");
        }

        $sourceCode = file_get_contents($filePath);

        if ($sourceCode === false) {
            throw new \RuntimeException("Failed to read contract file: {$filePath}");
        }

        return $this->compile($sourceCode, $contractName);
    }

    /**
     * Load pre-compiled artifacts
     *
     * @return array<string, mixed>|null
     */
    public function loadArtifacts(string $contractName, string $version = '1.0.0'): ?array
    {
        $artifactPath = $this->getArtifactPath($contractName, $version);

        if (! File::exists($artifactPath)) {
            return null;
        }

        $content = File::get($artifactPath);
        $artifacts = json_decode($content, true);

        return is_array($artifacts) ? $artifacts : null;
    }

    /**
     * Store compilation artifacts
     *
     * @param  array<string, mixed>  $artifacts
     */
    public function storeArtifacts(string $contractName, string $version, array $artifacts): void
    {
        $artifactPath = $this->getArtifactPath($contractName, $version);
        $directory = dirname($artifactPath);

        if (! File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $metadata = [
            'name' => $contractName,
            'version' => $version,
            'compiled_at' => now()->toIso8601String(),
            'compiler_version' => $this->getCompilerVersion(),
            'optimization_enabled' => $this->optimize,
            'optimization_runs' => $this->optimizeRuns,
            'abi' => $artifacts['abi'] ?? [],
            'bytecode' => $artifacts['bytecode'] ?? '',
            'deployed_bytecode' => $artifacts['deployed_bytecode'] ?? '',
            'source_hash' => $artifacts['source_hash'] ?? null,
        ];

        File::put($artifactPath, json_encode($metadata, JSON_PRETTY_PRINT));
    }

    /**
     * Validate ABI
     *
     * @param  array<int, mixed>  $abi
     */
    public function validateAbi(array $abi): bool
    {
        foreach ($abi as $item) {
            if (! is_array($item) || ! isset($item['type'])) {
                return false;
            }

            $validTypes = ['function', 'constructor', 'event', 'fallback', 'receive'];
            if (! in_array($item['type'], $validTypes)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Parse constructor from ABI
     *
     * @param  array<int, mixed>  $abi
     * @return array<string, mixed>|null
     */
    public function getConstructor(array $abi): ?array
    {
        foreach ($abi as $item) {
            if (is_array($item) && ($item['type'] ?? '') === 'constructor') {
                return $item;
            }
        }

        return null;
    }

    /**
     * Get method from ABI by name
     *
     * @param  array<int, mixed>  $abi
     * @return array<string, mixed>|null
     */
    public function getMethod(array $abi, string $methodName): ?array
    {
        foreach ($abi as $item) {
            if (is_array($item) && 
                ($item['type'] ?? '') === 'function' && 
                ($item['name'] ?? '') === $methodName) {
                return $item;
            }
        }

        return null;
    }

    /**
     * Build compile command
     */
    protected function buildCompileCommand(string $sourceFile, string $contractName): string
    {
        $parts = [
            escapeshellcmd($this->solcPath),
            '--combined-json abi,bin,bin-runtime',
        ];

        if ($this->optimize) {
            $parts[] = '--optimize';
            $parts[] = '--optimize-runs '.$this->optimizeRuns;
        }

        $parts[] = escapeshellarg($sourceFile);

        return implode(' ', $parts);
    }

    /**
     * Parse compilation output
     *
     * @return array<string, mixed>
     */
    protected function parseCompilationOutput(string $output): array
    {
        // Try to find JSON in output
        $jsonStart = strpos($output, '{');
        if ($jsonStart === false) {
            throw new \RuntimeException('No JSON output found from compiler');
        }

        $jsonOutput = substr($output, $jsonStart);
        $data = json_decode($jsonOutput, true);

        if (! is_array($data) || ! isset($data['contracts'])) {
            throw new \RuntimeException('Invalid compiler output format');
        }

        // Extract first contract from output
        $contracts = $data['contracts'];
        $firstContract = reset($contracts);

        if (! is_array($firstContract)) {
            throw new \RuntimeException('No contracts found in compilation output');
        }

        return [
            'abi' => json_decode($firstContract['abi'] ?? '[]', true),
            'bytecode' => '0x'.($firstContract['bin'] ?? ''),
            'deployed_bytecode' => '0x'.($firstContract['bin-runtime'] ?? ''),
        ];
    }

    /**
     * Get artifact file path
     */
    protected function getArtifactPath(string $contractName, string $version): string
    {
        return $this->storagePath.'/'.$contractName.'/'.$version.'/artifact.json';
    }

    /**
     * Get compiler version
     */
    protected function getCompilerVersion(): string
    {
        $output = [];
        exec(escapeshellcmd($this->solcPath).' --version 2>&1', $output);

        foreach ($output as $line) {
            if (str_contains($line, 'Version:')) {
                return trim(str_replace('Version:', '', $line));
            }
        }

        return 'unknown';
    }
}

