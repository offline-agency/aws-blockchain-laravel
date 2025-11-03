<?php

namespace AwsBlockchain\Laravel\Tests\Unit\Drivers;

use Aws\Exception\AwsException;
use Aws\Qldb\QldbClient;
use Aws\QldbSession\QldbSessionClient;
use Aws\Result;
use AwsBlockchain\Laravel\Drivers\QldbDriver;
use AwsBlockchain\Laravel\Tests\TestCase;
use Mockery;

class QldbDriverTest extends TestCase
{
    protected $mockClient;

    protected $mockSessionClient;

    protected $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'region' => 'us-east-1',
            'access_key_id' => 'test-key',
            'secret_access_key' => 'test-secret',
            'ledger_name' => 'test-ledger',
        ];

        $this->mockClient = Mockery::mock(QldbClient::class);
        $this->mockSessionClient = Mockery::mock(QldbSessionClient::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_create_qldb_driver()
    {
        $driver = new QldbDriver($this->config);

        $this->assertInstanceOf(QldbDriver::class, $driver);
        $this->assertEquals('qldb', $driver->getType());
    }

    public function test_can_record_event_successfully()
    {
        // Mock session token (called twice: once in recordEvent, once in startTransaction)
        $this->mockClient->shouldReceive('sendCommand')
            ->twice()
            ->andReturn(new Result(['StartSession' => ['SessionToken' => 'test-session-token']]));

        // Mock start transaction
        $this->mockSessionClient->shouldReceive('sendCommand')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['StartTransaction']);
            }))
            ->andReturn(new Result(['StartTransaction' => ['TransactionId' => 'test-tx-id']]));

        // Mock execute statement
        $this->mockSessionClient->shouldReceive('sendCommand')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['ExecuteStatement']) &&
                       $args['ExecuteStatement']['Statement'] === "INSERT INTO SupplyChainEvents VALUE {'id': ?, 'data': ?, 'timestamp': ?, 'hash': ?}";
            }))
            ->andReturn(new Result(['ExecuteStatement' => ['TransactionId' => 'test-tx-id']]));

        $driver = new QldbDriver($this->config, $this->mockClient, $this->mockSessionClient);

        $data = ['test' => 'data'];
        $eventId = $driver->recordEvent($data);

        $this->assertIsString($eventId);
        $this->assertStringStartsWith('doc_', $eventId);
    }

    public function test_handles_record_event_exception()
    {
        $this->mockClient->shouldReceive('sendCommand')
            ->once()
            ->andThrow(new AwsException('Network error', new \Aws\Command('sendCommand')));

        $driver = new QldbDriver($this->config, $this->mockClient, $this->mockSessionClient);

        $this->expectException(AwsException::class);

        $driver->recordEvent(['test' => 'data']);
    }

    public function test_can_get_event_successfully()
    {
        $expectedData = ['test' => 'data'];

        // Mock session token (called twice: once in getEvent, once in startTransaction)
        $this->mockClient->shouldReceive('sendCommand')
            ->twice()
            ->andReturn(new Result(['StartSession' => ['SessionToken' => 'test-session-token']]));

        // Mock start transaction
        $this->mockSessionClient->shouldReceive('sendCommand')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['StartTransaction']);
            }))
            ->andReturn(new Result(['StartTransaction' => ['TransactionId' => 'test-tx-id']]));

        // Mock execute statement
        $this->mockSessionClient->shouldReceive('sendCommand')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['ExecuteStatement']) &&
                       $args['ExecuteStatement']['Statement'] === 'SELECT * FROM SupplyChainEvents WHERE id = ?';
            }))
            ->andReturn(new Result([
                'ExecuteStatement' => [
                    'FirstPage' => [
                        'Values' => [
                            ['Document' => ['data' => json_encode($expectedData)]],
                        ],
                    ],
                ],
            ]));

        $driver = new QldbDriver($this->config, $this->mockClient, $this->mockSessionClient);

        $result = $driver->getEvent('test-event-id');

        $this->assertEquals($expectedData, $result);
    }

    public function test_handles_get_event_exception()
    {
        $this->mockClient->shouldReceive('sendCommand')
            ->once()
            ->andThrow(new AwsException('Network error', new \Aws\Command('sendCommand')));

        $driver = new QldbDriver($this->config, $this->mockClient, $this->mockSessionClient);

        $result = $driver->getEvent('test-event-id');

        $this->assertNull($result);
    }

    public function test_returns_null_for_empty_get_event_result()
    {
        // Mock session token (called twice: once in getEvent, once in startTransaction)
        $this->mockClient->shouldReceive('sendCommand')
            ->twice()
            ->andReturn(new Result(['StartSession' => ['SessionToken' => 'test-session-token']]));

        // Mock start transaction
        $this->mockSessionClient->shouldReceive('sendCommand')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['StartTransaction']);
            }))
            ->andReturn(new Result(['StartTransaction' => ['TransactionId' => 'test-tx-id']]));

        // Mock empty result
        $this->mockSessionClient->shouldReceive('sendCommand')
            ->once()
            ->andReturn(new Result(['ExecuteStatement' => ['FirstPage' => []]]));

        $driver = new QldbDriver($this->config, $this->mockClient, $this->mockSessionClient);

        $result = $driver->getEvent('nonexistent-event-id');

        $this->assertNull($result);
    }

    public function test_can_verify_integrity_successfully()
    {
        $testData = ['test' => 'data'];
        $expectedHash = hash('sha256', json_encode($testData).'test-ledger');

        // Mock session token (called twice: once in verifyIntegrity, once in startTransaction)
        $this->mockClient->shouldReceive('sendCommand')
            ->twice()
            ->andReturn(new Result(['StartSession' => ['SessionToken' => 'test-session-token']]));

        // Mock start transaction
        $this->mockSessionClient->shouldReceive('sendCommand')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['StartTransaction']);
            }))
            ->andReturn(new Result(['StartTransaction' => ['TransactionId' => 'test-tx-id']]));

        // Mock execute statement
        $this->mockSessionClient->shouldReceive('sendCommand')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['ExecuteStatement']) &&
                       $args['ExecuteStatement']['Statement'] === 'SELECT hash FROM SupplyChainEvents WHERE id = ?';
            }))
            ->andReturn(new Result([
                'ExecuteStatement' => [
                    'FirstPage' => [
                        'Values' => [
                            ['Document' => ['hash' => $expectedHash]],
                        ],
                    ],
                ],
            ]));

        $driver = new QldbDriver($this->config, $this->mockClient, $this->mockSessionClient);

        $result = $driver->verifyIntegrity('test-event-id', $testData);

        $this->assertTrue($result);
    }

    public function test_handles_verify_integrity_exception()
    {
        $this->mockClient->shouldReceive('sendCommand')
            ->once()
            ->andThrow(new AwsException('Network error', new \Aws\Command('sendCommand')));

        $driver = new QldbDriver($this->config, $this->mockClient, $this->mockSessionClient);

        $result = $driver->verifyIntegrity('test-event-id', ['test' => 'data']);

        $this->assertFalse($result);
    }

    public function test_returns_false_for_empty_verify_integrity_result()
    {
        // Mock session token (called twice: once in verifyIntegrity, once in startTransaction)
        $this->mockClient->shouldReceive('sendCommand')
            ->twice()
            ->andReturn(new Result(['StartSession' => ['SessionToken' => 'test-session-token']]));

        // Mock start transaction
        $this->mockSessionClient->shouldReceive('sendCommand')
            ->once()
            ->with(Mockery::on(function ($args) {
                return isset($args['StartTransaction']);
            }))
            ->andReturn(new Result(['StartTransaction' => ['TransactionId' => 'test-tx-id']]));

        // Mock empty result
        $this->mockSessionClient->shouldReceive('sendCommand')
            ->once()
            ->andReturn(new Result(['ExecuteStatement' => ['FirstPage' => []]]));

        $driver = new QldbDriver($this->config, $this->mockClient, $this->mockSessionClient);

        $result = $driver->verifyIntegrity('nonexistent-event-id', ['test' => 'data']);

        $this->assertFalse($result);
    }

    public function test_can_check_availability_successfully()
    {
        $this->mockClient->shouldReceive('describeLedger')
            ->once()
            ->with(['Name' => 'test-ledger'])
            ->andReturn(new Result(['Ledger' => ['Name' => 'test-ledger']]));

        $driver = new QldbDriver($this->config, $this->mockClient, $this->mockSessionClient);

        $result = $driver->isAvailable();

        $this->assertTrue($result);
    }

    public function test_handles_availability_check_exception()
    {
        $this->mockClient->shouldReceive('describeLedger')
            ->once()
            ->andThrow(new AwsException('Network error', new \Aws\Command('describeLedger')));

        $driver = new QldbDriver($this->config, $this->mockClient, $this->mockSessionClient);

        $result = $driver->isAvailable();

        $this->assertFalse($result);
    }

    public function test_can_get_driver_info()
    {
        // Mock describeLedger for isAvailable() call in getDriverInfo()
        $this->mockClient->shouldReceive('describeLedger')
            ->once()
            ->with(['Name' => 'test-ledger'])
            ->andReturn(new Result(['Ledger' => ['Name' => 'test-ledger']]));

        $driver = new QldbDriver($this->config, $this->mockClient, $this->mockSessionClient);

        $info = $driver->getDriverInfo();

        $this->assertIsArray($info);
        $this->assertEquals('qldb', $info['type']);
        $this->assertEquals('test-ledger', $info['ledger_name']);
        $this->assertEquals('QldbDriver', $info['driver']);
    }

    public function test_uses_default_ledger_name()
    {
        $minimalConfig = [
            'access_key_id' => 'test-key',
            'secret_access_key' => 'test-secret',
        ];

        // Mock describeLedger for isAvailable() call in getDriverInfo()
        $this->mockClient->shouldReceive('describeLedger')
            ->once()
            ->with(['Name' => 'supply-chain-ledger'])
            ->andReturn(new Result(['Ledger' => ['Name' => 'supply-chain-ledger']]));

        $driver = new QldbDriver($minimalConfig, $this->mockClient, $this->mockSessionClient);

        $info = $driver->getDriverInfo();

        $this->assertEquals('supply-chain-ledger', $info['ledger_name']);
    }
}
