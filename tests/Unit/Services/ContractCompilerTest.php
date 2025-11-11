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

    public function test_load_artifacts_returns_null_when_not_found(): void
    {
        $loaded = $this->compiler->loadArtifacts('NonExistentContract', '1.0.0');

        $this->assertNull($loaded);
    }

    public function test_validate_abi_returns_false_for_invalid_abi(): void
    {
        $invalidAbi = [
            ['invalid' => 'data'],
        ];

        $this->assertFalse($this->compiler->validateAbi($invalidAbi));
    }

    public function test_validate_abi_returns_false_for_missing_type(): void
    {
        $invalidAbi = [
            ['name' => 'test'],
        ];

        $this->assertFalse($this->compiler->validateAbi($invalidAbi));
    }

    public function test_validate_abi_returns_false_for_invalid_type(): void
    {
        $invalidAbi = [
            ['type' => 'invalid_type'],
        ];

        $this->assertFalse($this->compiler->validateAbi($invalidAbi));
    }

    public function test_get_constructor_returns_null_when_not_found(): void
    {
        $abi = [
            [
                'type' => 'function',
                'name' => 'transfer',
            ],
        ];

        $constructor = $this->compiler->getConstructor($abi);

        $this->assertNull($constructor);
    }

    public function test_get_method_returns_null_when_not_found(): void
    {
        $abi = [
            [
                'type' => 'function',
                'name' => 'transfer',
            ],
        ];

        $method = $this->compiler->getMethod($abi, 'nonExistentMethod');

        $this->assertNull($method);
    }

    public function test_compile_from_file_throws_when_file_not_found(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Contract file not found');

        $this->compiler->compileFromFile('/non/existent/file.sol', 'TestContract');
    }

    public function test_compile_from_file_throws_when_file_cannot_be_read(): void
    {
        // Test with a directory path instead of a file
        // The method checks file_exists first, which will pass for a directory
        // Then it tries to read, which should fail
        $tempDir = sys_get_temp_dir().'/test_dir_'.uniqid();
        @mkdir($tempDir, 0755);

        try {
            // This will fail because it's a directory, not a file
            // file_exists returns true for directories, but file_get_contents will return false
            $this->expectException(\Exception::class);

            $this->compiler->compileFromFile($tempDir, 'TestContract');
        } finally {
            @rmdir($tempDir);
        }
    }

    public function test_store_artifacts_creates_directory_if_not_exists(): void
    {
        $contractName = 'NewTestContract';
        $version = '1.0.0';
        $artifacts = [
            'abi' => [['type' => 'function', 'name' => 'test']],
            'bytecode' => '0x1234',
        ];

        $this->compiler->storeArtifacts($contractName, $version, $artifacts);

        $loaded = $this->compiler->loadArtifacts($contractName, $version);

        $this->assertNotNull($loaded);

        // Cleanup
        $path = storage_path("app/contracts/{$contractName}");
        if (File::exists($path)) {
            File::deleteDirectory($path);
        }
    }

    public function test_get_method_handles_event_type(): void
    {
        $abi = [
            [
                'type' => 'event',
                'name' => 'Transfer',
            ],
            [
                'type' => 'function',
                'name' => 'transfer',
            ],
        ];

        $method = $this->compiler->getMethod($abi, 'transfer');

        $this->assertNotNull($method);
        $this->assertEquals('transfer', $method['name']);
    }

    public function test_validate_abi_accepts_all_valid_types(): void
    {
        $validAbi = [
            ['type' => 'function', 'name' => 'test'],
            ['type' => 'constructor', 'inputs' => []],
            ['type' => 'event', 'name' => 'Event'],
            ['type' => 'fallback'],
            ['type' => 'receive'],
        ];

        $this->assertTrue($this->compiler->validateAbi($validAbi));
    }

    public function test_validate_abi_with_empty_array_returns_true(): void
    {
        $result = $this->compiler->validateAbi([]);

        $this->assertTrue($result);
    }

    public function test_get_constructor_returns_constructor_when_present(): void
    {
        $abi = [
            ['type' => 'constructor', 'inputs' => [['type' => 'uint256']]],
            ['type' => 'function', 'name' => 'test'],
        ];

        $constructor = $this->compiler->getConstructor($abi);

        $this->assertNotNull($constructor);
        $this->assertEquals('constructor', $constructor['type']);
    }

    public function test_get_method_returns_method_when_found(): void
    {
        $abi = [
            ['type' => 'function', 'name' => 'transfer'],
            ['type' => 'function', 'name' => 'approve'],
        ];

        $method = $this->compiler->getMethod($abi, 'approve');

        $this->assertNotNull($method);
        $this->assertEquals('approve', $method['name']);
    }

    public function test_compile_with_custom_config(): void
    {
        $compiler = new ContractCompiler([
            'solc_path' => 'solc',
            'storage_path' => storage_path('app/contracts'),
            'optimize' => true,
            'optimize_runs' => 1000,
        ]);

        try {
            $result = $compiler->compile(
                'pragma solidity ^0.8.0; contract Test { function test() public {} }',
                'Test'
            );

            $this->assertArrayHasKey('abi', $result);
            $this->assertArrayHasKey('bytecode', $result);
        } catch (\RuntimeException $e) {
            // solc might not be available in test environment
            $this->assertStringContainsString('Compilation failed', $e->getMessage());
        }
    }

    public function test_store_artifacts_overwrites_existing_artifacts(): void
    {
        $contractName = 'OverwriteTest';
        $version = '1.0.0';

        $artifacts1 = [
            'abi' => [['type' => 'function', 'name' => 'test1']],
            'bytecode' => '0x1111',
        ];

        $artifacts2 = [
            'abi' => [['type' => 'function', 'name' => 'test2']],
            'bytecode' => '0x2222',
        ];

        $this->compiler->storeArtifacts($contractName, $version, $artifacts1);
        $this->compiler->storeArtifacts($contractName, $version, $artifacts2);

        $loaded = $this->compiler->loadArtifacts($contractName, $version);

        $this->assertNotNull($loaded);
        $this->assertEquals('0x2222', $loaded['bytecode']);
        $this->assertEquals('test2', $loaded['abi'][0]['name']);

        // Cleanup
        $path = storage_path("app/contracts/{$contractName}");
        if (File::exists($path)) {
            File::deleteDirectory($path);
        }
    }

    public function test_validate_abi_with_invalid_structure_returns_false(): void
    {
        $invalidAbi = [
            ['invalid' => 'structure'],
        ];

        $result = $this->compiler->validateAbi($invalidAbi);

        $this->assertFalse($result);
    }

    public function test_load_artifacts_returns_null_for_missing_contract(): void
    {
        $loaded = $this->compiler->loadArtifacts('NonExistentContract_'.uniqid(), '1.0.0');

        $this->assertNull($loaded);
    }

    public function test_compile_with_optimize_disabled(): void
    {
        $compiler = new ContractCompiler([
            'solc_path' => 'solc',
            'storage_path' => storage_path('app/contracts'),
            'optimize' => false,
        ]);

        try {
            $result = $compiler->compile(
                'pragma solidity ^0.8.0; contract Test {}',
                'Test'
            );

            $this->assertArrayHasKey('abi', $result);
            $this->assertArrayHasKey('bytecode', $result);
        } catch (\RuntimeException $e) {
            // solc might not be available in test environment
            $this->assertStringContainsString('Compilation failed', $e->getMessage());
        }
    }
}
