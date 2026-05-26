<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class GitObject
{
    public function __construct(
        public readonly string $type,
        public readonly string $body,
    ) {
        if (!in_array($type, ['blob', 'tree', 'commit', 'tag'], true)) {
            throw new \InvalidArgumentException("Unsupported Git object type: {$type}");
        }
    }

    public static function fromStorageBytes(string $bytes): self
    {
        $nul = strpos($bytes, "\0");
        if ($nul === false) {
            throw new \InvalidArgumentException('Git object is missing header terminator');
        }

        $header = substr($bytes, 0, $nul);
        $body = substr($bytes, $nul + 1);
        if (!preg_match('/^(blob|tree|commit|tag) ([0-9]+)$/', $header, $matches)) {
            throw new \InvalidArgumentException('Invalid Git object header: ' . $header);
        }

        $length = (int) $matches[2];
        if ($length !== strlen($body)) {
            throw new \InvalidArgumentException("Git object body length mismatch: expected {$length}, got " . strlen($body));
        }

        return new self($matches[1], $body);
    }

    public function storageBytes(): string
    {
        return $this->type . ' ' . strlen($this->body) . "\0" . $this->body;
    }

    public function oid(string $algorithm = 'sha1'): string
    {
        return hash($algorithm, $this->storageBytes());
    }
}

