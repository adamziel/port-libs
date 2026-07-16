<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class HashType
{
    public const NONE = 'none';
    public const MD5 = 'md5';
    public const SHA1 = 'sha1';
    public const CRC32 = 'crc32';
    public const SHA256 = 'sha256';
    public const SHA512 = 'sha512';
    public const QUICKXOR = 'quickxor';

    /**
     * @return list<string>
     */
    public static function supported(): array
    {
        return [
            self::MD5,
            self::SHA1,
            self::CRC32,
            self::SHA256,
            self::SHA512,
            self::QUICKXOR,
        ];
    }

    public static function fromString(string $name): string
    {
        return match ($name) {
            'none', 'None' => self::NONE,
            'md5', 'MD5' => self::MD5,
            'sha1', 'SHA-1', 'SHA1', 'Sha1' => self::SHA1,
            'crc32', 'CRC-32' => self::CRC32,
            'sha256', 'SHA-256', 'SHA256' => self::SHA256,
            'sha512', 'SHA-512', 'SHA512' => self::SHA512,
            'quickxor', 'quickxorhash', 'QuickXor', 'QuickXorHash' => self::QUICKXOR,
            default => throw new \InvalidArgumentException('unknown hash type "' . $name . '"'),
        };
    }

    public static function phpAlgorithm(string $type): string
    {
        return match ($type) {
            self::MD5 => 'md5',
            self::SHA1 => 'sha1',
            self::CRC32 => 'crc32b',
            self::SHA256 => 'sha256',
            self::SHA512 => 'sha512',
            self::QUICKXOR => throw new \InvalidArgumentException('quickxor is not a PHP hash() algorithm'),
            default => throw new \InvalidArgumentException('unsupported hash type "' . $type . '"'),
        };
    }

    public static function width(string $type): int
    {
        return match ($type) {
            self::NONE => 0,
            self::CRC32 => 8,
            self::MD5 => 32,
            self::SHA1 => 40,
            self::SHA256 => 64,
            self::SHA512 => 128,
            self::QUICKXOR => 40,
            default => throw new \InvalidArgumentException('unsupported hash type "' . $type . '"'),
        };
    }
}
