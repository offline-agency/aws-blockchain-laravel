<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Unit\Models;

use AwsBlockchain\Laravel\Models\BlockchainContract;
use AwsBlockchain\Laravel\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BlockchainContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_blockchain_contract(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'address' => '0x1234567890123456789012345678901234567890',
            'network' => 'local',
            'status' => 'deployed',
        ]);

        $this->assertDatabaseHas('blockchain_contracts', [
            'name' => 'TestContract',
            'address' => '0x1234567890123456789012345678901234567890',
        ]);
    }

    public function test_can_get_full_identifier(): void
    {
        $contract = new BlockchainContract([
            'name' => 'TestContract',
            'version' => '2.0.0',
        ]);

        $this->assertEquals('TestContract@2.0.0', $contract->getFullIdentifier());
    }

    public function test_can_check_if_upgradeable(): void
    {
        $contract = new BlockchainContract(['is_upgradeable' => true]);
        $this->assertTrue($contract->isUpgradeable());

        $contract = new BlockchainContract(['is_upgradeable' => false]);
        $this->assertFalse($contract->isUpgradeable());
    }

    public function test_can_deprecate_contract(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
        ]);

        $contract->deprecate();

        $this->assertEquals('deprecated', $contract->fresh()->status);
    }

    public function test_can_parse_abi(): void
    {
        $abi = [
            ['type' => 'function', 'name' => 'test'],
        ];

        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'abi' => json_encode($abi),
        ]);

        $parsedAbi = $contract->getParsedAbi();

        $this->assertIsArray($parsedAbi);
        $this->assertEquals($abi, $parsedAbi);
    }
}
