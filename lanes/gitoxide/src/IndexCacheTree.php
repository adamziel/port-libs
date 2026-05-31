<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class IndexCacheTree
{
    public const SIGNATURE = 'TREE';

    /**
     * @param list<IndexCacheTree> $children
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $oid,
        public readonly ?int $numEntries,
        public readonly array $children = [],
    ) {
        if (str_contains($name, "\0") || str_contains($name, '/')) {
            throw new \InvalidArgumentException('Cache tree names cannot contain NUL bytes or slashes');
        }
        if ($numEntries !== null && $numEntries < 0) {
            throw new \InvalidArgumentException('Cache tree entry count cannot be negative');
        }
        if ($numEntries !== null && !is_string($oid)) {
            throw new \InvalidArgumentException('Valid cache tree entries require an object id');
        }
        if ($oid !== null && !preg_match('/^[0-9a-f]{40}$/', $oid)) {
            throw new \InvalidArgumentException('Cache tree object id must be a 40-character SHA-1 hex string');
        }
        foreach ($children as $child) {
            if (!$child instanceof self) {
                throw new \InvalidArgumentException('Cache tree children must be IndexCacheTree instances');
            }
        }

        $names = [];
        foreach ($this->sortedChildren($children) as $child) {
            if (isset($names[$child->name])) {
                throw new \InvalidArgumentException("Duplicate cache tree child: {$child->name}");
            }
            $names[$child->name] = true;
        }
    }

    /**
     * @param callable(string): GitObject $readObject
     */
    public static function fromTree(Tree $tree, callable $readObject): self
    {
        return self::fromTreeNode('', $tree->toObject()->oid(), $tree, $readObject)[0];
    }

    public static function fromExtensionBytes(string $bytes): self
    {
        if (strlen($bytes) < 8 || substr($bytes, 0, 4) !== self::SIGNATURE) {
            throw new \InvalidArgumentException('Cache tree extension is missing TREE signature');
        }

        $size = self::readUInt32At($bytes, 4);
        if (strlen($bytes) !== 8 + $size) {
            throw new \InvalidArgumentException('Cache tree extension size does not match payload length');
        }

        return self::fromBody(substr($bytes, 8));
    }

    public static function fromBody(string $body): self
    {
        $offset = 0;
        $tree = self::decodeOne($body, $offset);
        if ($offset !== strlen($body)) {
            throw new \InvalidArgumentException('Cache tree extension has trailing bytes');
        }

        return $tree;
    }

    public function bodyBytes(): string
    {
        $bytes = $this->name . "\0";
        $bytes .= ($this->numEntries === null ? '-1' : (string) $this->numEntries)
            . ' '
            . count($this->children)
            . "\n";

        if ($this->numEntries !== null) {
            $oidBytes = hex2bin((string) $this->oid);
            if ($oidBytes === false) {
                throw new \RuntimeException('Unable to decode cache tree object id');
            }
            $bytes .= $oidBytes;
        }

        foreach ($this->children as $child) {
            $bytes .= $child->bodyBytes();
        }

        return $bytes;
    }

    public function extensionBytes(): string
    {
        $body = $this->bodyBytes();
        $length = strlen($body);
        if ($length > 0xffffffff) {
            throw new \RuntimeException('Cache tree extension exceeds 4GB');
        }

        return self::SIGNATURE . pack('N', $length) . $body;
    }

    public function verifyEntryCounts(int $numIndexEntries): void
    {
        if ($this->name !== '') {
            throw new \RuntimeException("TREE root name must be empty, got {$this->name}");
        }
        $this->verifyEntryCountsRecursive($numIndexEntries);
    }

    public function childNamed(string $name): ?self
    {
        foreach ($this->children as $child) {
            if ($child->name === $name) {
                return $child;
            }
        }

        return null;
    }

    /**
     * @param callable(string): GitObject $readObject
     * @return array{0:IndexCacheTree,1:int}
     */
    private static function fromTreeNode(string $name, string $oid, Tree $tree, callable $readObject): array
    {
        $children = [];
        $numEntries = 0;

        foreach ($tree->entries as $entry) {
            if (!$entry->isTree()) {
                $numEntries++;
                continue;
            }

            $object = $readObject($entry->oid);
            if (!$object instanceof GitObject) {
                throw new \RuntimeException('Object reader must return GitObject instances');
            }
            if ($object->type !== 'tree') {
                throw new \RuntimeException("Expected tree object for {$entry->oid}, got {$object->type}");
            }

            [$child, $childEntries] = self::fromTreeNode($entry->filename, $entry->oid, Tree::fromObject($object), $readObject);
            $children[] = $child;
            $numEntries += $childEntries;
        }

        return [new self($name, $oid, $numEntries, self::sortChildren($children)), $numEntries];
    }

    private static function decodeOne(string $body, int &$offset): self
    {
        $length = strlen($body);
        $nul = strpos($body, "\0", $offset);
        if ($nul === false) {
            throw new \InvalidArgumentException('Cache tree entry is missing name terminator');
        }
        $name = substr($body, $offset, $nul - $offset);
        $offset = $nul + 1;

        $space = strpos($body, ' ', $offset);
        if ($space === false) {
            throw new \InvalidArgumentException('Cache tree entry is missing entry-count delimiter');
        }
        $entryCountBytes = substr($body, $offset, $space - $offset);
        if (!preg_match('/^-?[0-9]+$/', $entryCountBytes)) {
            throw new \InvalidArgumentException('Cache tree entry count is not an integer');
        }
        $entryCount = (int) $entryCountBytes;
        $offset = $space + 1;

        $newline = strpos($body, "\n", $offset);
        if ($newline === false) {
            throw new \InvalidArgumentException('Cache tree entry is missing subtree-count terminator');
        }
        $subtreeCountBytes = substr($body, $offset, $newline - $offset);
        if (!preg_match('/^[0-9]+$/', $subtreeCountBytes)) {
            throw new \InvalidArgumentException('Cache tree subtree count is not an unsigned integer');
        }
        $subtreeCount = (int) $subtreeCountBytes;
        $offset = $newline + 1;

        $oid = null;
        $numEntries = null;
        if ($entryCount >= 0) {
            if ($offset + 20 > $length) {
                throw new \InvalidArgumentException('Cache tree object id is truncated');
            }
            $oid = bin2hex(substr($body, $offset, 20));
            $numEntries = $entryCount;
            $offset += 20;
        }

        $children = [];
        for ($i = 0; $i < $subtreeCount; $i++) {
            $children[] = self::decodeOne($body, $offset);
        }

        $children = self::sortChildren($children);
        $names = [];
        foreach ($children as $child) {
            if (isset($names[$child->name])) {
                throw new \InvalidArgumentException("Duplicate cache tree child: {$child->name}");
            }
            $names[$child->name] = true;
        }

        return new self($name, $oid, $numEntries, $children);
    }

    /**
     * @param list<IndexCacheTree> $children
     * @return list<IndexCacheTree>
     */
    private static function sortChildren(array $children): array
    {
        usort($children, static fn (self $left, self $right): int => strcmp($left->name, $right->name));

        return $children;
    }

    /**
     * @param list<IndexCacheTree> $children
     * @return list<IndexCacheTree>
     */
    private function sortedChildren(array $children): array
    {
        return self::sortChildren($children);
    }

    private function verifyEntryCountsRecursive(int $numIndexEntries): void
    {
        if ($this->numEntries !== null && $this->numEntries > $numIndexEntries) {
            throw new \RuntimeException("TREE entry '{$this->name}' declared {$this->numEntries} entries, but the index only contains {$numIndexEntries} entries");
        }

        foreach ($this->children as $child) {
            $child->verifyEntryCountsRecursive($numIndexEntries);
        }
    }

    private static function readUInt32At(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('Unable to read cache tree uint32');
        }

        return $value[1];
    }
}
