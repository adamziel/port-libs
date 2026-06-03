<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ZipPackage
{
    private const EOCD_SIGNATURE = "PK\x05\x06";
    private const CENTRAL_DIRECTORY_SIGNATURE = "PK\x01\x02";
    private const LOCAL_FILE_SIGNATURE = "PK\x03\x04";
    private const UTF8_GENERAL_PURPOSE_FLAG = 0x0800;

    /**
     * @param array<string, ZipPackageEntry> $entriesByName
     * @param list<ZipPackageEntry> $entries
     */
    private function __construct(
        private readonly string $bytes,
        private readonly array $entriesByName,
        private readonly array $entries,
        private readonly int $centralDirectoryOffset,
        private readonly string $packageComment = '',
    ) {
    }

    public static function fromString(string $bytes): self
    {
        $eocdOffset = self::findEndOfCentralDirectory($bytes);
        $entryCount = self::readUInt16($bytes, $eocdOffset + 10);
        $centralDirectorySize = self::readUInt32($bytes, $eocdOffset + 12);
        $centralDirectoryOffset = self::readUInt32($bytes, $eocdOffset + 16);
        $packageCommentLength = self::readUInt16($bytes, $eocdOffset + 20);

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
            $modifiedTime = self::readUInt16($bytes, $cursor + 12);
            $modifiedDate = self::readUInt16($bytes, $cursor + 14);
            $crc32 = self::readUInt32($bytes, $cursor + 16);
            $compressedSize = self::readUInt32($bytes, $cursor + 20);
            $uncompressedSize = self::readUInt32($bytes, $cursor + 24);
            $nameLength = self::readUInt16($bytes, $cursor + 28);
            $extraLength = self::readUInt16($bytes, $cursor + 30);
            $commentLength = self::readUInt16($bytes, $cursor + 32);
            $diskStart = self::readUInt16($bytes, $cursor + 34);
            $externalAttributes = self::readUInt32($bytes, $cursor + 38);
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

            $centralExtraFieldData = substr($bytes, $variableStart + $nameLength, $extraLength);
            $comment = substr($bytes, $variableStart + $nameLength + $extraLength, $commentLength);
            $entry = new ZipPackageEntry(
                $name,
                $method,
                $flags,
                $crc32,
                $compressedSize,
                $uncompressedSize,
                $localHeaderOffset,
                $comment,
                $modifiedTime,
                $modifiedDate,
                $externalAttributes,
                $centralExtraFieldData
            );

            $entries[] = $entry;
            $entriesByName[$name] = $entry;
            $cursor += 46 + $variableLength;
        }

        if ($cursor !== $centralDirectoryOffset + $centralDirectorySize) {
            throw new \RuntimeException('Central directory size does not match parsed ZIP entries');
        }

        $packageComment = substr($bytes, $eocdOffset + 22, $packageCommentLength);

        return new self($bytes, $entriesByName, $entries, $centralDirectoryOffset, $packageComment);
    }

    /**
     * @param list<array{name:string, data?:string, compressionMethod?:int, comment?:string, modifiedAt?:int, modifiedDosTime?:int, modifiedDosDate?:int, externalAttributes?:int}> $parts
     */
    public static function fromParts(array $parts, string $packageComment = ''): self
    {
        return self::fromString(self::build($parts, $packageComment));
    }

    /**
     * @param list<array{name:string, data?:string, compressionMethod?:int, comment?:string, modifiedAt?:int, modifiedDosTime?:int, modifiedDosDate?:int, externalAttributes?:int}> $parts
     */
    public static function build(array $parts, string $packageComment = ''): string
    {
        self::assertUInt16Length($packageComment, 'ZIP package comment');
        if (count($parts) > 0xffff) {
            throw new \RuntimeException('ZIP package writer cannot emit more than 65535 entries without ZIP64');
        }

        $body = '';
        $central = '';
        $entriesByName = [];

        foreach ($parts as $index => $part) {
            if (!is_array($part)) {
                throw new \RuntimeException("ZIP package part {$index} must be an array");
            }

            if (!isset($part['name']) || !is_string($part['name'])) {
                throw new \RuntimeException("ZIP package part {$index} is missing a string name");
            }

            $name = $part['name'];
            self::assertSafePartName($name);
            self::assertUInt16Length($name, "ZIP entry name {$name}");
            if (isset($entriesByName[$name])) {
                throw new \RuntimeException("Duplicate ZIP package entry: {$name}");
            }
            $entriesByName[$name] = true;

            $data = $part['data'] ?? '';
            if (!is_string($data)) {
                throw new \RuntimeException("ZIP package entry {$name} data must be a string");
            }

            $method = $part['compressionMethod'] ?? ($data === '' || str_ends_with($name, '/') ? 0 : 8);
            if (!is_int($method)) {
                throw new \RuntimeException("ZIP package entry {$name} compression method must be an integer");
            }

            if (str_ends_with($name, '/') && $data !== '') {
                throw new \RuntimeException("ZIP package directory entry {$name} must not contain file data");
            }

            if (str_ends_with($name, '/') && $method !== 0) {
                throw new \RuntimeException("ZIP package directory entry {$name} must use stored compression");
            }

            $compressed = match ($method) {
                0 => $data,
                8 => gzdeflate($data),
                default => throw new \RuntimeException(
                    "Unsupported ZIP compression method {$method} for entry {$name}"
                ),
            };

            if ($compressed === false) {
                throw new \RuntimeException("Unable to deflate ZIP entry {$name}");
            }

            $comment = $part['comment'] ?? '';
            if (!is_string($comment)) {
                throw new \RuntimeException("ZIP package entry {$name} comment must be a string");
            }
            self::assertUInt16Length($comment, "ZIP entry comment {$name}");

            $crc32 = self::unsignedCrc32($data);
            $compressedSize = strlen($compressed);
            $uncompressedSize = strlen($data);
            $localHeaderOffset = strlen($body);
            [$modifiedTime, $modifiedDate] = self::resolveModifiedDateTime($part, $name);
            $extraFieldData = self::buildExtraFieldData($part, $name);
            $externalAttributes = $part['externalAttributes'] ?? (str_ends_with($name, '/') ? 0x10 : 0);
            if (!is_int($externalAttributes)) {
                throw new \RuntimeException("ZIP entry {$name} external attributes must be an integer");
            }

            self::assertUInt32Value($compressedSize, "ZIP entry {$name} compressed size");
            self::assertUInt32Value($uncompressedSize, "ZIP entry {$name} uncompressed size");
            self::assertUInt32Value($localHeaderOffset, "ZIP entry {$name} local header offset");
            self::assertUInt32Value($externalAttributes, "ZIP entry {$name} external attributes");
            self::assertUInt16Length($extraFieldData, "ZIP entry {$name} extra fields");

            $body .= pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                self::UTF8_GENERAL_PURPOSE_FLAG,
                $method,
                $modifiedTime,
                $modifiedDate,
                $crc32,
                $compressedSize,
                $uncompressedSize,
                strlen($name),
                strlen($extraFieldData)
            );
            $body .= $name . $extraFieldData . $compressed;

            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                0x0314,
                20,
                self::UTF8_GENERAL_PURPOSE_FLAG,
                $method,
                $modifiedTime,
                $modifiedDate,
                $crc32,
                $compressedSize,
                $uncompressedSize,
                strlen($name),
                strlen($extraFieldData),
                strlen($comment),
                0,
                0,
                $externalAttributes,
                $localHeaderOffset
            );
            $central .= $name . $extraFieldData . $comment;
        }

        $centralDirectoryOffset = strlen($body);
        $centralDirectorySize = strlen($central);
        self::assertUInt32Value($centralDirectoryOffset, 'ZIP central directory offset');
        self::assertUInt32Value($centralDirectorySize, 'ZIP central directory size');

        return $body
            . $central
            . pack(
                'VvvvvVVv',
                0x06054b50,
                0,
                0,
                count($parts),
                count($parts),
                $centralDirectorySize,
                $centralDirectoryOffset,
                strlen($packageComment)
            )
            . $packageComment;
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

    public function bytes(): string
    {
        return $this->bytes;
    }

    public function packageComment(): string
    {
        return $this->packageComment;
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
        $modifiedTime = self::readUInt16($this->bytes, $entry->localHeaderOffset + 10);
        $modifiedDate = self::readUInt16($this->bytes, $entry->localHeaderOffset + 12);
        $localCrc32 = self::readUInt32($this->bytes, $entry->localHeaderOffset + 14);
        $localCompressedSize = self::readUInt32($this->bytes, $entry->localHeaderOffset + 18);
        $localUncompressedSize = self::readUInt32($this->bytes, $entry->localHeaderOffset + 22);
        $nameLength = self::readUInt16($this->bytes, $entry->localHeaderOffset + 26);
        $extraLength = self::readUInt16($this->bytes, $entry->localHeaderOffset + 28);
        $nameStart = $entry->localHeaderOffset + 30;
        self::assertRange($this->bytes, $nameStart, $nameLength + $extraLength, 'local file header variable fields');

        $localName = substr($this->bytes, $nameStart, $nameLength);
        if ($localName !== $entry->name) {
            throw new \RuntimeException("ZIP local header name does not match central directory entry {$entry->name}");
        }

        $localExtraFieldData = substr($this->bytes, $nameStart + $nameLength, $extraLength);
        ZipPackageEntry::validateExtraFieldData($localExtraFieldData, "local extra fields for {$entry->name}");

        if (($flags & 0x0001) !== 0 || $flags !== $entry->generalPurposeFlags) {
            throw new \RuntimeException("ZIP local header flags do not match central directory entry {$entry->name}");
        }

        if ($method !== $entry->compressionMethod) {
            throw new \RuntimeException("ZIP local header compression method does not match entry {$entry->name}");
        }

        if ($modifiedTime !== $entry->lastModifiedTime || $modifiedDate !== $entry->lastModifiedDate) {
            throw new \RuntimeException("ZIP local header modification time does not match central directory entry {$entry->name}");
        }

        if (($entry->generalPurposeFlags & 0x0008) === 0) {
            if ($localCrc32 !== $entry->crc32) {
                throw new \RuntimeException("ZIP local header CRC32 does not match central directory entry {$entry->name}");
            }

            if ($localCompressedSize !== $entry->compressedSize || $localUncompressedSize !== $entry->uncompressedSize) {
                throw new \RuntimeException("ZIP local header sizes do not match central directory entry {$entry->name}");
            }
        }

        $dataStart = $nameStart + $nameLength + $extraLength;
        self::assertRange($this->bytes, $dataStart, $entry->compressedSize, "compressed data for {$entry->name}");

        if ($dataStart + $entry->compressedSize > $this->centralDirectoryOffset) {
            throw new \RuntimeException("ZIP compressed data for {$entry->name} overlaps the central directory");
        }

        if (($entry->generalPurposeFlags & 0x0008) !== 0) {
            $this->validateDataDescriptor($entry, $dataStart + $entry->compressedSize);
        }

        return substr($this->bytes, $dataStart, $entry->compressedSize);
    }

    private function validateDataDescriptor(ZipPackageEntry $entry, int $offset): void
    {
        $valuesOffset = $offset;
        if (substr($this->bytes, $offset, 4) === "PK\x07\x08") {
            $valuesOffset += 4;
        }

        self::assertRange($this->bytes, $valuesOffset, 12, "data descriptor for {$entry->name}");
        if ($valuesOffset + 12 > $this->centralDirectoryOffset) {
            throw new \RuntimeException("ZIP data descriptor for {$entry->name} overlaps the central directory");
        }

        $crc32 = self::readUInt32($this->bytes, $valuesOffset);
        $compressedSize = self::readUInt32($this->bytes, $valuesOffset + 4);
        $uncompressedSize = self::readUInt32($this->bytes, $valuesOffset + 8);

        if ($crc32 !== $entry->crc32) {
            throw new \RuntimeException("ZIP data descriptor CRC32 does not match central directory entry {$entry->name}");
        }

        if ($compressedSize !== $entry->compressedSize || $uncompressedSize !== $entry->uncompressedSize) {
            throw new \RuntimeException("ZIP data descriptor sizes do not match central directory entry {$entry->name}");
        }
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

    /**
     * @param array<string, mixed> $part
     *
     * @return array{0:int, 1:int}
     */
    private static function resolveModifiedDateTime(array $part, string $name): array
    {
        $hasUnixTimestamp = array_key_exists('modifiedAt', $part);
        $hasDosTime = array_key_exists('modifiedDosTime', $part);
        $hasDosDate = array_key_exists('modifiedDosDate', $part);

        if ($hasUnixTimestamp && ($hasDosTime || $hasDosDate)) {
            throw new \RuntimeException("ZIP entry {$name} modification time must use either modifiedAt or DOS fields");
        }

        if ($hasDosTime || $hasDosDate) {
            if (!$hasDosTime || !$hasDosDate || !is_int($part['modifiedDosTime']) || !is_int($part['modifiedDosDate'])) {
                throw new \RuntimeException("ZIP entry {$name} DOS modification time and date must be integers");
            }

            self::assertUInt16Value($part['modifiedDosTime'], "ZIP entry {$name} DOS modification time");
            self::assertUInt16Value($part['modifiedDosDate'], "ZIP entry {$name} DOS modification date");

            return [$part['modifiedDosTime'], $part['modifiedDosDate']];
        }

        if ($hasUnixTimestamp) {
            if (!is_int($part['modifiedAt'])) {
                throw new \RuntimeException("ZIP entry {$name} modifiedAt timestamp must be an integer");
            }

            return self::dosDateTimeFromUnixTimestamp($part['modifiedAt'], $name);
        }

        return [0, 0];
    }

    /**
     * @param array<string, mixed> $part
     */
    private static function buildExtraFieldData(array $part, string $name): string
    {
        if (!array_key_exists('modifiedAt', $part)) {
            return '';
        }

        if (!is_int($part['modifiedAt'])) {
            throw new \RuntimeException("ZIP entry {$name} modifiedAt timestamp must be an integer");
        }

        $timestamp = $part['modifiedAt'];
        if ($timestamp < 0 || $timestamp > 0xffffffff) {
            return '';
        }

        return pack('vvCV', 0x5455, 5, 0x01, $timestamp);
    }

    /**
     * @return array{0:int, 1:int}
     */
    private static function dosDateTimeFromUnixTimestamp(int $timestamp, string $name): array
    {
        $year = (int) gmdate('Y', $timestamp);
        if ($year < 1980 || $year > 2107) {
            throw new \RuntimeException("ZIP entry {$name} modifiedAt timestamp is outside the DOS date range");
        }

        $month = (int) gmdate('n', $timestamp);
        $day = (int) gmdate('j', $timestamp);
        $hour = (int) gmdate('G', $timestamp);
        $minute = (int) gmdate('i', $timestamp);
        $second = (int) gmdate('s', $timestamp);

        $dosTime = ($hour << 11) | ($minute << 5) | intdiv($second, 2);
        $dosDate = (($year - 1980) << 9) | ($month << 5) | $day;

        return [$dosTime, $dosDate];
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

    private static function assertUInt16Length(string $bytes, string $label): void
    {
        if (strlen($bytes) > 0xffff) {
            throw new \RuntimeException("{$label} is too long for a bounded ZIP package");
        }
    }

    private static function assertUInt16Value(int $value, string $label): void
    {
        if ($value < 0 || $value > 0xffff) {
            throw new \RuntimeException("{$label} must fit in an unsigned 16-bit ZIP field");
        }
    }

    private static function assertUInt32Value(int $value, string $label): void
    {
        if ($value < 0 || $value > 0xffffffff) {
            throw new \RuntimeException("{$label} requires ZIP64 and is not supported by this bounded package writer");
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
