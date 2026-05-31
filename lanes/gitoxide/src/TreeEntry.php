<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class TreeEntry
{
    public function __construct(
        public readonly string $mode,
        public readonly string $filename,
        public readonly string $oid,
    ) {
        self::assertValidMode($mode);
        if (str_contains($filename, "\0")) {
            throw new \InvalidArgumentException('Tree entry filename cannot contain NUL bytes');
        }
        if (!preg_match('/^(?:[0-9a-f]{40}|[0-9a-f]{64})$/', $oid)) {
            throw new \InvalidArgumentException('Tree entry object id must be a 40-character SHA-1 or 64-character SHA-256 hex string');
        }
    }

    public static function assertValidMode(string $mode): void
    {
        if (!preg_match('/^[0-7]{1,7}$/', $mode)) {
            throw new \InvalidArgumentException('Tree entry mode must be one to seven octal digits');
        }
    }

    public static function assertValidPathComponent(string $filename, string $path): void
    {
        if ($filename === '') {
            throw new \InvalidArgumentException("The path \"{$path}\" is invalid: tree path components cannot be empty");
        }
        if (str_contains($filename, '/') || str_contains($filename, '\\')) {
            throw new \InvalidArgumentException("The path \"{$path}\" is invalid: Path separators like / or \\ are not allowed");
        }
    }

    public function kind(): string
    {
        $value = octdec($this->mode);
        $type = $value & 0o170000;

        if ($type === 0o100000) {
            return ($value & 0o000100) === 0o000100 ? 'blob-executable' : 'blob';
        }
        if ($type === 0o120000) {
            return 'link';
        }
        if ($type === 0o040000) {
            return 'tree';
        }

        return 'commit';
    }

    public function isTree(): bool
    {
        return $this->kind() === 'tree';
    }

    public function isBlob(): bool
    {
        return $this->kind() === 'blob' || $this->kind() === 'blob-executable';
    }

    public function isExecutable(): bool
    {
        return $this->kind() === 'blob-executable';
    }

    public function isLink(): bool
    {
        return $this->kind() === 'link';
    }

    public function isCommit(): bool
    {
        return $this->kind() === 'commit';
    }
}
