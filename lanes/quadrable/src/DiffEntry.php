<?php

declare(strict_types=1);

namespace PortLibs\Quadrable;

final class DiffEntry
{
    public const ADDED = 'added';
    public const DELETED = 'deleted';
    public const CHANGED = 'changed';

    private Key $key;

    public function __construct(
        public readonly string $type,
        Key $key,
        public readonly string $value,
        public readonly int $nodeId = 0
    ) {
        if (!in_array($type, [self::ADDED, self::DELETED, self::CHANGED], true)) {
            throw new \InvalidArgumentException('unrecognized diff entry type');
        }
        if ($nodeId < 0) {
            throw new \InvalidArgumentException('diff node id must be non-negative');
        }

        $this->key = new Key($key->bytes());
    }

    public function key(): Key
    {
        return new Key($this->key->bytes());
    }

    public function keyHex(): string
    {
        return $this->key->hex();
    }
}
