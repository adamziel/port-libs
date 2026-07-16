<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class Key
{
    public const BYTE_LENGTH = 32;
    public const MAX_INTEGER = PHP_INT_MAX - 2;

    private string $bytes;

    public function __construct(string $bytes)
    {
        if (strlen($bytes) !== self::BYTE_LENGTH) {
            throw new \InvalidArgumentException('Quadrable keys must be exactly 32 bytes');
        }
        $this->bytes = $bytes;
    }

    public static function null(): self
    {
        return new self(str_repeat("\0", self::BYTE_LENGTH));
    }

    public static function max(): self
    {
        return new self(str_repeat("\xff", self::BYTE_LENGTH));
    }

    public static function hash(string $value): self
    {
        return new self(Blake2s::hash($value));
    }

    public static function fromHex(string $hex): self
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $hex)) {
            throw new \InvalidArgumentException('Expected lowercase 32-byte hash hex');
        }

        return new self(hex2bin($hex));
    }

    public static function fromInteger(int $number): self
    {
        if ($number < 0) {
            throw new \InvalidArgumentException('integer keys must be non-negative');
        }
        if ($number > self::MAX_INTEGER) {
            throw new \InvalidArgumentException('int range exceeded');
        }

        $valueBits = self::floorLog2($number + 2);
        $offset = (1 << $valueBits) - 2;
        $payload = $number - $offset;

        $key = self::null();
        $key->writeBits(0, 6, $valueBits - 1);
        $key->writeBits(6, $valueBits, $payload);

        return $key;
    }

    public static function fromIntegerAndHash(int $number, string $hash): self
    {
        $hashLength = strlen($hash);
        if ($hashLength < 23 || $hashLength > 31) {
            throw new \InvalidArgumentException('truncated hash should be 23-31 bytes');
        }

        $key = self::fromInteger($number);
        $key->bytes = substr($key->bytes, 0, self::BYTE_LENGTH - $hashLength) . $hash;

        return $key;
    }

    /**
     * @return array{input: string, hashHex: string, attempts: int}
     */
    public static function mineHashPrefix(string $prefix, int $start = 1, int $maxAttempts = 1000000): array
    {
        self::assertBitPrefix($prefix);
        if ($start < 0) {
            throw new \InvalidArgumentException('mineHash start must be non-negative');
        }
        if ($maxAttempts < 1) {
            throw new \InvalidArgumentException('mineHash max attempts must be positive');
        }

        $candidate = $start;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $input = (string) $candidate;
            $hash = self::hash($input);
            if ($hash->hasBitPrefix($prefix)) {
                return [
                    'input' => $input,
                    'hashHex' => $hash->hex(),
                    'attempts' => $attempt,
                ];
            }

            if ($candidate === PHP_INT_MAX) {
                break;
            }
            $candidate++;
        }

        throw new \RuntimeException('unable to mine hash prefix within attempt limit');
    }

    public static function hashMatchesBitPrefix(string $value, string $prefix): bool
    {
        return self::hash($value)->hasBitPrefix($prefix);
    }

    public function toInteger(): int
    {
        for ($i = 16; $i < self::BYTE_LENGTH; $i++) {
            if (ord($this->bytes[$i]) !== 0) {
                throw new \RuntimeException('hash is not in integer format');
            }
        }

        $bitsMinusOne = $this->readBits(0, 6);
        $valueBits = $bitsMinusOne + 1;
        if ($valueBits > 62) {
            throw new \RuntimeException('int range exceeded');
        }

        $payload = $this->readBits(6, $valueBits);
        $offset = (1 << $valueBits) - 2;

        return $payload + $offset;
    }

    public function hasBitPrefix(string $prefix): bool
    {
        self::assertBitPrefix($prefix);

        $length = strlen($prefix);
        for ($i = 0; $i < $length; $i++) {
            if ($this->getBit($i) !== (int) $prefix[$i]) {
                return false;
            }
        }

        return true;
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    public function hex(): string
    {
        return bin2hex($this->bytes);
    }

    public function getBit(int $offset): int
    {
        if ($offset < 0 || $offset > 255) {
            throw new \InvalidArgumentException('bit offset must be between 0 and 255');
        }

        return (ord($this->bytes[intdiv($offset, 8)]) >> (7 - ($offset % 8))) & 1;
    }

    public function setBit(int $offset, int $bit): void
    {
        if ($offset < 0 || $offset > 255) {
            throw new \InvalidArgumentException('bit offset must be between 0 and 255');
        }
        if ($bit !== 0 && $bit !== 1) {
            throw new \InvalidArgumentException('bit must be 0 or 1');
        }

        $byteOffset = intdiv($offset, 8);
        $mask = 0x80 >> ($offset % 8);
        $byte = ord($this->bytes[$byteOffset]);
        $byte = $bit === 1 ? ($byte | $mask) : ($byte & ~$mask);
        $this->bytes[$byteOffset] = chr($byte);
    }

    public function keepPrefixBits(int $bitCount): void
    {
        if ($bitCount < 0 || $bitCount > 256) {
            throw new \InvalidArgumentException('prefix length must be between 0 and 256');
        }
        for ($i = $bitCount; $i < 256; $i++) {
            $this->setBit($i, 0);
        }
    }

    private static function floorLog2(int $value): int
    {
        $bits = 0;
        while ($value > 1) {
            $value = intdiv($value, 2);
            $bits++;
        }

        return $bits;
    }

    private static function assertBitPrefix(string $prefix): void
    {
        if (strlen($prefix) > 256) {
            throw new \InvalidArgumentException('bit prefix must be at most 256 bits');
        }
        if (!preg_match('/^[01]*$/', $prefix)) {
            throw new \InvalidArgumentException('bit prefix must contain only 0 and 1');
        }
    }

    private function writeBits(int $offset, int $count, int $value): void
    {
        for ($i = 0; $i < $count; $i++) {
            $shift = $count - $i - 1;
            $this->setBit($offset + $i, ($value >> $shift) & 1);
        }
    }

    private function readBits(int $offset, int $count): int
    {
        $value = 0;
        for ($i = 0; $i < $count; $i++) {
            $value = ($value << 1) | $this->getBit($offset + $i);
        }

        return $value;
    }
}
