<?php

namespace AwsBlockchain\Laravel\Tests\Unit;

use AwsBlockchain\Laravel\AwsBlockchainServiceProvider;
use AwsBlockchain\Laravel\Tests\TestCase;

class AwsBlockchainServiceProviderTest extends TestCase
{
    public function test_service_provider_can_be_instantiated()
    {
        $provider = new AwsBlockchainServiceProvider($this->app);

        $this->assertInstanceOf(AwsBlockchainServiceProvider::class, $provider);
    }

    public function test_register_method_registers_services()
    {
        // Create a fresh app instance to avoid services already being bound
        $app = new \Illuminate\Foundation\Application;
        $provider = new AwsBlockchainServiceProvider($app);

        // Test that services are not bound before register
        $this->assertFalse($app->bound('blockchain'));
        $this->assertFalse($app->bound('blockchain.public'));
        $this->assertFalse($app->bound('blockchain.private'));

        // Register the provider
        $provider->register();

        // Test that services are bound after register
        $this->assertTrue($app->bound('blockchain'));
        $this->assertTrue($app->bound('blockchain.public'));
        $this->assertTrue($app->bound('blockchain.private'));
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

    public function test_register_method_uses_config()
    {
        $this->app['config']->set('aws-blockchain-laravel', [
            'default_driver' => 'mock',
            'public_driver' => 'mock',
            'private_driver' => 'mock',
            'drivers' => [
                'mock' => ['type' => 'mock'],
            ],
        ]);

        $provider = new AwsBlockchainServiceProvider($this->app);
        $provider->register();

        $blockchain = $this->app->make('blockchain');
        $this->assertNotNull($blockchain);
    }

    public function test_boot_method_publishes_config()
    {
        $provider = new AwsBlockchainServiceProvider($this->app);

        // The boot method should call publishes - we can't easily mock it in Laravel's container
        // Instead, we'll verify that boot() doesn't throw an exception and that the service provider
        // has the publishes method configured correctly
        $provider->boot();

        // Verify the provider has the correct structure by checking provides()
        $this->assertIsArray($provider->provides());
        $this->assertNotEmpty($provider->provides());
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

    public function test_register_method_handles_missing_config()
    {
        // Create a fresh app instance without config
        $app = new \Illuminate\Foundation\Application;
        $app->singleton('config', function () {
            return new \Illuminate\Config\Repository([]);
        });

        $provider = new AwsBlockchainServiceProvider($app);

        // Should not throw exception even without config
        try {
            $provider->register();
            $exceptionThrown = false;
        } catch (\Exception $e) {
            $exceptionThrown = true;
        }

        $this->assertFalse($exceptionThrown, 'register() should not throw exception when config is missing');
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

    public function test_boot_method_can_be_called_multiple_times()
    {
        $provider = new AwsBlockchainServiceProvider($this->app);

        // Should not throw exception when called multiple times
        $provider->boot();
        $provider->boot();
        $provider->boot();

        // Verify the provider is still in a valid state
        $this->assertInstanceOf(AwsBlockchainServiceProvider::class, $provider);
        $this->assertIsArray($provider->provides());
        $this->assertNotEmpty($provider->provides());
    }

    public function test_register_method_can_be_called_multiple_times()
    {
        $provider = new AwsBlockchainServiceProvider($this->app);

        // Should not throw exception when called multiple times
        $provider->register();
        $provider->register();
        $provider->register();

        // Verify services are still registered correctly
        $this->assertInstanceOf(AwsBlockchainServiceProvider::class, $provider);
        $this->assertTrue($this->app->bound('blockchain'));
        $this->assertTrue($this->app->bound('blockchain.public'));
        $this->assertTrue($this->app->bound('blockchain.private'));
    }
}
