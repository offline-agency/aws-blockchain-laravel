<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Drivers;

use AwsBlockchain\Laravel\Contracts\BlockchainDriverInterface;

class MockDriver implements BlockchainDriverInterface
{
    /** @var array<string, array{id: string, data: array<string, mixed>, timestamp: string, hash: string}> */
    protected array $events = [];

    protected string $type;

    protected bool $available = true;

    public function __construct(string $type = 'mock')
    {
        $this->type = $type;
    }

    /**
     * Record an event on the blockchain
     *
     * @param  array<string, mixed>  $data
     */
    public function recordEvent(array $data = []): string
    {
        $id = 'mock_'.$this->type.'_'.uniqid();

        $this->events[$id] = [
            'id' => $id,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
            'hash' => $this->generateHash($data),
        ];

        return $id;
    }

    /**
     * Get an event from the blockchain
     *
     * @return array{id: string, data: array<string, mixed>, timestamp: string, hash: string}|null
     */
    public function getEvent(string $id): ?array
    {
        return $this->events[$id] ?? null;
    }

    /**
     * Verify event integrity
     *
     * @param  array<string, mixed>  $data
     */
    public function verifyIntegrity(string $id, array $data): bool
    {
        $event = $this->getEvent($id);

        if (! $event) {
            return false;
        }

        $expectedHash = $this->generateHash($data);

        return $event['hash'] === $expectedHash;
    }

    /**
     * Check if driver is available
     */
    public function isAvailable(): bool
    {
        return $this->available;
    }

    /**
     * Get driver type
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Get driver info
     *
     * @return array{type: string, available: bool, events_count: int, driver: string}
     */
    public function getDriverInfo(): array
    {
        return [
            'type' => $this->type,
            'available' => $this->available,
            'events_count' => count($this->events),
            'driver' => 'MockDriver',
        ];
    }

    /**
     * Get all events (for testing)
     *
     * @return array<string, array{id: string, data: array<string, mixed>, timestamp: string, hash: string}>
     */
    public function getAllEvents(): array
    {
        return $this->events;
    }

    /**
     * Clear all events (for testing)
     */
    public function clearEvents(): void
    {
        $this->events = [];
    }

    /**
     * Set availability (for testing)
     */
    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    /**
     * Get events by type (for testing)
     *
     * @return array<string, array{id: string, data: array<string, mixed>, timestamp: string, hash: string}>
     */
    public function getEventsByType(string $type): array
    {
        return array_filter($this->events, function ($event) use ($type) {
            return ($event['data']['event_type'] ?? null) === $type;
        });
    }

    /**
     * Get events count (for testing)
     */
    public function getEventsCount(): int
    {
        return count($this->events);
    }

    /**
     * Simulate network delay (for testing)
     */
    public function simulateNetworkDelay(int $milliseconds = 100): void
    {
        usleep($milliseconds * 1000);
    }

    /**
     * Simulate failure (for testing)
     */
    public function simulateFailure(bool $shouldFail = true): void
    {
        $this->available = ! $shouldFail;
    }

    /**
     * Generate a mock hash for data integrity
     *
     * @param  array<string, mixed>  $data
     */
    protected function generateHash(array $data): string
    {
        return hash('sha256', json_encode($data).$this->type);
    }
}
