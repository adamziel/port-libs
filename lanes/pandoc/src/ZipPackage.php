<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ZipPackage
{
    private const EOCD_SIGNATURE = "PK\x05\x06";
    private const CENTRAL_DIRECTORY_SIGNATURE = "PK\x01\x02";
    private const LOCAL_FILE_SIGNATURE = "PK\x03\x04";

    /**
     * @param array<string, ZipPackageEntry> $entriesByName
     * @param list<ZipPackageEntry> $entries
     */
    private function __construct(
        private readonly string $bytes,
        private readonly array $entriesByName,
        private readonly array $entries,
        private readonly int $centralDirectoryOffset,
    ) {
    }

    public static function fromString(string $bytes): self
    {
        $eocdOffset = self::findEndOfCentralDirectory($bytes);
        $entryCount = self::readUInt16($bytes, $eocdOffset + 10);
        $centralDirectorySize = self::readUInt32($bytes, $eocdOffset + 12);
        $centralDirectoryOffset = self::readUInt32($bytes, $eocdOffset + 16);

        $diskNumber = self::readUInt16($bytes, $eocdOffset + 4);
        $centralDirectoryDisk = self::readUInt16($bytes, $eocdOffset + 6);
        $diskEntryCount = self::readUInt16($bytes, $eocdOffset + 8);

        if ($diskNumber !== 0 || $centralDirectoryDisk !== 0 || $diskEntryCount !== $entryCount) {
            throw new \RuntimeException('Split ZIP packages are not supported by the pandoc package reader');
        }

        if ($entryCount === 0xffff || $centralDirectorySize === 0xffffffff || $centralDirectoryOffset === 0xffffffff) {
            throw new \RuntimeException('ZIP64 packages are not supported by this bounded package reader');
        }

        self::assertRange($bytes, $centralDirectoryOffset, $centralDirectorySize, 'central directory');
        if ($centralDirectoryOffset + $centralDirectorySize > $eocdOffset) {
            throw new \RuntimeException('Central directory overlaps the end-of-central-directory record');
        }

        $entries = [];
        $entriesByName = [];
        $cursor = $centralDirectoryOffset;
        for ($index = 0; $index < $entryCount; $index++) {
            if (substr($bytes, $cursor, 4) !== self::CENTRAL_DIRECTORY_SIGNATURE) {
                throw new \RuntimeException("Invalid ZIP central directory header at entry {$index}");
            }

            self::assertRange($bytes, $cursor, 46, 'central directory entry');
            $flags = self::readUInt16($bytes, $cursor + 8);
            $method = self::readUInt16($bytes, $cursor + 10);
            $crc32 = self::readUInt32($bytes, $cursor + 16);
            $compressedSize = self::readUInt32($bytes, $cursor + 20);
            $uncompressedSize = self::readUInt32($bytes, $cursor + 24);
            $nameLength = self::readUInt16($bytes, $cursor + 28);
            $extraLength = self::readUInt16($bytes, $cursor + 30);
            $commentLength = self::readUInt16($bytes, $cursor + 32);
            $diskStart = self::readUInt16($bytes, $cursor + 34);
            $localHeaderOffset = self::readUInt32($bytes, $cursor + 42);
            $variableStart = $cursor + 46;
            $variableLength = $nameLength + $extraLength + $commentLength;
            self::assertRange($bytes, $variableStart, $variableLength, 'central directory entry variable fields');

            if (($flags & 0x0001) !== 0) {
                throw new \RuntimeException('Encrypted ZIP entries are not supported by the pandoc package reader');
            }

            if ($diskStart !== 0) {
                throw new \RuntimeException('Split ZIP entry data is not supported by the pandoc package reader');
            }

            if ($compressedSize === 0xffffffff || $uncompressedSize === 0xffffffff || $localHeaderOffset === 0xffffffff) {
                throw new \RuntimeException('ZIP64 entry sizes or offsets are not supported by this bounded package reader');
            }

            $name = substr($bytes, $variableStart, $nameLength);
            self::assertSafePartName($name);
            if (isset($entriesByName[$name])) {
                throw new \RuntimeException("Duplicate ZIP package entry: {$name}");
            }

            $comment = substr($bytes, $variableStart + $nameLength + $extraLength, $commentLength);
            $entry = new ZipPackageEntry(
                $name,
                $method,
                $flags,
                $crc32,
                $compressedSize,
                $uncompressedSize,
                $localHeaderOffset,
                $comment
            );

            $entries[] = $entry;
            $entriesByName[$name] = $entry;
            $cursor += 46 + $variableLength;
        }

        if ($cursor !== $centralDirectoryOffset + $centralDirectorySize) {
            throw new \RuntimeException('Central directory size does not match parsed ZIP entries');
        }

        return new self($bytes, $entriesByName, $entries, $centralDirectoryOffset);
    }

    /**
     * @return list<ZipPackageEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (ZipPackageEntry $entry): string => $entry->name, $this->entries);
    }

    public function has(string $partName): bool
    {
        return isset($this->entriesByName[$this->normalizeLookupPartName($partName)]);
    }

    public function entry(string $partName): ZipPackageEntry
    {
        $name = $this->normalizeLookupPartName($partName);
        if (!isset($this->entriesByName[$name])) {
            throw new \RuntimeException("ZIP package entry not found: {$partName}");
        }

        return $this->entriesByName[$name];
    }

    public function read(string $partName): string
    {
        $entry = $this->entry($partName);
        if ($entry->isDirectory()) {
            return '';
        }

        $compressed = $this->readCompressedEntryBytes($entry);
        $contents = match ($entry->compressionMethod) {
            0 => $compressed,
            8 => $this->inflateEntry($entry, $compressed),
            default => throw new \RuntimeException(
                "Unsupported ZIP compression method {$entry->compressionMethod} for entry {$entry->name}"
            ),
        };

        if (strlen($contents) !== $entry->uncompressedSize) {
            throw new \RuntimeException("ZIP entry {$entry->name} expanded to an unexpected size");
        }

        if (self::unsignedCrc32($contents) !== $entry->crc32) {
            throw new \RuntimeException("ZIP entry {$entry->name} failed CRC32 verification");
        }

        return $contents;
    }

    public function centralDirectoryOffset(): int
    {
        return $this->centralDirectoryOffset;
    }

    private static function findEndOfCentralDirectory(string $bytes): int
    {
        $length = strlen($bytes);
        $minimumSize = 22;
        if ($length < $minimumSize) {
            throw new \RuntimeException('ZIP package is too short to contain an end-of-central-directory record');
        }

        $searchStart = max(0, $length - ($minimumSize + 0xffff));
        for ($offset = $length - $minimumSize; $offset >= $searchStart; $offset--) {
            if (substr($bytes, $offset, 4) !== self::EOCD_SIGNATURE) {
                continue;
            }

            $commentLength = self::readUInt16($bytes, $offset + 20);
            if ($offset + $minimumSize + $commentLength === $length) {
                return $offset;
            }
        }

        throw new \RuntimeException('ZIP end-of-central-directory record not found');
    }

    private function readCompressedEntryBytes(ZipPackageEntry $entry): string
    {
        self::assertRange($this->bytes, $entry->localHeaderOffset, 30, 'local file header');
        if (substr($this->bytes, $entry->localHeaderOffset, 4) !== self::LOCAL_FILE_SIGNATURE) {
            throw new \RuntimeException("Invalid ZIP local file header for entry {$entry->name}");
        }

        $flags = self::readUInt16($this->bytes, $entry->localHeaderOffset + 6);
        $method = self::readUInt16($this->bytes, $entry->localHeaderOffset + 8);
        $nameLength = self::readUInt16($this->bytes, $entry->localHeaderOffset + 26);
        $extraLength = self::readUInt16($this->bytes, $entry->localHeaderOffset + 28);
        $nameStart = $entry->localHeaderOffset + 30;
        self::assertRange($this->bytes, $nameStart, $nameLength + $extraLength, 'local file header variable fields');

        $localName = substr($this->bytes, $nameStart, $nameLength);
        if ($localName !== $entry->name) {
            throw new \RuntimeException("ZIP local header name does not match central directory entry {$entry->name}");
        }

        if (($flags & 0x0001) !== 0 || $flags !== $entry->generalPurposeFlags) {
            throw new \RuntimeException("ZIP local header flags do not match central directory entry {$entry->name}");
        }

        if ($method !== $entry->compressionMethod) {
            throw new \RuntimeException("ZIP local header compression method does not match entry {$entry->name}");
        }

        $dataStart = $nameStart + $nameLength + $extraLength;
        self::assertRange($this->bytes, $dataStart, $entry->compressedSize, "compressed data for {$entry->name}");

        if ($dataStart + $entry->compressedSize > $this->centralDirectoryOffset) {
            throw new \RuntimeException("ZIP compressed data for {$entry->name} overlaps the central directory");
        }

        return substr($this->bytes, $dataStart, $entry->compressedSize);
    }

    private function inflateEntry(ZipPackageEntry $entry, string $compressed): string
    {
        $inflated = gzinflate($compressed);
        if ($inflated === false) {
            throw new \RuntimeException("Unable to inflate ZIP entry {$entry->name}");
        }

        return $inflated;
    }

    private function normalizeLookupPartName(string $partName): string
    {
        $name = ltrim($partName, '/');
        self::assertSafePartName($name);

        return $name;
    }

    private static function assertSafePartName(string $name): void
    {
        if ($name === '') {
            throw new \RuntimeException('ZIP package entry names must not be empty');
        }

        if (str_contains($name, "\0") || str_starts_with($name, '/') || str_contains($name, '\\')) {
            throw new \RuntimeException("Unsafe ZIP package entry name: {$name}");
        }

        $segments = explode('/', $name);
        foreach ($segments as $index => $segment) {
            $isTrailingDirectorySegment = $index === count($segments) - 1 && $segment === '';
            if ($isTrailingDirectorySegment) {
                continue;
            }

            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \RuntimeException("Unsafe ZIP package entry name: {$name}");
            }
        }
    }

    private static function assertRange(string $bytes, int $offset, int $length, string $label): void
    {
        if ($offset < 0 || $length < 0 || $offset > strlen($bytes) || $offset + $length > strlen($bytes)) {
            throw new \RuntimeException("ZIP package {$label} extends beyond available bytes");
        }
    }

    private static function readUInt16(string $bytes, int $offset): int
    {
        self::assertRange($bytes, $offset, 2, 'uint16');
        $values = unpack('vvalue', substr($bytes, $offset, 2));
        if (!is_array($values)) {
            throw new \RuntimeException('Unable to read ZIP uint16 value');
        }

        return (int) $values['value'];
    }

    private static function readUInt32(string $bytes, int $offset): int
    {
        self::assertRange($bytes, $offset, 4, 'uint32');
        $values = unpack('Vvalue', substr($bytes, $offset, 4));
        if (!is_array($values)) {
            throw new \RuntimeException('Unable to read ZIP uint32 value');
        }

        return (int) $values['value'];
    }

    private static function unsignedCrc32(string $bytes): int
    {
        return (int) sprintf('%u', crc32($bytes));
    }
}
