<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class FetchFilterSpec
{
    public const BLOB_NONE = 'blob-none';
    public const BLOB_LIMIT = 'blob-limit';
    public const TREE_DEPTH = 'tree-depth';
    public const SPARSE_OID = 'sparse-oid';

    private function __construct(
        public readonly string $kind,
        public readonly string $spec,
        public readonly ?int $limit = null,
        public readonly ?int $depth = null,
        public readonly ?string $object = null,
    ) {
    }

    public static function blobNone(): self
    {
        return new self(self::BLOB_NONE, 'blob:none');
    }

    public static function blobLimit(int $bytes): self
    {
        if ($bytes < 0) {
            throw new \InvalidArgumentException('Blob limit filter must not be negative');
        }

        return new self(self::BLOB_LIMIT, 'blob:limit=' . $bytes, limit: $bytes);
    }

    public static function treeDepth(int $depth): self
    {
        if ($depth < 0) {
            throw new \InvalidArgumentException('Tree depth filter must not be negative');
        }

        return new self(self::TREE_DEPTH, 'tree:' . $depth, depth: $depth);
    }

    public static function sparseOid(string $objectId): self
    {
        self::assertObjectId($objectId);
        $objectId = strtolower($objectId);

        return new self(self::SPARSE_OID, 'sparse:oid=' . $objectId, object: $objectId);
    }

    public static function parse(string $spec): self
    {
        $spec = trim($spec);
        if ($spec === '') {
            throw new \InvalidArgumentException('Fetch filter spec cannot be empty');
        }

        if ($spec === 'blob:none') {
            return self::blobNone();
        }
        if (preg_match('/^blob:limit=([0-9]+)([kmgKMG]?)$/', $spec, $matches) === 1) {
            $limit = (int) $matches[1];
            $suffix = strtolower($matches[2]);
            $multiplier = match ($suffix) {
                'k' => 1024,
                'm' => 1024 ** 2,
                'g' => 1024 ** 3,
                default => 1,
            };

            return new self(self::BLOB_LIMIT, 'blob:limit=' . ($limit * $multiplier), limit: $limit * $multiplier);
        }
        if (preg_match('/^tree:([0-9]+)$/', $spec, $matches) === 1) {
            return self::treeDepth((int) $matches[1]);
        }
        if (preg_match('/^sparse:oid=([0-9a-fA-F]{40})$/', $spec, $matches) === 1) {
            return self::sparseOid($matches[1]);
        }

        throw new \InvalidArgumentException("Unsupported fetch filter spec: {$spec}");
    }

    public function requestArgument(): string
    {
        return 'filter ' . $this->spec;
    }

    public function includesObject(GitObject $object, int $treeDepth = 0): bool
    {
        return match ($this->kind) {
            self::BLOB_NONE => $object->type !== 'blob',
            self::BLOB_LIMIT => $object->type !== 'blob' || strlen($object->body) < ($this->limit ?? 0),
            self::TREE_DEPTH => !in_array($object->type, ['blob', 'tree'], true) || $treeDepth < ($this->depth ?? 0),
            self::SPARSE_OID => true,
            default => throw new \LogicException("Unknown fetch filter kind: {$this->kind}"),
        };
    }

    public function __toString(): string
    {
        return $this->spec;
    }

    private static function assertObjectId(string $objectId): void
    {
        if (preg_match('/^[0-9a-fA-F]{40}$/', $objectId) !== 1) {
            throw new \InvalidArgumentException('Sparse filter object id must be a 40-character SHA-1 hex string');
        }
    }
}
