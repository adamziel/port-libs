<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ZipPackage
{
    private const EOCD_SIGNATURE = "PK\x05\x06";
    private const CENTRAL_DIRECTORY_SIGNATURE = "PK\x01\x02";
    private const CENTRAL_DIRECTORY_DIGITAL_SIGNATURE = "PK\x05\x05";
    private const LOCAL_FILE_SIGNATURE = "PK\x03\x04";
    private const ENCRYPTED_GENERAL_PURPOSE_FLAG = 0x0001;
    private const ENHANCED_DEFLATE_GENERAL_PURPOSE_FLAG = 0x0010;
    private const COMPRESSED_PATCHED_DATA_GENERAL_PURPOSE_FLAG = 0x0020;
    private const STRONG_ENCRYPTION_GENERAL_PURPOSE_FLAG = 0x0040;
    private const UTF8_GENERAL_PURPOSE_FLAG = 0x0800;
    private const CENTRAL_DIRECTORY_ENCRYPTED_GENERAL_PURPOSE_FLAG = 0x2000;
    private const SUPPORTED_GENERAL_PURPOSE_FLAGS = 0x0002 | 0x0004 | 0x0008 | self::UTF8_GENERAL_PURPOSE_FLAG;
    private const INFOZIP_UNICODE_PATH_EXTRA_ID = 0x7075;
    private const INFOZIP_UNICODE_COMMENT_EXTRA_ID = 0x6375;
    private const UNIX_FILE_TYPE_MASK = 0xf000;
    private const UNIX_SYMLINK_TYPE = 0xa000;
    private const CP437_EXTENDED_CHARS = [
        "\u{00c7}", "\u{00fc}", "\u{00e9}", "\u{00e2}", "\u{00e4}", "\u{00e0}", "\u{00e5}", "\u{00e7}",
        "\u{00ea}", "\u{00eb}", "\u{00e8}", "\u{00ef}", "\u{00ee}", "\u{00ec}", "\u{00c4}", "\u{00c5}",
        "\u{00c9}", "\u{00e6}", "\u{00c6}", "\u{00f4}", "\u{00f6}", "\u{00f2}", "\u{00fb}", "\u{00f9}",
        "\u{00ff}", "\u{00d6}", "\u{00dc}", "\u{00a2}", "\u{00a3}", "\u{00a5}", "\u{20a7}", "\u{0192}",
        "\u{00e1}", "\u{00ed}", "\u{00f3}", "\u{00fa}", "\u{00f1}", "\u{00d1}", "\u{00aa}", "\u{00ba}",
        "\u{00bf}", "\u{2310}", "\u{00ac}", "\u{00bd}", "\u{00bc}", "\u{00a1}", "\u{00ab}", "\u{00bb}",
        "\u{2591}", "\u{2592}", "\u{2593}", "\u{2502}", "\u{2524}", "\u{2561}", "\u{2562}", "\u{2556}",
        "\u{2555}", "\u{2563}", "\u{2551}", "\u{2557}", "\u{255d}", "\u{255c}", "\u{255b}", "\u{2510}",
        "\u{2514}", "\u{2534}", "\u{252c}", "\u{251c}", "\u{2500}", "\u{253c}", "\u{255e}", "\u{255f}",
        "\u{255a}", "\u{2554}", "\u{2569}", "\u{2566}", "\u{2560}", "\u{2550}", "\u{256c}", "\u{2567}",
        "\u{2568}", "\u{2564}", "\u{2565}", "\u{2559}", "\u{2558}", "\u{2552}", "\u{2553}", "\u{256b}",
        "\u{256a}", "\u{2518}", "\u{250c}", "\u{2588}", "\u{2584}", "\u{258c}", "\u{2590}", "\u{2580}",
        "\u{03b1}", "\u{00df}", "\u{0393}", "\u{03c0}", "\u{03a3}", "\u{03c3}", "\u{00b5}", "\u{03c4}",
        "\u{03a6}", "\u{0398}", "\u{03a9}", "\u{03b4}", "\u{221e}", "\u{03c6}", "\u{03b5}", "\u{2229}",
        "\u{2261}", "\u{00b1}", "\u{2265}", "\u{2264}", "\u{2320}", "\u{2321}", "\u{00f7}", "\u{2248}",
        "\u{00b0}", "\u{2219}", "\u{00b7}", "\u{221a}", "\u{207f}", "\u{00b2}", "\u{25a0}", "\u{00a0}",
    ];

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
        $localHeaderOffsets = [];
        $cursor = $centralDirectoryOffset;
        for ($index = 0; $index < $entryCount; $index++) {
            if (substr($bytes, $cursor, 4) !== self::CENTRAL_DIRECTORY_SIGNATURE) {
                throw new \RuntimeException("Invalid ZIP central directory header at entry {$index}");
            }

            self::assertRange($bytes, $cursor, 46, 'central directory entry');
            $versionMadeBy = self::readUInt16($bytes, $cursor + 4);
            $versionNeededToExtract = self::readUInt16($bytes, $cursor + 6);
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

            self::assertSupportedGeneralPurposeFlags($flags, "central directory entry {$index}");

            if ($diskStart !== 0) {
                throw new \RuntimeException('Split ZIP entry data is not supported by the pandoc package reader');
            }

            if ($compressedSize === 0xffffffff || $uncompressedSize === 0xffffffff || $localHeaderOffset === 0xffffffff) {
                throw new \RuntimeException('ZIP64 entry sizes or offsets are not supported by this bounded package reader');
            }

            if (isset($localHeaderOffsets[$localHeaderOffset])) {
                throw new \RuntimeException("Duplicate ZIP local header offset {$localHeaderOffset} at central directory entry {$index}");
            }
            $localHeaderOffsets[$localHeaderOffset] = true;

            $rawName = substr($bytes, $variableStart, $nameLength);
            $centralExtraFieldData = substr($bytes, $variableStart + $nameLength, $extraLength);
            $rawComment = substr($bytes, $variableStart + $nameLength + $extraLength, $commentLength);
            self::assertSafePartName($rawName);
            $decodedName = self::decodeZipText(
                $rawName,
                $flags,
                $centralExtraFieldData,
                self::INFOZIP_UNICODE_PATH_EXTRA_ID,
                'info-zip-unicode-path',
                "central directory entry {$index} name"
            );
            $name = $decodedName['text'];
            self::assertSafePartName($name);
            if (isset($entriesByName[$name])) {
                throw new \RuntimeException("Duplicate ZIP package entry: {$name}");
            }

            $decodedComment = self::decodeZipText(
                $rawComment,
                $flags,
                $centralExtraFieldData,
                self::INFOZIP_UNICODE_COMMENT_EXTRA_ID,
                'info-zip-unicode-comment',
                "central directory entry {$name} comment"
            );
            $comment = $decodedComment['text'];
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
                $centralExtraFieldData,
                $versionMadeBy,
                $rawName,
                $rawComment,
                $decodedName['encoding'],
                $decodedComment['encoding'],
                $versionNeededToExtract
            );
            self::assertDirectoryEntryMetadata($entry);
            if ($entry->isUnixSymlink()) {
                throw new \RuntimeException("ZIP symlink entries are not supported by the pandoc package reader: {$name}");
            }

            $entries[] = $entry;
            $entriesByName[$name] = $entry;
            $cursor += 46 + $variableLength;
        }

        $centralDirectoryEnd = $centralDirectoryOffset + $centralDirectorySize;
        if ($cursor !== $centralDirectoryEnd) {
            self::rejectUnexpectedCentralDirectoryTail($bytes, $cursor, 'inside the central directory');
        }

        if ($centralDirectoryEnd !== $eocdOffset) {
            self::rejectUnexpectedCentralDirectoryTail(
                $bytes,
                $centralDirectoryEnd,
                'between the central directory and end-of-central-directory record'
            );
        }

        $packageComment = substr($bytes, $eocdOffset + 22, $packageCommentLength);

        $package = new self($bytes, $entriesByName, $entries, $centralDirectoryOffset, $packageComment);
        $package->validateLocalEntryPrefix();
        foreach ($entries as $entry) {
            $package->validateEntryLocalLayout($entry);
            if ($entry->isDirectory()) {
                $package->validateDirectoryLocalHeader($entry);
            }
        }

        return $package;
    }

    /**
     * @param list<array{name:string, data?:string, compressionMethod?:int, comment?:string, modifiedAt?:int, modifiedDosTime?:int, modifiedDosDate?:int, externalAttributes?:int, extraFieldData?:string}> $parts
     */
    public static function fromParts(array $parts, string $packageComment = ''): self
    {
        return self::fromString(self::build($parts, $packageComment));
    }

    /**
     * @param list<array{name:string, data?:string, compressionMethod?:int, comment?:string, modifiedAt?:int, modifiedDosTime?:int, modifiedDosDate?:int, externalAttributes?:int, extraFieldData?:string}> $parts
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
            self::assertUtf8($name, "ZIP entry {$index} name");
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
            self::assertUtf8($comment, "ZIP entry {$name} comment");
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
            if (self::isUnixSymlinkExternalAttributes($externalAttributes)) {
                throw new \RuntimeException("ZIP symlink entries are not supported by the pandoc package writer: {$name}");
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
     * @return list<ZipPackageEntry>
     */
    public function localEntries(): array
    {
        $entries = $this->entries;
        usort(
            $entries,
            static fn (ZipPackageEntry $left, ZipPackageEntry $right): int => $left->localHeaderOffset <=> $right->localHeaderOffset
        );

        return $entries;
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_map(static fn (ZipPackageEntry $entry): string => $entry->name, $this->entries);
    }

    /**
     * @return list<string>
     */
    public function localNames(): array
    {
        return array_map(static fn (ZipPackageEntry $entry): string => $entry->name, $this->localEntries());
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

    /**
     * @return list<array{id:int, data:string}>
     */
    public function localExtraFields(string $partName): array
    {
        $entry = $this->entry($partName);

        return ZipPackageEntry::extraFieldsFromData(
            $this->readLocalHeader($entry)['extraFieldData'],
            "local extra fields for {$entry->name}"
        );
    }

    public function localExtraField(string $partName, int $id): ?string
    {
        if ($id < 0 || $id > 0xffff) {
            throw new \InvalidArgumentException('ZIP extra field id must fit in an unsigned 16-bit field');
        }

        foreach ($this->localExtraFields($partName) as $field) {
            if ($field['id'] === $id) {
                return $field['data'];
            }
        }

        return null;
    }

    public function localExtendedLastModifiedTimestamp(string $partName): ?int
    {
        $timestamps = $this->localExtendedTimestamps($partName);

        return $timestamps['modifiedAt'] ?? null;
    }

    /**
     * @return array{modifiedAt?:int, accessedAt?:int, createdAt?:int}|null
     */
    public function localExtendedTimestamps(string $partName): ?array
    {
        $entry = $this->entry($partName);

        return ZipPackageEntry::extendedTimestampsFromExtraField(
            $this->localExtraField($entry->name, 0x5455),
            "local extra fields for {$entry->name}"
        );
    }

    public function localExtendedAccessedTimestamp(string $partName): ?int
    {
        $timestamps = $this->localExtendedTimestamps($partName);

        return $timestamps['accessedAt'] ?? null;
    }

    public function localExtendedCreatedTimestamp(string $partName): ?int
    {
        $timestamps = $this->localExtendedTimestamps($partName);

        return $timestamps['createdAt'] ?? null;
    }

    /**
     * @return array{modifiedAt:int, accessedAt:int, createdAt:int}|null
     */
    public function localNtfsTimestamps(string $partName): ?array
    {
        $entry = $this->entry($partName);

        return self::ntfsTimestampsFromExtraFieldData(
            $this->readLocalHeader($entry)['extraFieldData'],
            "local extra fields for {$entry->name}"
        );
    }

    public function localNtfsLastModifiedTimestamp(string $partName): ?int
    {
        $timestamps = $this->localNtfsTimestamps($partName);

        return $timestamps['modifiedAt'] ?? null;
    }

    public function read(string $partName, ?int $maxUncompressedBytes = null): string
    {
        $entry = $this->entry($partName);
        self::assertReadLimit($maxUncompressedBytes, $entry->name);
        if ($maxUncompressedBytes !== null && $entry->uncompressedSize > $maxUncompressedBytes) {
            throw new \RuntimeException(
                "ZIP entry {$entry->name} exceeds maximum uncompressed read size {$maxUncompressedBytes} bytes"
            );
        }

        if ($entry->isDirectory()) {
            return '';
        }

        $compressed = $this->readCompressedEntryBytes($entry);
        $contents = match ($entry->compressionMethod) {
            0 => $compressed,
            8 => $this->inflateEntry($entry, $compressed, $maxUncompressedBytes),
            default => throw new \RuntimeException(
                "Unsupported ZIP compression method {$entry->compressionMethod} for entry {$entry->name}"
            ),
        };

        if ($maxUncompressedBytes !== null && strlen($contents) > $maxUncompressedBytes) {
            throw new \RuntimeException(
                "ZIP entry {$entry->name} exceeds maximum uncompressed read size {$maxUncompressedBytes} bytes"
            );
        }

        if (strlen($contents) !== $entry->uncompressedSize) {
            throw new \RuntimeException("ZIP entry {$entry->name} expanded to an unexpected size");
        }

        if (self::unsignedCrc32($contents) !== $entry->crc32) {
            throw new \RuntimeException("ZIP entry {$entry->name} failed CRC32 verification");
        }

        return $contents;
    }

    public function readBounded(string $partName, int $maxUncompressedBytes): string
    {
        return $this->read($partName, $maxUncompressedBytes);
    }

    public function centralDirectoryOffset(): int
    {
        return $this->centralDirectoryOffset;
    }

    /**
     * @return array{
     *     entryCount:int,
     *     fileCount:int,
     *     directoryCount:int,
     *     compressedBytes:int,
     *     uncompressedBytes:int,
     *     storedEntryCount:int,
     *     deflatedEntryCount:int,
     *     unsupportedCompressionMethodCount:int,
     *     expansionRatio:?float,
     *     largestEntry:?array{name:string, compressionMethod:int, isDirectory:bool, compressedSize:int, uncompressedSize:int, expansionRatio:?float},
     *     entries:list<array{name:string, compressionMethod:int, isDirectory:bool, compressedSize:int, uncompressedSize:int, expansionRatio:?float}>
     * }
     */
    public function sizePreflight(): array
    {
        $compressedBytes = 0;
        $uncompressedBytes = 0;
        $fileCount = 0;
        $directoryCount = 0;
        $storedEntryCount = 0;
        $deflatedEntryCount = 0;
        $unsupportedCompressionMethodCount = 0;
        $largestEntry = null;
        $entrySummaries = [];

        foreach ($this->entries as $entry) {
            $isDirectory = $entry->isDirectory();
            if ($isDirectory) {
                $directoryCount++;
            } else {
                $fileCount++;
            }

            if ($entry->compressionMethod === 0) {
                $storedEntryCount++;
            } elseif ($entry->compressionMethod === 8) {
                $deflatedEntryCount++;
            } else {
                $unsupportedCompressionMethodCount++;
            }

            $compressedBytes += $entry->compressedSize;
            $uncompressedBytes += $entry->uncompressedSize;
            $entrySummary = [
                'name' => $entry->name,
                'compressionMethod' => $entry->compressionMethod,
                'isDirectory' => $isDirectory,
                'compressedSize' => $entry->compressedSize,
                'uncompressedSize' => $entry->uncompressedSize,
                'expansionRatio' => self::expansionRatio($entry->uncompressedSize, $entry->compressedSize),
            ];
            $entrySummaries[] = $entrySummary;

            if (
                $largestEntry === null
                || $entrySummary['uncompressedSize'] > $largestEntry['uncompressedSize']
            ) {
                $largestEntry = $entrySummary;
            }
        }

        return [
            'entryCount' => count($this->entries),
            'fileCount' => $fileCount,
            'directoryCount' => $directoryCount,
            'compressedBytes' => $compressedBytes,
            'uncompressedBytes' => $uncompressedBytes,
            'storedEntryCount' => $storedEntryCount,
            'deflatedEntryCount' => $deflatedEntryCount,
            'unsupportedCompressionMethodCount' => $unsupportedCompressionMethodCount,
            'expansionRatio' => self::expansionRatio($uncompressedBytes, $compressedBytes),
            'largestEntry' => $largestEntry,
            'entries' => $entrySummaries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     fileCount:int,
     *     directoryCount:int,
     *     compressedBytes:int,
     *     uncompressedBytes:int,
     *     storedEntryCount:int,
     *     deflatedEntryCount:int,
     *     unsupportedCompressionMethodCount:int,
     *     expansionRatio:?float,
     *     largestEntry:?array{name:string, compressionMethod:int, isDirectory:bool, compressedSize:int, uncompressedSize:int, expansionRatio:?float},
     *     entries:list<array{name:string, compressionMethod:int, isDirectory:bool, compressedSize:int, uncompressedSize:int, expansionRatio:?float}>
     * }
     */
    public function assertSizePreflight(?int $maxTotalUncompressedBytes = null, ?float $maxExpansionRatio = null): array
    {
        if ($maxTotalUncompressedBytes !== null && $maxTotalUncompressedBytes < 0) {
            throw new \InvalidArgumentException('ZIP package maximum total uncompressed size must be non-negative');
        }

        if ($maxExpansionRatio !== null && $maxExpansionRatio < 0.0) {
            throw new \InvalidArgumentException('ZIP package maximum expansion ratio must be non-negative');
        }

        $summary = $this->sizePreflight();

        if (
            $maxTotalUncompressedBytes !== null
            && $summary['uncompressedBytes'] > $maxTotalUncompressedBytes
        ) {
            throw new \RuntimeException(
                "ZIP package expands to {$summary['uncompressedBytes']} bytes, exceeding maximum total uncompressed size {$maxTotalUncompressedBytes} bytes"
            );
        }

        if (
            $maxExpansionRatio !== null
            && $summary['expansionRatio'] === null
            && $summary['uncompressedBytes'] > 0
        ) {
            throw new \RuntimeException(
                'ZIP package expansion ratio cannot be evaluated because compressed size is zero'
            );
        }

        if (
            $maxExpansionRatio !== null
            && $summary['expansionRatio'] !== null
            && $summary['expansionRatio'] > $maxExpansionRatio
        ) {
            throw new \RuntimeException(
                "ZIP package expansion ratio {$summary['expansionRatio']} exceeds maximum {$maxExpansionRatio}"
            );
        }

        return $summary;
    }

    /**
     * @return array{
     *     entryCount:int,
     *     unixModeEntryCount:int,
     *     executableFileCount:int,
     *     executableEntries:list<array{name:string, isDirectory:bool, madeByHostSystem:int, unixMode:?int, permissions:?int, isExecutableFile:bool, externalAttributes:int}>,
     *     entries:list<array{name:string, isDirectory:bool, madeByHostSystem:int, unixMode:?int, permissions:?int, isExecutableFile:bool, externalAttributes:int}>
     * }
     */
    public function permissionPreflight(): array
    {
        $unixModeEntryCount = 0;
        $executableEntries = [];
        $entries = [];

        foreach ($this->entries as $entry) {
            $unixMode = $entry->unixMode();
            $permissions = $entry->unixPermissionBits();
            $isExecutableFile = $entry->isUnixExecutableFile();
            if ($unixMode !== null) {
                $unixModeEntryCount++;
            }

            $summary = [
                'name' => $entry->name,
                'isDirectory' => $entry->isDirectory(),
                'madeByHostSystem' => $entry->madeByHostSystem(),
                'unixMode' => $unixMode,
                'permissions' => $permissions,
                'isExecutableFile' => $isExecutableFile,
                'externalAttributes' => $entry->externalFileAttributes,
            ];
            $entries[] = $summary;
            if ($isExecutableFile) {
                $executableEntries[] = $summary;
            }
        }

        return [
            'entryCount' => count($this->entries),
            'unixModeEntryCount' => $unixModeEntryCount,
            'executableFileCount' => count($executableEntries),
            'executableEntries' => $executableEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     unixModeEntryCount:int,
     *     executableFileCount:int,
     *     executableEntries:list<array{name:string, isDirectory:bool, madeByHostSystem:int, unixMode:?int, permissions:?int, isExecutableFile:bool, externalAttributes:int}>,
     *     entries:list<array{name:string, isDirectory:bool, madeByHostSystem:int, unixMode:?int, permissions:?int, isExecutableFile:bool, externalAttributes:int}>
     * }
     */
    public function assertNoExecutableFiles(): array
    {
        $summary = $this->permissionPreflight();
        if ($summary['executableFileCount'] > 0) {
            $names = implode(
                ', ',
                array_map(static fn (array $entry): string => $entry['name'], $summary['executableEntries'])
            );

            throw new \RuntimeException(
                'ZIP package contains Unix executable file entries that require explicit import review: ' . $names
            );
        }

        return $summary;
    }

    private static function assertDirectoryEntryMetadata(ZipPackageEntry $entry): void
    {
        if (!$entry->isDirectory()) {
            return;
        }

        if ($entry->compressionMethod !== 0) {
            throw new \RuntimeException("ZIP package directory entry {$entry->name} must use stored compression");
        }

        if (($entry->generalPurposeFlags & 0x0008) !== 0) {
            throw new \RuntimeException("ZIP package directory entry {$entry->name} must not use a data descriptor");
        }

        if ($entry->compressedSize !== 0 || $entry->uncompressedSize !== 0) {
            throw new \RuntimeException("ZIP package directory entry {$entry->name} must not contain file data");
        }
    }

    private static function assertSupportedGeneralPurposeFlags(int $flags, string $label): void
    {
        if (($flags & self::ENCRYPTED_GENERAL_PURPOSE_FLAG) !== 0) {
            throw new \RuntimeException("Encrypted ZIP entries are not supported by the pandoc package reader: {$label}");
        }

        if (($flags & self::STRONG_ENCRYPTION_GENERAL_PURPOSE_FLAG) !== 0) {
            throw new \RuntimeException("Strong-encrypted ZIP entries are not supported by the pandoc package reader: {$label}");
        }

        if (($flags & self::ENHANCED_DEFLATE_GENERAL_PURPOSE_FLAG) !== 0) {
            throw new \RuntimeException("Enhanced-deflate ZIP entries are not supported by the pandoc package reader: {$label}");
        }

        if (($flags & self::COMPRESSED_PATCHED_DATA_GENERAL_PURPOSE_FLAG) !== 0) {
            throw new \RuntimeException("Compressed-patched ZIP entries are not supported by the pandoc package reader: {$label}");
        }

        if (($flags & self::CENTRAL_DIRECTORY_ENCRYPTED_GENERAL_PURPOSE_FLAG) !== 0) {
            throw new \RuntimeException("ZIP entries with central-directory encryption metadata are not supported by the pandoc package reader: {$label}");
        }

        $unsupportedFlags = $flags & ~self::SUPPORTED_GENERAL_PURPOSE_FLAGS;
        if ($unsupportedFlags !== 0) {
            throw new \RuntimeException(
                sprintf('Unsupported ZIP general-purpose flag bits 0x%04x for %s', $unsupportedFlags, $label)
            );
        }
    }

    private function validateEntryLocalLayout(ZipPackageEntry $entry): void
    {
        $localHeader = $this->readLocalHeader($entry);
        $dataEnd = $localHeader['dataStart'] + $entry->compressedSize;
        if ($dataEnd > strlen($this->bytes)) {
            throw new \RuntimeException("ZIP compressed data for {$entry->name} extends beyond available bytes");
        }

        if ($dataEnd > $this->centralDirectoryOffset) {
            throw new \RuntimeException("ZIP compressed data for {$entry->name} overlaps the central directory");
        }

        $recordEnd = $dataEnd;
        if (($entry->generalPurposeFlags & 0x0008) !== 0) {
            $this->validateDataDescriptor($entry, $dataEnd);
            $recordEnd += $this->dataDescriptorLengthAt($dataEnd);
            if ($recordEnd > strlen($this->bytes)) {
                throw new \RuntimeException("ZIP data descriptor for {$entry->name} extends beyond available bytes");
            }

            if ($recordEnd > $this->centralDirectoryOffset) {
                throw new \RuntimeException("ZIP data descriptor for {$entry->name} overlaps the central directory");
            }
        }

        $nextOffset = $this->nextEntryOrCentralDirectoryOffset($entry);
        if ($recordEnd > $nextOffset) {
            throw new \RuntimeException("ZIP local entry data for {$entry->name} overlaps the next local header");
        }

        if ($recordEnd < $nextOffset) {
            $nextLabel = $nextOffset === $this->centralDirectoryOffset ? 'central directory' : 'next local header';
            throw new \RuntimeException(
                "ZIP local entry {$entry->name} contains unexpected trailing bytes before the {$nextLabel}"
            );
        }
    }

    private function validateLocalEntryPrefix(): void
    {
        if ($this->entries === []) {
            if ($this->centralDirectoryOffset !== 0) {
                throw new \RuntimeException('ZIP package contains unexpected bytes before the central directory');
            }

            return;
        }

        $localEntries = $this->localEntries();
        if ($localEntries[0]->localHeaderOffset !== 0) {
            throw new \RuntimeException('ZIP package contains unexpected bytes before the first local header');
        }
    }

    private function validateDirectoryLocalHeader(ZipPackageEntry $entry): void
    {
        $localHeader = $this->readLocalHeader($entry);
        $nextOffset = $this->nextEntryOrCentralDirectoryOffset($entry);
        if ($localHeader['dataStart'] !== $nextOffset) {
            throw new \RuntimeException("ZIP package directory entry {$entry->name} contains payload bytes before the next header");
        }
    }

    private function nextEntryOrCentralDirectoryOffset(ZipPackageEntry $entry): int
    {
        $nextOffset = $this->centralDirectoryOffset;
        foreach ($this->entries as $candidate) {
            if ($candidate === $entry || $candidate->localHeaderOffset <= $entry->localHeaderOffset) {
                continue;
            }

            if ($candidate->localHeaderOffset < $nextOffset) {
                $nextOffset = $candidate->localHeaderOffset;
            }
        }

        return $nextOffset;
    }
    private function dataDescriptorLengthAt(int $offset): int
    {
        return substr($this->bytes, $offset, 4) === "PK\x07\x08" ? 16 : 12;
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

    private static function rejectUnexpectedCentralDirectoryTail(string $bytes, int $offset, string $label): void
    {
        if (substr($bytes, $offset, 4) === self::CENTRAL_DIRECTORY_DIGITAL_SIGNATURE) {
            throw new \RuntimeException(
                'ZIP central-directory digital signature records are not supported by the pandoc package reader'
            );
        }

        throw new \RuntimeException("Unexpected ZIP bytes {$label}");
    }

    private function readCompressedEntryBytes(ZipPackageEntry $entry): string
    {
        $localHeader = $this->readLocalHeader($entry);
        $dataStart = $localHeader['dataStart'];
        self::assertRange($this->bytes, $dataStart, $entry->compressedSize, "compressed data for {$entry->name}");

        if ($dataStart + $entry->compressedSize > $this->centralDirectoryOffset) {
            throw new \RuntimeException("ZIP compressed data for {$entry->name} overlaps the central directory");
        }

        if (($entry->generalPurposeFlags & 0x0008) !== 0) {
            $this->validateDataDescriptor($entry, $dataStart + $entry->compressedSize);
        }

        return substr($this->bytes, $dataStart, $entry->compressedSize);
    }

    /**
     * @return array{extraFieldData:string, dataStart:int}
     */
    private function readLocalHeader(ZipPackageEntry $entry): array
    {
        self::assertRange($this->bytes, $entry->localHeaderOffset, 30, 'local file header');
        if (substr($this->bytes, $entry->localHeaderOffset, 4) !== self::LOCAL_FILE_SIGNATURE) {
            throw new \RuntimeException("Invalid ZIP local file header for entry {$entry->name}");
        }

        $versionNeededToExtract = self::readUInt16($this->bytes, $entry->localHeaderOffset + 4);
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
        if ($localName !== $entry->rawName) {
            throw new \RuntimeException("ZIP local header name does not match central directory entry {$entry->name}");
        }

        if ($versionNeededToExtract !== $entry->versionNeededToExtract) {
            throw new \RuntimeException("ZIP local header version needed to extract does not match central directory entry {$entry->name}");
        }

        $localExtraFieldData = substr($this->bytes, $nameStart + $nameLength, $extraLength);
        ZipPackageEntry::validateExtraFieldData($localExtraFieldData, "local extra fields for {$entry->name}");
        $localUnicodePath = self::unicodeTextFromExtraFieldData(
            $localExtraFieldData,
            self::INFOZIP_UNICODE_PATH_EXTRA_ID,
            $localName,
            "local extra fields for {$entry->name}",
        );
        if ($localUnicodePath !== null && $localUnicodePath !== $entry->name) {
            throw new \RuntimeException("ZIP local header Unicode path does not match central directory entry {$entry->name}");
        }

        if ($flags !== $entry->generalPurposeFlags) {
            throw new \RuntimeException("ZIP local header flags do not match central directory entry {$entry->name}");
        }

        if ($method !== $entry->compressionMethod) {
            throw new \RuntimeException("ZIP local header compression method does not match entry {$entry->name}");
        }

        if ($modifiedTime !== $entry->lastModifiedTime || $modifiedDate !== $entry->lastModifiedDate) {
            throw new \RuntimeException("ZIP local header modification time does not match central directory entry {$entry->name}");
        }

        $localExtendedTimestamps = self::extendedTimestampsFromExtraFieldData(
            $localExtraFieldData,
            "local extra fields for {$entry->name}"
        );
        self::assertMatchingOptionalTimestamps(
            $localExtendedTimestamps,
            $entry->extendedTimestamps(),
            'extended timestamp',
            $entry->name
        );

        $localNtfsTimestamps = self::ntfsTimestampsFromExtraFieldData(
            $localExtraFieldData,
            "local extra fields for {$entry->name}"
        );
        $centralNtfsTimestamps = $entry->ntfsTimestamps();
        if (
            $localNtfsTimestamps !== null
            && $centralNtfsTimestamps !== null
            && $localNtfsTimestamps !== $centralNtfsTimestamps
        ) {
            throw new \RuntimeException(
                "ZIP local header NTFS timestamps do not match central directory entry {$entry->name}"
            );
        }

        if (($entry->generalPurposeFlags & 0x0008) === 0) {
            if ($localCrc32 !== $entry->crc32) {
                throw new \RuntimeException("ZIP local header CRC32 does not match central directory entry {$entry->name}");
            }

            if ($localCompressedSize !== $entry->compressedSize || $localUncompressedSize !== $entry->uncompressedSize) {
                throw new \RuntimeException("ZIP local header sizes do not match central directory entry {$entry->name}");
            }
        }

        return [
            'extraFieldData' => $localExtraFieldData,
            'dataStart' => $nameStart + $nameLength + $extraLength,
        ];
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

    private function inflateEntry(ZipPackageEntry $entry, string $compressed, ?int $maxUncompressedBytes = null): string
    {
        $inflateLimit = 0;
        if ($maxUncompressedBytes !== null) {
            $inflateLimit = $maxUncompressedBytes === PHP_INT_MAX ? 0 : $maxUncompressedBytes + 1;
        }

        $inflated = $inflateLimit > 0 ? gzinflate($compressed, $inflateLimit) : gzinflate($compressed);
        if ($inflated === false) {
            throw new \RuntimeException("Unable to inflate ZIP entry {$entry->name}");
        }

        return $inflated;
    }

    private static function assertReadLimit(?int $maxUncompressedBytes, string $entryName): void
    {
        if ($maxUncompressedBytes === null) {
            return;
        }

        if ($maxUncompressedBytes < 0) {
            throw new \InvalidArgumentException(
                "ZIP entry {$entryName} maximum uncompressed read size must be non-negative"
            );
        }
    }

    private static function expansionRatio(int $uncompressedBytes, int $compressedBytes): ?float
    {
        if ($uncompressedBytes === 0) {
            return 0.0;
        }

        if ($compressedBytes === 0) {
            return null;
        }

        return $uncompressedBytes / $compressedBytes;
    }

    private function normalizeLookupPartName(string $partName): string
    {
        $name = ltrim($partName, '/');
        self::assertSafePartName($name);

        return $name;
    }

    /**
     * @return array{modifiedAt?:int, accessedAt?:int, createdAt?:int}|null
     */
    private static function extendedTimestampsFromExtraFieldData(string $extraFieldData, string $label): ?array
    {
        foreach (ZipPackageEntry::extraFieldsFromData($extraFieldData, $label) as $field) {
            if ($field['id'] === 0x5455) {
                return ZipPackageEntry::extendedTimestampsFromExtraField($field['data'], $label);
            }
        }

        return null;
    }

    /**
     * @param array{modifiedAt?:int, accessedAt?:int, createdAt?:int}|null $local
     * @param array{modifiedAt?:int, accessedAt?:int, createdAt?:int}|null $central
     */
    private static function assertMatchingOptionalTimestamps(?array $local, ?array $central, string $label, string $entryName): void
    {
        if ($local === null || $central === null) {
            return;
        }

        foreach (['modifiedAt', 'accessedAt', 'createdAt'] as $field) {
            if (!array_key_exists($field, $local) || !array_key_exists($field, $central)) {
                continue;
            }

            if ($local[$field] !== $central[$field]) {
                throw new \RuntimeException(
                    "ZIP local header {$label} does not match central directory entry {$entryName}"
                );
            }
        }
    }

    /**
     * @return array{modifiedAt:int, accessedAt:int, createdAt:int}|null
     */
    private static function ntfsTimestampsFromExtraFieldData(string $extraFieldData, string $label): ?array
    {
        foreach (ZipPackageEntry::extraFieldsFromData($extraFieldData, $label) as $field) {
            if ($field['id'] === 0x000a) {
                return ZipPackageEntry::ntfsTimestampsFromExtraField($field['data'], $label);
            }
        }

        return null;
    }

    /**
     * @return array{text:string, encoding:string}
     */
    private static function decodeZipText(
        string $raw,
        int $flags,
        string $extraFieldData,
        int $unicodeExtraFieldId,
        string $unicodeEncodingLabel,
        string $label
    ): array {
        $unicodeText = self::unicodeTextFromExtraFieldData($extraFieldData, $unicodeExtraFieldId, $raw, $label);
        if ($unicodeText !== null) {
            return [
                'text' => $unicodeText,
                'encoding' => $unicodeEncodingLabel,
            ];
        }

        if (($flags & self::UTF8_GENERAL_PURPOSE_FLAG) !== 0) {
            self::assertUtf8($raw, "ZIP {$label}");

            return [
                'text' => $raw,
                'encoding' => 'utf-8',
            ];
        }

        return [
            'text' => self::decodeCp437($raw),
            'encoding' => 'cp437',
        ];
    }

    private static function unicodeTextFromExtraFieldData(string $extraFieldData, int $id, string $rawBytes, string $label): ?string
    {
        foreach (ZipPackageEntry::extraFieldsFromData($extraFieldData, $label) as $field) {
            if ($field['id'] !== $id) {
                continue;
            }

            $data = $field['data'];
            if (strlen($data) < 5) {
                throw new \RuntimeException("ZIP Unicode extra field for {$label} is truncated");
            }

            $version = ord($data[0]);
            if ($version !== 1) {
                throw new \RuntimeException("ZIP Unicode extra field for {$label} uses an unsupported version");
            }

            $crc = self::readUInt32($data, 1);
            if ($crc !== self::unsignedCrc32($rawBytes)) {
                throw new \RuntimeException("ZIP Unicode extra field CRC32 does not match {$label}");
            }

            $text = substr($data, 5);
            self::assertUtf8($text, "ZIP Unicode extra field for {$label}");

            return $text;
        }

        return null;
    }

    private static function decodeCp437(string $bytes): string
    {
        $text = '';
        for ($index = 0, $length = strlen($bytes); $index < $length; $index++) {
            $byte = ord($bytes[$index]);
            $text .= $byte < 0x80 ? chr($byte) : self::CP437_EXTENDED_CHARS[$byte - 0x80];
        }

        return $text;
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
        $extraFieldData = '';
        if (array_key_exists('modifiedAt', $part)) {
            if (!is_int($part['modifiedAt'])) {
                throw new \RuntimeException("ZIP entry {$name} modifiedAt timestamp must be an integer");
            }

            $timestamp = $part['modifiedAt'];
            if ($timestamp >= 0 && $timestamp <= 0xffffffff) {
                $extraFieldData .= pack('vvCV', 0x5455, 5, 0x01, $timestamp);
            }
        }

        if (array_key_exists('extraFieldData', $part)) {
            if (!is_string($part['extraFieldData'])) {
                throw new \RuntimeException("ZIP entry {$name} extraFieldData must be a string");
            }

            ZipPackageEntry::validateExtraFieldData($part['extraFieldData'], "generated extra fields for {$name}");
            $extraFieldData .= $part['extraFieldData'];
        }

        return $extraFieldData;
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

        if (
            str_contains($name, "\0")
            || str_starts_with($name, '/')
            || str_contains($name, '\\')
            || preg_match('/^[A-Za-z]:/', $name) === 1
        ) {
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

    private static function assertUtf8(string $text, string $label): void
    {
        if (preg_match('//u', $text) !== 1) {
            throw new \RuntimeException("{$label} must be valid UTF-8");
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

    private static function isUnixSymlinkExternalAttributes(int $externalAttributes): bool
    {
        $mode = ($externalAttributes >> 16) & 0xffff;

        return ($mode & self::UNIX_FILE_TYPE_MASK) === self::UNIX_SYMLINK_TYPE;
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
