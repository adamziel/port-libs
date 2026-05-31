<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class FetchShallowUpdate
{
    public const SHALLOW = 'shallow';
    public const UNSHALLOW = 'unshallow';

    private function __construct(
        public readonly string $kind,
        public readonly string $object,
    ) {
    }

    public static function shallow(string $objectId): self
    {
        return new self(self::SHALLOW, self::normalizeObjectId($objectId));
    }

    public static function unshallow(string $objectId): self
    {
        return new self(self::UNSHALLOW, self::normalizeObjectId($objectId));
    }

    public static function fromLine(string $line): self
    {
        $line = rtrim($line, "\r\n");
        [$prefix, $objectId] = array_pad(explode(' ', $line, 2), 2, null);
        if ($objectId === null) {
            throw new \InvalidArgumentException("fetch response: unknown line prefix in {$line}");
        }

        return match ($prefix) {
            'shallow' => self::shallow($objectId),
            'unshallow' => self::unshallow($objectId),
            default => throw new \InvalidArgumentException("fetch response: unknown line prefix in {$line}"),
        };
    }

    private static function normalizeObjectId(string $objectId): string
    {
        if (preg_match('/^(?:[0-9a-fA-F]{40}|[0-9a-fA-F]{64})$/', $objectId) !== 1) {
            throw new \InvalidArgumentException("fetch response: unknown line prefix in shallow {$objectId}");
        }

        return strtolower($objectId);
    }
}
