<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class RemoteRef
{
    private function __construct(
        public readonly string $kind,
        public readonly string $name,
        public readonly ?string $object,
        public readonly ?string $target,
        public readonly ?string $tag,
    ) {
        if (!in_array($kind, ['direct', 'symbolic', 'peeled', 'unborn'], true)) {
            throw new \InvalidArgumentException("Unsupported remote ref kind: {$kind}");
        }
        if ($name === '') {
            throw new \InvalidArgumentException('Remote ref name cannot be empty');
        }
        foreach (['object' => $object, 'tag' => $tag] as $label => $oid) {
            if ($oid !== null && preg_match('/^(?:[0-9a-f]{40}|[0-9a-f]{64})$/', $oid) !== 1) {
                throw new \InvalidArgumentException("Remote ref {$label} must be a SHA-1 or SHA-256 object id");
            }
        }
    }

    public static function direct(string $name, string $object): self
    {
        return new self('direct', $name, strtolower($object), null, null);
    }

    public static function symbolic(string $name, string $target, string $object, ?string $tag = null): self
    {
        return new self('symbolic', $name, strtolower($object), $target, $tag === null ? null : strtolower($tag));
    }

    public static function peeled(string $name, string $tag, string $object): self
    {
        return new self('peeled', $name, strtolower($object), null, strtolower($tag));
    }

    public static function unborn(string $name, string $target): self
    {
        return new self('unborn', $name, null, $target, null);
    }
}
