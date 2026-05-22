<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class ReferenceTarget
{
    private const HASH_HEX_LENGTHS = [
        'sha1' => 40,
        'sha256' => 64,
    ];

    private function __construct(
        public readonly string $kind,
        public readonly string $value,
    ) {
    }

    public static function object(string $oid, string $algorithm = 'sha1'): self
    {
        self::assertValidObjectId($oid, $algorithm);

        return new self('object', strtolower($oid));
    }

    public static function symbolic(string $name): self
    {
        ReferenceName::assertValid($name);

        return new self('symbolic', $name);
    }

    public static function assertValidObjectId(string $oid, string $algorithm = 'sha1'): void
    {
        $length = self::hashHexLength($algorithm);
        if (preg_match('/^[0-9a-fA-F]{' . $length . '}$/', $oid) !== 1) {
            throw new \InvalidArgumentException("Reference target must be a {$length}-character {$algorithm} hex object id");
        }
    }

    public static function hashHexLength(string $algorithm): int
    {
        $normalized = strtolower($algorithm);
        if (!isset(self::HASH_HEX_LENGTHS[$normalized])) {
            throw new \InvalidArgumentException("Unsupported Git reference hash algorithm: {$algorithm}");
        }

        return self::HASH_HEX_LENGTHS[$normalized];
    }

    public function isObject(): bool
    {
        return $this->kind === 'object';
    }

    public function isSymbolic(): bool
    {
        return $this->kind === 'symbolic';
    }

    public function storageBytes(): string
    {
        if ($this->isSymbolic()) {
            return 'ref: ' . $this->value . "\n";
        }

        return $this->value . "\n";
    }
}
