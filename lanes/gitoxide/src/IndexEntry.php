<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class IndexEntry
{
    public const STAGE_NORMAL = 0;
    public const STAGE_ANCESTOR = 1;
    public const STAGE_OURS = 2;
    public const STAGE_THEIRS = 3;

    public function __construct(
        public readonly string $path,
        public readonly int $stage,
        public readonly string $mode,
        public readonly string $oid,
        public readonly bool $skipWorktree = false,
        public readonly bool $assumeValid = false,
    ) {
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Index path must be non-empty and cannot contain NUL bytes');
        }
        if (!in_array($stage, [
            self::STAGE_NORMAL,
            self::STAGE_ANCESTOR,
            self::STAGE_OURS,
            self::STAGE_THEIRS,
        ], true)) {
            throw new \InvalidArgumentException("Unsupported index stage: {$stage}");
        }
        TreeEntry::assertValidMode($mode);
        if (!preg_match('/^[0-9a-f]{40}$/', $oid)) {
            throw new \InvalidArgumentException('Index entry object id must be a 40-character SHA-1 hex string');
        }
    }

    public function side(): string
    {
        return match ($this->stage) {
            self::STAGE_NORMAL => 'normal',
            self::STAGE_ANCESTOR => 'ancestor',
            self::STAGE_OURS => 'ours',
            self::STAGE_THEIRS => 'theirs',
        };
    }
}
