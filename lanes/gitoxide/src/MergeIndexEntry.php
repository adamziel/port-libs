<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class MergeIndexEntry
{
    public const STAGE_ANCESTOR = 1;
    public const STAGE_OURS = 2;
    public const STAGE_THEIRS = 3;

    public function __construct(
        public readonly string $path,
        public readonly int $stage,
        public readonly string $mode,
        public readonly string $oid,
    ) {
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Merge index path must be non-empty and cannot contain NUL bytes');
        }
        if (!in_array($stage, [self::STAGE_ANCESTOR, self::STAGE_OURS, self::STAGE_THEIRS], true)) {
            throw new \InvalidArgumentException("Unsupported merge index stage: {$stage}");
        }
        TreeEntry::assertValidMode($mode);
        if (!preg_match('/^[0-9a-f]{40}$/', $oid)) {
            throw new \InvalidArgumentException('Merge index object id must be a 40-character SHA-1 hex string');
        }
    }

    public function side(): string
    {
        return match ($this->stage) {
            self::STAGE_ANCESTOR => 'ancestor',
            self::STAGE_OURS => 'ours',
            self::STAGE_THEIRS => 'theirs',
        };
    }
}
