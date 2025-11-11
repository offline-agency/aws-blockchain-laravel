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

    public function test_get_parsed_abi_returns_null_when_abi_is_empty(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'abi' => null,
        ]);

        $this->assertNull($contract->getParsedAbi());
    }

    public function test_get_parsed_abi_returns_null_for_invalid_json(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'abi' => 'invalid json',
        ]);

        $parsedAbi = $contract->getParsedAbi();

        $this->assertNull($parsedAbi);
    }

    public function test_can_set_abi_from_array(): void
    {
        $abi = [
            ['type' => 'function', 'name' => 'test'],
        ];

        $contract = new BlockchainContract([
            'name' => 'TestContract',
            'version' => '1.0.0',
        ]);

        $contract->setAbiFromArray($abi);

        $this->assertNotNull($contract->abi);
        $this->assertEquals($abi, json_decode($contract->abi, true));
    }

    public function test_has_transactions_relationship(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $contract->transactions());
    }

    public function test_has_proxy_contract_relationship(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $contract->proxyContract());
    }

    public function test_has_implementation_contract_relationship(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $contract->implementationContract());
    }

    public function test_has_implementations_relationship(): void
    {
        $contract = BlockchainContract::create([
            'name' => 'TestContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $contract->implementations());
    }

    public function test_scope_deployed_filters_correctly(): void
    {
        BlockchainContract::create([
            'name' => 'DeployedContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
        ]);

        BlockchainContract::create([
            'name' => 'FailedContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'failed',
        ]);

        $deployed = BlockchainContract::deployed()->get();

        $this->assertCount(1, $deployed);
        $this->assertEquals('DeployedContract', $deployed->first()->name);
        $this->assertNotEquals('FailedContract', $deployed->first()->name);
    }

    public function test_scope_active_filters_correctly(): void
    {
        BlockchainContract::create([
            'name' => 'DeployedContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deployed',
        ]);

        BlockchainContract::create([
            'name' => 'UpgradedContract',
            'version' => '2.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'upgraded',
        ]);

        BlockchainContract::create([
            'name' => 'DeprecatedContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
            'status' => 'deprecated',
        ]);

        $active = BlockchainContract::active()->get();

        $this->assertCount(2, $active);
        $this->assertTrue($active->pluck('status')->contains('deployed'));
        $this->assertTrue($active->pluck('status')->contains('upgraded'));
    }

    public function test_scope_on_network_filters_correctly(): void
    {
        BlockchainContract::create([
            'name' => 'LocalContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'local',
        ]);

        BlockchainContract::create([
            'name' => 'TestnetContract',
            'version' => '1.0.0',
            'type' => 'evm',
            'network' => 'testnet',
        ]);

        $local = BlockchainContract::onNetwork('local')->get();

        $this->assertCount(1, $local);
        $this->assertEquals('LocalContract', $local->first()->name);
    }
}
