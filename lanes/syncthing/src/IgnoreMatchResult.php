<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class IgnoreMatchResult
{
    private function __construct(
        private readonly bool $ignored,
        private readonly bool $deletable,
        private readonly bool $caseFolded,
        private readonly bool $canSkipDir,
    ) {
    }

    public static function notIgnored(bool $caseFolded = false): self
    {
        return new self(false, false, $caseFolded, false);
    }

    public static function ignored(bool $deletable = false, bool $caseFolded = false, bool $canSkipDir = false): self
    {
        return new self(true, $deletable, $caseFolded, $canSkipDir);
    }

    public function isIgnored(): bool
    {
        return $this->ignored;
    }

    public function isDeletable(): bool
    {
        return $this->ignored && $this->deletable;
    }

    public function isCaseFolded(): bool
    {
        return $this->caseFolded;
    }

    public function canSkipDir(): bool
    {
        return $this->ignored && $this->canSkipDir;
    }
}
