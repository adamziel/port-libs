<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class MergeWorktreeFile
{
    public function __construct(
        public readonly string $path,
        public readonly string $mode,
        public readonly string $oid,
        public readonly string $content,
    ) {
        if ($path === '' || str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Merge worktree path must be non-empty and cannot contain NUL bytes');
        }
        TreeEntry::assertValidMode($mode);
        if (!preg_match('/^[0-9a-f]{40}$/', $oid)) {
            throw new \InvalidArgumentException('Merge worktree object id must be a 40-character SHA-1 hex string');
        }
    }
}
