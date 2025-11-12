<?php

namespace AwsBlockchain\Laravel\Tests\Unit;

use AwsBlockchain\Laravel\AwsBlockchainServiceProvider;
use AwsBlockchain\Laravel\Tests\TestCase;

class SimplifiedServiceProviderTest extends TestCase
{
    public function test_service_provider_can_be_instantiated()
    {
        $provider = new AwsBlockchainServiceProvider($this->app);

        $this->assertInstanceOf(AwsBlockchainServiceProvider::class, $provider);
    }

    public function test_register_method_registers_services()
    {
        $provider = new AwsBlockchainServiceProvider($this->app);
        $provider->register();

        // Test that services are bound after register
        $this->assertTrue($this->app->bound('blockchain'));
        $this->assertTrue($this->app->bound('blockchain.public'));
        $this->assertTrue($this->app->bound('blockchain.private'));
    }

    public function test_register_method_binds_singletons()
    {
        $provider = new AwsBlockchainServiceProvider($this->app);
        $provider->register();

        // Test that services are singletons
        $blockchain1 = $this->app->make('blockchain');
        $blockchain2 = $this->app->make('blockchain');

        $this->assertSame($blockchain1, $blockchain2);
    }

    public function test_provides_method_returns_correct_services()
    {
        $provider = new AwsBlockchainServiceProvider($this->app);
        $provides = $provider->provides();

        $expectedServices = [
            'blockchain',
            'blockchain.public',
            'blockchain.private',
        ];

        $this->assertEquals($expectedServices, $provides);
    }

    public function test_provides_method_returns_array()
    {
        $provider = new AwsBlockchainServiceProvider($this->app);
        $provides = $provider->provides();

        $this->assertIsArray($provides);
        $this->assertNotEmpty($provides);
    }

    public function test_service_provider_implements_correct_interface()
    {
        $provider = new AwsBlockchainServiceProvider($this->app);

        $this->assertInstanceOf(\Illuminate\Support\ServiceProvider::class, $provider);
    }

    public function test_register_method_creates_correct_instances()
    {
        $provider = new AwsBlockchainServiceProvider($this->app);
        $provider->register();

        $blockchain = $this->app->make('blockchain');
        $publicDriver = $this->app->make('blockchain.public');
        $privateDriver = $this->app->make('blockchain.private');

        $this->assertInstanceOf(\AwsBlockchain\Laravel\BlockchainManager::class, $blockchain);
        $this->assertNotNull($publicDriver);
        $this->assertNotNull($privateDriver);
    }

    public function test_boot_method_can_be_called()
    {
        $provider = new AwsBlockchainServiceProvider($this->app);

        // Should not throw exception
        $provider->boot();

        // Verify provider is in valid state after boot
        $this->assertInstanceOf(AwsBlockchainServiceProvider::class, $provider);
        $this->assertIsArray($provider->provides());
    }

    public function test_register_method_can_be_called_multiple_times()
    {
        $provider = new AwsBlockchainServiceProvider($this->app);

        // Should not throw exception when called multiple times
        $provider->register();
        $provider->register();
        $provider->register();

        // Verify services are registered
        $this->assertTrue($this->app->bound('blockchain'));
        $this->assertTrue($this->app->bound('blockchain.public'));
        $this->assertTrue($this->app->bound('blockchain.private'));
    }
}
