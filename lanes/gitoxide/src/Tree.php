<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class Tree
{
    /**
     * @param list<TreeEntry> $entries
     */
    public function __construct(public readonly array $entries)
    {
        foreach ($entries as $entry) {
            if (!$entry instanceof TreeEntry) {
                throw new \InvalidArgumentException('Tree entries must be TreeEntry instances');
            }
        }
    }

    public static function fromObject(GitObject $object, string $algorithm = 'sha1'): self
    {
        if ($object->type !== 'tree') {
            throw new \InvalidArgumentException("Expected a tree object, got {$object->type}");
        }

        return self::parse($object->body, $algorithm);
    }

    public static function parse(string $body, string $algorithm = 'sha1'): self
    {
        $entries = [];
        $offset = 0;
        $length = strlen($body);
        $objectIdBytes = intdiv(ReferenceTarget::hashHexLength($algorithm), 2);

        while ($offset < $length) {
            $space = strpos($body, ' ', $offset);
            if ($space === false) {
                throw new \InvalidArgumentException('Tree entry is missing mode/name delimiter');
            }

            $mode = substr($body, $offset, $space - $offset);
            TreeEntry::assertValidMode($mode, true);

            $nameStart = $space + 1;
            $nul = strpos($body, "\0", $nameStart);
            if ($nul === false) {
                throw new \InvalidArgumentException('Tree entry is missing filename terminator');
            }

            $oidStart = $nul + 1;
            if ($oidStart + $objectIdBytes > $length) {
                throw new \InvalidArgumentException('Tree entry object id is truncated');
            }

            $entries[] = new TreeEntry(
                $mode,
                substr($body, $nameStart, $nul - $nameStart),
                bin2hex(substr($body, $oidStart, $objectIdBytes)),
            );
            $offset = $oidStart + $objectIdBytes;
        }

        return new self($entries);
    }

    public function storageBytes(): string
    {
        $bytes = '';
        foreach ($this->entries as $entry) {
            $oidBytes = hex2bin($entry->oid);
            if ($oidBytes === false) {
                throw new \RuntimeException('Unable to decode tree entry object id');
            }
            $bytes .= $entry->mode . ' ' . $entry->filename . "\0" . $oidBytes;
        }

        return $bytes;
    }

    public function toObject(): GitObject
    {
        return new GitObject('tree', $this->storageBytes());
    }

    public function entryNamed(string $filename, ?bool $isTree = null): ?TreeEntry
    {
        foreach ($this->entries as $entry) {
            if ($entry->filename !== $filename) {
                continue;
            }
            if ($isTree !== null && $entry->isTree() !== $isTree) {
                continue;
            }

            return $entry;
        }

        return null;
    }
}
