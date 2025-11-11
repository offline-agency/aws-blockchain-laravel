<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Tests\Unit\Services;

use AwsBlockchain\Laravel\Services\AbiEncoder;
use AwsBlockchain\Laravel\Tests\TestCase;

class AbiEncoderTest extends TestCase
{
    protected AbiEncoder $encoder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->encoder = new AbiEncoder();
    }

    public function test_get_function_selector(): void
    {
        $selector = $this->encoder->getFunctionSelector('transfer', [
            ['type' => 'address'],
            ['type' => 'uint256'],
        ]);

        $this->assertIsString($selector);
        $this->assertStringStartsWith('0x', $selector);
        $this->assertEquals(10, strlen($selector)); // 0x + 8 hex chars
    }

    public function test_get_function_selector_with_no_inputs(): void
    {
        $selector = $this->encoder->getFunctionSelector('balanceOf', []);

        $this->assertIsString($selector);
        $this->assertStringStartsWith('0x', $selector);
    }

    public function test_encode_function_call(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'address'],
                ['type' => 'uint256'],
            ],
        ];

        $encoded = $this->encoder->encodeFunctionCall('transfer', ['0x1234567890123456789012345678901234567890', 1000], $methodAbi);

        $this->assertIsString($encoded);
        $this->assertStringStartsWith('0x', $encoded);
        $this->assertGreaterThan(10, strlen($encoded));
    }

    public function test_encode_function_call_with_no_params(): void
    {
        $methodAbi = [
            'inputs' => [],
        ];

        $encoded = $this->encoder->encodeFunctionCall('balanceOf', [], $methodAbi);

        $this->assertIsString($encoded);
        $this->assertStringStartsWith('0x', $encoded);
    }

    public function test_encode_constructor_call(): void
    {
        $bytecode = '0x608060405234801561001057600080fd5b50';
        $constructorAbi = [
            'inputs' => [
                ['type' => 'uint256'],
            ],
        ];

        $encoded = $this->encoder->encodeConstructorCall([1000], $constructorAbi, $bytecode);

        $this->assertIsString($encoded);
        $this->assertStringStartsWith('0x', $encoded);
        $this->assertStringContainsString(substr($bytecode, 2), $encoded);
    }

    public function test_encode_constructor_call_without_0x_prefix(): void
    {
        $bytecode = '608060405234801561001057600080fd5b50';
        $constructorAbi = null;

        $encoded = $this->encoder->encodeConstructorCall([], $constructorAbi, $bytecode);

        $this->assertIsString($encoded);
        $this->assertStringStartsWith('0x', $encoded);
        $this->assertStringContainsString($bytecode, $encoded);
    }

    public function test_encode_constructor_call_with_params(): void
    {
        $bytecode = '0x6080604052';
        $constructorAbi = [
            'inputs' => [
                ['type' => 'address'],
                ['type' => 'uint256'],
            ],
        ];

        $encoded = $this->encoder->encodeConstructorCall(
            ['0x1234567890123456789012345678901234567890', 1000],
            $constructorAbi,
            $bytecode
        );

        $this->assertIsString($encoded);
        $this->assertStringStartsWith('0x', $encoded);
    }

    public function test_encode_parameters_throws_on_count_mismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter count mismatch');

        $inputs = [
            ['type' => 'address'],
            ['type' => 'uint256'],
        ];

        $this->encoder->encodeFunctionCall('transfer', ['0x123'], [
            'inputs' => $inputs,
        ]);
    }

    public function test_encode_uint(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'uint256'],
            ],
        ];

        $encoded = $this->encoder->encodeFunctionCall('test', [1000], $methodAbi);

        $this->assertIsString($encoded);
        $this->assertStringStartsWith('0x', $encoded);
    }

    public function test_encode_uint_with_hex_string(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'uint256'],
            ],
        ];

        $encoded = $this->encoder->encodeFunctionCall('test', ['0x3e8'], $methodAbi);

        $this->assertIsString($encoded);
    }

    public function test_encode_uint8(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'uint8'],
            ],
        ];

        $encoded = $this->encoder->encodeFunctionCall('test', [255], $methodAbi);

        $this->assertIsString($encoded);
    }

    public function test_encode_int(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'int256'],
            ],
        ];

        $encoded = $this->encoder->encodeFunctionCall('test', [-1000], $methodAbi);

        $this->assertIsString($encoded);
    }

    public function test_encode_address(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'address'],
            ],
        ];

        $address = '0x1234567890123456789012345678901234567890';
        $encoded = $this->encoder->encodeFunctionCall('test', [$address], $methodAbi);

        $this->assertIsString($encoded);
        $this->assertStringStartsWith('0x', $encoded);
    }

    public function test_encode_address_without_0x_prefix(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'address'],
            ],
        ];

        $address = '1234567890123456789012345678901234567890';
        $encoded = $this->encoder->encodeFunctionCall('test', [$address], $methodAbi);

        $this->assertIsString($encoded);
    }

    public function test_encode_bool(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'bool'],
            ],
        ];

        $encodedTrue = $this->encoder->encodeFunctionCall('test', [true], $methodAbi);
        $encodedFalse = $this->encoder->encodeFunctionCall('test', [false], $methodAbi);

        $this->assertIsString($encodedTrue);
        $this->assertIsString($encodedFalse);
        $this->assertNotEquals($encodedTrue, $encodedFalse);
    }

    public function test_encode_bytes32(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'bytes32'],
            ],
        ];

        $bytes = '0x1234567890123456789012345678901234567890123456789012345678901234';
        $encoded = $this->encoder->encodeFunctionCall('test', [$bytes], $methodAbi);

        $this->assertIsString($encoded);
    }

    public function test_encode_bytes32_without_0x(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'bytes32'],
            ],
        ];

        $bytes = '1234567890123456789012345678901234567890123456789012345678901234';
        $encoded = $this->encoder->encodeFunctionCall('test', [$bytes], $methodAbi);

        $this->assertIsString($encoded);
    }

    public function test_encode_bytes_dynamic(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'bytes'],
            ],
        ];

        $bytes = '0x1234567890abcdef';
        $encoded = $this->encoder->encodeFunctionCall('test', [$bytes], $methodAbi);

        $this->assertIsString($encoded);
        $this->assertStringStartsWith('0x', $encoded);
    }

    public function test_encode_string(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'string'],
            ],
        ];

        $encoded = $this->encoder->encodeFunctionCall('test', ['Hello World'], $methodAbi);

        $this->assertIsString($encoded);
        $this->assertStringStartsWith('0x', $encoded);
    }

    public function test_encode_array(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'uint256[]'],
            ],
        ];

        $encoded = $this->encoder->encodeFunctionCall('test', [[1, 2, 3]], $methodAbi);

        $this->assertIsString($encoded);
        $this->assertStringStartsWith('0x', $encoded);
    }

    public function test_encode_multiple_parameters(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'address'],
                ['type' => 'uint256'],
                ['type' => 'bool'],
            ],
        ];

        $encoded = $this->encoder->encodeFunctionCall('transfer', [
            '0x1234567890123456789012345678901234567890',
            1000,
            true,
        ], $methodAbi);

        $this->assertIsString($encoded);
        $this->assertStringStartsWith('0x', $encoded);
    }

    public function test_decode_function_result_single_output(): void
    {
        $outputs = [
            ['type' => 'uint256'],
        ];

        // Encoded value: 1000 in hex (0x3e8 padded to 64 chars)
        $data = '0x00000000000000000000000000000000000000000000000000000000000003e8';
        $decoded = $this->encoder->decodeFunctionResult($data, $outputs);

        $this->assertEquals(1000, $decoded);
    }

    public function test_decode_function_result_multiple_outputs(): void
    {
        $outputs = [
            ['type' => 'uint256'],
            ['type' => 'address'],
        ];

        $data = '0x00000000000000000000000000000000000000000000000000000000000003e8'
            .'0000000000000000000000001234567890123456789012345678901234567890';

        $decoded = $this->encoder->decodeFunctionResult($data, $outputs);

        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
        $this->assertEquals(1000, $decoded[0]);
        $this->assertEquals('0x1234567890123456789012345678901234567890', $decoded[1]);
    }

    public function test_decode_function_result_empty_outputs(): void
    {
        $decoded = $this->encoder->decodeFunctionResult('0x1234', []);

        $this->assertNull($decoded);
    }

    public function test_decode_uint256(): void
    {
        $outputs = [
            ['type' => 'uint256'],
        ];

        $data = '0x0000000000000000000000000000000000000000000000000000000000000fff';
        $decoded = $this->encoder->decodeFunctionResult($data, $outputs);

        $this->assertEquals(4095, $decoded);
    }

    public function test_decode_address(): void
    {
        $outputs = [
            ['type' => 'address'],
        ];

        $data = '0x0000000000000000000000001234567890123456789012345678901234567890';
        $decoded = $this->encoder->decodeFunctionResult($data, $outputs);

        $this->assertEquals('0x1234567890123456789012345678901234567890', $decoded);
    }

    public function test_decode_bool(): void
    {
        $outputs = [
            ['type' => 'bool'],
        ];

        $dataTrue = '0x0000000000000000000000000000000000000000000000000000000000000001';
        $dataFalse = '0x0000000000000000000000000000000000000000000000000000000000000000';

        $decodedTrue = $this->encoder->decodeFunctionResult($dataTrue, $outputs);
        $decodedFalse = $this->encoder->decodeFunctionResult($dataFalse, $outputs);

        $this->assertTrue($decodedTrue);
        $this->assertFalse($decodedFalse);
    }

    public function test_decode_bytes32(): void
    {
        $outputs = [
            ['type' => 'bytes32'],
        ];

        $data = '0x1234567890123456789012345678901234567890123456789012345678901234';
        $decoded = $this->encoder->decodeFunctionResult($data, $outputs);

        $this->assertEquals('0x1234567890123456789012345678901234567890123456789012345678901234', $decoded);
    }

    public function test_decode_string(): void
    {
        $outputs = [
            ['type' => 'string'],
        ];

        // Length: 11 (0x0b), then "Hello World" in hex
        $data = '0x000000000000000000000000000000000000000000000000000000000000000b'
            .'48656c6c6f20576f726c64000000000000000000000000000000000000000000';

        $decoded = $this->encoder->decodeFunctionResult($data, $outputs);

        $this->assertEquals('Hello World', $decoded);
    }

    public function test_decode_without_0x_prefix(): void
    {
        $outputs = [
            ['type' => 'uint256'],
        ];

        $data = '00000000000000000000000000000000000000000000000000000000000003e8';
        $decoded = $this->encoder->decodeFunctionResult($data, $outputs);

        $this->assertEquals(1000, $decoded);
    }

    public function test_encode_defaults_to_uint256(): void
    {
        $methodAbi = [
            'inputs' => [
                ['type' => 'unknown'],
            ],
        ];

        // Should default to uint256 encoding
        $encoded = $this->encoder->encodeFunctionCall('test', [1000], $methodAbi);

        $this->assertIsString($encoded);
        $this->assertStringStartsWith('0x', $encoded);
    }
}

