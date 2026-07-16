<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class FetchWantedRef
{
    public function __construct(
        public readonly string $object,
        public readonly string $path,
    ) {
        if (preg_match('/^(?:[0-9a-fA-F]{40}|[0-9a-fA-F]{64})$/', $object) !== 1) {
            throw new \InvalidArgumentException("fetch response: invalid wanted-ref object {$object}");
        }
        if ($path === '' || str_contains($path, "\0") || str_contains($path, "\n") || str_contains($path, "\r")) {
            throw new \InvalidArgumentException('fetch response: invalid wanted-ref path');
        }
    }

    public static function fromLine(string $line): self
    {
        $line = ProtocolLine::trimEnd($line);
        [$object, $path] = array_pad(explode(' ', $line, 2), 2, null);
        if ($path === null) {
            throw new \InvalidArgumentException("fetch response: unknown line prefix in {$line}");
        }

        return new self(strtolower($object), $path);
    }
}
