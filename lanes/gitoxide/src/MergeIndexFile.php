<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class MergeIndexFile
{
    private const SIGNATURE = 'DIRC';
    private const VERSION = 2;
    private const HASH_BYTES = 20;
    private const ENTRY_BASE_BYTES = 62;
    private const PATH_LENGTH_MASK = 0x0fff;

    /**
     * @param list<MergeIndexEntry> $entries
     */
    public static function write(string $path, array $entries): string
    {
        $bytes = self::bytesFor($entries);
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("Unable to create index directory: {$directory}");
        }
        if (file_put_contents($path, $bytes) === false) {
            throw new \RuntimeException("Unable to write merge index: {$path}");
        }

        return self::checksum($bytes);
    }

    /**
     * @param callable(string): GitObject $readObject
     */
    public static function writeResult(string $path, TreeMergeResult $result, callable $readObject): string
    {
        return self::write($path, self::entriesForResult($result, $readObject));
    }

    /**
     * @param callable(string): GitObject $readObject
     * @return list<MergeIndexEntry>
     */
    public static function entriesForResult(TreeMergeResult $result, callable $readObject): array
    {
        $expanded = [];
        foreach ($result->conflicts as $conflict) {
            foreach ([
                MergeIndexEntry::STAGE_ANCESTOR => $conflict->base,
                MergeIndexEntry::STAGE_OURS => $conflict->ours,
                MergeIndexEntry::STAGE_THEIRS => $conflict->theirs,
            ] as $stage => $entry) {
                if ($entry === null) {
                    continue;
                }
                $path = self::indexPathForConflictStage($conflict, $entry);
                array_push($expanded, ...self::expandEntry(new MergeIndexEntry($path, $stage, $entry->mode, $entry->oid), $readObject));
            }
        }
        usort($expanded, static fn (MergeIndexEntry $left, MergeIndexEntry $right): int => strcmp($left->path, $right->path) ?: $left->stage <=> $right->stage);

        return $expanded;
    }

    private static function indexPathForConflictStage(TreeMergeConflict $conflict, TreeEntry $entry): string
    {
        if (!in_array($conflict->reason, ['rename-rename', 'nested-directory-rename'], true)) {
            return $conflict->path;
        }

        $baseName = basename($conflict->path);
        if ($entry->filename === $baseName) {
            return $conflict->path;
        }

        $directory = dirname($conflict->path);

        return ($directory === '.' ? '' : $directory . '/') . $entry->filename;
    }

    /**
     * @param list<MergeIndexEntry> $entries
     */
    public static function bytesFor(array $entries): string
    {
        foreach ($entries as $entry) {
            if (!$entry instanceof MergeIndexEntry) {
                throw new \InvalidArgumentException('Merge index file entries must be MergeIndexEntry instances');
            }
        }

        usort($entries, static fn (MergeIndexEntry $left, MergeIndexEntry $right): int => strcmp($left->path, $right->path) ?: $left->stage <=> $right->stage);

        $bytes = self::SIGNATURE . pack('N2', self::VERSION, count($entries));
        foreach ($entries as $entry) {
            $bytes .= self::entryBytes($entry);
        }

        return $bytes . hex2bin(hash('sha1', $bytes));
    }

    /**
     * @return list<MergeIndexEntry>
     */
    public static function entriesFromBytes(string $bytes): array
    {
        $length = strlen($bytes);
        if ($length < 12 + self::HASH_BYTES || !str_starts_with($bytes, self::SIGNATURE)) {
            throw new \InvalidArgumentException('Merge index is too small or missing the DIRC signature');
        }

        $expectedChecksum = substr($bytes, -self::HASH_BYTES);
        $actualChecksum = hex2bin(hash('sha1', substr($bytes, 0, -self::HASH_BYTES)));
        if ($expectedChecksum !== $actualChecksum) {
            throw new \RuntimeException('Merge index checksum mismatch');
        }

        $offset = 4;
        $version = self::readUInt32($bytes, $offset);
        if ($version !== self::VERSION) {
            throw new \InvalidArgumentException("Unsupported merge index version: {$version}");
        }
        $count = self::readUInt32($bytes, $offset);

        $entries = [];
        for ($i = 0; $i < $count; $i++) {
            $entryStart = $offset;
            if ($entryStart + self::ENTRY_BASE_BYTES > $length - self::HASH_BYTES) {
                throw new \InvalidArgumentException('Merge index entry is truncated');
            }

            $modeOffset = $entryStart + 24;
            $mode = decoct(self::readUInt32At($bytes, $modeOffset));
            $oid = bin2hex(substr($bytes, $entryStart + 40, self::HASH_BYTES));
            $flags = self::readUInt16At($bytes, $entryStart + 60);
            $stage = ($flags >> 12) & 0x03;
            $pathStart = $entryStart + self::ENTRY_BASE_BYTES;
            $pathLength = $flags & self::PATH_LENGTH_MASK;
            if ($pathLength === self::PATH_LENGTH_MASK) {
                $nul = strpos($bytes, "\0", $pathStart);
            } else {
                $nul = $pathStart + $pathLength;
                if (($bytes[$nul] ?? null) !== "\0") {
                    throw new \InvalidArgumentException('Merge index entry path length does not match its terminator');
                }
            }
            if ($nul === false || $nul >= $length - self::HASH_BYTES) {
                throw new \InvalidArgumentException('Merge index entry path is not NUL-terminated');
            }

            $entries[] = new MergeIndexEntry(substr($bytes, $pathStart, $nul - $pathStart), $stage, $mode, $oid);
            $offset = $nul + 1;
            while (($offset - $entryStart) % 8 !== 0) {
                $offset++;
            }
        }

        if ($offset !== $length - self::HASH_BYTES) {
            throw new \InvalidArgumentException('Merge index has trailing data before its checksum');
        }

        return $entries;
    }

    public static function checksum(string $bytes): string
    {
        if (strlen($bytes) < self::HASH_BYTES) {
            throw new \InvalidArgumentException('Merge index is too small to contain a checksum');
        }

        return bin2hex(substr($bytes, -self::HASH_BYTES));
    }

    private static function entryBytes(MergeIndexEntry $entry): string
    {
        $mode = self::indexMode($entry->mode);
        $pathLength = min(strlen($entry->path), self::PATH_LENGTH_MASK);
        $flags = (($entry->stage & 0x03) << 12) | $pathLength;
        $oidBytes = hex2bin($entry->oid);
        if ($oidBytes === false) {
            throw new \RuntimeException('Unable to decode merge index object id');
        }

        $bytes = pack('N10', 0, 0, 0, 0, 0, 0, $mode, 0, 0, 0)
            . $oidBytes
            . pack('n', $flags)
            . $entry->path
            . "\0";
        $padding = (8 - (strlen($bytes) % 8)) % 8;

        return $bytes . str_repeat("\0", $padding);
    }

    private static function indexMode(string $mode): int
    {
        $value = octdec($mode);
        $kind = (new TreeEntry($mode, 'entry', str_repeat('0', 40)))->kind();
        if (!in_array($kind, ['blob', 'blob-executable', 'link', 'commit'], true)) {
            throw new \RuntimeException("Cannot write {$kind} merge entry to a Git index file");
        }
        if (!in_array($value, [0o100644, 0o100755, 0o120000, 0o160000], true)) {
            throw new \RuntimeException("Unsupported Git index entry mode: {$mode}");
        }

        return $value;
    }

    /**
     * @param callable(string): GitObject $readObject
     * @return list<MergeIndexEntry>
     */
    private static function expandEntry(MergeIndexEntry $entry, callable $readObject): array
    {
        $treeEntry = new TreeEntry($entry->mode, basename($entry->path), $entry->oid);
        if (!$treeEntry->isTree()) {
            return [$entry];
        }

        $expanded = [];
        self::expandTree(Tree::fromObject(self::readTypedObject($readObject, $entry->oid, 'tree')), $entry->path, $entry->stage, $readObject, $expanded);

        return $expanded;
    }

    /**
     * @param callable(string): GitObject $readObject
     * @param list<MergeIndexEntry> $expanded
     */
    private static function expandTree(Tree $tree, string $prefix, int $stage, callable $readObject, array &$expanded): void
    {
        foreach ($tree->entries as $entry) {
            $path = $prefix . '/' . $entry->filename;
            if ($entry->isTree()) {
                self::expandTree(Tree::fromObject(self::readTypedObject($readObject, $entry->oid, 'tree')), $path, $stage, $readObject, $expanded);
                continue;
            }

            $expanded[] = new MergeIndexEntry($path, $stage, $entry->mode, $entry->oid);
        }
    }

    /**
     * @param callable(string): GitObject $readObject
     */
    private static function readTypedObject(callable $readObject, string $oid, string $type): GitObject
    {
        $object = $readObject($oid);
        if (!$object instanceof GitObject) {
            throw new \RuntimeException('Object reader must return GitObject instances');
        }
        if ($object->type !== $type) {
            throw new \RuntimeException("Expected {$type} object for {$oid}, got {$object->type}");
        }

        return $object;
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
            throw new \InvalidArgumentException('Unable to read merge index uint32');
        }

        return $value[1];
    }

    private static function readUInt16At(string $bytes, int $offset): int
    {
        $value = unpack('n', substr($bytes, $offset, 2));
        if ($value === false) {
            throw new \InvalidArgumentException('Unable to read merge index uint16');
        }

        return $value[1];
    }
}
