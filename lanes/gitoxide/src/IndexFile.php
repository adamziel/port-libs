<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class IndexFile
{
    private const SIGNATURE = 'DIRC';
    private const VERSION_V2 = 2;
    private const VERSION_V3 = 3;
    private const HASH_BYTES = 20;
    private const ENTRY_BASE_BYTES = 62;
    private const PATH_LENGTH_MASK = 0x0fff;
    private const FLAG_STAGE_MASK = 0x3000;
    private const FLAG_EXTENDED = 0x4000;
    private const FLAG_ASSUME_VALID = 0x8000;
    private const EXTENDED_INTENT_TO_ADD = 0x2000;
    private const EXTENDED_SKIP_WORKTREE = 0x4000;

    /**
     * @param list<IndexEntry> $entries
     */
    public static function write(string $path, array $entries, ?IndexCacheTree $cacheTree = null): string
    {
        $bytes = self::bytesFor($entries, $cacheTree);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create index directory: {$directory}");
        }
        if (file_put_contents($path, $bytes) === false) {
            throw new \RuntimeException("Unable to write index: {$path}");
        }

        return self::checksum($bytes);
    }

    /**
     * @param list<IndexEntry> $entries
     */
    public static function bytesFor(array $entries, ?IndexCacheTree $cacheTree = null): string
    {
        foreach ($entries as $entry) {
            if (!$entry instanceof IndexEntry) {
                throw new \InvalidArgumentException('Index file entries must be IndexEntry instances');
            }
        }

        usort($entries, static fn (IndexEntry $left, IndexEntry $right): int => strcmp($left->path, $right->path) ?: $left->stage <=> $right->stage);
        $version = self::requiresVersion3($entries) ? self::VERSION_V3 : self::VERSION_V2;

        $bytes = self::SIGNATURE . pack('N2', $version, count($entries));
        foreach ($entries as $entry) {
            $bytes .= self::entryBytes($entry);
        }
        if ($cacheTree !== null) {
            $bytes .= $cacheTree->extensionBytes();
        }

        return $bytes . hex2bin(hash('sha1', $bytes));
    }

    /**
     * @param callable(string): GitObject $readObject
     * @return list<IndexEntry>
     */
    public static function entriesForCheckout(
        Tree $tree,
        callable $readObject,
        ?SparseCheckoutSpec $sparseCheckout = null,
    ): array {
        $entries = [];
        self::appendCheckoutEntries($tree, '', $readObject, $sparseCheckout, $entries);
        usort($entries, static fn (IndexEntry $left, IndexEntry $right): int => strcmp($left->path, $right->path));

        return $entries;
    }

    /**
     * @param callable(string): GitObject $readObject
     */
    public static function bytesForCheckout(
        Tree $tree,
        callable $readObject,
        ?SparseCheckoutSpec $sparseCheckout = null,
    ): string {
        return self::bytesFor(
            self::entriesForCheckout($tree, $readObject, $sparseCheckout),
            IndexCacheTree::fromTree($tree, $readObject),
        );
    }

    /**
     * @return list<IndexEntry>
     */
    public static function entriesFromBytes(string $bytes): array
    {
        return self::parseIndex($bytes)['entries'];
    }

    public static function cacheTreeFromBytes(string $bytes): ?IndexCacheTree
    {
        foreach (self::extensionPayloads($bytes) as $signature => $payloads) {
            if ($signature === IndexCacheTree::SIGNATURE) {
                return IndexCacheTree::fromBody($payloads[0]);
            }
        }

        return null;
    }

    /**
     * @param callable(string): GitObject $readObject
     */
    public static function verifyCheckoutCacheTree(string $bytes, Tree $tree, callable $readObject): IndexCacheTree
    {
        $parsed = self::parseIndex($bytes);
        $cacheTree = null;
        foreach (self::extensionPayloadsFromParsed($bytes, $parsed) as $signature => $payloads) {
            if ($signature === IndexCacheTree::SIGNATURE) {
                $cacheTree = IndexCacheTree::fromBody($payloads[0]);
                break;
            }
        }

        if ($cacheTree === null) {
            throw new \RuntimeException('Index does not contain a TREE cache extension');
        }

        $cacheTree->verifyEntryCounts(count($parsed['entries']));
        $cacheTree->verifyCheckoutTree($tree, $readObject);

        return $cacheTree;
    }

    public static function versionFromBytes(string $bytes): int
    {
        return self::parseIndex($bytes)['version'];
    }

    public static function checksum(string $bytes): string
    {
        if (strlen($bytes) < self::HASH_BYTES) {
            throw new \InvalidArgumentException('Index is too small to contain a checksum');
        }

        return bin2hex(substr($bytes, -self::HASH_BYTES));
    }

    /**
     * @param list<IndexEntry> $entries
     */
    private static function requiresVersion3(array $entries): bool
    {
        foreach ($entries as $entry) {
            if ($entry->skipWorktree) {
                return true;
            }
        }

        return false;
    }

    private static function entryBytes(IndexEntry $entry): string
    {
        $mode = self::indexMode($entry->mode);
        $pathLength = min(strlen($entry->path), self::PATH_LENGTH_MASK);
        $flags = (($entry->stage & 0x03) << 12) | $pathLength;
        if ($entry->assumeValid) {
            $flags |= self::FLAG_ASSUME_VALID;
        }
        if ($entry->skipWorktree) {
            $flags |= self::FLAG_EXTENDED;
        }
        $oidBytes = hex2bin($entry->oid);
        if ($oidBytes === false) {
            throw new \RuntimeException('Unable to decode index entry object id');
        }

        $bytes = pack('N10', 0, 0, 0, 0, 0, 0, $mode, 0, 0, 0)
            . $oidBytes
            . pack('n', $flags);
        if ($entry->skipWorktree) {
            $bytes .= pack('n', self::EXTENDED_SKIP_WORKTREE);
        }
        $bytes .= $entry->path . "\0";
        $padding = (8 - (strlen($bytes) % 8)) % 8;

        return $bytes . str_repeat("\0", $padding);
    }

    private static function indexMode(string $mode): int
    {
        $value = octdec($mode);
        if (!in_array($value, [0o100644, 0o100755, 0o120000, 0o160000, 0o040000], true)) {
            throw new \RuntimeException("Unsupported Git index entry mode: {$mode}");
        }

        return $value;
    }

    private static function checkoutMode(TreeEntry $entry): string
    {
        return match ($entry->kind()) {
            'blob' => '100644',
            'blob-executable' => '100755',
            'link' => '120000',
            'commit' => '160000',
            default => throw new \RuntimeException("Cannot create checkout index entry for {$entry->kind()}"),
        };
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param list<IndexEntry> $entries
     */
    private static function appendCheckoutEntries(
        Tree $tree,
        string $prefix,
        callable $readObject,
        ?SparseCheckoutSpec $sparseCheckout,
        array &$entries,
    ): void {
        foreach ($tree->entries as $entry) {
            $path = $prefix === '' ? $entry->filename : $prefix . '/' . $entry->filename;
            if ($entry->isTree()) {
                $object = $readObject($entry->oid);
                if (!$object instanceof GitObject) {
                    throw new \RuntimeException('Object reader must return GitObject instances');
                }
                if ($object->type !== 'tree') {
                    throw new \RuntimeException("Expected tree object for {$entry->oid}, got {$object->type}");
                }
                self::appendCheckoutEntries(Tree::fromObject($object), $path, $readObject, $sparseCheckout, $entries);
                continue;
            }

            $entries[] = new IndexEntry(
                $path,
                IndexEntry::STAGE_NORMAL,
                self::checkoutMode($entry),
                $entry->oid,
                $sparseCheckout?->skipWorktree($path, false) ?? false,
            );
        }
    }

    /**
     * @return array{version:int,entries:list<IndexEntry>,extensionOffset:int}
     */
    private static function parseIndex(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 12 + self::HASH_BYTES || !str_starts_with($bytes, self::SIGNATURE)) {
            throw new \InvalidArgumentException('Index is too small or missing the DIRC signature');
        }

        $expectedChecksum = substr($bytes, -self::HASH_BYTES);
        $actualChecksum = hex2bin(hash('sha1', substr($bytes, 0, -self::HASH_BYTES)));
        if ($expectedChecksum !== $actualChecksum) {
            throw new \RuntimeException('Index checksum mismatch');
        }

        $offset = 4;
        $version = self::readUInt32($bytes, $offset);
        if (!in_array($version, [self::VERSION_V2, self::VERSION_V3], true)) {
            throw new \InvalidArgumentException("Unsupported index version: {$version}");
        }
        $count = self::readUInt32($bytes, $offset);

        $entries = [];
        $dataEnd = $length - self::HASH_BYTES;
        for ($i = 0; $i < $count; $i++) {
            $entryStart = $offset;
            if ($entryStart + self::ENTRY_BASE_BYTES > $dataEnd) {
                throw new \InvalidArgumentException('Index entry is truncated');
            }

            $modeOffset = $entryStart + 24;
            $mode = decoct(self::readUInt32At($bytes, $modeOffset));
            $oid = bin2hex(substr($bytes, $entryStart + 40, self::HASH_BYTES));
            $flags = self::readUInt16At($bytes, $entryStart + 60);
            $stage = ($flags & self::FLAG_STAGE_MASK) >> 12;
            $pathStart = $entryStart + self::ENTRY_BASE_BYTES;
            $extendedFlags = 0;
            if (($flags & self::FLAG_EXTENDED) !== 0) {
                if ($version < self::VERSION_V3) {
                    throw new \InvalidArgumentException('Index v2 entry cannot contain extended flags');
                }
                if ($pathStart + 2 > $dataEnd) {
                    throw new \InvalidArgumentException('Index entry extended flags are truncated');
                }
                $extendedFlags = self::readUInt16At($bytes, $pathStart);
                $pathStart += 2;
            }

            $pathLength = $flags & self::PATH_LENGTH_MASK;
            if ($pathLength === self::PATH_LENGTH_MASK) {
                $nul = strpos($bytes, "\0", $pathStart);
            } else {
                $nul = $pathStart + $pathLength;
                if ($nul >= $dataEnd || ($bytes[$nul] ?? null) !== "\0") {
                    throw new \InvalidArgumentException('Index entry path length does not match its terminator');
                }
            }
            if ($nul === false || $nul >= $dataEnd) {
                throw new \InvalidArgumentException('Index entry path is not NUL-terminated');
            }

            $entries[] = new IndexEntry(
                substr($bytes, $pathStart, $nul - $pathStart),
                $stage,
                $mode,
                $oid,
                ($extendedFlags & self::EXTENDED_SKIP_WORKTREE) !== 0,
                ($flags & self::FLAG_ASSUME_VALID) !== 0,
            );
            $offset = $nul + 1;
            while (($offset - $entryStart) % 8 !== 0) {
                $offset++;
            }
            if ($offset > $dataEnd) {
                throw new \InvalidArgumentException('Index entry padding exceeds index payload');
            }
        }

        return ['version' => $version, 'entries' => $entries, 'extensionOffset' => $offset];
    }

    /**
     * @return array<string,list<string>>
     */
    private static function extensionPayloads(string $bytes): array
    {
        return self::extensionPayloadsFromParsed($bytes, self::parseIndex($bytes));
    }

    /**
     * @param array{version:int,entries:list<IndexEntry>,extensionOffset:int} $parsed
     * @return array<string,list<string>>
     */
    private static function extensionPayloadsFromParsed(string $bytes, array $parsed): array
    {
        $offset = $parsed['extensionOffset'];
        $dataEnd = strlen($bytes) - self::HASH_BYTES;
        $extensions = [];

        while ($offset < $dataEnd) {
            if ($offset + 8 > $dataEnd) {
                throw new \InvalidArgumentException('Index extension header is truncated');
            }
            $signature = substr($bytes, $offset, 4);
            $size = self::readUInt32At($bytes, $offset + 4);
            $offset += 8;
            if ($offset + $size > $dataEnd) {
                throw new \InvalidArgumentException("Index extension {$signature} is truncated");
            }
            $extensions[$signature] ??= [];
            $extensions[$signature][] = substr($bytes, $offset, $size);
            $offset += $size;
        }

        return $extensions;
    }

    private static function readUInt32(string $bytes, int &$offset): int
    {
        $value = self::readUInt32At($bytes, $offset);
        $offset += 4;

        return $value;
    }

    private static function readUInt32At(string $bytes, int $offset): int
    {
        $value = unpack('N', substr($bytes, $offset, 4));
        if ($value === false) {
            throw new \InvalidArgumentException('Unable to read index uint32');
        }

        return $value[1];
    }

    private static function readUInt16At(string $bytes, int $offset): int
    {
        $value = unpack('n', substr($bytes, $offset, 2));
        if ($value === false) {
            throw new \InvalidArgumentException('Unable to read index uint16');
        }

        return $value[1];
    }
}
