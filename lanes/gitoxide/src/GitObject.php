<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitObject
{
    public function __construct(
        public readonly string $type,
        public readonly string $body,
    ) {
        self::assertType($type);
    }

    public static function fromStorageBytes(string $bytes): self
    {
        $header = self::decodeLooseHeader($bytes);
        $actual = strlen($bytes) - $header['headerLength'];
        if ($header['size'] !== $actual) {
            throw new \InvalidArgumentException("Git object body length mismatch: expected {$header['size']}, got {$actual}");
        }

        return self::fromLooseBytes($bytes);
    }

    public static function fromLooseBytes(string $bytes): self
    {
        $header = self::decodeLooseHeader($bytes);
        $body = substr($bytes, $header['headerLength']);
        if (strlen($body) < $header['size']) {
            throw new \InvalidArgumentException('object data was shorter than its size declared in the header');
        }

        return new self($header['type'], substr($body, 0, $header['size']));
    }

    public static function looseHeader(string $type, int $size): string
    {
        self::assertType($type);
        if ($size < 0) {
            throw new \InvalidArgumentException('Git object size must not be negative');
        }

        return $type . ' ' . $size . "\0";
    }

    /**
     * @return array{type: string, size: int, headerLength: int}
     */
    public static function decodeLooseHeader(string $bytes): array
    {
        $space = strpos($bytes, ' ');
        if ($space === false) {
            throw new \InvalidArgumentException("Expected '<type> <size>'");
        }

        $type = substr($bytes, 0, $space);
        if (!self::isSupportedType($type)) {
            throw new \InvalidArgumentException("Unknown object kind: {$type}");
        }

        $nul = strpos($bytes, "\0");
        if ($nul === false) {
            throw new \InvalidArgumentException('Did not find 0 byte in header');
        }

        $header = substr($bytes, 0, $nul);
        $sizeBytes = substr($bytes, $space + 1, $nul - $space - 1);
        if (!preg_match('/\A[+-]?[0-9]+\z/', $sizeBytes)) {
            throw new \InvalidArgumentException('Invalid Git object header: ' . $header);
        }
        $size = self::parseLooseHeaderSize($sizeBytes, $header);

        return [
            'type' => $type,
            'size' => $size,
            'headerLength' => $nul + 1,
        ];
    }

    public function storageBytes(): string
    {
        return self::looseHeader($this->type, strlen($this->body)) . $this->body;
    }

    public function oid(string $algorithm = 'sha1'): string
    {
        return hash($algorithm, $this->storageBytes());
    }

    private static function assertType(string $type): void
    {
        if (!self::isSupportedType($type)) {
            throw new \InvalidArgumentException("Unsupported Git object type: {$type}");
        }
    }

    private static function isSupportedType(string $type): bool
    {
        return in_array($type, ['blob', 'tree', 'commit', 'tag'], true);
    }

    private static function parseLooseHeaderSize(string $size, string $header): int
    {
        if (str_starts_with($size, '+')) {
            $size = substr($size, 1);
        } elseif (str_starts_with($size, '-')) {
            $digits = substr($size, 1);
            if ($digits !== '' && ltrim($digits, '0') === '') {
                return 0;
            }

            throw new \InvalidArgumentException('Invalid Git object header: ' . $header);
        }

        if ($size === '') {
            throw new \InvalidArgumentException('Invalid Git object header: ' . $header);
        }

        $max = (string) PHP_INT_MAX;
        $trimmed = ltrim($size, '0');
        if ($trimmed !== '' && (strlen($trimmed) > strlen($max) || (strlen($trimmed) === strlen($max) && strcmp($trimmed, $max) > 0))) {
            throw new \InvalidArgumentException('Git object size exceeds PHP integer range: ' . $header);
        }

        return (int) $size;
    }
}
