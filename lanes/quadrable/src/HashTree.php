<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class HashTree
{
    public const EMPTY_HASH = "0000000000000000000000000000000000000000000000000000000000000000";

    public function keyHash(string $key): string
    {
        return Blake2s::hashHex($key);
    }

    public function valueHash(string $value): string
    {
        return Blake2s::hashHex($value);
    }

    public function leafHash(string $key, string $value): string
    {
        return $this->leafHashForKeyHash($this->keyHash($key), $value);
    }

    public function leafHashForKeyHash(string $keyHashHex, string $value): string
    {
        $this->assertHash($keyHashHex);

        return $this->leafHashForKeyHashAndValueHash($keyHashHex, $this->valueHash($value));
    }

    public function leafHashForKeyHashAndValueHash(string $keyHashHex, string $valueHashHex): string
    {
        $this->assertHash($keyHashHex);
        $this->assertHash($valueHashHex);

        return Blake2s::hashHex(hex2bin($keyHashHex) . hex2bin($valueHashHex) . "\0");
    }

    public function branchHash(string $leftHex, string $rightHex): string
    {
        $this->assertHash($leftHex);
        $this->assertHash($rightHex);
        if ($leftHex === self::EMPTY_HASH && $rightHex === self::EMPTY_HASH) {
            return self::EMPTY_HASH;
        }

        return Blake2s::hashHex(hex2bin($leftHex) . hex2bin($rightHex));
    }

    public function bitAt(string $hashHex, int $depth): int
    {
        $this->assertHash($hashHex);
        if ($depth < 0 || $depth > 255) {
            throw new \InvalidArgumentException('Depth must be between 0 and 255');
        }
        $byte = ord(hex2bin(substr($hashHex, intdiv($depth, 8) * 2, 2)));
        return ($byte >> (7 - ($depth % 8))) & 1;
    }

    private function assertHash(string $hashHex): void
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $hashHex)) {
            throw new \InvalidArgumentException('Expected lowercase 32-byte hash hex');
        }
    }
}
