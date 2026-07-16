<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class PathspecMatch
{
    public const KIND_ALWAYS = 'always';
    public const KIND_VERBATIM = 'verbatim';
    public const KIND_PREFIX = 'prefix';
    public const KIND_WILDCARD = 'wildcard';

    public function __construct(
        public readonly PathspecPattern $pattern,
        public readonly int $sequenceNumber,
        public readonly string $kind,
    ) {
        if (!in_array($kind, [self::KIND_ALWAYS, self::KIND_VERBATIM, self::KIND_PREFIX, self::KIND_WILDCARD], true)) {
            throw new \InvalidArgumentException("Unsupported pathspec match kind: {$kind}");
        }
    }

    public function isExcluded(): bool
    {
        return $this->pattern->exclude;
    }
}
