<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ZipPackage
{
    private const EOCD_SIGNATURE = "PK\x05\x06";
    private const CENTRAL_DIRECTORY_SIGNATURE = "PK\x01\x02";
    private const CENTRAL_DIRECTORY_DIGITAL_SIGNATURE = "PK\x05\x05";
    private const ZIP64_END_OF_CENTRAL_DIRECTORY_SIGNATURE = "PK\x06\x06";
    private const ZIP64_END_OF_CENTRAL_DIRECTORY_LOCATOR_SIGNATURE = "PK\x06\x07";
    private const LOCAL_FILE_SIGNATURE = "PK\x03\x04";
    private const ENCRYPTED_GENERAL_PURPOSE_FLAG = 0x0001;
    private const ENHANCED_DEFLATE_GENERAL_PURPOSE_FLAG = 0x0010;
    private const COMPRESSED_PATCHED_DATA_GENERAL_PURPOSE_FLAG = 0x0020;
    private const STRONG_ENCRYPTION_GENERAL_PURPOSE_FLAG = 0x0040;
    private const UTF8_GENERAL_PURPOSE_FLAG = 0x0800;
    private const CENTRAL_DIRECTORY_ENCRYPTED_GENERAL_PURPOSE_FLAG = 0x2000;
    private const DEFLATE_OPTION_GENERAL_PURPOSE_FLAGS = 0x0002 | 0x0004;
    private const SUPPORTED_GENERAL_PURPOSE_FLAGS = 0x0002 | 0x0004 | 0x0008 | self::UTF8_GENERAL_PURPOSE_FLAG;
    private const MAX_SUPPORTED_VERSION_NEEDED_TO_EXTRACT = 20;
    private const INFOZIP_UNICODE_PATH_EXTRA_ID = 0x7075;
    private const INFOZIP_UNICODE_COMMENT_EXTRA_ID = 0x6375;
    private const UINT32_FACTOR = 4294967296;
    private const UNIX_FILE_TYPE_MASK = 0xf000;
    private const UNIX_FIFO_TYPE = 0x1000;
    private const UNIX_CHARACTER_DEVICE_TYPE = 0x2000;
    private const UNIX_DIRECTORY_TYPE = 0x4000;
    private const UNIX_BLOCK_DEVICE_TYPE = 0x6000;
    private const UNIX_REGULAR_FILE_TYPE = 0x8000;
    private const UNIX_SYMLINK_TYPE = 0xa000;
    private const UNIX_SOCKET_TYPE = 0xc000;
    private const CREATOR_HOST_SYSTEM_NAMES = [
        0 => 'ms-dos-fat',
        1 => 'amiga',
        2 => 'openvms',
        3 => 'unix',
        4 => 'vm-cms',
        5 => 'atari-st',
        6 => 'os2-hpfs',
        7 => 'macintosh',
        8 => 'z-system',
        9 => 'cp-m',
        10 => 'windows-ntfs',
        11 => 'mvs',
        12 => 'vse',
        13 => 'acorn-risc',
        14 => 'vfat',
        15 => 'alternate-mvs',
        16 => 'beos',
        17 => 'tandem',
        18 => 'os400',
        19 => 'os-x',
    ];
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
        private readonly ?string $centralDirectorySignatureData = null,
        private readonly ?int $centralDirectorySignatureOffset = null,
    ) {
    }

    public static function fromString(string $bytes): self
    {
        $archivePreflight = self::endOfCentralDirectoryPreflight($bytes);
        if (
            $archivePreflight['hasZip64EndOfCentralDirectory']
            || $archivePreflight['hasZip64EndOfCentralDirectoryLocator']
        ) {
            throw new \RuntimeException('ZIP64 end-of-central-directory records are not supported by this bounded package reader');
        }

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
            self::assertSupportedVersionNeededToExtract($versionNeededToExtract, $name);
            self::assertDeflateOptionFlagsMatchMethod($flags, $method, $name);
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
            self::assertDirectoryAttributeConsistency($entry);
            if ($entry->isUnixSymlink()) {
                throw new \RuntimeException("ZIP symlink entries are not supported by the pandoc package reader: {$name}");
            }

            if ($entry->isUnixSpecialFile()) {
                throw new \RuntimeException(
                    "ZIP Unix special file entries are not supported by the pandoc package reader: {$name} "
                    . '(' . $entry->unixFileTypeName() . ')'
                );
            }

            $entries[] = $entry;
            $entriesByName[$name] = $entry;
            $cursor += 46 + $variableLength;
        }

        $centralDirectoryEnd = $centralDirectoryOffset + $centralDirectorySize;
        $centralDirectorySignatureData = null;
        $centralDirectorySignatureOffset = null;
        if ($cursor !== $centralDirectoryEnd) {
            $signature = self::centralDirectoryDigitalSignatureRecordAt($bytes, $cursor);
            if ($signature === null || $signature['endOffset'] !== $centralDirectoryEnd) {
                self::rejectUnexpectedCentralDirectoryTail($bytes, $cursor, 'inside the central directory');
            }

            $centralDirectorySignatureData = $signature['data'];
            $centralDirectorySignatureOffset = $signature['offset'];
            $cursor = $signature['endOffset'];
        }

        if ($centralDirectoryEnd !== $eocdOffset) {
            if ($centralDirectorySignatureData !== null) {
                self::rejectUnexpectedCentralDirectoryTail(
                    $bytes,
                    $centralDirectoryEnd,
                    'between the central-directory digital signature and end-of-central-directory record'
                );
            }

            $signature = self::centralDirectoryDigitalSignatureRecordAt($bytes, $centralDirectoryEnd);
            if ($signature !== null && $signature['endOffset'] === $eocdOffset) {
                $centralDirectorySignatureData = $signature['data'];
                $centralDirectorySignatureOffset = $signature['offset'];
            }

            if ($centralDirectorySignatureOffset === null) {
                self::rejectUnexpectedCentralDirectoryTail(
                    $bytes,
                    $centralDirectoryEnd,
                    'between the central directory and end-of-central-directory record'
                );
            }
        }

        $packageComment = substr($bytes, $eocdOffset + 22, $packageCommentLength);

        $package = new self(
            $bytes,
            $entriesByName,
            $entries,
            $centralDirectoryOffset,
            $packageComment,
            $centralDirectorySignatureData,
            $centralDirectorySignatureOffset
        );
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
        self::assertUtf8($packageComment, 'ZIP package comment');
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

            if (self::isUnixSpecialFileExternalAttributes($externalAttributes)) {
                throw new \RuntimeException("ZIP Unix special file entries are not supported by the pandoc package writer: {$name}");
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

    /**
     * @return array{
     *     entryName:string,
     *     exists:bool,
     *     firstLocalEntryName:?string,
     *     isFirstLocalEntry:bool,
     *     compressionMethod:?int,
     *     compressionMethodName:?string,
     *     isStored:bool,
     *     centralExtraFieldIds:list<int>,
     *     localExtraFieldIds:list<int>,
     *     hasCentralExtraFields:bool,
     *     hasLocalExtraFields:bool,
     *     expectedBytes:int,
     *     contentBytes:?int,
     *     contentsMatch:bool,
     *     isValid:bool,
     *     diagnostics:list<string>
     * }
     */
    public function storedFirstEntryPreflight(string $partName, string $expectedContents): array
    {
        $name = $this->normalizeLookupPartName($partName);
        $localEntries = $this->localEntries();
        $firstLocalEntryName = $localEntries[0]->name ?? null;
        $exists = isset($this->entriesByName[$name]);
        $diagnostics = [];
        $compressionMethod = null;
        $compressionMethodName = null;
        $isStored = false;
        $centralExtraFieldIds = [];
        $localExtraFieldIds = [];
        $contentBytes = null;
        $contentsMatch = false;

        if (!$exists) {
            $diagnostics[] = "missing entry {$name}";
        }

        if ($firstLocalEntryName !== $name) {
            $diagnostics[] = "entry {$name} is not the first local ZIP entry";
        }

        if ($exists) {
            $entry = $this->entriesByName[$name];
            $compressionMethod = $entry->compressionMethod;
            $compressionMethodName = self::compressionMethodName($compressionMethod);
            $isStored = $compressionMethod === 0;
            $centralExtraFieldIds = array_map(
                static fn (array $field): int => $field['id'],
                $entry->centralExtraFields()
            );
            $localExtraFieldIds = array_map(
                static fn (array $field): int => $field['id'],
                $this->localExtraFields($name)
            );

            if (!$isStored) {
                $diagnostics[] = "entry {$name} must use stored compression";
            }

            if ($centralExtraFieldIds !== [] || $localExtraFieldIds !== []) {
                $diagnostics[] = "entry {$name} must not carry ZIP extra fields";
            }

            try {
                $contents = $this->read($name);
                $contentBytes = strlen($contents);
                $contentsMatch = $contents === $expectedContents;
                if (!$contentsMatch) {
                    $diagnostics[] = "entry {$name} contents do not match expected bytes";
                }
            } catch (\RuntimeException $exception) {
                $diagnostics[] = "entry {$name} could not be read: " . $exception->getMessage();
            }
        }

        return [
            'entryName' => $name,
            'exists' => $exists,
            'firstLocalEntryName' => $firstLocalEntryName,
            'isFirstLocalEntry' => $firstLocalEntryName === $name,
            'compressionMethod' => $compressionMethod,
            'compressionMethodName' => $compressionMethodName,
            'isStored' => $isStored,
            'centralExtraFieldIds' => $centralExtraFieldIds,
            'localExtraFieldIds' => $localExtraFieldIds,
            'hasCentralExtraFields' => $centralExtraFieldIds !== [],
            'hasLocalExtraFields' => $localExtraFieldIds !== [],
            'expectedBytes' => strlen($expectedContents),
            'contentBytes' => $contentBytes,
            'contentsMatch' => $contentsMatch,
            'isValid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
        ];
    }

    /**
     * @return array{
     *     entryName:string,
     *     exists:bool,
     *     firstLocalEntryName:?string,
     *     isFirstLocalEntry:bool,
     *     compressionMethod:?int,
     *     compressionMethodName:?string,
     *     isStored:bool,
     *     centralExtraFieldIds:list<int>,
     *     localExtraFieldIds:list<int>,
     *     hasCentralExtraFields:bool,
     *     hasLocalExtraFields:bool,
     *     expectedBytes:int,
     *     contentBytes:?int,
     *     contentsMatch:bool,
     *     isValid:bool,
     *     diagnostics:list<string>
     * }
     */
    public function assertStoredFirstEntry(string $partName, string $expectedContents, ?string $label = null): array
    {
        $summary = $this->storedFirstEntryPreflight($partName, $expectedContents);
        if ($summary['isValid']) {
            return $summary;
        }

        $label ??= $summary['entryName'];
        throw new \RuntimeException(
            "ZIP package stored-first entry preflight failed for {$label}: "
            . implode('; ', $summary['diagnostics'])
        );
    }

    public function bytes(): string
    {
        return $this->bytes;
    }

    public function packageComment(): string
    {
        return $this->packageComment;
    }

    public function hasCentralDirectorySignature(): bool
    {
        return $this->centralDirectorySignatureOffset !== null;
    }

    public function centralDirectorySignature(): ?string
    {
        return $this->centralDirectorySignatureData;
    }

    /**
     * @return array{present:bool, offset:?int, signatureData:?string, signatureLength:int, cryptographicVerification:string}
     */
    public function centralDirectorySignaturePreflight(): array
    {
        return [
            'present' => $this->hasCentralDirectorySignature(),
            'offset' => $this->centralDirectorySignatureOffset,
            'signatureData' => $this->centralDirectorySignatureData,
            'signatureLength' => $this->centralDirectorySignatureData === null ? 0 : strlen($this->centralDirectorySignatureData),
            'cryptographicVerification' => $this->centralDirectorySignatureData === null
                ? 'not-present'
                : 'not-performed-native-bounded-reader',
        ];
    }

    /**
     * @return array{
     *     packageComment:string,
     *     rawPackageComment:string,
     *     packageCommentEncoding:string,
     *     packageCommentLength:int,
     *     hasPackageComment:bool,
     *     hasEntryComments:bool,
     *     hasComments:bool,
     *     entryCommentCount:int,
     *     commentedEntryNames:list<string>,
     *     commentedEntries:list<array{name:string, comment:string, rawComment:string, commentEncoding:string, commentLength:int}>,
     *     entries:list<array{name:string, comment:string, rawComment:string, commentEncoding:string, commentLength:int}>
     * }
     */
    public function commentPreflight(): array
    {
        $packageComment = self::decodePackageComment($this->packageComment);
        $entries = [];
        $commentedEntries = [];

        foreach ($this->entries as $entry) {
            $summary = [
                'name' => $entry->name,
                'comment' => $entry->comment,
                'rawComment' => $entry->rawComment,
                'commentEncoding' => $entry->commentEncoding,
                'commentLength' => strlen($entry->rawComment),
            ];
            $entries[] = $summary;
            if ($entry->comment !== '') {
                $commentedEntries[] = $summary;
            }
        }

        return [
            'packageComment' => $packageComment['text'],
            'rawPackageComment' => $this->packageComment,
            'packageCommentEncoding' => $packageComment['encoding'],
            'packageCommentLength' => strlen($this->packageComment),
            'hasPackageComment' => $this->packageComment !== '',
            'hasEntryComments' => $commentedEntries !== [],
            'hasComments' => $this->packageComment !== '' || $commentedEntries !== [],
            'entryCommentCount' => count($commentedEntries),
            'commentedEntryNames' => array_map(static fn (array $entry): string => $entry['name'], $commentedEntries),
            'commentedEntries' => $commentedEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     packageComment:string,
     *     rawPackageComment:string,
     *     packageCommentEncoding:string,
     *     packageCommentLength:int,
     *     hasPackageComment:bool,
     *     hasEntryComments:bool,
     *     hasComments:bool,
     *     entryCommentCount:int,
     *     commentedEntryNames:list<string>,
     *     commentedEntries:list<array{name:string, comment:string, rawComment:string, commentEncoding:string, commentLength:int}>,
     *     entries:list<array{name:string, comment:string, rawComment:string, commentEncoding:string, commentLength:int}>
     * }
     */
    public function assertNoPackageOrEntryComments(): array
    {
        $summary = $this->commentPreflight();
        if (!$summary['hasComments']) {
            return $summary;
        }

        $commentSources = [];
        if ($summary['hasPackageComment']) {
            $commentSources[] = 'package comment';
        }

        foreach ($summary['commentedEntryNames'] as $name) {
            $commentSources[] = $name . ' entry comment';
        }

        throw new \RuntimeException(
            'ZIP package contains package or entry comments that require explicit import review: '
            . implode(', ', $commentSources)
        );
    }

    /**
     * @return array{
     *     entryCount:int,
     *     extraFieldEntryCount:int,
     *     duplicateExtraFieldEntryCount:int,
     *     duplicateCentralExtraFieldEntryCount:int,
     *     duplicateLocalExtraFieldEntryCount:int,
     *     mismatchedExtraFieldEntryCount:int,
     *     mismatchedExtraFieldValueEntryCount:int,
     *     centralOnlyExtraFieldEntryCount:int,
     *     localOnlyExtraFieldEntryCount:int,
     *     duplicateEntries:list<array<string, mixed>>,
     *     mismatchedEntries:list<array<string, mixed>>,
     *     valueMismatchedEntries:list<array<string, mixed>>,
     *     entries:list<array<string, mixed>>
     * }
     */
    public function extraFieldPreflight(): array
    {
        $extraFieldEntryCount = 0;
        $duplicateCentralExtraFieldEntryCount = 0;
        $duplicateLocalExtraFieldEntryCount = 0;
        $centralOnlyExtraFieldEntryCount = 0;
        $localOnlyExtraFieldEntryCount = 0;
        $duplicateEntries = [];
        $mismatchedEntries = [];
        $valueMismatchedEntries = [];
        $entries = [];

        foreach ($this->entries as $entry) {
            $centralExtraFields = $entry->centralExtraFields();
            $localExtraFields = $this->localExtraFields($entry->name);
            $centralExtraFieldIds = array_map(
                static fn (array $field): int => $field['id'],
                $centralExtraFields
            );
            $localExtraFieldIds = array_map(
                static fn (array $field): int => $field['id'],
                $localExtraFields
            );
            $duplicateCentralExtraFieldIds = self::duplicateIntegerValues($centralExtraFieldIds);
            $duplicateLocalExtraFieldIds = self::duplicateIntegerValues($localExtraFieldIds);
            $centralOnlyExtraFieldIds = self::integerValuesOnlyIn($centralExtraFieldIds, $localExtraFieldIds);
            $localOnlyExtraFieldIds = self::integerValuesOnlyIn($localExtraFieldIds, $centralExtraFieldIds);
            $mismatchedExtraFieldValueIds = self::mismatchedExtraFieldValueIds($centralExtraFields, $localExtraFields);
            $hasDuplicateExtraFieldIds = $duplicateCentralExtraFieldIds !== []
                || $duplicateLocalExtraFieldIds !== [];
            $hasMismatchedExtraFieldIds = $centralOnlyExtraFieldIds !== []
                || $localOnlyExtraFieldIds !== [];
            $hasMismatchedExtraFieldValues = $mismatchedExtraFieldValueIds !== [];

            if ($centralExtraFieldIds !== [] || $localExtraFieldIds !== []) {
                $extraFieldEntryCount++;
            }

            if ($duplicateCentralExtraFieldIds !== []) {
                $duplicateCentralExtraFieldEntryCount++;
            }

            if ($duplicateLocalExtraFieldIds !== []) {
                $duplicateLocalExtraFieldEntryCount++;
            }

            if ($centralOnlyExtraFieldIds !== []) {
                $centralOnlyExtraFieldEntryCount++;
            }

            if ($localOnlyExtraFieldIds !== []) {
                $localOnlyExtraFieldEntryCount++;
            }

            $summary = [
                'name' => $entry->name,
                'centralExtraFieldIds' => $centralExtraFieldIds,
                'localExtraFieldIds' => $localExtraFieldIds,
                'duplicateCentralExtraFieldIds' => $duplicateCentralExtraFieldIds,
                'duplicateLocalExtraFieldIds' => $duplicateLocalExtraFieldIds,
                'centralOnlyExtraFieldIds' => $centralOnlyExtraFieldIds,
                'localOnlyExtraFieldIds' => $localOnlyExtraFieldIds,
                'mismatchedExtraFieldValueIds' => $mismatchedExtraFieldValueIds,
                'hasDuplicateExtraFieldIds' => $hasDuplicateExtraFieldIds,
                'hasMismatchedExtraFieldIds' => $hasMismatchedExtraFieldIds,
                'hasMismatchedExtraFieldValues' => $hasMismatchedExtraFieldValues,
            ];
            $entries[] = $summary;
            if ($hasDuplicateExtraFieldIds) {
                $duplicateEntries[] = $summary;
            }
            if ($hasMismatchedExtraFieldIds) {
                $mismatchedEntries[] = $summary;
            }
            if ($hasMismatchedExtraFieldValues) {
                $valueMismatchedEntries[] = $summary;
            }
        }

        return [
            'entryCount' => count($this->entries),
            'extraFieldEntryCount' => $extraFieldEntryCount,
            'duplicateExtraFieldEntryCount' => count($duplicateEntries),
            'duplicateCentralExtraFieldEntryCount' => $duplicateCentralExtraFieldEntryCount,
            'duplicateLocalExtraFieldEntryCount' => $duplicateLocalExtraFieldEntryCount,
            'mismatchedExtraFieldEntryCount' => count($mismatchedEntries),
            'mismatchedExtraFieldValueEntryCount' => count($valueMismatchedEntries),
            'centralOnlyExtraFieldEntryCount' => $centralOnlyExtraFieldEntryCount,
            'localOnlyExtraFieldEntryCount' => $localOnlyExtraFieldEntryCount,
            'duplicateEntries' => $duplicateEntries,
            'mismatchedEntries' => $mismatchedEntries,
            'valueMismatchedEntries' => $valueMismatchedEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     extraFieldEntryCount:int,
     *     duplicateExtraFieldEntryCount:int,
     *     duplicateCentralExtraFieldEntryCount:int,
     *     duplicateLocalExtraFieldEntryCount:int,
     *     mismatchedExtraFieldEntryCount:int,
     *     mismatchedExtraFieldValueEntryCount:int,
     *     centralOnlyExtraFieldEntryCount:int,
     *     localOnlyExtraFieldEntryCount:int,
     *     duplicateEntries:list<array<string, mixed>>,
     *     mismatchedEntries:list<array<string, mixed>>,
     *     valueMismatchedEntries:list<array<string, mixed>>,
     *     entries:list<array<string, mixed>>
     * }
     */
    public function assertNoDuplicateExtraFieldIds(): array
    {
        $summary = $this->extraFieldPreflight();
        if ($summary['duplicateExtraFieldEntryCount'] > 0) {
            $entries = implode(
                ', ',
                array_map(
                    static function (array $entry): string {
                        $parts = [];
                        if ($entry['duplicateCentralExtraFieldIds'] !== []) {
                            $parts[] = 'central ids ' . implode('/', $entry['duplicateCentralExtraFieldIds']);
                        }
                        if ($entry['duplicateLocalExtraFieldIds'] !== []) {
                            $parts[] = 'local ids ' . implode('/', $entry['duplicateLocalExtraFieldIds']);
                        }

                        return $entry['name'] . ' (' . implode('; ', $parts) . ')';
                    },
                    $summary['duplicateEntries']
                )
            );

            throw new \RuntimeException(
                'ZIP package contains duplicate extra field ids that require explicit import review: ' . $entries
            );
        }

        return $summary;
    }

    /**
     * @return array{
     *     entryCount:int,
     *     extraFieldEntryCount:int,
     *     duplicateExtraFieldEntryCount:int,
     *     duplicateCentralExtraFieldEntryCount:int,
     *     duplicateLocalExtraFieldEntryCount:int,
     *     mismatchedExtraFieldEntryCount:int,
     *     mismatchedExtraFieldValueEntryCount:int,
     *     centralOnlyExtraFieldEntryCount:int,
     *     localOnlyExtraFieldEntryCount:int,
     *     duplicateEntries:list<array<string, mixed>>,
     *     mismatchedEntries:list<array<string, mixed>>,
     *     valueMismatchedEntries:list<array<string, mixed>>,
     *     entries:list<array<string, mixed>>
     * }
     */
    public function assertMatchingExtraFieldIds(): array
    {
        $summary = $this->extraFieldPreflight();
        if ($summary['mismatchedExtraFieldEntryCount'] > 0) {
            $entries = implode(
                ', ',
                array_map(
                    static function (array $entry): string {
                        $parts = [];
                        if ($entry['centralOnlyExtraFieldIds'] !== []) {
                            $parts[] = 'central-only ids ' . implode('/', $entry['centralOnlyExtraFieldIds']);
                        }
                        if ($entry['localOnlyExtraFieldIds'] !== []) {
                            $parts[] = 'local-only ids ' . implode('/', $entry['localOnlyExtraFieldIds']);
                        }

                        return $entry['name'] . ' (' . implode('; ', $parts) . ')';
                    },
                    $summary['mismatchedEntries']
                )
            );

            throw new \RuntimeException(
                'ZIP package contains central/local extra field id mismatches that require explicit import review: ' . $entries
            );
        }

        return $summary;
    }

    /**
     * @return array{
     *     entryCount:int,
     *     extraFieldEntryCount:int,
     *     duplicateExtraFieldEntryCount:int,
     *     duplicateCentralExtraFieldEntryCount:int,
     *     duplicateLocalExtraFieldEntryCount:int,
     *     mismatchedExtraFieldEntryCount:int,
     *     mismatchedExtraFieldValueEntryCount:int,
     *     centralOnlyExtraFieldEntryCount:int,
     *     localOnlyExtraFieldEntryCount:int,
     *     duplicateEntries:list<array<string, mixed>>,
     *     mismatchedEntries:list<array<string, mixed>>,
     *     valueMismatchedEntries:list<array<string, mixed>>,
     *     entries:list<array<string, mixed>>
     * }
     */
    public function assertMatchingExtraFieldValues(): array
    {
        $summary = $this->extraFieldPreflight();
        if ($summary['mismatchedExtraFieldValueEntryCount'] > 0) {
            $entries = implode(
                ', ',
                array_map(
                    static fn (array $entry): string => $entry['name']
                        . ' (ids ' . implode('/', $entry['mismatchedExtraFieldValueIds']) . ')',
                    $summary['valueMismatchedEntries']
                )
            );

            throw new \RuntimeException(
                'ZIP package contains central/local extra field value mismatches that require explicit import review: '
                . $entries
            );
        }

        return $summary;
    }

    /**
     * @return array{
     *     entryCount:int,
     *     collisionEntryCount:int,
     *     collisionEntries:list<array{name:string, path:string, isDirectory:bool, samePathFileName:?string, samePathDirectoryName:?string, ancestorFileNames:list<string>, descendantEntryNames:list<string>, hasPathHierarchyCollision:bool, issues:list<string>}>,
     *     entries:list<array{name:string, path:string, isDirectory:bool, samePathFileName:?string, samePathDirectoryName:?string, ancestorFileNames:list<string>, descendantEntryNames:list<string>, hasPathHierarchyCollision:bool, issues:list<string>}>
     * }
     */
    public function pathHierarchyPreflight(): array
    {
        $fileNamesByPath = [];
        $directoryNamesByPath = [];
        $pathsByName = [];

        foreach ($this->entries as $entry) {
            $path = rtrim($entry->name, '/');
            $pathsByName[$entry->name] = $path;
            if ($entry->isDirectory()) {
                $directoryNamesByPath[$path] = $entry->name;
            } else {
                $fileNamesByPath[$path] = $entry->name;
            }
        }

        $ancestorFileNamesByEntryName = [];
        $descendantEntryNamesByFileName = [];
        foreach ($fileNamesByPath as $fileName) {
            $descendantEntryNamesByFileName[$fileName] = [];
        }

        foreach ($this->entries as $entry) {
            $path = $pathsByName[$entry->name];
            $segments = explode('/', $path);
            for ($depth = 1, $segmentCount = count($segments); $depth < $segmentCount; $depth++) {
                $ancestorPath = implode('/', array_slice($segments, 0, $depth));
                if (!isset($fileNamesByPath[$ancestorPath])) {
                    continue;
                }

                $ancestorFileName = $fileNamesByPath[$ancestorPath];
                $ancestorFileNamesByEntryName[$entry->name][] = $ancestorFileName;
                $descendantEntryNamesByFileName[$ancestorFileName][] = $entry->name;
            }
        }

        $entries = [];
        $collisionEntries = [];
        foreach ($this->entries as $entry) {
            $path = $pathsByName[$entry->name];
            $samePathFileName = $entry->isDirectory() ? ($fileNamesByPath[$path] ?? null) : null;
            $samePathDirectoryName = $entry->isDirectory() ? null : ($directoryNamesByPath[$path] ?? null);
            $ancestorFileNames = $ancestorFileNamesByEntryName[$entry->name] ?? [];
            $descendantEntryNames = $entry->isDirectory() ? [] : ($descendantEntryNamesByFileName[$entry->name] ?? []);
            $issues = [];

            if ($samePathFileName !== null || $samePathDirectoryName !== null) {
                $issues[] = 'file-directory-same-path';
            }

            if ($ancestorFileNames !== []) {
                $issues[] = 'ancestor-file-entry';
            }

            if ($descendantEntryNames !== []) {
                $issues[] = 'file-used-as-directory';
            }

            $summary = [
                'name' => $entry->name,
                'path' => $path,
                'isDirectory' => $entry->isDirectory(),
                'samePathFileName' => $samePathFileName,
                'samePathDirectoryName' => $samePathDirectoryName,
                'ancestorFileNames' => $ancestorFileNames,
                'descendantEntryNames' => $descendantEntryNames,
                'hasPathHierarchyCollision' => $issues !== [],
                'issues' => $issues,
            ];
            $entries[] = $summary;
            if ($issues !== []) {
                $collisionEntries[] = $summary;
            }
        }

        return [
            'entryCount' => count($this->entries),
            'collisionEntryCount' => count($collisionEntries),
            'collisionEntries' => $collisionEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     collisionEntryCount:int,
     *     collisionEntries:list<array{name:string, path:string, isDirectory:bool, samePathFileName:?string, samePathDirectoryName:?string, ancestorFileNames:list<string>, descendantEntryNames:list<string>, hasPathHierarchyCollision:bool, issues:list<string>}>,
     *     entries:list<array{name:string, path:string, isDirectory:bool, samePathFileName:?string, samePathDirectoryName:?string, ancestorFileNames:list<string>, descendantEntryNames:list<string>, hasPathHierarchyCollision:bool, issues:list<string>}>
     * }
     */
    public function assertNoPathHierarchyCollisions(): array
    {
        $summary = $this->pathHierarchyPreflight();
        if ($summary['collisionEntryCount'] > 0) {
            $entries = implode(
                ', ',
                array_map(
                    static fn (array $entry): string => $entry['name'] . ' (' . implode('/', $entry['issues']) . ')',
                    $summary['collisionEntries']
                )
            );

            throw new \RuntimeException(
                'ZIP package contains file/directory path hierarchy collisions that require explicit import review: '
                . $entries
            );
        }

        return $summary;
    }

    /**
     * @return array{
     *     entryCount:int,
     *     collisionGroupCount:int,
     *     collisionEntryCount:int,
     *     collisionGroups:list<array{caseFoldKey:string, entryNames:list<string>}>,
     *     collisionEntries:list<array{name:string, caseFoldKey:string, equivalentEntryNames:list<string>, hasCaseInsensitiveNameCollision:bool, issues:list<string>}>,
     *     entries:list<array{name:string, caseFoldKey:string, equivalentEntryNames:list<string>, hasCaseInsensitiveNameCollision:bool, issues:list<string>}>
     * }
     */
    public function caseInsensitiveNamePreflight(): array
    {
        $entryNamesByCaseFoldKey = [];
        foreach ($this->entries as $entry) {
            $entryNamesByCaseFoldKey[self::caseFoldZipEntryName($entry->name)][] = $entry->name;
        }

        $collisionGroups = [];
        foreach ($entryNamesByCaseFoldKey as $caseFoldKey => $entryNames) {
            if (count($entryNames) > 1) {
                $collisionGroups[] = [
                    'caseFoldKey' => $caseFoldKey,
                    'entryNames' => $entryNames,
                ];
            }
        }

        $entries = [];
        $collisionEntries = [];
        foreach ($this->entries as $entry) {
            $caseFoldKey = self::caseFoldZipEntryName($entry->name);
            $equivalentEntryNames = $entryNamesByCaseFoldKey[$caseFoldKey] ?? [];
            $hasCollision = count($equivalentEntryNames) > 1;
            $issues = $hasCollision ? ['case-insensitive-name-collision'] : [];
            $summary = [
                'name' => $entry->name,
                'caseFoldKey' => $caseFoldKey,
                'equivalentEntryNames' => $equivalentEntryNames,
                'hasCaseInsensitiveNameCollision' => $hasCollision,
                'issues' => $issues,
            ];
            $entries[] = $summary;
            if ($hasCollision) {
                $collisionEntries[] = $summary;
            }
        }

        return [
            'entryCount' => count($this->entries),
            'collisionGroupCount' => count($collisionGroups),
            'collisionEntryCount' => count($collisionEntries),
            'collisionGroups' => $collisionGroups,
            'collisionEntries' => $collisionEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     collisionGroupCount:int,
     *     collisionEntryCount:int,
     *     collisionGroups:list<array{caseFoldKey:string, entryNames:list<string>}>,
     *     collisionEntries:list<array{name:string, caseFoldKey:string, equivalentEntryNames:list<string>, hasCaseInsensitiveNameCollision:bool, issues:list<string>}>,
     *     entries:list<array{name:string, caseFoldKey:string, equivalentEntryNames:list<string>, hasCaseInsensitiveNameCollision:bool, issues:list<string>}>
     * }
     */
    public function assertNoCaseInsensitiveNameCollisions(): array
    {
        $summary = $this->caseInsensitiveNamePreflight();
        if ($summary['collisionEntryCount'] > 0) {
            $groups = implode(
                ', ',
                array_map(
                    static fn (array $group): string => $group['caseFoldKey'] . ' (' . implode(', ', $group['entryNames']) . ')',
                    $summary['collisionGroups']
                )
            );

            throw new \RuntimeException(
                'ZIP package contains case-insensitive entry name collisions that require explicit import review: '
                . $groups
            );
        }

        return $summary;
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
     *     eocdOffset:int,
     *     diskNumber:int,
     *     centralDirectoryDisk:int,
     *     diskEntryCount:int,
     *     totalEntryCount:int,
     *     centralDirectorySize:int,
     *     centralDirectoryOffset:int,
     *     centralDirectoryEnd:int,
     *     packageComment:string,
     *     packageCommentLength:int,
     *     isSingleDisk:bool,
     *     requiresZip64:bool,
     *     isArchiveLayoutSupported:bool,
     *     hasZip64EndOfCentralDirectoryLocator:bool,
     *     hasZip64EndOfCentralDirectory:bool,
     *     zip64EndOfCentralDirectoryLocatorOffset:?int,
     *     zip64EndOfCentralDirectoryOffset:?int,
     *     zip64EndOfCentralDirectorySize:?int,
     *     zip64TotalDisks:?int
     * }
     */
    public static function endOfCentralDirectoryPreflight(string $bytes): array
    {
        $eocdOffset = self::findEndOfCentralDirectory($bytes);
        $diskNumber = self::readUInt16($bytes, $eocdOffset + 4);
        $centralDirectoryDisk = self::readUInt16($bytes, $eocdOffset + 6);
        $diskEntryCount = self::readUInt16($bytes, $eocdOffset + 8);
        $totalEntryCount = self::readUInt16($bytes, $eocdOffset + 10);
        $centralDirectorySize = self::readUInt32($bytes, $eocdOffset + 12);
        $centralDirectoryOffset = self::readUInt32($bytes, $eocdOffset + 16);
        $packageCommentLength = self::readUInt16($bytes, $eocdOffset + 20);
        $isSingleDisk = $diskNumber === 0
            && $centralDirectoryDisk === 0
            && $diskEntryCount === $totalEntryCount;
        $requiresZip64 = $totalEntryCount === 0xffff
            || $centralDirectorySize === 0xffffffff
            || $centralDirectoryOffset === 0xffffffff;
        $zip64 = self::zip64EndOfCentralDirectoryPreflight($bytes, $eocdOffset);
        $requiresZip64 = $requiresZip64 || $zip64['hasZip64EndOfCentralDirectoryLocator'];

        return [
            'eocdOffset' => $eocdOffset,
            'diskNumber' => $diskNumber,
            'centralDirectoryDisk' => $centralDirectoryDisk,
            'diskEntryCount' => $diskEntryCount,
            'totalEntryCount' => $totalEntryCount,
            'centralDirectorySize' => $centralDirectorySize,
            'centralDirectoryOffset' => $centralDirectoryOffset,
            'centralDirectoryEnd' => $centralDirectoryOffset + $centralDirectorySize,
            'packageComment' => substr($bytes, $eocdOffset + 22, $packageCommentLength),
            'packageCommentLength' => $packageCommentLength,
            'isSingleDisk' => $isSingleDisk,
            'requiresZip64' => $requiresZip64,
            'isArchiveLayoutSupported' => $isSingleDisk && !$requiresZip64,
            'hasZip64EndOfCentralDirectoryLocator' => $zip64['hasZip64EndOfCentralDirectoryLocator'],
            'hasZip64EndOfCentralDirectory' => $zip64['hasZip64EndOfCentralDirectory'],
            'zip64EndOfCentralDirectoryLocatorOffset' => $zip64['zip64EndOfCentralDirectoryLocatorOffset'],
            'zip64EndOfCentralDirectoryOffset' => $zip64['zip64EndOfCentralDirectoryOffset'],
            'zip64EndOfCentralDirectorySize' => $zip64['zip64EndOfCentralDirectorySize'],
            'zip64TotalDisks' => $zip64['zip64TotalDisks'],
        ];
    }

    /**
     * @return array{
     *     eocdOffset:int,
     *     diskNumber:int,
     *     centralDirectoryDisk:int,
     *     diskEntryCount:int,
     *     totalEntryCount:int,
     *     centralDirectorySize:int,
     *     centralDirectoryOffset:int,
     *     centralDirectoryEnd:int,
     *     packageComment:string,
     *     packageCommentLength:int,
     *     isSingleDisk:bool,
     *     requiresZip64:bool,
     *     isArchiveLayoutSupported:bool,
     *     hasZip64EndOfCentralDirectoryLocator:bool,
     *     hasZip64EndOfCentralDirectory:bool,
     *     zip64EndOfCentralDirectoryLocatorOffset:?int,
     *     zip64EndOfCentralDirectoryOffset:?int,
     *     zip64EndOfCentralDirectorySize:?int,
     *     zip64TotalDisks:?int,
     *     hasCentralDirectorySignature:bool,
     *     centralDirectorySignatureOffset:?int,
     *     centralDirectorySignatureLength:int
     * }
     */
    public function archivePreflight(): array
    {
        $summary = self::endOfCentralDirectoryPreflight($this->bytes);
        $summary['hasCentralDirectorySignature'] = $this->hasCentralDirectorySignature();
        $summary['centralDirectorySignatureOffset'] = $this->centralDirectorySignatureOffset;
        $summary['centralDirectorySignatureLength'] = $this->centralDirectorySignatureData === null
            ? 0
            : strlen($this->centralDirectorySignatureData);

        return $summary;
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
     *     readableEntryCount:int,
     *     failedEntryCount:int,
     *     maxEntryUncompressedBytes:?int,
     *     failedEntries:list<array{name:string, compressionMethod:int, isDirectory:bool, compressedSize:int, uncompressedSize:int, crc32:int, crc32Hex:string, isReadable:bool, bytesRead:?int, error:string}>,
     *     entries:list<array{name:string, compressionMethod:int, isDirectory:bool, compressedSize:int, uncompressedSize:int, crc32:int, crc32Hex:string, isReadable:bool, bytesRead:?int, error:?string}>
     * }
     */
    public function readIntegrityPreflight(?int $maxEntryUncompressedBytes = null): array
    {
        self::assertReadLimit($maxEntryUncompressedBytes, 'package integrity preflight');

        $entries = [];
        $failedEntries = [];

        foreach ($this->entries as $entry) {
            $summary = [
                'name' => $entry->name,
                'compressionMethod' => $entry->compressionMethod,
                'isDirectory' => $entry->isDirectory(),
                'compressedSize' => $entry->compressedSize,
                'uncompressedSize' => $entry->uncompressedSize,
                'crc32' => $entry->crc32,
                'crc32Hex' => $entry->crc32Hex(),
                'isReadable' => true,
                'bytesRead' => null,
                'error' => null,
            ];

            try {
                $summary['bytesRead'] = strlen($this->read($entry->name, $maxEntryUncompressedBytes));
            } catch (\RuntimeException $exception) {
                $summary['isReadable'] = false;
                $summary['error'] = $exception->getMessage();
                $failedEntries[] = [
                    'name' => $summary['name'],
                    'compressionMethod' => $summary['compressionMethod'],
                    'isDirectory' => $summary['isDirectory'],
                    'compressedSize' => $summary['compressedSize'],
                    'uncompressedSize' => $summary['uncompressedSize'],
                    'crc32' => $summary['crc32'],
                    'crc32Hex' => $summary['crc32Hex'],
                    'isReadable' => false,
                    'bytesRead' => null,
                    'error' => $summary['error'],
                ];
            }

            $entries[] = $summary;
        }

        return [
            'entryCount' => count($this->entries),
            'readableEntryCount' => count($this->entries) - count($failedEntries),
            'failedEntryCount' => count($failedEntries),
            'maxEntryUncompressedBytes' => $maxEntryUncompressedBytes,
            'failedEntries' => $failedEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     descriptorEntryCount:int,
     *     signedDescriptorEntryCount:int,
     *     unsignedDescriptorEntryCount:int,
     *     zip64SizedDescriptorEntryCount:int,
     *     descriptorEntries:list<array{name:string, usesDataDescriptor:bool, hasSignature:bool, descriptorOffset:int, valueOffset:int, descriptorLength:int, crc32:int, crc32Hex:string, compressedSize:int, uncompressedSize:int, usesZip64SizedDescriptor:bool, localHeaderCrc32:int, localHeaderCompressedSize:int, localHeaderUncompressedSize:int, hasZeroLocalHeaderPlaceholders:bool}>,
     *     entries:list<array{name:string, usesDataDescriptor:bool, hasSignature:?bool, descriptorOffset:?int, valueOffset:?int, descriptorLength:?int, crc32:?int, crc32Hex:?string, compressedSize:int, uncompressedSize:int, usesZip64SizedDescriptor:bool, localHeaderCrc32:?int, localHeaderCompressedSize:?int, localHeaderUncompressedSize:?int, hasZeroLocalHeaderPlaceholders:?bool}>
     * }
     */
    public function dataDescriptorPreflight(): array
    {
        $entries = [];
        $descriptorEntries = [];
        $signedDescriptorEntryCount = 0;
        $unsignedDescriptorEntryCount = 0;
        $zip64SizedDescriptorEntryCount = 0;

        foreach ($this->entries as $entry) {
            $usesDataDescriptor = ($entry->generalPurposeFlags & 0x0008) !== 0;
            $summary = [
                'name' => $entry->name,
                'usesDataDescriptor' => $usesDataDescriptor,
                'hasSignature' => null,
                'descriptorOffset' => null,
                'valueOffset' => null,
                'descriptorLength' => null,
                'crc32' => null,
                'crc32Hex' => null,
                'compressedSize' => $entry->compressedSize,
                'uncompressedSize' => $entry->uncompressedSize,
                'usesZip64SizedDescriptor' => false,
                'localHeaderCrc32' => null,
                'localHeaderCompressedSize' => null,
                'localHeaderUncompressedSize' => null,
                'hasZeroLocalHeaderPlaceholders' => null,
            ];

            if ($usesDataDescriptor) {
                $localHeader = $this->readLocalHeader($entry);
                $summary['localHeaderCrc32'] = $localHeader['crc32'];
                $summary['localHeaderCompressedSize'] = $localHeader['compressedSize'];
                $summary['localHeaderUncompressedSize'] = $localHeader['uncompressedSize'];
                $summary['hasZeroLocalHeaderPlaceholders'] = $localHeader['crc32'] === 0
                    && $localHeader['compressedSize'] === 0
                    && $localHeader['uncompressedSize'] === 0;
                $descriptor = $this->dataDescriptorMetadata(
                    $entry,
                    $localHeader['dataStart'] + $entry->compressedSize,
                    $this->nextEntryOrCentralDirectoryOffset($entry)
                );
                $summary = array_merge($summary, $descriptor);
                $descriptorEntries[] = $summary;
                if ($descriptor['hasSignature']) {
                    $signedDescriptorEntryCount++;
                } else {
                    $unsignedDescriptorEntryCount++;
                }
                if ($descriptor['usesZip64SizedDescriptor']) {
                    $zip64SizedDescriptorEntryCount++;
                }
            }

            $entries[] = $summary;
        }

        return [
            'entryCount' => count($this->entries),
            'descriptorEntryCount' => count($descriptorEntries),
            'signedDescriptorEntryCount' => $signedDescriptorEntryCount,
            'unsignedDescriptorEntryCount' => $unsignedDescriptorEntryCount,
            'zip64SizedDescriptorEntryCount' => $zip64SizedDescriptorEntryCount,
            'descriptorEntries' => $descriptorEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     readableEntryCount:int,
     *     failedEntryCount:int,
     *     maxEntryUncompressedBytes:?int,
     *     failedEntries:list<array{name:string, compressionMethod:int, isDirectory:bool, compressedSize:int, uncompressedSize:int, crc32:int, crc32Hex:string, isReadable:bool, bytesRead:?int, error:string}>,
     *     entries:list<array{name:string, compressionMethod:int, isDirectory:bool, compressedSize:int, uncompressedSize:int, crc32:int, crc32Hex:string, isReadable:bool, bytesRead:?int, error:?string}>
     * }
     */
    public function assertReadableEntries(?int $maxEntryUncompressedBytes = null): array
    {
        $summary = $this->readIntegrityPreflight($maxEntryUncompressedBytes);
        if ($summary['failedEntryCount'] > 0) {
            $entries = implode(
                ', ',
                array_map(
                    static fn (array $entry): string => $entry['name'] . ' (' . $entry['error'] . ')',
                    $summary['failedEntries']
                )
            );

            throw new \RuntimeException(
                'ZIP package contains entries that cannot be read by native pandoc package import: ' . $entries
            );
        }

        return $summary;
    }

    /**
     * @return array{
     *     entryCount:int,
     *     isValid:bool,
     *     diagnostics:list<string>,
     *     maxTotalUncompressedBytes:?int,
     *     maxExpansionRatio:?float,
     *     maxEntryUncompressedBytes:?int,
     *     archive:array<string, mixed>,
     *     size:array<string, mixed>,
     *     compressionMethods:array<string, mixed>,
     *     comments:array<string, mixed>,
     *     extraFields:array<string, mixed>,
     *     pathHierarchy:array<string, mixed>,
     *     caseInsensitiveNames:array<string, mixed>,
     *     permissions:array<string, mixed>,
     *     creatorHostSystems:array<string, mixed>,
     *     dataDescriptors:array<string, mixed>,
     *     readIntegrity:array<string, mixed>
     * }
     */
    public function strictImportPreflight(
        ?int $maxTotalUncompressedBytes = null,
        ?float $maxExpansionRatio = null,
        ?int $maxEntryUncompressedBytes = null
    ): array {
        if ($maxTotalUncompressedBytes !== null && $maxTotalUncompressedBytes < 0) {
            throw new \InvalidArgumentException('ZIP package maximum total uncompressed size must be non-negative');
        }

        if ($maxExpansionRatio !== null && $maxExpansionRatio < 0.0) {
            throw new \InvalidArgumentException('ZIP package maximum expansion ratio must be non-negative');
        }

        self::assertReadLimit($maxEntryUncompressedBytes, 'strict package import preflight');

        $archive = $this->archivePreflight();
        $size = $this->sizePreflight();
        $compressionMethods = $this->compressionMethodPreflight();
        $comments = $this->commentPreflight();
        $extraFields = $this->extraFieldPreflight();
        $pathHierarchy = $this->pathHierarchyPreflight();
        $caseInsensitiveNames = $this->caseInsensitiveNamePreflight();
        $permissions = $this->permissionPreflight();
        $creatorHostSystems = $this->creatorHostSystemPreflight();
        $dataDescriptors = $this->dataDescriptorPreflight();
        $readIntegrity = $this->readIntegrityPreflight($maxEntryUncompressedBytes);
        $diagnostics = [];

        if (!$archive['isArchiveLayoutSupported']) {
            $diagnostics[] = 'unsupported-archive-layout';
        }

        if ($comments['hasComments']) {
            $diagnostics[] = 'package-or-entry-comments';
        }

        if ($compressionMethods['unsupportedCompressionMethodCount'] > 0) {
            $diagnostics[] = 'unsupported-compression-methods';
        }

        if ($extraFields['duplicateExtraFieldEntryCount'] > 0) {
            $diagnostics[] = 'duplicate-extra-field-ids';
        }

        if ($extraFields['mismatchedExtraFieldEntryCount'] > 0) {
            $diagnostics[] = 'central-local-extra-field-id-mismatch';
        }

        if ($extraFields['mismatchedExtraFieldValueEntryCount'] > 0) {
            $diagnostics[] = 'central-local-extra-field-value-mismatch';
        }

        if ($pathHierarchy['collisionEntryCount'] > 0) {
            $diagnostics[] = 'path-hierarchy-collisions';
        }

        if ($caseInsensitiveNames['collisionEntryCount'] > 0) {
            $diagnostics[] = 'case-insensitive-name-collisions';
        }

        if ($permissions['executableFileCount'] > 0) {
            $diagnostics[] = 'executable-file-entries';
        }

        if ($creatorHostSystems['unknownHostSystemEntryCount'] > 0) {
            $diagnostics[] = 'unknown-creator-host-systems';
        }

        if (
            $maxTotalUncompressedBytes !== null
            && $size['uncompressedBytes'] > $maxTotalUncompressedBytes
        ) {
            $diagnostics[] = 'total-uncompressed-size-exceeds-limit';
        }

        if (
            $maxExpansionRatio !== null
            && $size['expansionRatio'] === null
            && $size['uncompressedBytes'] > 0
        ) {
            $diagnostics[] = 'expansion-ratio-unknown';
        } elseif (
            $maxExpansionRatio !== null
            && $size['expansionRatio'] !== null
            && $size['expansionRatio'] > $maxExpansionRatio
        ) {
            $diagnostics[] = 'expansion-ratio-exceeds-limit';
        }

        if ($readIntegrity['failedEntryCount'] > 0) {
            $diagnostics[] = 'unreadable-entries';
        }

        return [
            'entryCount' => count($this->entries),
            'isValid' => $diagnostics === [],
            'diagnostics' => $diagnostics,
            'maxTotalUncompressedBytes' => $maxTotalUncompressedBytes,
            'maxExpansionRatio' => $maxExpansionRatio,
            'maxEntryUncompressedBytes' => $maxEntryUncompressedBytes,
            'archive' => $archive,
            'size' => $size,
            'compressionMethods' => $compressionMethods,
            'comments' => $comments,
            'extraFields' => $extraFields,
            'pathHierarchy' => $pathHierarchy,
            'caseInsensitiveNames' => $caseInsensitiveNames,
            'permissions' => $permissions,
            'creatorHostSystems' => $creatorHostSystems,
            'dataDescriptors' => $dataDescriptors,
            'readIntegrity' => $readIntegrity,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     isValid:bool,
     *     diagnostics:list<string>,
     *     maxTotalUncompressedBytes:?int,
     *     maxExpansionRatio:?float,
     *     maxEntryUncompressedBytes:?int,
     *     archive:array<string, mixed>,
     *     size:array<string, mixed>,
     *     compressionMethods:array<string, mixed>,
     *     comments:array<string, mixed>,
     *     extraFields:array<string, mixed>,
     *     pathHierarchy:array<string, mixed>,
     *     caseInsensitiveNames:array<string, mixed>,
     *     permissions:array<string, mixed>,
     *     creatorHostSystems:array<string, mixed>,
     *     dataDescriptors:array<string, mixed>,
     *     readIntegrity:array<string, mixed>
     * }
     */
    public function assertStrictImportable(
        ?int $maxTotalUncompressedBytes = null,
        ?float $maxExpansionRatio = null,
        ?int $maxEntryUncompressedBytes = null
    ): array {
        $summary = $this->strictImportPreflight(
            $maxTotalUncompressedBytes,
            $maxExpansionRatio,
            $maxEntryUncompressedBytes
        );
        if ($summary['isValid']) {
            return $summary;
        }

        throw new \RuntimeException(
            'ZIP package failed strict native pandoc import preflight: '
            . implode(', ', $summary['diagnostics'])
        );
    }

    /**
     * @return array{
     *     entryCount:int,
     *     supportedEntryCount:int,
     *     unsupportedCompressionMethodCount:int,
     *     storedEntryCount:int,
     *     deflatedEntryCount:int,
     *     unsupportedEntries:list<array{name:string, compressionMethod:int, isDirectory:bool, compressedSize:int, uncompressedSize:int}>,
     *     entries:list<array{name:string, compressionMethod:int, compressionMethodName:string, isSupported:bool, isDirectory:bool, compressedSize:int, uncompressedSize:int}>
     * }
     */
    public function compressionMethodPreflight(): array
    {
        $storedEntryCount = 0;
        $deflatedEntryCount = 0;
        $unsupportedEntries = [];
        $entries = [];

        foreach ($this->entries as $entry) {
            if ($entry->compressionMethod === 0) {
                $storedEntryCount++;
            } elseif ($entry->compressionMethod === 8) {
                $deflatedEntryCount++;
            }

            $isSupported = $entry->compressionMethod === 0 || $entry->compressionMethod === 8;
            $summary = [
                'name' => $entry->name,
                'compressionMethod' => $entry->compressionMethod,
                'compressionMethodName' => self::compressionMethodName($entry->compressionMethod),
                'isSupported' => $isSupported,
                'isDirectory' => $entry->isDirectory(),
                'compressedSize' => $entry->compressedSize,
                'uncompressedSize' => $entry->uncompressedSize,
            ];
            $entries[] = $summary;
            if (!$isSupported) {
                $unsupportedEntries[] = [
                    'name' => $entry->name,
                    'compressionMethod' => $entry->compressionMethod,
                    'isDirectory' => $entry->isDirectory(),
                    'compressedSize' => $entry->compressedSize,
                    'uncompressedSize' => $entry->uncompressedSize,
                ];
            }
        }

        return [
            'entryCount' => count($this->entries),
            'supportedEntryCount' => count($this->entries) - count($unsupportedEntries),
            'unsupportedCompressionMethodCount' => count($unsupportedEntries),
            'storedEntryCount' => $storedEntryCount,
            'deflatedEntryCount' => $deflatedEntryCount,
            'unsupportedEntries' => $unsupportedEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     supportedEntryCount:int,
     *     unsupportedCompressionMethodCount:int,
     *     storedEntryCount:int,
     *     deflatedEntryCount:int,
     *     unsupportedEntries:list<array{name:string, compressionMethod:int, isDirectory:bool, compressedSize:int, uncompressedSize:int}>,
     *     entries:list<array{name:string, compressionMethod:int, compressionMethodName:string, isSupported:bool, isDirectory:bool, compressedSize:int, uncompressedSize:int}>
     * }
     */
    public function assertSupportedCompressionMethods(): array
    {
        $summary = $this->compressionMethodPreflight();
        if ($summary['unsupportedCompressionMethodCount'] > 0) {
            $entries = implode(
                ', ',
                array_map(
                    static fn (array $entry): string => $entry['name'] . ' method ' . $entry['compressionMethod'],
                    $summary['unsupportedEntries']
                )
            );

            throw new \RuntimeException(
                'ZIP package contains unsupported compression methods for native pandoc package import: ' . $entries
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

    /**
     * @return array{
     *     entryCount:int,
     *     knownHostSystemEntryCount:int,
     *     unknownHostSystemEntryCount:int,
     *     hostSystems:list<array{id:int, name:string, isKnown:bool, entryCount:int}>,
     *     unknownEntries:list<array{name:string, madeByHostSystem:int, madeByHostSystemName:string, madeByVersion:int, versionMadeBy:int, isKnown:bool}>,
     *     entries:list<array{name:string, madeByHostSystem:int, madeByHostSystemName:string, madeByVersion:int, versionMadeBy:int, isKnown:bool}>
     * }
     */
    public function creatorHostSystemPreflight(): array
    {
        $entries = [];
        $unknownEntries = [];
        $hostSystems = [];

        foreach ($this->entries as $entry) {
            $hostSystem = $entry->madeByHostSystem();
            $hostSystemName = self::creatorHostSystemName($hostSystem);
            $isKnown = self::isKnownCreatorHostSystem($hostSystem);
            $summary = [
                'name' => $entry->name,
                'madeByHostSystem' => $hostSystem,
                'madeByHostSystemName' => $hostSystemName,
                'madeByVersion' => $entry->madeByVersion(),
                'versionMadeBy' => $entry->versionMadeBy,
                'isKnown' => $isKnown,
            ];
            $entries[] = $summary;
            if (!$isKnown) {
                $unknownEntries[] = $summary;
            }

            if (!isset($hostSystems[$hostSystem])) {
                $hostSystems[$hostSystem] = [
                    'id' => $hostSystem,
                    'name' => $hostSystemName,
                    'isKnown' => $isKnown,
                    'entryCount' => 0,
                ];
            }
            $hostSystems[$hostSystem]['entryCount']++;
        }

        return [
            'entryCount' => count($this->entries),
            'knownHostSystemEntryCount' => count($this->entries) - count($unknownEntries),
            'unknownHostSystemEntryCount' => count($unknownEntries),
            'hostSystems' => array_values($hostSystems),
            'unknownEntries' => $unknownEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     knownHostSystemEntryCount:int,
     *     unknownHostSystemEntryCount:int,
     *     hostSystems:list<array{id:int, name:string, isKnown:bool, entryCount:int}>,
     *     unknownEntries:list<array{name:string, madeByHostSystem:int, madeByHostSystemName:string, madeByVersion:int, versionMadeBy:int, isKnown:bool}>,
     *     entries:list<array{name:string, madeByHostSystem:int, madeByHostSystemName:string, madeByVersion:int, versionMadeBy:int, isKnown:bool}>
     * }
     */
    public function assertKnownCreatorHostSystems(): array
    {
        $summary = $this->creatorHostSystemPreflight();
        if ($summary['unknownHostSystemEntryCount'] > 0) {
            $entries = implode(
                ', ',
                array_map(
                    static fn (array $entry): string => $entry['name'] . ' host ' . $entry['madeByHostSystem'],
                    $summary['unknownEntries']
                )
            );

            throw new \RuntimeException(
                'ZIP package contains unknown creator host-system entries that require explicit import review: ' . $entries
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

    private static function assertDirectoryAttributeConsistency(ZipPackageEntry $entry): void
    {
        if (!$entry->isDirectory() && $entry->hasDosDirectoryAttribute()) {
            throw new \RuntimeException(
                "ZIP package entry {$entry->name} has directory external attributes but is not named as a directory"
            );
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

    private static function assertDeflateOptionFlagsMatchMethod(int $flags, int $method, string $entryName): void
    {
        $deflateOptionFlags = $flags & self::DEFLATE_OPTION_GENERAL_PURPOSE_FLAGS;
        if ($deflateOptionFlags === 0 || $method === 8) {
            return;
        }

        throw new \RuntimeException(
            sprintf(
                'ZIP entry %s uses deflate compression option flag bits 0x%04x without deflated compression',
                $entryName,
                $deflateOptionFlags
            )
        );
    }

    private static function assertSupportedVersionNeededToExtract(int $versionNeededToExtract, string $entryName): void
    {
        if ($versionNeededToExtract <= self::MAX_SUPPORTED_VERSION_NEEDED_TO_EXTRACT) {
            return;
        }

        throw new \RuntimeException(
            "ZIP entry {$entryName} requires version needed to extract {$versionNeededToExtract}; "
            . 'this bounded package reader supports versions up to '
            . self::MAX_SUPPORTED_VERSION_NEEDED_TO_EXTRACT
        );
    }

    private function validateEntryLocalLayout(ZipPackageEntry $entry): void
    {
        $localHeader = $this->readLocalHeader($entry);
        if ($entry->compressionMethod === 0 && $entry->compressedSize !== $entry->uncompressedSize) {
            throw new \RuntimeException(
                "Stored ZIP entry {$entry->name} has mismatched compressed and uncompressed sizes"
            );
        }

        $dataEnd = $localHeader['dataStart'] + $entry->compressedSize;
        if ($dataEnd > strlen($this->bytes)) {
            throw new \RuntimeException("ZIP compressed data for {$entry->name} extends beyond available bytes");
        }

        if ($dataEnd > $this->centralDirectoryOffset) {
            throw new \RuntimeException("ZIP compressed data for {$entry->name} overlaps the central directory");
        }

        $recordEnd = $dataEnd;
        $nextOffset = $this->nextEntryOrCentralDirectoryOffset($entry);
        if (($entry->generalPurposeFlags & 0x0008) !== 0) {
            $descriptor = $this->dataDescriptorMetadata($entry, $dataEnd, $nextOffset);
            $recordEnd += $descriptor['descriptorLength'];
            if ($recordEnd > strlen($this->bytes)) {
                throw new \RuntimeException("ZIP data descriptor for {$entry->name} extends beyond available bytes");
            }

            if ($recordEnd > $this->centralDirectoryOffset) {
                throw new \RuntimeException("ZIP data descriptor for {$entry->name} overlaps the central directory");
            }
        }

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

    /**
     * @return array{
     *     hasZip64EndOfCentralDirectoryLocator:bool,
     *     hasZip64EndOfCentralDirectory:bool,
     *     zip64EndOfCentralDirectoryLocatorOffset:?int,
     *     zip64EndOfCentralDirectoryOffset:?int,
     *     zip64EndOfCentralDirectorySize:?int,
     *     zip64TotalDisks:?int
     * }
     */
    private static function zip64EndOfCentralDirectoryPreflight(string $bytes, int $eocdOffset): array
    {
        $empty = [
            'hasZip64EndOfCentralDirectoryLocator' => false,
            'hasZip64EndOfCentralDirectory' => false,
            'zip64EndOfCentralDirectoryLocatorOffset' => null,
            'zip64EndOfCentralDirectoryOffset' => null,
            'zip64EndOfCentralDirectorySize' => null,
            'zip64TotalDisks' => null,
        ];

        $locatorOffset = $eocdOffset - 20;
        if ($locatorOffset < 0 || substr($bytes, $locatorOffset, 4) !== self::ZIP64_END_OF_CENTRAL_DIRECTORY_LOCATOR_SIGNATURE) {
            return $empty;
        }

        self::assertRange($bytes, $locatorOffset, 20, 'ZIP64 end-of-central-directory locator');
        $recordOffset = self::readUInt64($bytes, $locatorOffset + 8);
        $totalDisks = self::readUInt32($bytes, $locatorOffset + 16);
        if (substr($bytes, $recordOffset, 4) !== self::ZIP64_END_OF_CENTRAL_DIRECTORY_SIGNATURE) {
            throw new \RuntimeException('ZIP64 end-of-central-directory locator points to an invalid record');
        }

        self::assertRange($bytes, $recordOffset, 12, 'ZIP64 end-of-central-directory record');
        $declaredRecordPayloadSize = self::readUInt64($bytes, $recordOffset + 4);
        $recordSize = 12 + $declaredRecordPayloadSize;
        self::assertRange($bytes, $recordOffset, $recordSize, 'ZIP64 end-of-central-directory record');
        if ($recordOffset + $recordSize !== $locatorOffset) {
            throw new \RuntimeException('ZIP64 end-of-central-directory record does not end before its locator');
        }

        return [
            'hasZip64EndOfCentralDirectoryLocator' => true,
            'hasZip64EndOfCentralDirectory' => true,
            'zip64EndOfCentralDirectoryLocatorOffset' => $locatorOffset,
            'zip64EndOfCentralDirectoryOffset' => $recordOffset,
            'zip64EndOfCentralDirectorySize' => $recordSize,
            'zip64TotalDisks' => $totalDisks,
        ];
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
                'Malformed ZIP central-directory digital signature record'
            );
        }

        throw new \RuntimeException("Unexpected ZIP bytes {$label}");
    }

    /**
     * @return array{offset:int, data:string, endOffset:int}|null
     */
    private static function centralDirectoryDigitalSignatureRecordAt(string $bytes, int $offset): ?array
    {
        if (substr($bytes, $offset, 4) !== self::CENTRAL_DIRECTORY_DIGITAL_SIGNATURE) {
            return null;
        }

        self::assertRange($bytes, $offset, 6, 'central-directory digital signature record');
        $length = self::readUInt16($bytes, $offset + 4);
        $dataOffset = $offset + 6;
        self::assertRange($bytes, $dataOffset, $length, 'central-directory digital signature data');

        return [
            'offset' => $offset,
            'data' => substr($bytes, $dataOffset, $length),
            'endOffset' => $dataOffset + $length,
        ];
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
            $this->dataDescriptorMetadata(
                $entry,
                $dataStart + $entry->compressedSize,
                $this->nextEntryOrCentralDirectoryOffset($entry)
            );
        }

        return substr($this->bytes, $dataStart, $entry->compressedSize);
    }

    /**
     * @return array{extraFieldData:string, dataStart:int, crc32:int, compressedSize:int, uncompressedSize:int}
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

        if (($entry->generalPurposeFlags & 0x0008) !== 0) {
            if ($localCrc32 !== 0 || $localCompressedSize !== 0 || $localUncompressedSize !== 0) {
                throw new \RuntimeException(
                    "ZIP local header data descriptor placeholders must be zero for entry {$entry->name}"
                );
            }
        } else {
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
            'crc32' => $localCrc32,
            'compressedSize' => $localCompressedSize,
            'uncompressedSize' => $localUncompressedSize,
        ];
    }

    /**
     * @return array{hasSignature:bool, descriptorOffset:int, valueOffset:int, descriptorLength:int, crc32:int, crc32Hex:string, usesZip64SizedDescriptor:bool}
     */
    private function dataDescriptorMetadata(ZipPackageEntry $entry, int $offset, ?int $nextOffset = null): array
    {
        $valuesOffset = $offset;
        $hasSignature = substr($this->bytes, $offset, 4) === "PK\x07\x08";
        if ($hasSignature) {
            $valuesOffset += 4;
        }

        if ($nextOffset !== null && $nextOffset - $offset === ($hasSignature ? 24 : 20)) {
            self::assertRange($this->bytes, $valuesOffset, 20, "ZIP64-sized data descriptor for {$entry->name}");
            $zip64Crc32 = self::readUInt32($this->bytes, $valuesOffset);
            $zip64CompressedSize = self::readUInt64($this->bytes, $valuesOffset + 4);
            $zip64UncompressedSize = self::readUInt64($this->bytes, $valuesOffset + 12);
            if (
                $zip64Crc32 === $entry->crc32
                && $zip64CompressedSize === $entry->compressedSize
                && $zip64UncompressedSize === $entry->uncompressedSize
            ) {
                throw new \RuntimeException(
                    "ZIP data descriptor for {$entry->name} uses ZIP64-sized fields, "
                    . 'which are not supported by this bounded package reader'
                );
            }
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

        return [
            'hasSignature' => $hasSignature,
            'descriptorOffset' => $offset,
            'valueOffset' => $valuesOffset,
            'descriptorLength' => ($hasSignature ? 16 : 12),
            'crc32' => $crc32,
            'crc32Hex' => sprintf('%08x', $crc32),
            'usesZip64SizedDescriptor' => false,
        ];
    }

    private function inflateEntry(ZipPackageEntry $entry, string $compressed, ?int $maxUncompressedBytes = null): string
    {
        $context = @inflate_init(ZLIB_ENCODING_RAW);
        if ($context === false) {
            throw new \RuntimeException("Unable to initialize ZIP deflate reader for entry {$entry->name}");
        }

        $inflated = '';
        $compressedLength = strlen($compressed);
        $chunkSize = 1024;
        for ($offset = 0; $offset < $compressedLength; $offset += $chunkSize) {
            $chunk = substr($compressed, $offset, $chunkSize);
            $isFinalInputChunk = $offset + strlen($chunk) >= $compressedLength;
            $output = @inflate_add($context, $chunk, $isFinalInputChunk ? ZLIB_FINISH : ZLIB_NO_FLUSH);
            if ($output === false) {
                throw new \RuntimeException("Unable to inflate ZIP entry {$entry->name}");
            }

            $inflated .= $output;
            if ($maxUncompressedBytes !== null && strlen($inflated) > $maxUncompressedBytes) {
                throw new \RuntimeException(
                    "ZIP entry {$entry->name} exceeds maximum uncompressed read size {$maxUncompressedBytes} bytes"
                );
            }

            if (strlen($inflated) > $entry->uncompressedSize) {
                throw new \RuntimeException("ZIP entry {$entry->name} expanded beyond its declared size");
            }

            $status = inflate_get_status($context);
            if ($status === ZLIB_STREAM_END) {
                break;
            }

            if ($status < 0) {
                throw new \RuntimeException("Unable to inflate ZIP entry {$entry->name}");
            }
        }

        if (inflate_get_status($context) !== ZLIB_STREAM_END) {
            throw new \RuntimeException("Unable to inflate ZIP entry {$entry->name}");
        }

        $consumedBytes = inflate_get_read_len($context);
        if ($consumedBytes !== $compressedLength) {
            throw new \RuntimeException(
                "ZIP entry {$entry->name} contains trailing bytes after the raw deflate stream"
            );
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

    private static function compressionMethodName(int $method): string
    {
        return match ($method) {
            0 => 'stored',
            8 => 'deflated',
            default => 'unsupported',
        };
    }

    private static function creatorHostSystemName(int $hostSystem): string
    {
        return self::CREATOR_HOST_SYSTEM_NAMES[$hostSystem] ?? 'unknown';
    }

    private static function isKnownCreatorHostSystem(int $hostSystem): bool
    {
        return isset(self::CREATOR_HOST_SYSTEM_NAMES[$hostSystem]);
    }

    /**
     * @param list<int> $values
     *
     * @return list<int>
     */
    private static function duplicateIntegerValues(array $values): array
    {
        $counts = [];
        $duplicates = [];
        foreach ($values as $value) {
            $counts[$value] = ($counts[$value] ?? 0) + 1;
            if ($counts[$value] === 2) {
                $duplicates[] = $value;
            }
        }

        return $duplicates;
    }

    /**
     * @param list<int> $left
     * @param list<int> $right
     *
     * @return list<int>
     */
    private static function integerValuesOnlyIn(array $left, array $right): array
    {
        $rightSet = array_fill_keys($right, true);
        $only = [];
        foreach ($left as $value) {
            if (isset($rightSet[$value]) || in_array($value, $only, true)) {
                continue;
            }

            $only[] = $value;
        }

        return $only;
    }

    /**
     * @param list<array{id:int, data:string}> $centralFields
     * @param list<array{id:int, data:string}> $localFields
     *
     * @return list<int>
     */
    private static function mismatchedExtraFieldValueIds(array $centralFields, array $localFields): array
    {
        $central = self::uniqueExtraFieldDataById($centralFields);
        $local = self::uniqueExtraFieldDataById($localFields);
        $mismatched = [];

        foreach ($central as $id => $centralData) {
            if (!array_key_exists($id, $local)) {
                continue;
            }

            if ($centralData !== $local[$id]) {
                $mismatched[] = (int) $id;
            }
        }

        return $mismatched;
    }

    /**
     * @param list<array{id:int, data:string}> $fields
     *
     * @return array<int, string>
     */
    private static function uniqueExtraFieldDataById(array $fields): array
    {
        $counts = [];
        $dataById = [];
        foreach ($fields as $field) {
            $id = $field['id'];
            $counts[$id] = ($counts[$id] ?? 0) + 1;
            $dataById[$id] = $field['data'];
        }

        foreach ($counts as $id => $count) {
            if ($count !== 1) {
                unset($dataById[$id]);
            }
        }

        return $dataById;
    }

    private function normalizeLookupPartName(string $partName): string
    {
        $name = ltrim($partName, '/');
        self::assertSafePartName($name);

        return $name;
    }

    private static function caseFoldZipEntryName(string $name): string
    {
        return strtolower($name);
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
        $isUtf8Flagged = ($flags & self::UTF8_GENERAL_PURPOSE_FLAG) !== 0;
        if ($isUtf8Flagged) {
            self::assertUtf8($raw, "ZIP {$label}");
        }

        $unicodeText = self::unicodeTextFromExtraFieldData($extraFieldData, $unicodeExtraFieldId, $raw, $label);
        if ($unicodeText !== null) {
            if ($isUtf8Flagged && $unicodeText !== $raw) {
                throw new \RuntimeException("ZIP Unicode extra field does not match UTF-8 header text for {$label}");
            }

            return [
                'text' => $unicodeText,
                'encoding' => $unicodeEncodingLabel,
            ];
        }

        if ($isUtf8Flagged) {
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

    /**
     * @return array{text:string, encoding:string}
     */
    private static function decodePackageComment(string $raw): array
    {
        if ($raw === '' || preg_match('//u', $raw) === 1) {
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

    private static function isUnixSpecialFileExternalAttributes(int $externalAttributes): bool
    {
        $type = (($externalAttributes >> 16) & 0xffff) & self::UNIX_FILE_TYPE_MASK;

        return $type === self::UNIX_FIFO_TYPE
            || $type === self::UNIX_CHARACTER_DEVICE_TYPE
            || $type === self::UNIX_BLOCK_DEVICE_TYPE
            || $type === self::UNIX_SOCKET_TYPE
            || (
                $type !== 0
                && $type !== self::UNIX_DIRECTORY_TYPE
                && $type !== self::UNIX_REGULAR_FILE_TYPE
                && $type !== self::UNIX_SYMLINK_TYPE
            );
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

    private static function readUInt64(string $bytes, int $offset): int
    {
        self::assertRange($bytes, $offset, 8, 'uint64');
        $values = unpack('Vlow/Vhigh', substr($bytes, $offset, 8));
        if (!is_array($values)) {
            throw new \RuntimeException('Unable to read ZIP uint64 value');
        }

        $low = (int) $values['low'];
        $high = (int) $values['high'];
        $maxHigh = intdiv(PHP_INT_MAX, self::UINT32_FACTOR);
        if ($high > $maxHigh) {
            throw new \RuntimeException('ZIP uint64 value is too large for this platform');
        }

        $value = ($high * self::UINT32_FACTOR) + $low;
        if ($value > PHP_INT_MAX) {
            throw new \RuntimeException('ZIP uint64 value is too large for this platform');
        }

        return $value;
    }

    private static function unsignedCrc32(string $bytes): int
    {
        return (int) sprintf('%u', crc32($bytes));
    }
}
