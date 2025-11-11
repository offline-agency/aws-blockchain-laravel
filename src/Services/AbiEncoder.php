<?php

declare(strict_types=1);

namespace AwsBlockchain\Laravel\Services;

use kornrunner\Keccak;

class AbiEncoder
{
    /**
     * Get function selector (first 4 bytes of keccak256 hash of function signature)
     *
     * @param  array<int, array<string, mixed>>  $inputs
     */
    public function getFunctionSelector(string $methodName, array $inputs): string
    {
        $signature = $this->getMethodSignature($methodName, $inputs);
        $hash = Keccak::hash($signature, 256);

        return '0x'.substr($hash, 0, 8);
    }

    /**
     * Get method signature string
     *
     * @param  array<int, array<string, mixed>>  $inputs
     */
    protected function getMethodSignature(string $methodName, array $inputs): string
    {
        $types = [];
        foreach ($inputs as $input) {
            $types[] = $input['type'] ?? 'unknown';
        }

        return $methodName.'('.implode(',', $types).')';
    }

    /**
     * Encode a function call with parameters
     *
     * @param  array<int, mixed>  $params
     * @param  array<string, mixed>  $methodAbi
     */
    public function encodeFunctionCall(string $methodName, array $params, array $methodAbi): string
    {
        $selector = $this->getFunctionSelector($methodName, $methodAbi['inputs'] ?? []);
        $encodedParams = $this->encodeParameters($params, $methodAbi['inputs'] ?? []);

        return $selector.$encodedParams;
    }

    /**
     * Encode constructor call
     *
     * @param  array<int, mixed>  $params
     * @param  array<string, mixed>|null  $constructorAbi
     */
    public function encodeConstructorCall(array $params, ?array $constructorAbi, string $bytecode): string
    {
        // Remove 0x prefix from bytecode if present
        if (str_starts_with($bytecode, '0x')) {
            $bytecode = substr($bytecode, 2);
        }

        $encodedParams = '';
        if ($constructorAbi !== null && ! empty($constructorAbi['inputs'] ?? [])) {
            $encodedParams = $this->encodeParameters($params, $constructorAbi['inputs']);
        }

        return '0x'.$bytecode.$encodedParams;
    }

    /**
     * Encode parameters according to ABI
     *
     * @param  array<int, mixed>  $params
     * @param  array<int, array<string, mixed>>  $inputs
     */
    protected function encodeParameters(array $params, array $inputs): string
    {
        if (count($params) !== count($inputs)) {
            throw new \InvalidArgumentException(
                'Parameter count mismatch: expected '.count($inputs).', got '.count($params)
            );
        }

        $encoded = '';
        foreach ($inputs as $index => $input) {
            $type = $input['type'] ?? '';
            $value = $params[$index] ?? null;
            $encoded .= $this->encodeParameter($value, $type);
        }

        return $encoded;
    }

    /**
     * Encode a single parameter
     */
    protected function encodeParameter(mixed $value, string $type): string
    {
        // Handle uint/int types
        if (preg_match('/^u?int(\d+)?$/', $type, $matches)) {
            $bits = isset($matches[1]) ? (int) $matches[1] : 256;
            return $this->encodeUint($value, $bits);
        }

        // Handle address
        if ($type === 'address') {
            return $this->encodeAddress($value);
        }

        // Handle bool
        if ($type === 'bool') {
            return $this->encodeBool($value);
        }

        // Handle bytes (fixed size)
        if (preg_match('/^bytes(\d+)$/', $type, $matches)) {
            $size = (int) $matches[1];
            return $this->encodeBytes($value, $size);
        }

        // Handle bytes (dynamic)
        if ($type === 'bytes') {
            return $this->encodeBytesDynamic($value);
        }

        // Handle string
        if ($type === 'string') {
            return $this->encodeString($value);
        }

        // Handle arrays (basic support)
        if (str_ends_with($type, '[]')) {
            $baseType = substr($type, 0, -2);
            return $this->encodeArray($value, $baseType);
        }

        // Default: try to encode as uint256
        return $this->encodeUint($value, 256);
    }

    /**
     * Encode unsigned integer
     */
    protected function encodeUint(mixed $value, int $bits): string
    {
        if (is_string($value) && str_starts_with($value, '0x')) {
            $value = hexdec(substr($value, 2));
        }

        $value = (int) $value;
        $hex = dechex($value);
        $padded = str_pad($hex, 64, '0', STR_PAD_LEFT);

        return $padded;
    }

