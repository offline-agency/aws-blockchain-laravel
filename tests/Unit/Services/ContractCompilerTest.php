<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Unit\Services;

use AwsBlockchain\Laravel\Services\ContractCompiler;
use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\File;

class ContractCompilerTest extends TestCase
{
    protected ContractCompiler $compiler;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->compiler = new ContractCompiler([
            'solc_path' => 'solc',
            'storage_path' => storage_path('app/contracts'),
            'optimize' => true,
            'optimize_runs' => 200,
        ]);
    }

    public function test_can_validate_abi(): void
    {
        $validAbi = [
            [
                'type' => 'function',
                'name' => 'transfer',
                'inputs' => [],
            ],
        ];

        $this->assertTrue($this->compiler->validateAbi($validAbi));
    }

    public function test_can_get_constructor_from_abi(): void
    {
        $abi = [
            [
                'type' => 'constructor',
                'inputs' => [
                    ['name' => 'initialSupply', 'type' => 'uint256'],
                ],
            ],
            [
                'type' => 'function',
                'name' => 'transfer',
            ],
        ];

        $constructor = $this->compiler->getConstructor($abi);

        $this->assertNotNull($constructor);
        $this->assertEquals('constructor', $constructor['type']);
    }

    public function test_can_get_method_from_abi(): void
    {
        $abi = [
            [
                'type' => 'function',
                'name' => 'transfer',
                'inputs' => [],
            ],
            [
                'type' => 'function',
                'name' => 'balanceOf',
                'inputs' => [],
            ],
        ];

        $method = $this->compiler->getMethod($abi, 'transfer');

        $this->assertNotNull($method);
        $this->assertEquals('transfer', $method['name']);
    }

    public function test_can_store_and_load_artifacts(): void
    {
        $contractName = 'TestContract';
        $version = '1.0.0';
        $artifacts = [
            'abi' => [['type' => 'function', 'name' => 'test']],
            'bytecode' => '0x1234',
        ];

        $this->compiler->storeArtifacts($contractName, $version, $artifacts);

        $loaded = $this->compiler->loadArtifacts($contractName, $version);

        $this->assertNotNull($loaded);
        $this->assertEquals($artifacts['abi'], $loaded['abi']);
        $this->assertEquals($artifacts['bytecode'], $loaded['bytecode']);

        // Cleanup
        $path = storage_path("app/contracts/{$contractName}/{$version}");
        if (File::exists($path)) {
            File::deleteDirectory(dirname($path));
        }
    }
}

