<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel;

use AwsBlockchain\Laravel\Contracts\BlockchainDriverInterface;
use AwsBlockchain\Laravel\Drivers\ManagedBlockchainDriver;
use AwsBlockchain\Laravel\Drivers\MockDriver;
use AwsBlockchain\Laravel\Drivers\QldbDriver;

class BlockchainManager
{
    /** @var array<string, BlockchainDriverInterface> */
    protected array $drivers = [];

    protected string $defaultDriver;

    /** @var array<string, mixed> */
    protected array $config;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->defaultDriver = $config['default_driver'] ?? 'mock';
    }

    /**
     * Get a blockchain driver
     */
    public function driver(?string $name = null): BlockchainDriverInterface
    {
        $name = $name ?: $this->defaultDriver;

        if (! isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->createDriver($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Create a blockchain driver
     */
    protected function createDriver(string $name): BlockchainDriverInterface
    {
        if (! isset($this->config['drivers'][$name])) {
            throw new \InvalidArgumentException("Driver '{$name}' is not configured.");
        }

        $config = $this->config['drivers'][$name];
        $driverType = $config['type'] ?? $name;

        switch ($driverType) {
            case 'managed_blockchain':
                return new ManagedBlockchainDriver($config);
            case 'qldb':
                return new QldbDriver($config);
            case 'mock':
                return new MockDriver($config['type'] ?? $name);
            default:
                throw new \InvalidArgumentException("Driver type '{$driverType}' is not supported.");
        }
    }

    /**
     * Get all available drivers
     *
     * @return array<string, array{type: string, available: bool, info?: array<string, mixed>, error?: string}>
     */
    public function getAvailableDrivers(): array
    {
        $drivers = [];

        foreach (($this->config['drivers'] ?? []) as $name => $config) {
            try {
                $driver = $this->createDriver($name);
                $drivers[$name] = [
                    'type' => $driver->getType(),
                    'available' => $driver->isAvailable(),
                    'info' => $driver->getDriverInfo(),
                ];
            } catch (\Exception $e) {
                $drivers[$name] = [
                    'type' => $name,
                    'available' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $drivers;
    }

    /**
     * Switch the default driver
     */
    public function setDefaultDriver(string $name): void
    {
        $oldDefault = $this->defaultDriver;
        $this->defaultDriver = $name;

        // Clear cached driver if default changed
        if ($oldDefault !== $name && isset($this->drivers[$oldDefault])) {
            unset($this->drivers[$oldDefault]);
        }
    }

    /**
     * Get the default driver name
     */
    public function getDefaultDriver(): string
    {
        return $this->defaultDriver;
    }

    /**
     * Get the public driver
     */
    public function publicDriver(): BlockchainDriverInterface
    {
        return $this->driver($this->config['public_driver'] ?? 'mock');
    }

    /**
     * Get the private driver
     */
    public function privateDriver(): BlockchainDriverInterface
    {
        return $this->driver($this->config['private_driver'] ?? 'mock');
    }
}
