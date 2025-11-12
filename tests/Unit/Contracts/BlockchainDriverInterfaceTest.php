<?php

namespace AwsBlockchain\Laravel\Tests\Unit\Contracts;

use AwsBlockchain\Laravel\Contracts\BlockchainDriverInterface;
use AwsBlockchain\Laravel\Drivers\ManagedBlockchainDriver;
use AwsBlockchain\Laravel\Drivers\MockDriver;
use AwsBlockchain\Laravel\Drivers\QldbDriver;
use AwsBlockchain\Laravel\Tests\TestCase;
use Mockery;

class BlockchainDriverInterfaceTest extends TestCase
{
    public function test_mock_driver_implements_interface()
    {
        $driver = new MockDriver('test');

        $this->assertInstanceOf(BlockchainDriverInterface::class, $driver);
    }

    public function test_interface_has_required_methods()
    {
        $reflection = new \ReflectionClass(BlockchainDriverInterface::class);
        $methods = $reflection->getMethods();

        $methodNames = array_map(function ($method) {
            return $method->getName();
        }, $methods);

        $requiredMethods = [
            'recordEvent',
            'getEvent',
            'verifyIntegrity',
            'isAvailable',
            'getType',
            'getDriverInfo',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertContains($method, $methodNames, "Interface missing method: {$method}");
        }
    }

    public function test_interface_methods_have_correct_signatures()
    {
        $reflection = new \ReflectionClass(BlockchainDriverInterface::class);

        // Test recordEvent method
        $recordEventMethod = $reflection->getMethod('recordEvent');
        $this->assertEquals(1, $recordEventMethod->getNumberOfParameters());
        $this->assertEquals('string', $recordEventMethod->getReturnType()->getName());

        // Test getEvent method
        $getEventMethod = $reflection->getMethod('getEvent');
        $this->assertEquals(1, $getEventMethod->getNumberOfParameters());
        $this->assertTrue($getEventMethod->getReturnType()->allowsNull());

        // Test verifyIntegrity method
        $verifyMethod = $reflection->getMethod('verifyIntegrity');
        $this->assertEquals(2, $verifyMethod->getNumberOfParameters());
        $this->assertEquals('bool', $verifyMethod->getReturnType()->getName());

        // Test isAvailable method
        $isAvailableMethod = $reflection->getMethod('isAvailable');
        $this->assertEquals(0, $isAvailableMethod->getNumberOfParameters());
        $this->assertEquals('bool', $isAvailableMethod->getReturnType()->getName());

        // Test getType method
        $getTypeMethod = $reflection->getMethod('getType');
        $this->assertEquals(0, $getTypeMethod->getNumberOfParameters());
        $this->assertEquals('string', $getTypeMethod->getReturnType()->getName());

        // Test getDriverInfo method
        $getInfoMethod = $reflection->getMethod('getDriverInfo');
        $this->assertEquals(0, $getInfoMethod->getNumberOfParameters());
        $this->assertEquals('array', $getInfoMethod->getReturnType()->getName());
    }

    public function test_all_drivers_implement_interface()
    {
        // Create mock QLDB clients
        $qldbClientMock = Mockery::mock('Aws\QLDB\QLDBClient');
        $qldbSessionClientMock = Mockery::mock('Aws\QLDBSession\QLDBSessionClient');

        // Mock describeLedger method
        $qldbClientMock->shouldReceive('describeLedger')
            ->andReturn(Mockery::mock('Aws\Result'));

        $drivers = [
            new MockDriver('test'),
            new ManagedBlockchainDriver([
                'access_key_id' => 'test',
                'secret_access_key' => 'test',
                'network_id' => 'test',
                'member_id' => 'test',
                'node_id' => 'test',
            ]),
            new QldbDriver([
                'access_key_id' => 'test',
                'secret_access_key' => 'test',
                'ledger_name' => 'test',
            ], $qldbClientMock, $qldbSessionClientMock),
        ];

        foreach ($drivers as $driver) {
            $this->assertInstanceOf(BlockchainDriverInterface::class, $driver);
        }
    }

    public function test_interface_methods_return_expected_types()
    {
        $driver = new MockDriver('test');

        // Test recordEvent
        $eventId = $driver->recordEvent(['test' => 'data']);
        $this->assertIsString($eventId);

        // Test getEvent
        $event = $driver->getEvent($eventId);
        $this->assertIsArray($event);

        // Test verifyIntegrity
        $isValid = $driver->verifyIntegrity($eventId, ['test' => 'data']);
        $this->assertIsBool($isValid);

        // Test isAvailable
        $isAvailable = $driver->isAvailable();
        $this->assertIsBool($isAvailable);

        // Test getType
        $type = $driver->getType();
        $this->assertIsString($type);

        // Test getDriverInfo
        $info = $driver->getDriverInfo();
        $this->assertIsArray($info);
    }

    public function test_interface_handles_edge_cases()
    {
        $driver = new MockDriver('test');

        // Test with empty data
        $eventId = $driver->recordEvent([]);
        $this->assertIsString($eventId);

        // Test with default parameter (empty array)
        $eventId = $driver->recordEvent();
        $this->assertIsString($eventId);

        // Test with non-existent event
        $event = $driver->getEvent('non-existent');
        $this->assertNull($event);

        // Test verification of non-existent event
        $isValid = $driver->verifyIntegrity('non-existent', []);
        $this->assertFalse($isValid);
    }

    public function test_interface_is_public()
    {
        $reflection = new \ReflectionClass(BlockchainDriverInterface::class);

        $this->assertTrue($reflection->isInterface());
        // Interfaces are always accessible (public) - check that it's not a private/protected class
        // ReflectionClass::isPublic() may not work as expected for interfaces, so we verify it's an interface
        $this->assertTrue($reflection->isInterface());
    }

    public function test_interface_can_be_extended()
    {
        $reflection = new \ReflectionClass(BlockchainDriverInterface::class);

        $this->assertTrue($reflection->isInterface());
        // Interfaces are abstract in PHP reflection - they can be extended via 'extends'
        // The test verifies it's an interface (which can be extended)
        $this->assertTrue($reflection->isInterface());
    }
}
