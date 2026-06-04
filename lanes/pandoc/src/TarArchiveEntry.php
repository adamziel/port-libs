<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class TarArchiveEntry
{
    public const TYPE_FILE = 'file';
    public const TYPE_DIRECTORY = 'directory';

    /**
     * @param array<string, string> $paxHeaders
     */
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly int $size,
        public readonly int $modifiedAt,
        public readonly int $mode,
        public readonly int $uid,
        public readonly int $gid,
        public readonly string $linkName,
        public readonly string $userName,
        public readonly string $groupName,
        public readonly array $paxHeaders,
        public readonly int $dataOffset,
    ) {
    }

    public function isRegularFile(): bool
    {
        return $this->type === self::TYPE_FILE;
    }

    public function isDirectory(): bool
    {
        return $this->type === self::TYPE_DIRECTORY;
    }
}
