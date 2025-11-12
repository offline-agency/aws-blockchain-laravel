<?php

namespace AwsBlockchain\Laravel\Tests;

use AwsBlockchain\Laravel\AwsBlockchainServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [
            AwsBlockchainServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup database
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('aws-blockchain-laravel', [
            'default_driver' => 'mock',
            'public_driver' => 'mock',
            'private_driver' => 'mock',
            'drivers' => [
                'mock' => [
                    'type' => 'mock',
                ],
                'managed_blockchain' => [
                    'type' => 'managed_blockchain',
                    'region' => 'us-east-1',
                    'access_key_id' => 'test-key',
                    'secret_access_key' => 'test-secret',
                    'network_id' => 'test-network',
                    'member_id' => 'test-member',
                    'node_id' => 'test-node',
                    'channel_name' => 'test-channel',
                    'chaincode_name' => 'test-chaincode',
                ],
                'qldb' => [
                    'type' => 'qldb',
                    'region' => 'us-east-1',
                    'access_key_id' => 'test-key',
                    'secret_access_key' => 'test-secret',
                    'ledger_name' => 'test-ledger',
                ],
            ],
            'data_categories' => [
                'public' => [
                    'product_origin',
                    'certifications',
                    'public_timestamps',
                    'quality_scores',
                ],
                'private' => [
                    'supplier_details',
                    'pricing',
                    'internal_notes',
                    'sensitive_locations',
                ],
            ],
        ]);
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(realpath(__DIR__.'/../database/migrations'));
    }
}
