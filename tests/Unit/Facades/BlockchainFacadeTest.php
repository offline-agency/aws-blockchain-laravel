<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Unit\Facades;

use AwsBlockchain\Laravel\Facades\Blockchain;
use AwsBlockchain\Laravel\Tests\TestCase;

class BlockchainFacadeTest extends TestCase
{
    public function test_facade_get_facade_root_returns_instance(): void
    {
        $reflection = new \ReflectionClass(Blockchain::class);
        $method = $reflection->getMethod('getFacadeRoot');
        $method->setAccessible(true);

        $instance = $method->invoke(null);

        // Should return the blockchain manager instance
        $this->assertNotNull($instance);
        $this->assertInstanceOf(\AwsBlockchain\Laravel\BlockchainManager::class, $instance);
    }

    public function test_facade_call_static_with_method_not_existing(): void
    {
        // This should throw BadMethodCallException when method doesn't exist
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('Call to undefined method');

        Blockchain::nonExistentMethod();
    }
}
