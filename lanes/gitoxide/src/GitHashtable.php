<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitHashtable
{
    private const UINT32_MODULUS = 4294967296;

    private function __construct()
    {
    }

    public static function hasher(): GitHashtableHasher
    {
        return new GitHashtableHasher();
    }

    public static function uint64ToNativeEndianBytes(int $value): string
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Only non-negative u64 values that fit in a PHP integer are supported');
        }

        $high = intdiv($value, self::UINT32_MODULUS);
        $low = $value % self::UINT32_MODULUS;

        if (self::isLittleEndian()) {
            return pack('V2', $low, $high);
        }

        return pack('N2', $high, $low);
    }

    public static function uint64FromNativeEndianBytes(string $bytes): int
    {
        if (strlen($bytes) < 8) {
            throw new \InvalidArgumentException('Hasher input must contain at least 8 bytes');
        }

        $chunk = substr($bytes, 0, 8);
        $parts = self::isLittleEndian()
            ? unpack('Vlo/Vhi', $chunk)
            : unpack('Nhi/Nlo', $chunk);
        if ($parts === false) {
            throw new \InvalidArgumentException('Hasher input must contain at least 8 bytes');
        }

        $high = $parts['hi'];
        $low = $parts['lo'];
        if ($high > intdiv(PHP_INT_MAX - $low, self::UINT32_MODULUS)) {
            throw new \OverflowException('Hasher value exceeds PHP_INT_MAX');
        }

        return $high * self::UINT32_MODULUS + $low;
    }

    public static function isLittleEndian(): bool
    {
        return pack('S', 1) === "\x01\x00";
    }
}

final class GitHashtableHasher
{
    private int $hash = 0;

    public function write(string $bytes): void
    {
        $this->hash = GitHashtable::uint64FromNativeEndianBytes($bytes);
    }

    public function finish(): int
    {
        return $this->hash;
    }

    public function writeUsize(int $value): void
    {
        throw new \LogicException('This hasher only supports manually verified `Hash` implementations');
    }
}