    /**
     * Encode address (20 bytes, padded to 32 bytes)
     */
    protected function encodeAddress(mixed $value): string
    {
        if (is_string($value) && str_starts_with($value, '0x')) {
            $value = substr($value, 2);
        }

        $value = (string) $value;
        $padded = str_pad($value, 64, '0', STR_PAD_LEFT);

        return $padded;
    }

    /**
     * Encode boolean
     */
    protected function encodeBool(mixed $value): string
    {
        $intValue = $value ? 1 : 0;
        $hex = dechex($intValue);
        $padded = str_pad($hex, 64, '0', STR_PAD_LEFT);

        return $padded;
    }

    /**
     * Encode fixed-size bytes
     */
    protected function encodeBytes(mixed $value, int $size): string
    {
        if (is_string($value) && str_starts_with($value, '0x')) {
            $value = substr($value, 2);
        }

        $value = (string) $value;
        $padded = str_pad($value, $size * 2, '0', STR_PAD_RIGHT);
        // Pad to 32 bytes (64 hex chars)
        $padded = str_pad($padded, 64, '0', STR_PAD_RIGHT);

        return substr($padded, 0, 64);
    }

    /**
     * Encode dynamic bytes
     */
    protected function encodeBytesDynamic(mixed $value): string
    {
        if (is_string($value) && str_starts_with($value, '0x')) {
            $value = substr($value, 2);
        }

        $value = (string) $value;
        $length = (int) (strlen($value) / 2); // Length in bytes
        $lengthHex = str_pad(dechex($length), 64, '0', STR_PAD_LEFT);
        $padded = str_pad($value, ((int) ceil($length / 32)) * 64, '0', STR_PAD_RIGHT);

        return $lengthHex.$padded;
    }

    /**
     * Encode string
     */
    protected function encodeString(mixed $value): string
    {
        $value = (string) $value;
        $hex = bin2hex($value);
        $length = strlen($value);
        $lengthHex = str_pad(dechex($length), 64, '0', STR_PAD_LEFT);
        $padded = str_pad($hex, ((int) ceil($length / 32)) * 64, '0', STR_PAD_RIGHT);

        return $lengthHex.$padded;
    }

    /**
     * Encode array (basic implementation)
     *
     * @param  array<int, mixed>  $value
     */
    protected function encodeArray(array $value, string $baseType): string
    {
        $length = count($value);
        $lengthHex = str_pad(dechex($length), 64, '0', STR_PAD_LEFT);
        $encoded = '';

        foreach ($value as $item) {
            $encoded .= $this->encodeParameter($item, $baseType);
        }

        return $lengthHex.$encoded;
    }

    /**
     * Decode function result
     *
     * @param  array<int, array<string, mixed>>  $outputs
     */
    public function decodeFunctionResult(string $data, array $outputs): mixed
    {
        if (str_starts_with($data, '0x')) {
            $data = substr($data, 2);
        }

        if (empty($outputs)) {
            return null;
        }

        // For single output, return decoded value
        if (count($outputs) === 1) {
            return $this->decodeParameter($data, $outputs[0]['type'] ?? 'uint256');
        }

        // For multiple outputs, return array
        $results = [];
        $offset = 0;
        foreach ($outputs as $output) {
            $type = $output['type'] ?? 'uint256';
            $decoded = $this->decodeParameter(substr($data, $offset), $type);
            $results[] = $decoded;
            $offset += 64; // Each parameter is 32 bytes (64 hex chars)
        }

        return $results;
    }

    /**
     * Decode a single parameter
     */
    protected function decodeParameter(string $data, string $type): mixed
    {
        // Handle uint/int types
        if (preg_match('/^u?int(\d+)?$/', $type)) {
            return $this->hexToDec('0x'.substr($data, 0, 64));
        }

        // Handle address
        if ($type === 'address') {
            $hex = substr($data, 24, 40); // Last 20 bytes (40 hex chars)
            return '0x'.$hex;
        }

        // Handle bool
        if ($type === 'bool') {
            return $this->hexToDec('0x'.substr($data, 0, 64)) === 1;
        }

        // Handle bytes32
        if ($type === 'bytes32') {
            return '0x'.substr($data, 0, 64);
        }

        // Handle string (dynamic)
        if ($type === 'string') {
            $length = $this->hexToDec('0x'.substr($data, 0, 64));
            $hex = substr($data, 64, $length * 2);
            return hex2bin($hex) ?: '';
        }

        // Default: return as hex string
        return '0x'.substr($data, 0, 64);
    }

    /**
     * Convert hex to decimal
     */
    protected function hexToDec(string $hex): int
    {
        if (str_starts_with($hex, '0x')) {
            $hex = substr($hex, 2);
        }

        return (int) hexdec($hex);
    }
}

