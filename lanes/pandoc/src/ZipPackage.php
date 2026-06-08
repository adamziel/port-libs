<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ZipPackage
{
    private const EOCD_SIGNATURE = "PK\x05\x06";
    private const CENTRAL_DIRECTORY_SIGNATURE = "PK\x01\x02";
    private const CENTRAL_DIRECTORY_DIGITAL_SIGNATURE = "PK\x05\x05";
    private const ARCHIVE_EXTRA_DATA_RECORD_SIGNATURE = "PK\x06\x08";
    private const ZIP64_END_OF_CENTRAL_DIRECTORY_SIGNATURE = "PK\x06\x06";
    private const ZIP64_END_OF_CENTRAL_DIRECTORY_LOCATOR_SIGNATURE = "PK\x06\x07";
    private const LOCAL_FILE_SIGNATURE = "PK\x03\x04";
    private const ZIP64_EXTENDED_INFORMATION_EXTRA_ID = 0x0001;
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
    private const WINZIP_AES_EXTRA_ID = 0x9901;
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
    private const BOUNDED_UNICODE_CASE_FOLD_FALLBACKS = [
        'À' => 'à', 'Á' => 'á', 'Â' => 'â', 'Ã' => 'ã', 'Ä' => 'ä', 'Å' => 'å',
        'Ç' => 'ç',
        'È' => 'è', 'É' => 'é', 'Ê' => 'ê', 'Ë' => 'ë',
        'Ì' => 'ì', 'Í' => 'í', 'Î' => 'î', 'Ï' => 'ï',
        'Ñ' => 'ñ',
        'Ò' => 'ò', 'Ó' => 'ó', 'Ô' => 'ô', 'Õ' => 'õ', 'Ö' => 'ö',
        'Ù' => 'ù', 'Ú' => 'ú', 'Û' => 'û', 'Ü' => 'ü',
        'Ý' => 'ý', 'Ÿ' => 'ÿ',
    ];
    private const BOUNDED_LATIN_COMPOSITION_FALLBACKS = [
        "a\u{0300}" => 'à', "a\u{0301}" => 'á', "a\u{0302}" => 'â', "a\u{0303}" => 'ã',
        "a\u{0308}" => 'ä', "a\u{030a}" => 'å',
        "c\u{0327}" => 'ç',
        "e\u{0300}" => 'è', "e\u{0301}" => 'é', "e\u{0302}" => 'ê', "e\u{0308}" => 'ë',
        "i\u{0300}" => 'ì', "i\u{0301}" => 'í', "i\u{0302}" => 'î', "i\u{0308}" => 'ï',
        "n\u{0303}" => 'ñ',
        "o\u{0300}" => 'ò', "o\u{0301}" => 'ó', "o\u{0302}" => 'ô', "o\u{0303}" => 'õ',
        "o\u{0308}" => 'ö',
        "u\u{0300}" => 'ù', "u\u{0301}" => 'ú', "u\u{0302}" => 'û', "u\u{0308}" => 'ü',
        "y\u{0301}" => 'ý', "y\u{0308}" => 'ÿ',
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
                self::rejectUnsupportedArchiveExtraDataRecord($bytes, $cursor, "central directory entry {$index}");
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
            $internalAttributes = self::readUInt16($bytes, $cursor + 36);
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
                $internalAttributes,
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
     * @param list<array{name:string, data?:string, compressionMethod?:int, comment?:string, modifiedAt?:int, modifiedDosTime?:int, modifiedDosDate?:int, externalAttributes?:int, internalAttributes?:int, extraFieldData?:string}> $parts
     */
    public static function fromParts(array $parts, string $packageComment = ''): self
    {
        return self::fromString(self::build($parts, $packageComment));
    }

    /**
     * @param list<array{name:string, data?:string, compressionMethod?:int, comment?:string, modifiedAt?:int, modifiedDosTime?:int, modifiedDosDate?:int, externalAttributes?:int, internalAttributes?:int, extraFieldData?:string}> $parts
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
            $internalAttributes = $part['internalAttributes'] ?? 0;
            if (!is_int($internalAttributes)) {
                throw new \RuntimeException("ZIP entry {$name} internal attributes must be an integer");
            }
            self::assertUnixFileTypeMatchesEntryName($name, $externalAttributes);
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
            self::assertUInt16Value($internalAttributes, "ZIP entry {$name} internal attributes");
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
                $internalAttributes,
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
     *     entryCount:int,
     *     firstLocalEntryName:?string,
     *     centralDirectoryOffset:int,
     *     entries:list<array{
     *         name:string,
     *         localHeaderOffset:int,
     *         localHeaderLength:int,
     *         localNameLength:int,
     *         localExtraFieldLength:int,
     *         dataStart:int,
     *         compressedSize:int,
     *         compressedDataEnd:int,
     *         usesDataDescriptor:bool,
     *         descriptorOffset:?int,
     *         descriptorLength:?int,
     *         recordEnd:int,
     *         nextOffset:int,
     *         isContiguousWithNext:bool,
     *         compressionMethod:int,
     *         generalPurposeFlags:int,
     *         localHeaderCrc32:int,
     *         localHeaderCompressedSize:int,
     *         localHeaderUncompressedSize:int,
     *         hasZeroLocalHeaderPlaceholders:?bool
     *     }>
     * }
     */
    public function localHeaderPreflight(): array
    {
        $entries = [];
        $localEntries = $this->localEntries();

        foreach ($localEntries as $entry) {
            $localHeader = $this->readLocalHeader($entry);
            $compressedDataEnd = $localHeader['dataStart'] + $entry->compressedSize;
            $recordEnd = $compressedDataEnd;
            $nextOffset = $this->nextEntryOrCentralDirectoryOffset($entry);
            $usesDataDescriptor = ($entry->generalPurposeFlags & 0x0008) !== 0;
            $descriptorOffset = null;
            $descriptorLength = null;
            $hasZeroLocalHeaderPlaceholders = null;

            if ($usesDataDescriptor) {
                $descriptor = $this->dataDescriptorMetadata($entry, $compressedDataEnd, $nextOffset);
                $descriptorOffset = $descriptor['descriptorOffset'];
                $descriptorLength = $descriptor['descriptorLength'];
                $recordEnd += $descriptorLength;
                $hasZeroLocalHeaderPlaceholders = $localHeader['crc32'] === 0
                    && $localHeader['compressedSize'] === 0
                    && $localHeader['uncompressedSize'] === 0;
            }

            $entries[] = [
                'name' => $entry->name,
                'localHeaderOffset' => $entry->localHeaderOffset,
                'localHeaderLength' => $localHeader['localHeaderLength'],
                'localNameLength' => $localHeader['nameLength'],
                'localExtraFieldLength' => $localHeader['extraFieldLength'],
                'dataStart' => $localHeader['dataStart'],
                'compressedSize' => $entry->compressedSize,
                'compressedDataEnd' => $compressedDataEnd,
                'usesDataDescriptor' => $usesDataDescriptor,
                'descriptorOffset' => $descriptorOffset,
                'descriptorLength' => $descriptorLength,
                'recordEnd' => $recordEnd,
                'nextOffset' => $nextOffset,
                'isContiguousWithNext' => $recordEnd === $nextOffset,
                'compressionMethod' => $entry->compressionMethod,
                'generalPurposeFlags' => $entry->generalPurposeFlags,
                'localHeaderCrc32' => $localHeader['crc32'],
                'localHeaderCompressedSize' => $localHeader['compressedSize'],
                'localHeaderUncompressedSize' => $localHeader['uncompressedSize'],
                'hasZeroLocalHeaderPlaceholders' => $hasZeroLocalHeaderPlaceholders,
            ];
        }

        return [
            'entryCount' => count($localEntries),
            'firstLocalEntryName' => $localEntries[0]->name ?? null,
            'centralDirectoryOffset' => $this->centralDirectoryOffset,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     totalEntryCount:int,
     *     centralDirectoryOffset:int,
     *     centralDirectorySize:int,
     *     mismatchedEntryCount:int,
     *     isSupportedByBoundedReader:bool,
     *     issues:list<string>,
     *     mismatchedEntries:list<array{
     *         centralDirectoryIndex:int,
     *         centralDirectoryOffset:int,
     *         localHeaderOffset:int,
     *         centralName:string,
     *         centralRawName:string,
     *         centralNameEncoding:string,
     *         centralNameLength:int,
     *         centralExtraFieldLength:int,
     *         centralGeneralPurposeFlags:int,
     *         localName:string,
     *         localRawName:string,
     *         localNameEncoding:string,
     *         localNameLength:int,
     *         localExtraFieldLength:int,
     *         localHeaderLength:int,
     *         localGeneralPurposeFlags:int,
     *         rawNameMatchesCentral:bool,
     *         decodedNameMatchesCentral:bool,
     *         generalPurposeFlagsMatchCentral:bool,
     *         issues:list<string>
     *     }>,
     *     entries:list<array{
     *         centralDirectoryIndex:int,
     *         centralDirectoryOffset:int,
     *         localHeaderOffset:int,
     *         centralName:string,
     *         centralRawName:string,
     *         centralNameEncoding:string,
     *         centralNameLength:int,
     *         centralExtraFieldLength:int,
     *         centralGeneralPurposeFlags:int,
     *         localName:string,
     *         localRawName:string,
     *         localNameEncoding:string,
     *         localNameLength:int,
     *         localExtraFieldLength:int,
     *         localHeaderLength:int,
     *         localGeneralPurposeFlags:int,
     *         rawNameMatchesCentral:bool,
     *         decodedNameMatchesCentral:bool,
     *         generalPurposeFlagsMatchCentral:bool,
     *         issues:list<string>
     *     }>
     * }
     */
    public static function localHeaderNamePreflight(string $bytes): array
    {
        $archive = self::endOfCentralDirectoryPreflight($bytes);
        if ($archive['requiresZip64']) {
            throw new \RuntimeException('ZIP64 package-level central-directory fields require ZIP64 EOCD parsing before local header names can be scanned');
        }

        self::assertRange(
            $bytes,
            $archive['centralDirectoryOffset'],
            $archive['centralDirectorySize'],
            'central directory'
        );
        if ($archive['centralDirectoryEnd'] > $archive['eocdOffset']) {
            throw new \RuntimeException('Central directory overlaps the end-of-central-directory record');
        }

        $entries = [];
        $mismatchedEntries = [];
        $packageIssues = [];
        if (!$archive['isSingleDisk']) {
            $packageIssues[] = 'split-archive-eocd';
        }

        $cursor = $archive['centralDirectoryOffset'];
        for ($index = 0; $index < $archive['totalEntryCount']; $index++) {
            if (substr($bytes, $cursor, 4) !== self::CENTRAL_DIRECTORY_SIGNATURE) {
                throw new \RuntimeException("Invalid ZIP central directory header at entry {$index}");
            }

            self::assertRange($bytes, $cursor, 46, 'central directory entry');
            $flags = self::readUInt16($bytes, $cursor + 8);
            $nameLength = self::readUInt16($bytes, $cursor + 28);
            $extraLength = self::readUInt16($bytes, $cursor + 30);
            $commentLength = self::readUInt16($bytes, $cursor + 32);
            $localHeaderOffset = self::readUInt32($bytes, $cursor + 42);
            $variableStart = $cursor + 46;
            $variableLength = $nameLength + $extraLength + $commentLength;
            self::assertRange($bytes, $variableStart, $variableLength, 'central directory entry variable fields');

            $rawName = substr($bytes, $variableStart, $nameLength);
            $centralExtraFieldData = substr($bytes, $variableStart + $nameLength, $extraLength);
            self::assertSafePartName($rawName);
            $decodedName = self::decodeZipText(
                $rawName,
                $flags,
                $centralExtraFieldData,
                self::INFOZIP_UNICODE_PATH_EXTRA_ID,
                'info-zip-unicode-path',
                "central directory entry {$index} name"
            );
            self::assertSafePartName($decodedName['text']);

            $localHeader = self::readLocalHeaderNameMetadata(
                $bytes,
                $localHeaderOffset,
                $index
            );

            $rawNameMatchesCentral = $localHeader['rawName'] === $rawName;
            $decodedNameMatchesCentral = $localHeader['name'] === $decodedName['text'];
            $flagsMatchCentral = $localHeader['generalPurposeFlags'] === $flags;
            $issues = [];
            if (!$rawNameMatchesCentral) {
                $issues[] = 'local-header-name-mismatch';
            }
            if (!$decodedNameMatchesCentral) {
                $issues[] = 'local-header-decoded-name-mismatch';
            }
            if (!$flagsMatchCentral) {
                $issues[] = 'local-header-flags-mismatch';
            }
            foreach ($issues as $issue) {
                if (!in_array($issue, $packageIssues, true)) {
                    $packageIssues[] = $issue;
                }
            }

            $entry = [
                'centralDirectoryIndex' => $index,
                'centralDirectoryOffset' => $cursor,
                'localHeaderOffset' => $localHeaderOffset,
                'centralName' => $decodedName['text'],
                'centralRawName' => $rawName,
                'centralNameEncoding' => $decodedName['encoding'],
                'centralNameLength' => $nameLength,
                'centralExtraFieldLength' => $extraLength,
                'centralGeneralPurposeFlags' => $flags,
                'localName' => $localHeader['name'],
                'localRawName' => $localHeader['rawName'],
                'localNameEncoding' => $localHeader['nameEncoding'],
                'localNameLength' => $localHeader['nameLength'],
                'localExtraFieldLength' => $localHeader['extraFieldLength'],
                'localHeaderLength' => $localHeader['localHeaderLength'],
                'localGeneralPurposeFlags' => $localHeader['generalPurposeFlags'],
                'rawNameMatchesCentral' => $rawNameMatchesCentral,
                'decodedNameMatchesCentral' => $decodedNameMatchesCentral,
                'generalPurposeFlagsMatchCentral' => $flagsMatchCentral,
                'issues' => $issues,
            ];
            $entries[] = $entry;
            if ($issues !== []) {
                $mismatchedEntries[] = $entry;
            }

            $cursor += 46 + $variableLength;
        }

        if ($cursor !== $archive['centralDirectoryEnd']) {
            $signature = self::centralDirectoryDigitalSignatureRecordAt($bytes, $cursor);
            if ($signature === null || $signature['endOffset'] !== $archive['centralDirectoryEnd']) {
                self::rejectUnexpectedCentralDirectoryTail($bytes, $cursor, 'inside the central directory');
            }
        }

        return [
            'entryCount' => count($entries),
            'totalEntryCount' => $archive['totalEntryCount'],
            'centralDirectoryOffset' => $archive['centralDirectoryOffset'],
            'centralDirectorySize' => $archive['centralDirectorySize'],
            'mismatchedEntryCount' => count($mismatchedEntries),
            'isSupportedByBoundedReader' => $packageIssues === [],
            'issues' => $packageIssues,
            'mismatchedEntries' => $mismatchedEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     totalEntryCount:int,
     *     centralDirectoryOffset:int,
     *     centralDirectorySize:int,
     *     mismatchedEntryCount:int,
     *     isSupportedByBoundedReader:bool,
     *     issues:list<string>,
     *     mismatchedEntries:list<array<string, mixed>>,
     *     entries:list<array<string, mixed>>
     * }
     */
    public static function localHeaderMetadataPreflight(string $bytes): array
    {
        $archive = self::endOfCentralDirectoryPreflight($bytes);
        if ($archive['requiresZip64']) {
            throw new \RuntimeException('ZIP64 package-level central-directory fields require ZIP64 EOCD parsing before local header metadata can be scanned');
        }

        self::assertRange(
            $bytes,
            $archive['centralDirectoryOffset'],
            $archive['centralDirectorySize'],
            'central directory'
        );
        if ($archive['centralDirectoryEnd'] > $archive['eocdOffset']) {
            throw new \RuntimeException('Central directory overlaps the end-of-central-directory record');
        }

        $entries = [];
        $mismatchedEntries = [];
        $packageIssues = [];
        if (!$archive['isSingleDisk']) {
            $packageIssues[] = 'split-archive-eocd';
        }

        $cursor = $archive['centralDirectoryOffset'];
        for ($index = 0; $index < $archive['totalEntryCount']; $index++) {
            if (substr($bytes, $cursor, 4) !== self::CENTRAL_DIRECTORY_SIGNATURE) {
                throw new \RuntimeException("Invalid ZIP central directory header at entry {$index}");
            }

            self::assertRange($bytes, $cursor, 46, 'central directory entry');
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
            $localHeaderOffset = self::readUInt32($bytes, $cursor + 42);
            $variableStart = $cursor + 46;
            $variableLength = $nameLength + $extraLength + $commentLength;
            self::assertRange($bytes, $variableStart, $variableLength, 'central directory entry variable fields');

            $rawName = substr($bytes, $variableStart, $nameLength);
            $centralExtraFieldData = substr($bytes, $variableStart + $nameLength, $extraLength);
            self::assertSafePartName($rawName);
            $decodedName = self::decodeZipText(
                $rawName,
                $flags,
                $centralExtraFieldData,
                self::INFOZIP_UNICODE_PATH_EXTRA_ID,
                'info-zip-unicode-path',
                "central directory entry {$index} name"
            );
            self::assertSafePartName($decodedName['text']);

            $localHeader = self::readLocalHeaderNameMetadata($bytes, $localHeaderOffset, $index);
            $usesDataDescriptor = ($flags & 0x0008) !== 0;
            $hasZeroLocalHeaderPlaceholders = null;
            $issues = [];

            if ($localHeader['versionNeededToExtract'] !== $versionNeededToExtract) {
                $issues[] = 'local-header-version-needed-mismatch';
            }

            if ($localHeader['generalPurposeFlags'] !== $flags) {
                $issues[] = 'local-header-flags-mismatch';
            }

            if ($localHeader['compressionMethod'] !== $method) {
                $issues[] = 'local-header-compression-method-mismatch';
            }

            if (
                $localHeader['modifiedDosTime'] !== $modifiedTime
                || $localHeader['modifiedDosDate'] !== $modifiedDate
            ) {
                $issues[] = 'local-header-modification-time-mismatch';
            }

            if ($usesDataDescriptor) {
                $hasZeroLocalHeaderPlaceholders = $localHeader['crc32'] === 0
                    && $localHeader['compressedSize'] === 0
                    && $localHeader['uncompressedSize'] === 0;
                if (!$hasZeroLocalHeaderPlaceholders) {
                    $issues[] = 'local-header-data-descriptor-placeholders-not-zero';
                }
            } else {
                if ($localHeader['crc32'] !== $crc32) {
                    $issues[] = 'local-header-crc32-mismatch';
                }

                if ($localHeader['compressedSize'] !== $compressedSize) {
                    $issues[] = 'local-header-compressed-size-mismatch';
                }

                if ($localHeader['uncompressedSize'] !== $uncompressedSize) {
                    $issues[] = 'local-header-uncompressed-size-mismatch';
                }
            }

            foreach ($issues as $issue) {
                if (!in_array($issue, $packageIssues, true)) {
                    $packageIssues[] = $issue;
                }
            }

            $summary = [
                'centralDirectoryIndex' => $index,
                'centralDirectoryOffset' => $cursor,
                'localHeaderOffset' => $localHeaderOffset,
                'centralName' => $decodedName['text'],
                'localName' => $localHeader['name'],
                'centralRawName' => $rawName,
                'localRawName' => $localHeader['rawName'],
                'centralNameEncoding' => $decodedName['encoding'],
                'localNameEncoding' => $localHeader['nameEncoding'],
                'centralVersionNeededToExtract' => $versionNeededToExtract,
                'localVersionNeededToExtract' => $localHeader['versionNeededToExtract'],
                'centralGeneralPurposeFlags' => $flags,
                'localGeneralPurposeFlags' => $localHeader['generalPurposeFlags'],
                'centralCompressionMethod' => $method,
                'localCompressionMethod' => $localHeader['compressionMethod'],
                'centralModifiedDosTime' => $modifiedTime,
                'localModifiedDosTime' => $localHeader['modifiedDosTime'],
                'centralModifiedDosDate' => $modifiedDate,
                'localModifiedDosDate' => $localHeader['modifiedDosDate'],
                'centralCrc32' => $crc32,
                'localCrc32' => $localHeader['crc32'],
                'centralCompressedSize' => $compressedSize,
                'localCompressedSize' => $localHeader['compressedSize'],
                'centralUncompressedSize' => $uncompressedSize,
                'localUncompressedSize' => $localHeader['uncompressedSize'],
                'usesDataDescriptor' => $usesDataDescriptor,
                'hasZeroLocalHeaderPlaceholders' => $hasZeroLocalHeaderPlaceholders,
                'hasMetadataMismatch' => $issues !== [],
                'issues' => $issues,
            ];
            $entries[] = $summary;
            if ($issues !== []) {
                $mismatchedEntries[] = $summary;
            }

            $cursor += 46 + $variableLength;
        }

        if ($cursor !== $archive['centralDirectoryEnd']) {
            $signature = self::centralDirectoryDigitalSignatureRecordAt($bytes, $cursor);
            if ($signature === null || $signature['endOffset'] !== $archive['centralDirectoryEnd']) {
                self::rejectUnexpectedCentralDirectoryTail($bytes, $cursor, 'inside the central directory');
            }
        }

        return [
            'entryCount' => count($entries),
            'totalEntryCount' => $archive['totalEntryCount'],
            'centralDirectoryOffset' => $archive['centralDirectoryOffset'],
            'centralDirectorySize' => $archive['centralDirectorySize'],
            'mismatchedEntryCount' => count($mismatchedEntries),
            'isSupportedByBoundedReader' => $packageIssues === [],
            'issues' => $packageIssues,
            'mismatchedEntries' => $mismatchedEntries,
            'entries' => $entries,
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
     *     timestampEntryCount:int,
     *     dosTimestampEntryCount:int,
     *     extendedTimestampEntryCount:int,
     *     ntfsTimestampEntryCount:int,
     *     invalidDosTimestampEntryCount:int,
     *     invalidDosTimestampEntries:list<array{name:string, modifiedDosTime:int, modifiedDosDate:int, hasDosTimestamp:bool, isDosTimestampValid:bool, dosModifiedAt:?int, extendedModifiedAt:?int, ntfsModifiedAt:?int, modifiedAt:?int, timestampSource:?string, issues:list<string>}>,
     *     entries:list<array{name:string, modifiedDosTime:int, modifiedDosDate:int, hasDosTimestamp:bool, isDosTimestampValid:bool, dosModifiedAt:?int, extendedModifiedAt:?int, ntfsModifiedAt:?int, modifiedAt:?int, timestampSource:?string, issues:list<string>}>
     * }
     */
    public function modificationTimePreflight(): array
    {
        $timestampEntryCount = 0;
        $dosTimestampEntryCount = 0;
        $extendedTimestampEntryCount = 0;
        $ntfsTimestampEntryCount = 0;
        $invalidDosTimestampEntries = [];
        $entries = [];

        foreach ($this->entries as $entry) {
            $hasDosTimestamp = $entry->hasDosLastModifiedTimestamp();
            $dosModifiedAt = $entry->dosLastModifiedTimestamp();
            $extendedModifiedAt = $entry->extendedLastModifiedTimestamp();
            $ntfsModifiedAt = $entry->ntfsLastModifiedTimestamp();
            $modifiedAt = $entry->lastModifiedTimestamp();
            $timestampSource = null;
            if ($extendedModifiedAt !== null) {
                $timestampSource = 'extended-timestamp';
                $extendedTimestampEntryCount++;
            } elseif ($ntfsModifiedAt !== null) {
                $timestampSource = 'ntfs';
                $ntfsTimestampEntryCount++;
            } elseif ($dosModifiedAt !== null) {
                $timestampSource = 'dos';
            }

            if ($hasDosTimestamp) {
                $dosTimestampEntryCount++;
            }
            if ($modifiedAt !== null) {
                $timestampEntryCount++;
            }
            if ($ntfsModifiedAt !== null && $extendedModifiedAt !== null) {
                $ntfsTimestampEntryCount++;
            }

            $isDosTimestampValid = !$hasDosTimestamp || $dosModifiedAt !== null;
            $issues = $isDosTimestampValid ? [] : ['invalid-dos-modified-timestamp'];
            $summary = [
                'name' => $entry->name,
                'modifiedDosTime' => $entry->lastModifiedTime,
                'modifiedDosDate' => $entry->lastModifiedDate,
                'hasDosTimestamp' => $hasDosTimestamp,
                'isDosTimestampValid' => $isDosTimestampValid,
                'dosModifiedAt' => $dosModifiedAt,
                'extendedModifiedAt' => $extendedModifiedAt,
                'ntfsModifiedAt' => $ntfsModifiedAt,
                'modifiedAt' => $modifiedAt,
                'timestampSource' => $timestampSource,
                'issues' => $issues,
            ];
            $entries[] = $summary;
            if (!$isDosTimestampValid) {
                $invalidDosTimestampEntries[] = $summary;
            }
        }

        return [
            'entryCount' => count($this->entries),
            'timestampEntryCount' => $timestampEntryCount,
            'dosTimestampEntryCount' => $dosTimestampEntryCount,
            'extendedTimestampEntryCount' => $extendedTimestampEntryCount,
            'ntfsTimestampEntryCount' => $ntfsTimestampEntryCount,
            'invalidDosTimestampEntryCount' => count($invalidDosTimestampEntries),
            'invalidDosTimestampEntries' => $invalidDosTimestampEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     timestampEntryCount:int,
     *     dosTimestampEntryCount:int,
     *     extendedTimestampEntryCount:int,
     *     ntfsTimestampEntryCount:int,
     *     invalidDosTimestampEntryCount:int,
     *     invalidDosTimestampEntries:list<array{name:string, modifiedDosTime:int, modifiedDosDate:int, hasDosTimestamp:bool, isDosTimestampValid:bool, dosModifiedAt:?int, extendedModifiedAt:?int, ntfsModifiedAt:?int, modifiedAt:?int, timestampSource:?string, issues:list<string>}>,
     *     entries:list<array{name:string, modifiedDosTime:int, modifiedDosDate:int, hasDosTimestamp:bool, isDosTimestampValid:bool, dosModifiedAt:?int, extendedModifiedAt:?int, ntfsModifiedAt:?int, modifiedAt:?int, timestampSource:?string, issues:list<string>}>
     * }
     */
    public function assertValidModificationTimes(): array
    {
        $summary = $this->modificationTimePreflight();
        if ($summary['invalidDosTimestampEntryCount'] === 0) {
            return $summary;
        }

        $entries = implode(
            ', ',
            array_map(
                static fn (array $entry): string => $entry['name']
                    . sprintf(' (dos time 0x%04x date 0x%04x)', $entry['modifiedDosTime'], $entry['modifiedDosDate']),
                $summary['invalidDosTimestampEntries']
            )
        );

        throw new \RuntimeException(
            'ZIP package contains invalid DOS modification timestamps that require explicit import review: '
            . $entries
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

    /**
     * @return array{
     *     entryCount:int,
     *     collisionGroupCount:int,
     *     collisionEntryCount:int,
     *     collisionGroups:list<array{rawName:string, rawNameHex:string, entryNames:list<string>}>,
     *     collisionEntries:list<array{name:string, rawName:string, rawNameHex:string, equivalentEntryNames:list<string>, hasRawNameCollision:bool, issues:list<string>}>,
     *     entries:list<array{name:string, rawName:string, rawNameHex:string, equivalentEntryNames:list<string>, hasRawNameCollision:bool, issues:list<string>}>
     * }
     */
    public function rawNamePreflight(): array
    {
        $entryNamesByRawNameHex = [];
        $rawNamesByHex = [];
        foreach ($this->entries as $entry) {
            $rawNameHex = bin2hex($entry->rawName);
            $entryNamesByRawNameHex[$rawNameHex][] = $entry->name;
            $rawNamesByHex[$rawNameHex] = $entry->rawName;
        }

        $collisionGroups = [];
        foreach ($entryNamesByRawNameHex as $rawNameHex => $entryNames) {
            if (count($entryNames) > 1) {
                $collisionGroups[] = [
                    'rawName' => $rawNamesByHex[$rawNameHex],
                    'rawNameHex' => $rawNameHex,
                    'entryNames' => $entryNames,
                ];
            }
        }

        $entries = [];
        $collisionEntries = [];
        foreach ($this->entries as $entry) {
            $rawNameHex = bin2hex($entry->rawName);
            $equivalentEntryNames = $entryNamesByRawNameHex[$rawNameHex] ?? [];
            $hasCollision = count($equivalentEntryNames) > 1;
            $issues = $hasCollision ? ['raw-name-collision'] : [];
            $summary = [
                'name' => $entry->name,
                'rawName' => $entry->rawName,
                'rawNameHex' => $rawNameHex,
                'equivalentEntryNames' => $equivalentEntryNames,
                'hasRawNameCollision' => $hasCollision,
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
     *     collisionGroups:list<array{rawName:string, rawNameHex:string, entryNames:list<string>}>,
     *     collisionEntries:list<array{name:string, rawName:string, rawNameHex:string, equivalentEntryNames:list<string>, hasRawNameCollision:bool, issues:list<string>}>,
     *     entries:list<array{name:string, rawName:string, rawNameHex:string, equivalentEntryNames:list<string>, hasRawNameCollision:bool, issues:list<string>}>
     * }
     */
    public function assertNoRawNameCollisions(): array
    {
        $summary = $this->rawNamePreflight();
        if ($summary['collisionEntryCount'] > 0) {
            $groups = implode(
                ', ',
                array_map(
                    static fn (array $group): string => $group['rawNameHex'] . ' (' . implode(', ', $group['entryNames']) . ')',
                    $summary['collisionGroups']
                )
            );

            throw new \RuntimeException(
                'ZIP package contains raw ZIP entry name collisions that require explicit import review: '
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
     *     entryCount:int,
     *     diskNumber:int,
     *     centralDirectoryDisk:int,
     *     diskEntryCount:int,
     *     totalEntryCount:int,
     *     centralDirectoryOffset:int,
     *     centralDirectorySize:int,
     *     isSingleDisk:bool,
     *     hasSplitArchiveMarkers:bool,
     *     splitArchiveEntryCount:int,
     *     isSupportedByBoundedReader:bool,
     *     issues:list<string>,
     *     splitArchiveEntries:list<array{name:string, rawName:string, centralDirectoryIndex:int, diskStart:int, localHeaderOffset:int, issues:list<string>}>,
     *     entries:list<array{name:string, rawName:string, centralDirectoryIndex:int, diskStart:int, localHeaderOffset:int, issues:list<string>}>
     * }
     */
    public static function splitArchivePreflight(string $bytes): array
    {
        $archive = self::endOfCentralDirectoryPreflight($bytes);
        if ($archive['requiresZip64']) {
            throw new \RuntimeException('ZIP64 package-level central-directory fields require ZIP64 EOCD parsing before split archive entries can be scanned');
        }

        self::assertRange(
            $bytes,
            $archive['centralDirectoryOffset'],
            $archive['centralDirectorySize'],
            'central directory'
        );
        if ($archive['centralDirectoryEnd'] > $archive['eocdOffset']) {
            throw new \RuntimeException('Central directory overlaps the end-of-central-directory record');
        }

        $entries = [];
        $splitArchiveEntries = [];
        $cursor = $archive['centralDirectoryOffset'];
        $index = 0;
        while ($cursor < $archive['centralDirectoryEnd']) {
            if (substr($bytes, $cursor, 4) !== self::CENTRAL_DIRECTORY_SIGNATURE) {
                throw new \RuntimeException("Invalid ZIP central directory header at entry {$index}");
            }

            self::assertRange($bytes, $cursor, 46, 'central directory entry');
            $flags = self::readUInt16($bytes, $cursor + 8);
            $nameLength = self::readUInt16($bytes, $cursor + 28);
            $extraLength = self::readUInt16($bytes, $cursor + 30);
            $commentLength = self::readUInt16($bytes, $cursor + 32);
            $diskStart = self::readUInt16($bytes, $cursor + 34);
            $localHeaderOffset = self::readUInt32($bytes, $cursor + 42);
            $variableStart = $cursor + 46;
            $variableLength = $nameLength + $extraLength + $commentLength;
            self::assertRange($bytes, $variableStart, $variableLength, 'central directory entry variable fields');

            $rawName = substr($bytes, $variableStart, $nameLength);
            $centralExtraFieldData = substr($bytes, $variableStart + $nameLength, $extraLength);
            $decodedName = self::decodeZipText(
                $rawName,
                $flags,
                $centralExtraFieldData,
                self::INFOZIP_UNICODE_PATH_EXTRA_ID,
                'info-zip-unicode-path',
                "central directory entry {$index} name"
            );
            $issues = $diskStart === 0 ? [] : ['split-entry-disk-start'];
            $entry = [
                'name' => $decodedName['text'],
                'rawName' => $rawName,
                'centralDirectoryIndex' => $index,
                'diskStart' => $diskStart,
                'localHeaderOffset' => $localHeaderOffset,
                'issues' => $issues,
            ];
            $entries[] = $entry;
            if ($issues !== []) {
                $splitArchiveEntries[] = $entry;
            }

            $cursor += 46 + $variableLength;
            $index++;
        }

        if ($cursor !== $archive['centralDirectoryEnd']) {
            throw new \RuntimeException('ZIP central directory size does not match scanned entry records');
        }

        $issues = [];
        if (!$archive['isSingleDisk']) {
            $issues[] = 'split-archive-eocd';
        }
        if ($splitArchiveEntries !== []) {
            $issues[] = 'split-entry-disk-start';
        }

        return [
            'entryCount' => count($entries),
            'diskNumber' => $archive['diskNumber'],
            'centralDirectoryDisk' => $archive['centralDirectoryDisk'],
            'diskEntryCount' => $archive['diskEntryCount'],
            'totalEntryCount' => $archive['totalEntryCount'],
            'centralDirectoryOffset' => $archive['centralDirectoryOffset'],
            'centralDirectorySize' => $archive['centralDirectorySize'],
            'isSingleDisk' => $archive['isSingleDisk'],
            'hasSplitArchiveMarkers' => $issues !== [],
            'splitArchiveEntryCount' => count($splitArchiveEntries),
            'isSupportedByBoundedReader' => $issues === [],
            'issues' => $issues,
            'splitArchiveEntries' => $splitArchiveEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     encryptedEntryCount:int,
     *     traditionalEncryptionEntryCount:int,
     *     strongEncryptionEntryCount:int,
     *     centralDirectoryEncryptionEntryCount:int,
     *     winZipAesEntryCount:int,
     *     hasEncryptedEntries:bool,
     *     extractionPolicy:string,
     *     isSupportedByBoundedReader:bool,
     *     issues:list<string>,
     *     encryptedEntries:list<array<string, mixed>>,
     *     entries:list<array<string, mixed>>
     * }
     */
    public static function encryptionPolicyPreflight(string $bytes): array
    {
        $archive = self::endOfCentralDirectoryPreflight($bytes);
        if ($archive['requiresZip64']) {
            throw new \RuntimeException('ZIP64 package-level central-directory fields require ZIP64 EOCD parsing before encrypted entries can be scanned');
        }

        self::assertRange(
            $bytes,
            $archive['centralDirectoryOffset'],
            $archive['centralDirectorySize'],
            'central directory'
        );
        if ($archive['centralDirectoryEnd'] > $archive['eocdOffset']) {
            throw new \RuntimeException('Central directory overlaps the end-of-central-directory record');
        }

        $entries = [];
        $encryptedEntries = [];
        $traditionalEncryptionEntryCount = 0;
        $strongEncryptionEntryCount = 0;
        $centralDirectoryEncryptionEntryCount = 0;
        $winZipAesEntryCount = 0;
        $cursor = $archive['centralDirectoryOffset'];
        $index = 0;
        while ($index < $archive['totalEntryCount']) {
            if (substr($bytes, $cursor, 4) !== self::CENTRAL_DIRECTORY_SIGNATURE) {
                throw new \RuntimeException("Invalid ZIP central directory header at entry {$index}");
            }

            self::assertRange($bytes, $cursor, 46, 'central directory entry');
            $flags = self::readUInt16($bytes, $cursor + 8);
            $method = self::readUInt16($bytes, $cursor + 10);
            $compressedSize = self::readUInt32($bytes, $cursor + 20);
            $uncompressedSize = self::readUInt32($bytes, $cursor + 24);
            $nameLength = self::readUInt16($bytes, $cursor + 28);
            $extraLength = self::readUInt16($bytes, $cursor + 30);
            $commentLength = self::readUInt16($bytes, $cursor + 32);
            $localHeaderOffset = self::readUInt32($bytes, $cursor + 42);
            $variableStart = $cursor + 46;
            $variableLength = $nameLength + $extraLength + $commentLength;
            self::assertRange($bytes, $variableStart, $variableLength, 'central directory entry variable fields');

            $rawName = substr($bytes, $variableStart, $nameLength);
            $centralExtraFieldData = substr($bytes, $variableStart + $nameLength, $extraLength);
            $decodedName = self::decodeZipNameForPolicy($rawName, $flags, "central directory entry {$index} name");
            $centralExtraFieldIds = self::extraFieldIdsForPolicy($centralExtraFieldData, "central extra fields for {$decodedName['text']}");
            $localHeader = self::localHeaderMetadataForPolicy($bytes, $localHeaderOffset, $index);
            $localExtraFieldIds = self::extraFieldIdsForPolicy($localHeader['extraFieldData'], "local extra fields for {$decodedName['text']}");

            $hasTraditionalEncryption = ($flags & self::ENCRYPTED_GENERAL_PURPOSE_FLAG) !== 0
                || (($localHeader['generalPurposeFlags'] & self::ENCRYPTED_GENERAL_PURPOSE_FLAG) !== 0);
            $hasStrongEncryption = ($flags & self::STRONG_ENCRYPTION_GENERAL_PURPOSE_FLAG) !== 0
                || (($localHeader['generalPurposeFlags'] & self::STRONG_ENCRYPTION_GENERAL_PURPOSE_FLAG) !== 0);
            $hasCentralDirectoryEncryption = ($flags & self::CENTRAL_DIRECTORY_ENCRYPTED_GENERAL_PURPOSE_FLAG) !== 0
                || (($localHeader['generalPurposeFlags'] & self::CENTRAL_DIRECTORY_ENCRYPTED_GENERAL_PURPOSE_FLAG) !== 0);
            $hasWinZipAesExtraField = in_array(self::WINZIP_AES_EXTRA_ID, $centralExtraFieldIds, true)
                || in_array(self::WINZIP_AES_EXTRA_ID, $localExtraFieldIds, true);

            $encryptionTypes = [];
            $diagnostics = [];
            if ($hasTraditionalEncryption) {
                $encryptionTypes[] = 'traditional';
                $diagnostics[] = 'zip-traditional-encryption';
            }
            if ($hasStrongEncryption) {
                $encryptionTypes[] = 'strong';
                $diagnostics[] = 'zip-strong-encryption';
            }
            if ($hasCentralDirectoryEncryption) {
                $encryptionTypes[] = 'central-directory';
                $diagnostics[] = 'zip-central-directory-encryption';
            }
            if ($hasWinZipAesExtraField) {
                $encryptionTypes[] = 'winzip-aes';
                $diagnostics[] = 'zip-winzip-aes-extra-field';
            }

            if ($flags !== $localHeader['generalPurposeFlags']) {
                $diagnostics[] = 'zip-local-header-flags-mismatch';
            }
            if ($method !== $localHeader['compressionMethod']) {
                $diagnostics[] = 'zip-local-header-method-mismatch';
            }
            if ($rawName !== $localHeader['rawName']) {
                $diagnostics[] = 'zip-local-header-name-mismatch';
            }

            if ($encryptionTypes !== []) {
                array_unshift($diagnostics, 'zip-encrypted-entry-not-extracted');
            }
            $diagnostics = array_values(array_unique($diagnostics));

            if ($hasTraditionalEncryption) {
                $traditionalEncryptionEntryCount++;
            }
            if ($hasStrongEncryption) {
                $strongEncryptionEntryCount++;
            }
            if ($hasCentralDirectoryEncryption) {
                $centralDirectoryEncryptionEntryCount++;
            }
            if ($hasWinZipAesExtraField) {
                $winZipAesEntryCount++;
            }

            $entry = [
                'name' => $decodedName['text'],
                'rawName' => $rawName,
                'nameEncoding' => $decodedName['encoding'],
                'centralDirectoryIndex' => $index,
                'centralDirectoryOffset' => $cursor,
                'localHeaderOffset' => $localHeaderOffset,
                'compressionMethod' => $method,
                'compressionMethodName' => self::compressionMethodName($method),
                'generalPurposeFlags' => $flags,
                'localGeneralPurposeFlags' => $localHeader['generalPurposeFlags'],
                'centralExtraFieldIds' => $centralExtraFieldIds,
                'localExtraFieldIds' => $localExtraFieldIds,
                'compressedSize' => $compressedSize,
                'uncompressedSize' => $uncompressedSize,
                'hasTraditionalEncryption' => $hasTraditionalEncryption,
                'hasStrongEncryption' => $hasStrongEncryption,
                'hasCentralDirectoryEncryption' => $hasCentralDirectoryEncryption,
                'hasWinZipAesExtraField' => $hasWinZipAesExtraField,
                'encryptionTypes' => array_values(array_unique($encryptionTypes)),
                'policy' => $encryptionTypes === [] ? 'metadata' : 'blocked',
                'diagnostics' => $diagnostics,
            ];
            $entries[] = $entry;
            if ($encryptionTypes !== []) {
                $encryptedEntries[] = $entry;
            }

            $cursor += 46 + $variableLength;
            $index++;
        }

        if ($cursor !== $archive['centralDirectoryEnd']) {
            throw new \RuntimeException('ZIP central directory size does not match scanned encrypted-entry policy records');
        }

        $issues = [];
        if (!$archive['isSingleDisk']) {
            $issues[] = 'split-archive-eocd';
        }
        if ($encryptedEntries !== []) {
            $issues[] = 'encrypted-zip-entries';
        }

        return [
            'entryCount' => count($entries),
            'encryptedEntryCount' => count($encryptedEntries),
            'traditionalEncryptionEntryCount' => $traditionalEncryptionEntryCount,
            'strongEncryptionEntryCount' => $strongEncryptionEntryCount,
            'centralDirectoryEncryptionEntryCount' => $centralDirectoryEncryptionEntryCount,
            'winZipAesEntryCount' => $winZipAesEntryCount,
            'hasEncryptedEntries' => $encryptedEntries !== [],
            'extractionPolicy' => $encryptedEntries === [] ? 'no-encrypted-zip-entries' : 'encrypted-zip-entries-blocked',
            'isSupportedByBoundedReader' => $issues === [],
            'issues' => $issues,
            'encryptedEntries' => $encryptedEntries,
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
     *     methodMismatchEntryCount:int,
     *     unsupportedVersionEntryCount:int,
     *     hasUnsupportedCompressionMethods:bool,
     *     extractionPolicy:string,
     *     isSupportedByBoundedReader:bool,
     *     issues:list<string>,
     *     unsupportedEntries:list<array<string, mixed>>,
     *     mismatchedEntries:list<array<string, mixed>>,
     *     unsupportedVersionEntries:list<array<string, mixed>>,
     *     entries:list<array<string, mixed>>
     * }
     */
    public static function compressionMethodPolicyPreflight(string $bytes): array
    {
        $archive = self::endOfCentralDirectoryPreflight($bytes);
        if ($archive['requiresZip64']) {
            throw new \RuntimeException('ZIP64 package-level central-directory fields require ZIP64 EOCD parsing before compression methods can be scanned');
        }

        self::assertRange(
            $bytes,
            $archive['centralDirectoryOffset'],
            $archive['centralDirectorySize'],
            'central directory'
        );
        if ($archive['centralDirectoryEnd'] > $archive['eocdOffset']) {
            throw new \RuntimeException('Central directory overlaps the end-of-central-directory record');
        }

        $entries = [];
        $unsupportedEntries = [];
        $mismatchedEntries = [];
        $unsupportedVersionEntries = [];
        $storedEntryCount = 0;
        $deflatedEntryCount = 0;
        $supportedEntryCount = 0;
        $cursor = $archive['centralDirectoryOffset'];
        $index = 0;
        while ($index < $archive['totalEntryCount']) {
            if (substr($bytes, $cursor, 4) !== self::CENTRAL_DIRECTORY_SIGNATURE) {
                throw new \RuntimeException("Invalid ZIP central directory header at entry {$index}");
            }

            self::assertRange($bytes, $cursor, 46, 'central directory entry');
            $versionNeededToExtract = self::readUInt16($bytes, $cursor + 6);
            $flags = self::readUInt16($bytes, $cursor + 8);
            $method = self::readUInt16($bytes, $cursor + 10);
            $compressedSize = self::readUInt32($bytes, $cursor + 20);
            $uncompressedSize = self::readUInt32($bytes, $cursor + 24);
            $nameLength = self::readUInt16($bytes, $cursor + 28);
            $extraLength = self::readUInt16($bytes, $cursor + 30);
            $commentLength = self::readUInt16($bytes, $cursor + 32);
            $localHeaderOffset = self::readUInt32($bytes, $cursor + 42);
            $variableStart = $cursor + 46;
            $variableLength = $nameLength + $extraLength + $commentLength;
            self::assertRange($bytes, $variableStart, $variableLength, 'central directory entry variable fields');

            $rawName = substr($bytes, $variableStart, $nameLength);
            $decodedName = self::decodeZipNameForPolicy($rawName, $flags, "central directory entry {$index} name");
            $localHeader = self::readLocalHeaderNameMetadata($bytes, $localHeaderOffset, $index);

            if ($method === 0) {
                $storedEntryCount++;
            } elseif ($method === 8) {
                $deflatedEntryCount++;
            }

            $methodIsSupported = $method === 0 || $method === 8;
            $localMethodIsSupported = $localHeader['compressionMethod'] === 0 || $localHeader['compressionMethod'] === 8;
            $hasUnsupportedMethod = !$methodIsSupported || !$localMethodIsSupported;
            $hasMethodMismatch = $method !== $localHeader['compressionMethod'];
            $hasUnsupportedVersion = $versionNeededToExtract > self::MAX_SUPPORTED_VERSION_NEEDED_TO_EXTRACT
                || $localHeader['versionNeededToExtract'] > self::MAX_SUPPORTED_VERSION_NEEDED_TO_EXTRACT;

            $diagnostics = [];
            if ($hasUnsupportedMethod) {
                $diagnostics[] = 'zip-unsupported-compression-method';
            }
            if ($hasMethodMismatch) {
                $diagnostics[] = 'zip-local-header-compression-method-mismatch';
            }
            if ($hasUnsupportedVersion) {
                $diagnostics[] = 'zip-version-needed-exceeds-bounded-reader';
            }
            if ($rawName !== $localHeader['rawName']) {
                $diagnostics[] = 'zip-local-header-name-mismatch';
            }

            $entry = [
                'name' => $decodedName['text'],
                'rawName' => $rawName,
                'nameEncoding' => $decodedName['encoding'],
                'localName' => $localHeader['name'],
                'localRawName' => $localHeader['rawName'],
                'localNameEncoding' => $localHeader['nameEncoding'],
                'centralDirectoryIndex' => $index,
                'centralDirectoryOffset' => $cursor,
                'localHeaderOffset' => $localHeaderOffset,
                'versionNeededToExtract' => $versionNeededToExtract,
                'localVersionNeededToExtract' => $localHeader['versionNeededToExtract'],
                'compressionMethod' => $method,
                'compressionMethodName' => self::compressionMethodName($method),
                'localCompressionMethod' => $localHeader['compressionMethod'],
                'localCompressionMethodName' => self::compressionMethodName($localHeader['compressionMethod']),
                'generalPurposeFlags' => $flags,
                'localGeneralPurposeFlags' => $localHeader['generalPurposeFlags'],
                'compressedSize' => $compressedSize,
                'uncompressedSize' => $uncompressedSize,
                'isDirectory' => str_ends_with($decodedName['text'], '/'),
                'isSupported' => $diagnostics === [],
                'policy' => $diagnostics === [] ? 'metadata' : 'blocked',
                'diagnostics' => array_values(array_unique($diagnostics)),
            ];

            $entries[] = $entry;
            if ($entry['isSupported']) {
                $supportedEntryCount++;
            }
            if ($hasUnsupportedMethod) {
                $unsupportedEntries[] = $entry;
            }
            if ($hasMethodMismatch) {
                $mismatchedEntries[] = $entry;
            }
            if ($hasUnsupportedVersion) {
                $unsupportedVersionEntries[] = $entry;
            }

            $cursor += 46 + $variableLength;
            $index++;
        }

        if ($cursor !== $archive['centralDirectoryEnd']) {
            throw new \RuntimeException('ZIP central directory size does not match scanned compression-method policy records');
        }

        $issues = [];
        if (!$archive['isSingleDisk']) {
            $issues[] = 'split-archive-eocd';
        }
        if ($unsupportedEntries !== []) {
            $issues[] = 'unsupported-compression-methods';
        }
        if ($mismatchedEntries !== []) {
            $issues[] = 'local-header-compression-method-mismatch';
        }
        if ($unsupportedVersionEntries !== []) {
            $issues[] = 'unsupported-version-needed';
        }

        $hasBlockedCompressionMetadata = $unsupportedEntries !== []
            || $mismatchedEntries !== []
            || $unsupportedVersionEntries !== [];

        return [
            'entryCount' => count($entries),
            'supportedEntryCount' => $supportedEntryCount,
            'unsupportedCompressionMethodCount' => count($unsupportedEntries),
            'storedEntryCount' => $storedEntryCount,
            'deflatedEntryCount' => $deflatedEntryCount,
            'methodMismatchEntryCount' => count($mismatchedEntries),
            'unsupportedVersionEntryCount' => count($unsupportedVersionEntries),
            'hasUnsupportedCompressionMethods' => $unsupportedEntries !== [],
            'extractionPolicy' => $hasBlockedCompressionMetadata ? 'unsupported-compression-methods-blocked' : 'supported-compression-methods',
            'isSupportedByBoundedReader' => $issues === [],
            'issues' => $issues,
            'unsupportedEntries' => $unsupportedEntries,
            'mismatchedEntries' => $mismatchedEntries,
            'unsupportedVersionEntries' => $unsupportedVersionEntries,
            'entries' => $entries,
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
     *     declaredEntryCount:int,
     *     diskEntryCount:int,
     *     scannedEntryCount:int,
     *     entryCount:int,
     *     centralDirectoryOffset:int,
     *     centralDirectorySize:int,
     *     centralDirectoryEnd:int,
     *     eocdOffset:int,
     *     scannedCentralDirectoryBytes:int,
     *     centralDirectoryTailBytes:int,
     *     hasEntryCountMismatch:bool,
     *     hasCentralDirectorySignature:bool,
     *     centralDirectorySignature:?array{offset:int, dataLength:int, endOffset:int, location:string},
     *     isSupportedByBoundedReader:bool,
     *     issues:list<string>,
     *     entries:list<array{name:string, rawName:string, nameEncoding:string, centralDirectoryIndex:int, offset:int, recordEnd:int, localHeaderOffset:int}>
     * }
     */
    public static function centralDirectoryInventoryPreflight(string $bytes): array
    {
        $archive = self::endOfCentralDirectoryPreflight($bytes);
        if ($archive['requiresZip64']) {
            throw new \RuntimeException('ZIP64 package-level central-directory fields require ZIP64 EOCD parsing before central directory inventory can be scanned');
        }

        self::assertRange(
            $bytes,
            $archive['centralDirectoryOffset'],
            $archive['centralDirectorySize'],
            'central directory'
        );
        if ($archive['centralDirectoryEnd'] > $archive['eocdOffset']) {
            throw new \RuntimeException('Central directory overlaps the end-of-central-directory record');
        }

        $entries = [];
        $issues = [];
        $cursor = $archive['centralDirectoryOffset'];
        $centralDirectorySignature = null;
        $index = 0;
        while ($cursor < $archive['centralDirectoryEnd']) {
            $signature = self::centralDirectoryDigitalSignatureRecordAt($bytes, $cursor);
            if ($signature !== null) {
                $centralDirectorySignature = [
                    'offset' => $signature['offset'],
                    'dataLength' => strlen($signature['data']),
                    'endOffset' => $signature['endOffset'],
                    'location' => 'inside-central-directory',
                ];
                $cursor = $signature['endOffset'];
                break;
            }

            if (substr($bytes, $cursor, 4) !== self::CENTRAL_DIRECTORY_SIGNATURE) {
                $issues[] = 'central-directory-unexpected-record';
                break;
            }

            self::assertRange($bytes, $cursor, 46, 'central directory entry');
            $flags = self::readUInt16($bytes, $cursor + 8);
            $nameLength = self::readUInt16($bytes, $cursor + 28);
            $extraLength = self::readUInt16($bytes, $cursor + 30);
            $commentLength = self::readUInt16($bytes, $cursor + 32);
            $localHeaderOffset = self::readUInt32($bytes, $cursor + 42);
            $variableStart = $cursor + 46;
            $variableLength = $nameLength + $extraLength + $commentLength;
            self::assertRange($bytes, $variableStart, $variableLength, 'central directory entry variable fields');

            $rawName = substr($bytes, $variableStart, $nameLength);
            $centralExtraFieldData = substr($bytes, $variableStart + $nameLength, $extraLength);
            self::assertSafePartName($rawName);
            $decodedName = self::decodeZipText(
                $rawName,
                $flags,
                $centralExtraFieldData,
                self::INFOZIP_UNICODE_PATH_EXTRA_ID,
                'info-zip-unicode-path',
                "central directory entry {$index} name"
            );
            self::assertSafePartName($decodedName['text']);
            $recordEnd = $cursor + 46 + $variableLength;
            $entries[] = [
                'name' => $decodedName['text'],
                'rawName' => $rawName,
                'nameEncoding' => $decodedName['encoding'],
                'centralDirectoryIndex' => $index,
                'offset' => $cursor,
                'recordEnd' => $recordEnd,
                'localHeaderOffset' => $localHeaderOffset,
            ];
            $cursor = $recordEnd;
            $index++;
        }

        if ($centralDirectorySignature === null && $archive['centralDirectoryEnd'] < $archive['eocdOffset']) {
            $signature = self::centralDirectoryDigitalSignatureRecordAt($bytes, $archive['centralDirectoryEnd']);
            if ($signature !== null && $signature['endOffset'] === $archive['eocdOffset']) {
                $centralDirectorySignature = [
                    'offset' => $signature['offset'],
                    'dataLength' => strlen($signature['data']),
                    'endOffset' => $signature['endOffset'],
                    'location' => 'between-central-directory-and-eocd',
                ];
            }
        }

        $entryCountMismatch = count($entries) !== $archive['totalEntryCount'];
        if ($entryCountMismatch) {
            $issues[] = 'central-directory-entry-count-mismatch';
        }
        if (!$archive['isSingleDisk']) {
            $issues[] = 'split-archive-eocd';
        }
        if ($cursor < $archive['centralDirectoryEnd']) {
            $issues[] = 'central-directory-unexpected-tail';
        }
        if (
            $archive['centralDirectoryEnd'] < $archive['eocdOffset']
            && (
                $centralDirectorySignature === null
                || $centralDirectorySignature['endOffset'] !== $archive['eocdOffset']
            )
        ) {
            $issues[] = 'central-directory-eocd-gap';
        }

        $issues = array_values(array_unique($issues));

        return [
            'declaredEntryCount' => $archive['totalEntryCount'],
            'diskEntryCount' => $archive['diskEntryCount'],
            'scannedEntryCount' => count($entries),
            'entryCount' => count($entries),
            'centralDirectoryOffset' => $archive['centralDirectoryOffset'],
            'centralDirectorySize' => $archive['centralDirectorySize'],
            'centralDirectoryEnd' => $archive['centralDirectoryEnd'],
            'eocdOffset' => $archive['eocdOffset'],
            'scannedCentralDirectoryBytes' => $cursor - $archive['centralDirectoryOffset'],
            'centralDirectoryTailBytes' => max(0, $archive['centralDirectoryEnd'] - $cursor),
            'hasEntryCountMismatch' => $entryCountMismatch,
            'hasCentralDirectorySignature' => $centralDirectorySignature !== null,
            'centralDirectorySignature' => $centralDirectorySignature,
            'isSupportedByBoundedReader' => $issues === [],
            'issues' => $issues,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     centralDirectoryOffset:int,
     *     centralDirectorySize:int,
     *     centralDirectoryEnd:int,
     *     eocdOffset:int,
     *     archiveExtraDataRecordCount:int,
     *     hasArchiveExtraDataRecord:bool,
     *     isSupportedByBoundedReader:bool,
     *     archiveExtraDataRecords:list<array{offset:int, dataOffset:int, dataLength:int, endOffset:int, location:string, issues:list<string>}>,
     *     entries:list<array{name:string, rawName:string, centralDirectoryIndex:int, offset:int}>
     * }
     */
    public static function archiveExtraDataRecordPreflight(string $bytes): array
    {
        $archive = self::endOfCentralDirectoryPreflight($bytes);
        if ($archive['requiresZip64']) {
            throw new \RuntimeException('ZIP64 package-level central-directory fields require ZIP64 EOCD parsing before archive extra data records can be scanned');
        }

        self::assertRange(
            $bytes,
            $archive['centralDirectoryOffset'],
            $archive['centralDirectorySize'],
            'central directory'
        );
        if ($archive['centralDirectoryEnd'] > $archive['eocdOffset']) {
            throw new \RuntimeException('Central directory overlaps the end-of-central-directory record');
        }

        $records = [];
        $entries = [];
        $cursor = $archive['centralDirectoryOffset'];
        $centralDirectoryEnd = $archive['centralDirectoryEnd'];

        $prefixRecord = self::archiveExtraDataRecordAt($bytes, $cursor);
        if ($prefixRecord !== null) {
            $records[] = self::archiveExtraDataRecordSummary(
                $prefixRecord,
                'central-directory-prefix',
                $archive['eocdOffset'],
                $centralDirectoryEnd
            );
            $cursor = $prefixRecord['endOffset'];
        }

        for ($index = 0; $index < $archive['totalEntryCount']; $index++) {
            if (substr($bytes, $cursor, 4) !== self::CENTRAL_DIRECTORY_SIGNATURE) {
                $record = self::archiveExtraDataRecordAt($bytes, $cursor);
                if ($record !== null) {
                    $records[] = self::archiveExtraDataRecordSummary(
                        $record,
                        'before-central-directory-entry',
                        $archive['eocdOffset'],
                        $centralDirectoryEnd
                    );
                    $cursor = $record['endOffset'];
                    break;
                }

                throw new \RuntimeException("Invalid ZIP central directory header at entry {$index}");
            }

            self::assertRange($bytes, $cursor, 46, 'central directory entry');
            $flags = self::readUInt16($bytes, $cursor + 8);
            $nameLength = self::readUInt16($bytes, $cursor + 28);
            $extraLength = self::readUInt16($bytes, $cursor + 30);
            $commentLength = self::readUInt16($bytes, $cursor + 32);
            $variableStart = $cursor + 46;
            self::assertRange($bytes, $variableStart, $nameLength + $extraLength + $commentLength, 'central directory entry variable fields');

            $rawName = substr($bytes, $variableStart, $nameLength);
            $centralExtraFieldData = substr($bytes, $variableStart + $nameLength, $extraLength);
            $decodedName = self::decodeZipText(
                $rawName,
                $flags,
                $centralExtraFieldData,
                self::INFOZIP_UNICODE_PATH_EXTRA_ID,
                'info-zip-unicode-path',
                "central directory entry {$index} name"
            );
            $entries[] = [
                'name' => $decodedName['text'],
                'rawName' => $rawName,
                'centralDirectoryIndex' => $index,
                'offset' => $cursor,
            ];
            $cursor += 46 + $nameLength + $extraLength + $commentLength;
        }

        while ($cursor < $centralDirectoryEnd) {
            $record = self::archiveExtraDataRecordAt($bytes, $cursor);
            if ($record !== null) {
                $records[] = self::archiveExtraDataRecordSummary(
                    $record,
                    'central-directory-tail',
                    $archive['eocdOffset'],
                    $centralDirectoryEnd
                );
                $cursor = $record['endOffset'];
                continue;
            }

            $signature = self::centralDirectoryDigitalSignatureRecordAt($bytes, $cursor);
            if ($signature !== null) {
                $cursor = $signature['endOffset'];
                continue;
            }

            throw new \RuntimeException('Unexpected ZIP bytes inside the central directory');
        }

        if ($centralDirectoryEnd < $archive['eocdOffset']) {
            $tailCursor = $centralDirectoryEnd;
            $signature = self::centralDirectoryDigitalSignatureRecordAt($bytes, $tailCursor);
            if ($signature !== null && $signature['endOffset'] <= $archive['eocdOffset']) {
                $tailCursor = $signature['endOffset'];
            }

            while ($tailCursor < $archive['eocdOffset']) {
                $record = self::archiveExtraDataRecordAt($bytes, $tailCursor);
                if ($record === null) {
                    break;
                }

                $records[] = self::archiveExtraDataRecordSummary(
                    $record,
                    $tailCursor === $centralDirectoryEnd
                        ? 'between-central-directory-and-eocd'
                        : 'after-central-directory-signature',
                    $archive['eocdOffset'],
                    $centralDirectoryEnd
                );
                $tailCursor = $record['endOffset'];
            }
        }

        return [
            'entryCount' => count($entries),
            'centralDirectoryOffset' => $archive['centralDirectoryOffset'],
            'centralDirectorySize' => $archive['centralDirectorySize'],
            'centralDirectoryEnd' => $centralDirectoryEnd,
            'eocdOffset' => $archive['eocdOffset'],
            'archiveExtraDataRecordCount' => count($records),
            'hasArchiveExtraDataRecord' => $records !== [],
            'isSupportedByBoundedReader' => $records === [],
            'archiveExtraDataRecords' => $records,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     zip64ExtraFieldEntryCount:int,
     *     centralZip64ExtraFieldEntryCount:int,
     *     localZip64ExtraFieldEntryCount:int,
     *     requiresZip64EntryCount:int,
     *     zip64Entries:list<array<string, mixed>>,
     *     entries:list<array<string, mixed>>
     * }
     */
    public static function zip64ExtraFieldPreflight(string $bytes): array
    {
        $eocdOffset = self::findEndOfCentralDirectory($bytes);
        $entryCount = self::readUInt16($bytes, $eocdOffset + 10);
        $centralDirectorySize = self::readUInt32($bytes, $eocdOffset + 12);
        $centralDirectoryOffset = self::readUInt32($bytes, $eocdOffset + 16);
        if ($entryCount === 0xffff || $centralDirectorySize === 0xffffffff || $centralDirectoryOffset === 0xffffffff) {
            throw new \RuntimeException('ZIP64 package-level central-directory fields require ZIP64 EOCD parsing before entry extra fields can be scanned');
        }

        self::assertRange($bytes, $centralDirectoryOffset, $centralDirectorySize, 'central directory');

        $entries = [];
        $zip64Entries = [];
        $zip64ExtraFieldEntryCount = 0;
        $centralZip64ExtraFieldEntryCount = 0;
        $localZip64ExtraFieldEntryCount = 0;
        $requiresZip64EntryCount = 0;
        $cursor = $centralDirectoryOffset;

        for ($index = 0; $index < $entryCount; $index++) {
            if (substr($bytes, $cursor, 4) !== self::CENTRAL_DIRECTORY_SIGNATURE) {
                throw new \RuntimeException("Invalid ZIP central directory header at entry {$index}");
            }

            self::assertRange($bytes, $cursor, 46, 'central directory entry');
            $flags = self::readUInt16($bytes, $cursor + 8);
            $method = self::readUInt16($bytes, $cursor + 10);
            $compressedSize = self::readUInt32($bytes, $cursor + 20);
            $uncompressedSize = self::readUInt32($bytes, $cursor + 24);
            $nameLength = self::readUInt16($bytes, $cursor + 28);
            $extraLength = self::readUInt16($bytes, $cursor + 30);
            $commentLength = self::readUInt16($bytes, $cursor + 32);
            $diskStart = self::readUInt16($bytes, $cursor + 34);
            $localHeaderOffset = self::readUInt32($bytes, $cursor + 42);
            $variableStart = $cursor + 46;
            self::assertRange($bytes, $variableStart, $nameLength + $extraLength + $commentLength, 'central directory entry variable fields');

            $rawName = substr($bytes, $variableStart, $nameLength);
            $name = (($flags & self::UTF8_GENERAL_PURPOSE_FLAG) !== 0 && preg_match('//u', $rawName) === 1)
                ? $rawName
                : self::decodeCp437($rawName);
            $centralExtraFieldData = substr($bytes, $variableStart + $nameLength, $extraLength);
            $centralExtraFields = ZipPackageEntry::extraFieldsFromData(
                $centralExtraFieldData,
                "central extra fields for {$name}",
                true
            );
            $centralZip64ExtraFieldData = self::singleZip64ExtraFieldData(
                $centralExtraFields,
                "central extra fields for {$name}"
            );
            $centralRequiredFields = [];
            if ($uncompressedSize === 0xffffffff) {
                $centralRequiredFields[] = 'uncompressedSize';
            }
            if ($compressedSize === 0xffffffff) {
                $centralRequiredFields[] = 'compressedSize';
            }
            if ($localHeaderOffset === 0xffffffff) {
                $centralRequiredFields[] = 'localHeaderOffset';
            }
            if ($diskStart === 0xffff) {
                $centralRequiredFields[] = 'diskStart';
            }

            $centralPlan = self::zip64ExtraFieldPlan(
                $centralZip64ExtraFieldData,
                $centralRequiredFields,
                "central extra fields for {$name}"
            );
            $actualLocalHeaderOffset = $centralPlan['values']['localHeaderOffset'] ?? (
                $localHeaderOffset === 0xffffffff ? null : $localHeaderOffset
            );
            $localPlan = self::zip64ExtraFieldPlan(null, [], "local extra fields for {$name}");
            $localHeaderCompressedSize = null;
            $localHeaderUncompressedSize = null;
            if ($actualLocalHeaderOffset !== null) {
                self::assertRange($bytes, $actualLocalHeaderOffset, 30, "local file header for {$name}");
                if (substr($bytes, $actualLocalHeaderOffset, 4) !== self::LOCAL_FILE_SIGNATURE) {
                    throw new \RuntimeException("Invalid ZIP local file header for entry {$name}");
                }

                $localHeaderCompressedSize = self::readUInt32($bytes, $actualLocalHeaderOffset + 18);
                $localHeaderUncompressedSize = self::readUInt32($bytes, $actualLocalHeaderOffset + 22);
                $localNameLength = self::readUInt16($bytes, $actualLocalHeaderOffset + 26);
                $localExtraLength = self::readUInt16($bytes, $actualLocalHeaderOffset + 28);
                $localVariableStart = $actualLocalHeaderOffset + 30;
                self::assertRange($bytes, $localVariableStart, $localNameLength + $localExtraLength, "local file header variable fields for {$name}");
                $localExtraFieldData = substr($bytes, $localVariableStart + $localNameLength, $localExtraLength);
                $localExtraFields = ZipPackageEntry::extraFieldsFromData(
                    $localExtraFieldData,
                    "local extra fields for {$name}",
                    true
                );
                $localZip64ExtraFieldData = self::singleZip64ExtraFieldData(
                    $localExtraFields,
                    "local extra fields for {$name}"
                );
                $localRequiredFields = [];
                if ($localHeaderUncompressedSize === 0xffffffff) {
                    $localRequiredFields[] = 'uncompressedSize';
                }
                if ($localHeaderCompressedSize === 0xffffffff) {
                    $localRequiredFields[] = 'compressedSize';
                }
                $localPlan = self::zip64ExtraFieldPlan(
                    $localZip64ExtraFieldData,
                    $localRequiredFields,
                    "local extra fields for {$name}"
                );
            }

            $requiresZip64 = $centralRequiredFields !== [] || $localPlan['requiredFields'] !== [];
            $hasZip64ExtraField = $centralPlan['present'] || $localPlan['present'];
            if ($centralPlan['present']) {
                $centralZip64ExtraFieldEntryCount++;
            }
            if ($localPlan['present']) {
                $localZip64ExtraFieldEntryCount++;
            }
            if ($hasZip64ExtraField) {
                $zip64ExtraFieldEntryCount++;
            }
            if ($requiresZip64) {
                $requiresZip64EntryCount++;
            }

            $issues = [];
            if ($hasZip64ExtraField) {
                $issues[] = 'zip64-extra-field';
            }
            if ($requiresZip64) {
                $issues[] = 'zip64-size-or-offset-sentinel';
            }
            $issues = array_values(array_unique(array_merge(
                $issues,
                $centralPlan['issues'],
                $localPlan['issues']
            )));

            $summary = [
                'name' => $name,
                'rawName' => $rawName,
                'centralDirectoryIndex' => $index,
                'compressionMethod' => $method,
                'centralCompressedSize' => $compressedSize,
                'centralUncompressedSize' => $uncompressedSize,
                'centralLocalHeaderOffset' => $localHeaderOffset,
                'centralDiskStart' => $diskStart,
                'localHeaderOffset' => $actualLocalHeaderOffset,
                'localHeaderCompressedSize' => $localHeaderCompressedSize,
                'localHeaderUncompressedSize' => $localHeaderUncompressedSize,
                'centralZip64ExtraFieldPresent' => $centralPlan['present'],
                'centralZip64RequiredFields' => $centralPlan['requiredFields'],
                'centralZip64Values' => $centralPlan['values'],
                'centralZip64ExtraBytes' => $centralPlan['extraBytes'],
                'localZip64ExtraFieldPresent' => $localPlan['present'],
                'localZip64RequiredFields' => $localPlan['requiredFields'],
                'localZip64Values' => $localPlan['values'],
                'localZip64ExtraBytes' => $localPlan['extraBytes'],
                'requiresZip64' => $requiresZip64,
                'isSupportedByBoundedReader' => !$requiresZip64 && !$hasZip64ExtraField,
                'issues' => $issues,
            ];
            $entries[] = $summary;
            if ($hasZip64ExtraField || $requiresZip64) {
                $zip64Entries[] = $summary;
            }

            $cursor += 46 + $nameLength + $extraLength + $commentLength;
        }

        if ($cursor !== $centralDirectoryOffset + $centralDirectorySize) {
            throw new \RuntimeException('ZIP central directory size does not match scanned entry records');
        }

        return [
            'entryCount' => $entryCount,
            'zip64ExtraFieldEntryCount' => $zip64ExtraFieldEntryCount,
            'centralZip64ExtraFieldEntryCount' => $centralZip64ExtraFieldEntryCount,
            'localZip64ExtraFieldEntryCount' => $localZip64ExtraFieldEntryCount,
            'requiresZip64EntryCount' => $requiresZip64EntryCount,
            'zip64Entries' => $zip64Entries,
            'entries' => $entries,
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
     *     totalEntryCount:int,
     *     descriptorEntryCount:int,
     *     matchedDescriptorEntryCount:int,
     *     mismatchedDescriptorEntryCount:int,
     *     signedDescriptorEntryCount:int,
     *     unsignedDescriptorEntryCount:int,
     *     zip64SizedDescriptorEntryCount:int,
     *     isSupportedByBoundedReader:bool,
     *     issues:list<string>,
     *     mismatchedDescriptorEntries:list<array<string, mixed>>,
     *     descriptorEntries:list<array<string, mixed>>,
     *     entries:list<array<string, mixed>>
     * }
     */
    public static function dataDescriptorIntegrityPreflight(string $bytes): array
    {
        $archive = self::endOfCentralDirectoryPreflight($bytes);
        if ($archive['requiresZip64']) {
            throw new \RuntimeException('ZIP64 package-level central-directory fields require ZIP64 EOCD parsing before data descriptors can be scanned');
        }

        self::assertRange(
            $bytes,
            $archive['centralDirectoryOffset'],
            $archive['centralDirectorySize'],
            'central directory'
        );
        if ($archive['centralDirectoryEnd'] > $archive['eocdOffset']) {
            throw new \RuntimeException('Central directory overlaps the end-of-central-directory record');
        }

        $centralEntries = [];
        $cursor = $archive['centralDirectoryOffset'];
        for ($index = 0; $index < $archive['totalEntryCount']; $index++) {
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
            $localHeaderOffset = self::readUInt32($bytes, $cursor + 42);
            $variableStart = $cursor + 46;
            $variableLength = $nameLength + $extraLength + $commentLength;
            self::assertRange($bytes, $variableStart, $variableLength, 'central directory entry variable fields');

            $rawName = substr($bytes, $variableStart, $nameLength);
            $centralExtraFieldData = substr($bytes, $variableStart + $nameLength, $extraLength);
            self::assertSafePartName($rawName);
            $decodedName = self::decodeZipText(
                $rawName,
                $flags,
                $centralExtraFieldData,
                self::INFOZIP_UNICODE_PATH_EXTRA_ID,
                'info-zip-unicode-path',
                "central directory entry {$index} name"
            );
            self::assertSafePartName($decodedName['text']);

            $centralEntries[] = [
                'centralDirectoryIndex' => $index,
                'centralDirectoryOffset' => $cursor,
                'name' => $decodedName['text'],
                'rawName' => $rawName,
                'nameEncoding' => $decodedName['encoding'],
                'generalPurposeFlags' => $flags,
                'compressionMethod' => $method,
                'crc32' => $crc32,
                'compressedSize' => $compressedSize,
                'uncompressedSize' => $uncompressedSize,
                'localHeaderOffset' => $localHeaderOffset,
            ];
            $cursor += 46 + $variableLength;
        }

        if ($cursor !== $archive['centralDirectoryEnd']) {
            $signature = self::centralDirectoryDigitalSignatureRecordAt($bytes, $cursor);
            if ($signature === null || $signature['endOffset'] !== $archive['centralDirectoryEnd']) {
                self::rejectUnexpectedCentralDirectoryTail($bytes, $cursor, 'inside the central directory');
            }
        }

        $entries = [];
        $descriptorEntries = [];
        $mismatchedDescriptorEntries = [];
        $packageIssues = [];
        $matchedDescriptorEntryCount = 0;
        $signedDescriptorEntryCount = 0;
        $unsignedDescriptorEntryCount = 0;
        $zip64SizedDescriptorEntryCount = 0;

        foreach ($centralEntries as $centralEntry) {
            $localHeader = self::readLocalHeaderNameMetadata(
                $bytes,
                $centralEntry['localHeaderOffset'],
                $centralEntry['centralDirectoryIndex']
            );
            $usesDataDescriptor = ($centralEntry['generalPurposeFlags'] & 0x0008) !== 0;
            $summary = [
                'name' => $centralEntry['name'],
                'rawName' => $centralEntry['rawName'],
                'nameEncoding' => $centralEntry['nameEncoding'],
                'centralDirectoryIndex' => $centralEntry['centralDirectoryIndex'],
                'centralDirectoryOffset' => $centralEntry['centralDirectoryOffset'],
                'localHeaderOffset' => $centralEntry['localHeaderOffset'],
                'usesDataDescriptor' => $usesDataDescriptor,
                'hasSignature' => null,
                'descriptorOffset' => null,
                'valueOffset' => null,
                'descriptorLength' => null,
                'crc32' => null,
                'crc32Hex' => null,
                'compressedSize' => null,
                'uncompressedSize' => null,
                'centralCrc32' => $centralEntry['crc32'],
                'centralCrc32Hex' => sprintf('%08x', $centralEntry['crc32']),
                'centralCompressedSize' => $centralEntry['compressedSize'],
                'centralUncompressedSize' => $centralEntry['uncompressedSize'],
                'usesZip64SizedDescriptor' => false,
                'localHeaderCrc32' => $localHeader['crc32'],
                'localHeaderCompressedSize' => $localHeader['compressedSize'],
                'localHeaderUncompressedSize' => $localHeader['uncompressedSize'],
                'hasZeroLocalHeaderPlaceholders' => null,
                'descriptorValuesMatchCentral' => null,
                'issues' => [],
            ];

            if ($usesDataDescriptor) {
                $summary['hasZeroLocalHeaderPlaceholders'] = $localHeader['crc32'] === 0
                    && $localHeader['compressedSize'] === 0
                    && $localHeader['uncompressedSize'] === 0;
                if (!$summary['hasZeroLocalHeaderPlaceholders']) {
                    $summary['issues'][] = 'local-header-data-descriptor-placeholders-not-zero';
                }

                $dataStart = $centralEntry['localHeaderOffset'] + $localHeader['localHeaderLength'];
                $descriptorOffset = $dataStart + $centralEntry['compressedSize'];
                $nextOffset = self::nextEntryOrCentralDirectoryOffsetForScannedEntries(
                    $centralEntries,
                    $centralEntry['localHeaderOffset'],
                    $archive['centralDirectoryOffset']
                );
                $descriptor = self::dataDescriptorIntegritySummaryFromBytes(
                    $bytes,
                    $centralEntry['name'],
                    $descriptorOffset,
                    $nextOffset,
                    $archive['centralDirectoryOffset'],
                    $centralEntry['crc32'],
                    $centralEntry['compressedSize'],
                    $centralEntry['uncompressedSize']
                );

                $localIssues = $summary['issues'];
                $summary = array_merge($summary, $descriptor);
                $summary['issues'] = array_values(array_unique(array_merge(
                    $localIssues,
                    $descriptor['issues']
                )));
                $descriptorEntries[] = $summary;

                if ($summary['hasSignature'] === true) {
                    $signedDescriptorEntryCount++;
                } else {
                    $unsignedDescriptorEntryCount++;
                }

                if ($summary['usesZip64SizedDescriptor']) {
                    $zip64SizedDescriptorEntryCount++;
                }

                if ($summary['descriptorValuesMatchCentral'] === true && $summary['issues'] === []) {
                    $matchedDescriptorEntryCount++;
                } else {
                    $mismatchedDescriptorEntries[] = $summary;
                }
            }

            foreach ($summary['issues'] as $issue) {
                if (!in_array($issue, $packageIssues, true)) {
                    $packageIssues[] = $issue;
                }
            }

            $entries[] = $summary;
        }

        return [
            'entryCount' => count($entries),
            'totalEntryCount' => $archive['totalEntryCount'],
            'descriptorEntryCount' => count($descriptorEntries),
            'matchedDescriptorEntryCount' => $matchedDescriptorEntryCount,
            'mismatchedDescriptorEntryCount' => count($mismatchedDescriptorEntries),
            'signedDescriptorEntryCount' => $signedDescriptorEntryCount,
            'unsignedDescriptorEntryCount' => $unsignedDescriptorEntryCount,
            'zip64SizedDescriptorEntryCount' => $zip64SizedDescriptorEntryCount,
            'isSupportedByBoundedReader' => $packageIssues === [],
            'issues' => $packageIssues,
            'mismatchedDescriptorEntries' => $mismatchedDescriptorEntries,
            'descriptorEntries' => $descriptorEntries,
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
     *     generalPurposeFlags:array<string, mixed>,
     *     compressionMethods:array<string, mixed>,
     *     comments:array<string, mixed>,
     *     modificationTimes:array<string, mixed>,
     *     extraFields:array<string, mixed>,
     *     pathHierarchy:array<string, mixed>,
     *     caseInsensitiveNames:array<string, mixed>,
     *     rawNames:array<string, mixed>,
     *     permissions:array<string, mixed>,
     *     dosAttributes:array<string, mixed>,
     *     internalAttributes:array<string, mixed>,
     *     creatorHostSystems:array<string, mixed>,
     *     localHeaders:array<string, mixed>,
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
        $generalPurposeFlags = $this->generalPurposeFlagPreflight();
        $compressionMethods = $this->compressionMethodPreflight();
        $comments = $this->commentPreflight();
        $modificationTimes = $this->modificationTimePreflight();
        $extraFields = $this->extraFieldPreflight();
        $pathHierarchy = $this->pathHierarchyPreflight();
        $caseInsensitiveNames = $this->caseInsensitiveNamePreflight();
        $rawNames = $this->rawNamePreflight();
        $permissions = $this->permissionPreflight();
        $dosAttributes = $this->dosAttributePreflight();
        $internalAttributes = $this->internalAttributePreflight();
        $creatorHostSystems = $this->creatorHostSystemPreflight();
        $localHeaders = $this->localHeaderPreflight();
        $dataDescriptors = $this->dataDescriptorPreflight();
        $readIntegrity = $this->readIntegrityPreflight($maxEntryUncompressedBytes);
        $diagnostics = [];

        if (!$archive['isArchiveLayoutSupported']) {
            $diagnostics[] = 'unsupported-archive-layout';
        }

        if ($archive['hasCentralDirectorySignature']) {
            $diagnostics[] = 'central-directory-signature-unverified';
        }

        if ($comments['hasComments']) {
            $diagnostics[] = 'package-or-entry-comments';
        }

        if ($modificationTimes['invalidDosTimestampEntryCount'] > 0) {
            $diagnostics[] = 'invalid-modification-times';
        }

        if ($compressionMethods['unsupportedCompressionMethodCount'] > 0) {
            $diagnostics[] = 'unsupported-compression-methods';
        }

        if ($generalPurposeFlags['unsupportedFlagEntryCount'] > 0) {
            $diagnostics[] = 'unsupported-general-purpose-flags';
        }

        if ($generalPurposeFlags['dataDescriptorEntryCount'] > 0) {
            $diagnostics[] = 'data-descriptor-entries';
        }

        if ($generalPurposeFlags['deflateOptionEntryCount'] > 0) {
            $diagnostics[] = 'deflate-option-flag-entries';
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

        if ($rawNames['collisionEntryCount'] > 0) {
            $diagnostics[] = 'raw-name-collisions';
        }

        if ($permissions['executableFileCount'] > 0) {
            $diagnostics[] = 'executable-file-entries';
        }

        if ($dosAttributes['hiddenSystemOrVolumeLabelEntryCount'] > 0) {
            $diagnostics[] = 'hidden-system-or-volume-label-entries';
        }

        if ($internalAttributes['internalAttributeEntryCount'] > 0) {
            $diagnostics[] = 'internal-file-attributes';
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
            'generalPurposeFlags' => $generalPurposeFlags,
            'compressionMethods' => $compressionMethods,
            'comments' => $comments,
            'modificationTimes' => $modificationTimes,
            'extraFields' => $extraFields,
            'pathHierarchy' => $pathHierarchy,
            'caseInsensitiveNames' => $caseInsensitiveNames,
            'rawNames' => $rawNames,
            'permissions' => $permissions,
            'dosAttributes' => $dosAttributes,
            'internalAttributes' => $internalAttributes,
            'creatorHostSystems' => $creatorHostSystems,
            'localHeaders' => $localHeaders,
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
     *     generalPurposeFlags:array<string, mixed>,
     *     compressionMethods:array<string, mixed>,
     *     comments:array<string, mixed>,
     *     modificationTimes:array<string, mixed>,
     *     extraFields:array<string, mixed>,
     *     pathHierarchy:array<string, mixed>,
     *     caseInsensitiveNames:array<string, mixed>,
     *     rawNames:array<string, mixed>,
     *     permissions:array<string, mixed>,
     *     dosAttributes:array<string, mixed>,
     *     internalAttributes:array<string, mixed>,
     *     creatorHostSystems:array<string, mixed>,
     *     localHeaders:array<string, mixed>,
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
     *     unsupportedFlagEntryCount:int,
     *     utf8NameEntryCount:int,
     *     dataDescriptorEntryCount:int,
     *     deflateOptionEntryCount:int,
     *     strictReviewEntryCount:int,
     *     unsupportedEntries:list<array{name:string, generalPurposeFlags:int, flagNames:list<string>, unsupportedFlagBits:int, isSupportedByReader:bool, usesUtf8Names:bool, usesDataDescriptor:bool, deflateOptionFlags:int, deflateOptionName:?string, requiresStrictReview:bool, issues:list<string>}>,
     *     strictReviewEntries:list<array{name:string, generalPurposeFlags:int, flagNames:list<string>, unsupportedFlagBits:int, isSupportedByReader:bool, usesUtf8Names:bool, usesDataDescriptor:bool, deflateOptionFlags:int, deflateOptionName:?string, requiresStrictReview:bool, issues:list<string>}>,
     *     entries:list<array{name:string, generalPurposeFlags:int, flagNames:list<string>, unsupportedFlagBits:int, isSupportedByReader:bool, usesUtf8Names:bool, usesDataDescriptor:bool, deflateOptionFlags:int, deflateOptionName:?string, requiresStrictReview:bool, issues:list<string>}>
     * }
     */
    public function generalPurposeFlagPreflight(): array
    {
        $utf8NameEntryCount = 0;
        $dataDescriptorEntryCount = 0;
        $deflateOptionEntryCount = 0;
        $unsupportedEntries = [];
        $strictReviewEntries = [];
        $entries = [];

        foreach ($this->entries as $entry) {
            $flags = $entry->generalPurposeFlags;
            $unsupportedFlagBits = $flags & ~self::SUPPORTED_GENERAL_PURPOSE_FLAGS;
            $usesUtf8Names = ($flags & self::UTF8_GENERAL_PURPOSE_FLAG) !== 0;
            $usesDataDescriptor = ($flags & 0x0008) !== 0;
            $deflateOptionFlags = $flags & self::DEFLATE_OPTION_GENERAL_PURPOSE_FLAGS;
            $requiresStrictReview = $usesDataDescriptor || $deflateOptionFlags !== 0;
            $issues = [];

            if ($unsupportedFlagBits !== 0) {
                $issues[] = 'unsupported-general-purpose-flags';
            }
            if ($usesDataDescriptor) {
                $issues[] = 'data-descriptor-entry';
            }
            if ($deflateOptionFlags !== 0) {
                $issues[] = 'deflate-option-flags';
            }

            if ($usesUtf8Names) {
                $utf8NameEntryCount++;
            }
            if ($usesDataDescriptor) {
                $dataDescriptorEntryCount++;
            }
            if ($deflateOptionFlags !== 0) {
                $deflateOptionEntryCount++;
            }

            $summary = [
                'name' => $entry->name,
                'generalPurposeFlags' => $flags,
                'flagNames' => self::generalPurposeFlagNames($flags),
                'unsupportedFlagBits' => $unsupportedFlagBits,
                'isSupportedByReader' => $unsupportedFlagBits === 0,
                'usesUtf8Names' => $usesUtf8Names,
                'usesDataDescriptor' => $usesDataDescriptor,
                'deflateOptionFlags' => $deflateOptionFlags,
                'deflateOptionName' => self::deflateOptionFlagName($deflateOptionFlags),
                'requiresStrictReview' => $requiresStrictReview,
                'issues' => $issues,
            ];

            $entries[] = $summary;
            if ($unsupportedFlagBits !== 0) {
                $unsupportedEntries[] = $summary;
            }
            if ($requiresStrictReview) {
                $strictReviewEntries[] = $summary;
            }
        }

        return [
            'entryCount' => count($this->entries),
            'supportedEntryCount' => count($this->entries) - count($unsupportedEntries),
            'unsupportedFlagEntryCount' => count($unsupportedEntries),
            'utf8NameEntryCount' => $utf8NameEntryCount,
            'dataDescriptorEntryCount' => $dataDescriptorEntryCount,
            'deflateOptionEntryCount' => $deflateOptionEntryCount,
            'strictReviewEntryCount' => count($strictReviewEntries),
            'unsupportedEntries' => $unsupportedEntries,
            'strictReviewEntries' => $strictReviewEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     supportedEntryCount:int,
     *     unsupportedFlagEntryCount:int,
     *     utf8NameEntryCount:int,
     *     dataDescriptorEntryCount:int,
     *     deflateOptionEntryCount:int,
     *     strictReviewEntryCount:int,
     *     unsupportedEntries:list<array{name:string, generalPurposeFlags:int, flagNames:list<string>, unsupportedFlagBits:int, isSupportedByReader:bool, usesUtf8Names:bool, usesDataDescriptor:bool, deflateOptionFlags:int, deflateOptionName:?string, requiresStrictReview:bool, issues:list<string>}>,
     *     strictReviewEntries:list<array{name:string, generalPurposeFlags:int, flagNames:list<string>, unsupportedFlagBits:int, isSupportedByReader:bool, usesUtf8Names:bool, usesDataDescriptor:bool, deflateOptionFlags:int, deflateOptionName:?string, requiresStrictReview:bool, issues:list<string>}>,
     *     entries:list<array{name:string, generalPurposeFlags:int, flagNames:list<string>, unsupportedFlagBits:int, isSupportedByReader:bool, usesUtf8Names:bool, usesDataDescriptor:bool, deflateOptionFlags:int, deflateOptionName:?string, requiresStrictReview:bool, issues:list<string>}>
     * }
     */
    public function assertNoStrictGeneralPurposeFlagReviewEntries(): array
    {
        $summary = $this->generalPurposeFlagPreflight();
        if ($summary['unsupportedFlagEntryCount'] > 0 || $summary['strictReviewEntryCount'] > 0) {
            $entries = implode(
                ', ',
                array_map(
                    static fn (array $entry): string => $entry['name'] . ' flags '
                        . sprintf('0x%04x', $entry['generalPurposeFlags'])
                        . ' (' . implode('/', $entry['issues']) . ')',
                    array_merge($summary['unsupportedEntries'], $summary['strictReviewEntries'])
                )
            );

            throw new \RuntimeException(
                'ZIP package contains general-purpose flag metadata that requires explicit strict import review: '
                . $entries
            );
        }

        return $summary;
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
     *     internalAttributeEntryCount:int,
     *     textInternalAttributeEntryCount:int,
     *     unknownInternalAttributeEntryCount:int,
     *     internalAttributeEntries:list<array{name:string, isDirectory:bool, internalFileAttributes:int, internalAttributeNames:list<string>, hasTextInternalAttribute:bool, unknownInternalAttributeBits:int, hasUnknownInternalAttributeBits:bool, hasInternalFileAttributes:bool, issues:list<string>}>,
     *     textInternalAttributeEntries:list<array{name:string, isDirectory:bool, internalFileAttributes:int, internalAttributeNames:list<string>, hasTextInternalAttribute:bool, unknownInternalAttributeBits:int, hasUnknownInternalAttributeBits:bool, hasInternalFileAttributes:bool, issues:list<string>}>,
     *     unknownInternalAttributeEntries:list<array{name:string, isDirectory:bool, internalFileAttributes:int, internalAttributeNames:list<string>, hasTextInternalAttribute:bool, unknownInternalAttributeBits:int, hasUnknownInternalAttributeBits:bool, hasInternalFileAttributes:bool, issues:list<string>}>,
     *     entries:list<array{name:string, isDirectory:bool, internalFileAttributes:int, internalAttributeNames:list<string>, hasTextInternalAttribute:bool, unknownInternalAttributeBits:int, hasUnknownInternalAttributeBits:bool, hasInternalFileAttributes:bool, issues:list<string>}>
     * }
     */
    public function internalAttributePreflight(): array
    {
        $internalAttributeEntryCount = 0;
        $textInternalAttributeEntryCount = 0;
        $unknownInternalAttributeEntryCount = 0;
        $internalAttributeEntries = [];
        $textInternalAttributeEntries = [];
        $unknownInternalAttributeEntries = [];
        $entries = [];

        foreach ($this->entries as $entry) {
            $hasText = $entry->hasTextInternalAttribute();
            $unknownBits = $entry->unknownInternalAttributeBits();
            $hasUnknownBits = $unknownBits !== 0;
            $hasInternalAttributes = $entry->internalFileAttributes !== 0;
            $issues = [];

            if ($hasText) {
                $textInternalAttributeEntryCount++;
                $issues[] = 'internal-text-attribute';
            }

            if ($hasUnknownBits) {
                $unknownInternalAttributeEntryCount++;
                $issues[] = 'unknown-internal-file-attribute-bits';
            }

            if ($hasInternalAttributes) {
                $internalAttributeEntryCount++;
            }

            $summary = [
                'name' => $entry->name,
                'isDirectory' => $entry->isDirectory(),
                'internalFileAttributes' => $entry->internalFileAttributes,
                'internalAttributeNames' => $entry->internalAttributeNames(),
                'hasTextInternalAttribute' => $hasText,
                'unknownInternalAttributeBits' => $unknownBits,
                'hasUnknownInternalAttributeBits' => $hasUnknownBits,
                'hasInternalFileAttributes' => $hasInternalAttributes,
                'issues' => $issues,
            ];
            $entries[] = $summary;

            if ($hasInternalAttributes) {
                $internalAttributeEntries[] = $summary;
            }

            if ($hasText) {
                $textInternalAttributeEntries[] = $summary;
            }

            if ($hasUnknownBits) {
                $unknownInternalAttributeEntries[] = $summary;
            }
        }

        return [
            'entryCount' => count($this->entries),
            'internalAttributeEntryCount' => $internalAttributeEntryCount,
            'textInternalAttributeEntryCount' => $textInternalAttributeEntryCount,
            'unknownInternalAttributeEntryCount' => $unknownInternalAttributeEntryCount,
            'internalAttributeEntries' => $internalAttributeEntries,
            'textInternalAttributeEntries' => $textInternalAttributeEntries,
            'unknownInternalAttributeEntries' => $unknownInternalAttributeEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     internalAttributeEntryCount:int,
     *     textInternalAttributeEntryCount:int,
     *     unknownInternalAttributeEntryCount:int,
     *     internalAttributeEntries:list<array{name:string, isDirectory:bool, internalFileAttributes:int, internalAttributeNames:list<string>, hasTextInternalAttribute:bool, unknownInternalAttributeBits:int, hasUnknownInternalAttributeBits:bool, hasInternalFileAttributes:bool, issues:list<string>}>,
     *     textInternalAttributeEntries:list<array{name:string, isDirectory:bool, internalFileAttributes:int, internalAttributeNames:list<string>, hasTextInternalAttribute:bool, unknownInternalAttributeBits:int, hasUnknownInternalAttributeBits:bool, hasInternalFileAttributes:bool, issues:list<string>}>,
     *     unknownInternalAttributeEntries:list<array{name:string, isDirectory:bool, internalFileAttributes:int, internalAttributeNames:list<string>, hasTextInternalAttribute:bool, unknownInternalAttributeBits:int, hasUnknownInternalAttributeBits:bool, hasInternalFileAttributes:bool, issues:list<string>}>,
     *     entries:list<array{name:string, isDirectory:bool, internalFileAttributes:int, internalAttributeNames:list<string>, hasTextInternalAttribute:bool, unknownInternalAttributeBits:int, hasUnknownInternalAttributeBits:bool, hasInternalFileAttributes:bool, issues:list<string>}>
     * }
     */
    public function assertNoInternalFileAttributes(): array
    {
        $summary = $this->internalAttributePreflight();
        if ($summary['internalAttributeEntryCount'] > 0) {
            $entries = implode(
                ', ',
                array_map(
                    static fn (array $entry): string => $entry['name']
                        . ' (' . implode('/', $entry['internalAttributeNames']) . ')',
                    $summary['internalAttributeEntries']
                )
            );

            throw new \RuntimeException(
                'ZIP package contains internal file attributes that require explicit import review: ' . $entries
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
     *     dosAttributeEntryCount:int,
     *     readOnlyEntryCount:int,
     *     hiddenEntryCount:int,
     *     systemEntryCount:int,
     *     volumeLabelEntryCount:int,
     *     directoryAttributeEntryCount:int,
     *     archiveEntryCount:int,
     *     hiddenSystemOrVolumeLabelEntryCount:int,
     *     hiddenSystemOrVolumeLabelEntries:list<array{name:string, isDirectory:bool, dosAttributes:int, dosAttributeNames:list<string>, hasReadOnlyAttribute:bool, hasHiddenAttribute:bool, hasSystemAttribute:bool, hasVolumeLabelAttribute:bool, hasDirectoryAttribute:bool, hasArchiveAttribute:bool, externalAttributes:int}>,
     *     entries:list<array{name:string, isDirectory:bool, dosAttributes:int, dosAttributeNames:list<string>, hasReadOnlyAttribute:bool, hasHiddenAttribute:bool, hasSystemAttribute:bool, hasVolumeLabelAttribute:bool, hasDirectoryAttribute:bool, hasArchiveAttribute:bool, externalAttributes:int}>
     * }
     */
    public function dosAttributePreflight(): array
    {
        $dosAttributeEntryCount = 0;
        $readOnlyEntryCount = 0;
        $hiddenEntryCount = 0;
        $systemEntryCount = 0;
        $volumeLabelEntryCount = 0;
        $directoryAttributeEntryCount = 0;
        $archiveEntryCount = 0;
        $hiddenSystemOrVolumeLabelEntries = [];
        $entries = [];

        foreach ($this->entries as $entry) {
            $hasReadOnly = $entry->hasDosReadOnlyAttribute();
            $hasHidden = $entry->hasDosHiddenAttribute();
            $hasSystem = $entry->hasDosSystemAttribute();
            $hasVolumeLabel = $entry->hasDosVolumeLabelAttribute();
            $hasDirectory = $entry->hasDosDirectoryAttribute();
            $hasArchive = $entry->hasDosArchiveAttribute();
            $dosAttributes = $entry->externalFileAttributes & 0xff;

            if ($dosAttributes !== 0) {
                $dosAttributeEntryCount++;
            }
            if ($hasReadOnly) {
                $readOnlyEntryCount++;
            }
            if ($hasHidden) {
                $hiddenEntryCount++;
            }
            if ($hasSystem) {
                $systemEntryCount++;
            }
            if ($hasVolumeLabel) {
                $volumeLabelEntryCount++;
            }
            if ($hasDirectory) {
                $directoryAttributeEntryCount++;
            }
            if ($hasArchive) {
                $archiveEntryCount++;
            }

            $summary = [
                'name' => $entry->name,
                'isDirectory' => $entry->isDirectory(),
                'dosAttributes' => $dosAttributes,
                'dosAttributeNames' => $entry->dosAttributeNames(),
                'hasReadOnlyAttribute' => $hasReadOnly,
                'hasHiddenAttribute' => $hasHidden,
                'hasSystemAttribute' => $hasSystem,
                'hasVolumeLabelAttribute' => $hasVolumeLabel,
                'hasDirectoryAttribute' => $hasDirectory,
                'hasArchiveAttribute' => $hasArchive,
                'externalAttributes' => $entry->externalFileAttributes,
            ];
            $entries[] = $summary;
            if ($hasHidden || $hasSystem || $hasVolumeLabel) {
                $hiddenSystemOrVolumeLabelEntries[] = $summary;
            }
        }

        return [
            'entryCount' => count($this->entries),
            'dosAttributeEntryCount' => $dosAttributeEntryCount,
            'readOnlyEntryCount' => $readOnlyEntryCount,
            'hiddenEntryCount' => $hiddenEntryCount,
            'systemEntryCount' => $systemEntryCount,
            'volumeLabelEntryCount' => $volumeLabelEntryCount,
            'directoryAttributeEntryCount' => $directoryAttributeEntryCount,
            'archiveEntryCount' => $archiveEntryCount,
            'hiddenSystemOrVolumeLabelEntryCount' => count($hiddenSystemOrVolumeLabelEntries),
            'hiddenSystemOrVolumeLabelEntries' => $hiddenSystemOrVolumeLabelEntries,
            'entries' => $entries,
        ];
    }

    /**
     * @return array{
     *     entryCount:int,
     *     dosAttributeEntryCount:int,
     *     readOnlyEntryCount:int,
     *     hiddenEntryCount:int,
     *     systemEntryCount:int,
     *     volumeLabelEntryCount:int,
     *     directoryAttributeEntryCount:int,
     *     archiveEntryCount:int,
     *     hiddenSystemOrVolumeLabelEntryCount:int,
     *     hiddenSystemOrVolumeLabelEntries:list<array{name:string, isDirectory:bool, dosAttributes:int, dosAttributeNames:list<string>, hasReadOnlyAttribute:bool, hasHiddenAttribute:bool, hasSystemAttribute:bool, hasVolumeLabelAttribute:bool, hasDirectoryAttribute:bool, hasArchiveAttribute:bool, externalAttributes:int}>,
     *     entries:list<array{name:string, isDirectory:bool, dosAttributes:int, dosAttributeNames:list<string>, hasReadOnlyAttribute:bool, hasHiddenAttribute:bool, hasSystemAttribute:bool, hasVolumeLabelAttribute:bool, hasDirectoryAttribute:bool, hasArchiveAttribute:bool, externalAttributes:int}>
     * }
     */
    public function assertNoHiddenSystemOrVolumeLabelEntries(): array
    {
        $summary = $this->dosAttributePreflight();
        if ($summary['hiddenSystemOrVolumeLabelEntryCount'] > 0) {
            $entries = implode(
                ', ',
                array_map(
                    static fn (array $entry): string => $entry['name'] . ' (' . implode('/', $entry['dosAttributeNames']) . ')',
                    $summary['hiddenSystemOrVolumeLabelEntries']
                )
            );

            throw new \RuntimeException(
                'ZIP package contains DOS hidden, system, or volume-label entries that require explicit import review: '
                . $entries
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

    /**
     * @param list<array{id:int, data:string}> $fields
     */
    private static function singleZip64ExtraFieldData(array $fields, string $label): ?string
    {
        $data = null;
        foreach ($fields as $field) {
            if ($field['id'] !== self::ZIP64_EXTENDED_INFORMATION_EXTRA_ID) {
                continue;
            }

            if ($data !== null) {
                throw new \RuntimeException("ZIP64 extra field for {$label} appears more than once");
            }

            $data = $field['data'];
        }

        return $data;
    }

    /**
     * @param list<string> $requiredFields
     * @return array{present:bool, requiredFields:list<string>, values:array<string, int>, parsedBytes:int, extraBytes:int, issues:list<string>}
     */
    private static function zip64ExtraFieldPlan(?string $data, array $requiredFields, string $label): array
    {
        $issues = [];
        if ($data === null) {
            if ($requiredFields !== []) {
                $issues[] = 'missing-zip64-extra-field';
            }

            return [
                'present' => false,
                'requiredFields' => $requiredFields,
                'values' => [],
                'parsedBytes' => 0,
                'extraBytes' => 0,
                'issues' => $issues,
            ];
        }

        if ($requiredFields === []) {
            $issues[] = 'zip64-extra-field-without-sentinel';
        }

        $values = [];
        $cursor = 0;
        foreach ($requiredFields as $field) {
            $width = $field === 'diskStart' ? 4 : 8;
            if ($cursor + $width > strlen($data)) {
                throw new \RuntimeException("ZIP64 extra field for {$label} is truncated before {$field}");
            }

            $values[$field] = $width === 8
                ? self::readUInt64($data, $cursor)
                : self::readUInt32($data, $cursor);
            $cursor += $width;
        }

        $extraBytes = strlen($data) - $cursor;
        if ($extraBytes > 0) {
            $issues[] = 'zip64-extra-field-trailing-bytes';
        }

        return [
            'present' => true,
            'requiredFields' => $requiredFields,
            'values' => $values,
            'parsedBytes' => $cursor,
            'extraBytes' => $extraBytes,
            'issues' => $issues,
        ];
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

        if ($entry->crc32 !== 0) {
            throw new \RuntimeException("ZIP package directory entry {$entry->name} must have a zero CRC32");
        }
    }

    private static function assertDirectoryAttributeConsistency(ZipPackageEntry $entry): void
    {
        $unixFileType = $entry->unixFileType();
        if ($entry->isDirectory()) {
            if ($unixFileType !== null && $unixFileType !== self::UNIX_DIRECTORY_TYPE) {
                throw new \RuntimeException(
                    "ZIP package directory entry {$entry->name} has Unix "
                    . ($entry->unixFileTypeName() ?? 'unknown')
                    . ' external attributes'
                );
            }

            return;
        }

        if (!$entry->isDirectory() && $entry->hasDosDirectoryAttribute()) {
            throw new \RuntimeException(
                "ZIP package entry {$entry->name} has directory external attributes but is not named as a directory"
            );
        }

        if ($unixFileType === self::UNIX_DIRECTORY_TYPE) {
            throw new \RuntimeException(
                "ZIP package entry {$entry->name} has Unix directory external attributes but is not named as a directory"
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
     * @param list<array<string, mixed>> $entries
     */
    private static function nextEntryOrCentralDirectoryOffsetForScannedEntries(
        array $entries,
        int $localHeaderOffset,
        int $centralDirectoryOffset
    ): int {
        $nextOffset = $centralDirectoryOffset;
        foreach ($entries as $candidate) {
            $candidateOffset = $candidate['localHeaderOffset'];
            if (!is_int($candidateOffset) || $candidateOffset <= $localHeaderOffset) {
                continue;
            }

            if ($candidateOffset < $nextOffset) {
                $nextOffset = $candidateOffset;
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
        self::rejectUnsupportedArchiveExtraDataRecord($bytes, $offset, $label);

        if (substr($bytes, $offset, 4) === self::CENTRAL_DIRECTORY_DIGITAL_SIGNATURE) {
            throw new \RuntimeException(
                'Malformed ZIP central-directory digital signature record'
            );
        }

        throw new \RuntimeException("Unexpected ZIP bytes {$label}");
    }

    private static function rejectUnsupportedArchiveExtraDataRecord(string $bytes, int $offset, string $label): void
    {
        $record = self::archiveExtraDataRecordAt($bytes, $offset);
        if ($record === null) {
            return;
        }

        throw new \RuntimeException(
            'ZIP archive extra data records are not supported by this bounded package reader '
            . "({$label}, {$record['dataLength']} bytes at offset {$record['offset']})"
        );
    }

    /**
     * @return array{offset:int, dataOffset:int, dataLength:int, endOffset:int}|null
     */
    private static function archiveExtraDataRecordAt(string $bytes, int $offset): ?array
    {
        if (substr($bytes, $offset, 4) !== self::ARCHIVE_EXTRA_DATA_RECORD_SIGNATURE) {
            return null;
        }

        self::assertRange($bytes, $offset, 8, 'ZIP archive extra data record');
        $dataLength = self::readUInt32($bytes, $offset + 4);
        $dataOffset = $offset + 8;
        self::assertRange($bytes, $dataOffset, $dataLength, 'ZIP archive extra data');

        return [
            'offset' => $offset,
            'dataOffset' => $dataOffset,
            'dataLength' => $dataLength,
            'endOffset' => $dataOffset + $dataLength,
        ];
    }

    /**
     * @param array{offset:int, dataOffset:int, dataLength:int, endOffset:int} $record
     * @return array{offset:int, dataOffset:int, dataLength:int, endOffset:int, location:string, issues:list<string>}
     */
    private static function archiveExtraDataRecordSummary(array $record, string $location, int $eocdOffset, int $centralDirectoryEnd): array
    {
        $issues = ['archive-extra-data-record'];
        if ($record['endOffset'] > $centralDirectoryEnd && $record['offset'] < $centralDirectoryEnd) {
            $issues[] = 'archive-extra-data-record-overlaps-central-directory-end';
        }
        if ($record['endOffset'] > $eocdOffset) {
            $issues[] = 'archive-extra-data-record-overlaps-eocd';
        }

        return [
            'offset' => $record['offset'],
            'dataOffset' => $record['dataOffset'],
            'dataLength' => $record['dataLength'],
            'endOffset' => $record['endOffset'],
            'location' => $location,
            'issues' => $issues,
        ];
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
     * @return array{name:string, rawName:string, nameEncoding:string, nameLength:int, extraFieldLength:int, localHeaderLength:int, versionNeededToExtract:int, generalPurposeFlags:int, compressionMethod:int, modifiedDosTime:int, modifiedDosDate:int, crc32:int, compressedSize:int, uncompressedSize:int}
     */
    private static function readLocalHeaderNameMetadata(string $bytes, int $localHeaderOffset, int $centralDirectoryIndex): array
    {
        self::assertRange($bytes, $localHeaderOffset, 30, 'local file header');
        if (substr($bytes, $localHeaderOffset, 4) !== self::LOCAL_FILE_SIGNATURE) {
            throw new \RuntimeException("Invalid ZIP local file header for central directory entry {$centralDirectoryIndex}");
        }

        $versionNeededToExtract = self::readUInt16($bytes, $localHeaderOffset + 4);
        $flags = self::readUInt16($bytes, $localHeaderOffset + 6);
        $method = self::readUInt16($bytes, $localHeaderOffset + 8);
        $modifiedTime = self::readUInt16($bytes, $localHeaderOffset + 10);
        $modifiedDate = self::readUInt16($bytes, $localHeaderOffset + 12);
        $crc32 = self::readUInt32($bytes, $localHeaderOffset + 14);
        $compressedSize = self::readUInt32($bytes, $localHeaderOffset + 18);
        $uncompressedSize = self::readUInt32($bytes, $localHeaderOffset + 22);
        $nameLength = self::readUInt16($bytes, $localHeaderOffset + 26);
        $extraLength = self::readUInt16($bytes, $localHeaderOffset + 28);
        $nameStart = $localHeaderOffset + 30;
        self::assertRange($bytes, $nameStart, $nameLength + $extraLength, 'local file header variable fields');

        $rawName = substr($bytes, $nameStart, $nameLength);
        $extraFieldData = substr($bytes, $nameStart + $nameLength, $extraLength);
        self::assertSafePartName($rawName);
        ZipPackageEntry::validateExtraFieldData(
            $extraFieldData,
            "local extra fields for central directory entry {$centralDirectoryIndex}"
        );
        $decodedName = self::decodeZipText(
            $rawName,
            $flags,
            $extraFieldData,
            self::INFOZIP_UNICODE_PATH_EXTRA_ID,
            'info-zip-unicode-path',
            "local file header entry {$centralDirectoryIndex} name"
        );
        self::assertSafePartName($decodedName['text']);

        return [
            'name' => $decodedName['text'],
            'rawName' => $rawName,
            'nameEncoding' => $decodedName['encoding'],
            'nameLength' => $nameLength,
            'extraFieldLength' => $extraLength,
            'localHeaderLength' => 30 + $nameLength + $extraLength,
            'versionNeededToExtract' => $versionNeededToExtract,
            'generalPurposeFlags' => $flags,
            'compressionMethod' => $method,
            'modifiedDosTime' => $modifiedTime,
            'modifiedDosDate' => $modifiedDate,
            'crc32' => $crc32,
            'compressedSize' => $compressedSize,
            'uncompressedSize' => $uncompressedSize,
        ];
    }

    /**
     * @return array{extraFieldData:string, dataStart:int, crc32:int, compressedSize:int, uncompressedSize:int, nameLength:int, extraFieldLength:int, localHeaderLength:int}
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
        $centralUnicodePath = self::unicodeTextFromExtraFieldData(
            $entry->centralExtraFieldData,
            self::INFOZIP_UNICODE_PATH_EXTRA_ID,
            $entry->rawName,
            "central extra fields for {$entry->name}",
        );
        if ($centralUnicodePath !== null && $localUnicodePath === null && $localName !== $entry->name) {
            throw new \RuntimeException("ZIP local header Unicode path metadata is missing for central directory entry {$entry->name}");
        }
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
            'nameLength' => $nameLength,
            'extraFieldLength' => $extraLength,
            'localHeaderLength' => 30 + $nameLength + $extraLength,
        ];
    }

    /**
     * @return array{hasSignature:bool, descriptorOffset:int, valueOffset:int, descriptorLength:int, crc32:int, crc32Hex:string, usesZip64SizedDescriptor:bool}
     */
    private function dataDescriptorMetadata(ZipPackageEntry $entry, int $offset, ?int $nextOffset = null): array
    {
        $hasSignatureMarker = substr($this->bytes, $offset, 4) === "PK\x07\x08";

        if ($nextOffset !== null) {
            $descriptorSpan = $nextOffset - $offset;
            if ($descriptorSpan === 20) {
                $this->rejectMatchedZip64SizedDataDescriptor($entry, $offset);
            } elseif ($descriptorSpan === 24 && $hasSignatureMarker) {
                $this->rejectMatchedZip64SizedDataDescriptor($entry, $offset + 4);
            }
        }

        if ($hasSignatureMarker) {
            $signedValues = $this->matchingStandardDataDescriptorValues($entry, $offset + 4);
            if ($signedValues !== null) {
                return self::standardDataDescriptorSummary($offset, $offset + 4, 16, $signedValues, true);
            }
        }

        $unsignedValues = $this->matchingStandardDataDescriptorValues($entry, $offset);
        if ($unsignedValues !== null) {
            return self::standardDataDescriptorSummary($offset, $offset, 12, $unsignedValues, false);
        }

        $valuesOffset = $hasSignatureMarker ? $offset + 4 : $offset;
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
            'hasSignature' => $hasSignatureMarker,
            'descriptorOffset' => $offset,
            'valueOffset' => $valuesOffset,
            'descriptorLength' => ($hasSignatureMarker ? 16 : 12),
            'crc32' => $crc32,
            'crc32Hex' => sprintf('%08x', $crc32),
            'usesZip64SizedDescriptor' => false,
        ];
    }

    private function rejectMatchedZip64SizedDataDescriptor(ZipPackageEntry $entry, int $valuesOffset): void
    {
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

    /**
     * @return array{crc32:int, compressedSize:int, uncompressedSize:int}|null
     */
    private function matchingStandardDataDescriptorValues(ZipPackageEntry $entry, int $valuesOffset): ?array
    {
        self::assertRange($this->bytes, $valuesOffset, 12, "data descriptor for {$entry->name}");
        if ($valuesOffset + 12 > $this->centralDirectoryOffset) {
            return null;
        }

        $crc32 = self::readUInt32($this->bytes, $valuesOffset);
        $compressedSize = self::readUInt32($this->bytes, $valuesOffset + 4);
        $uncompressedSize = self::readUInt32($this->bytes, $valuesOffset + 8);

        if (
            $crc32 !== $entry->crc32
            || $compressedSize !== $entry->compressedSize
            || $uncompressedSize !== $entry->uncompressedSize
        ) {
            return null;
        }

        return [
            'crc32' => $crc32,
            'compressedSize' => $compressedSize,
            'uncompressedSize' => $uncompressedSize,
        ];
    }

    /**
     * @param array{crc32:int, compressedSize:int, uncompressedSize:int} $values
     * @return array{hasSignature:bool, descriptorOffset:int, valueOffset:int, descriptorLength:int, crc32:int, crc32Hex:string, usesZip64SizedDescriptor:bool}
     */
    private static function standardDataDescriptorSummary(
        int $descriptorOffset,
        int $valueOffset,
        int $descriptorLength,
        array $values,
        bool $hasSignature
    ): array {
        return [
            'hasSignature' => $hasSignature,
            'descriptorOffset' => $descriptorOffset,
            'valueOffset' => $valueOffset,
            'descriptorLength' => $descriptorLength,
            'crc32' => $values['crc32'],
            'crc32Hex' => sprintf('%08x', $values['crc32']),
            'usesZip64SizedDescriptor' => false,
        ];
    }

    /**
     * @return array{hasSignature:?bool, descriptorOffset:int, valueOffset:?int, descriptorLength:?int, crc32:?int, crc32Hex:?string, compressedSize:?int, uncompressedSize:?int, usesZip64SizedDescriptor:bool, descriptorValuesMatchCentral:?bool, issues:list<string>}
     */
    private static function dataDescriptorIntegritySummaryFromBytes(
        string $bytes,
        string $entryName,
        int $descriptorOffset,
        int $nextOffset,
        int $centralDirectoryOffset,
        int $centralCrc32,
        int $centralCompressedSize,
        int $centralUncompressedSize
    ): array {
        $descriptorSpan = $nextOffset - $descriptorOffset;
        $hasSignatureMarker = substr($bytes, $descriptorOffset, 4) === "PK\x07\x08";

        if ($descriptorSpan === 20 && !$hasSignatureMarker) {
            $values = self::zip64DataDescriptorValuesFromBytes($bytes, $descriptorOffset, $centralDirectoryOffset);

            return self::dataDescriptorIntegrityResult(
                $entryName,
                $descriptorOffset,
                $descriptorOffset,
                20,
                false,
                $values,
                true,
                $centralCrc32,
                $centralCompressedSize,
                $centralUncompressedSize,
                ['zip64-sized-data-descriptor']
            );
        }

        if ($descriptorSpan === 24 && $hasSignatureMarker) {
            $values = self::zip64DataDescriptorValuesFromBytes($bytes, $descriptorOffset + 4, $centralDirectoryOffset);

            return self::dataDescriptorIntegrityResult(
                $entryName,
                $descriptorOffset,
                $descriptorOffset + 4,
                24,
                true,
                $values,
                true,
                $centralCrc32,
                $centralCompressedSize,
                $centralUncompressedSize,
                ['zip64-sized-data-descriptor']
            );
        }

        $signedValues = $hasSignatureMarker
            ? self::standardDataDescriptorValuesFromBytes($bytes, $descriptorOffset + 4, $centralDirectoryOffset)
            : null;
        $unsignedValues = self::standardDataDescriptorValuesFromBytes($bytes, $descriptorOffset, $centralDirectoryOffset);
        $signedMatches = self::dataDescriptorValuesMatchCentral(
            $signedValues,
            $centralCrc32,
            $centralCompressedSize,
            $centralUncompressedSize
        );
        $unsignedMatches = self::dataDescriptorValuesMatchCentral(
            $unsignedValues,
            $centralCrc32,
            $centralCompressedSize,
            $centralUncompressedSize
        );

        if ($signedMatches) {
            $valueOffset = $descriptorOffset + 4;
            $descriptorLength = 16;
            $hasSignature = true;
            $values = $signedValues;
        } elseif ($unsignedMatches) {
            $valueOffset = $descriptorOffset;
            $descriptorLength = 12;
            $hasSignature = false;
            $values = $unsignedValues;
        } elseif ($hasSignatureMarker && $signedValues !== null) {
            $valueOffset = $descriptorOffset + 4;
            $descriptorLength = 16;
            $hasSignature = true;
            $values = $signedValues;
        } elseif ($unsignedValues !== null) {
            $valueOffset = $descriptorOffset;
            $descriptorLength = 12;
            $hasSignature = false;
            $values = $unsignedValues;
        } else {
            return [
                'hasSignature' => $hasSignatureMarker,
                'descriptorOffset' => $descriptorOffset,
                'valueOffset' => $hasSignatureMarker ? $descriptorOffset + 4 : $descriptorOffset,
                'descriptorLength' => null,
                'crc32' => null,
                'crc32Hex' => null,
                'compressedSize' => null,
                'uncompressedSize' => null,
                'usesZip64SizedDescriptor' => false,
                'descriptorValuesMatchCentral' => null,
                'issues' => ['data-descriptor-truncated'],
            ];
        }

        $extraIssues = [];
        if ($descriptorSpan !== $descriptorLength) {
            $extraIssues[] = 'data-descriptor-length-mismatch';
        }

        return self::dataDescriptorIntegrityResult(
            $entryName,
            $descriptorOffset,
            $valueOffset,
            $descriptorLength,
            $hasSignature,
            $values,
            false,
            $centralCrc32,
            $centralCompressedSize,
            $centralUncompressedSize,
            $extraIssues
        );
    }

    /**
     * @param array{crc32:int, compressedSize:int, uncompressedSize:int}|null $values
     * @param list<string> $extraIssues
     * @return array{hasSignature:bool, descriptorOffset:int, valueOffset:int, descriptorLength:int, crc32:?int, crc32Hex:?string, compressedSize:?int, uncompressedSize:?int, usesZip64SizedDescriptor:bool, descriptorValuesMatchCentral:?bool, issues:list<string>}
     */
    private static function dataDescriptorIntegrityResult(
        string $entryName,
        int $descriptorOffset,
        int $valueOffset,
        int $descriptorLength,
        bool $hasSignature,
        ?array $values,
        bool $usesZip64SizedDescriptor,
        int $centralCrc32,
        int $centralCompressedSize,
        int $centralUncompressedSize,
        array $extraIssues = []
    ): array {
        $issues = $extraIssues;
        $descriptorValuesMatchCentral = null;
        $crc32 = null;
        $compressedSize = null;
        $uncompressedSize = null;

        if ($values === null) {
            $issues[] = $usesZip64SizedDescriptor
                ? 'zip64-sized-data-descriptor-truncated'
                : 'data-descriptor-truncated';
        } else {
            $crc32 = $values['crc32'];
            $compressedSize = $values['compressedSize'];
            $uncompressedSize = $values['uncompressedSize'];
            $descriptorValuesMatchCentral = self::dataDescriptorValuesMatchCentral(
                $values,
                $centralCrc32,
                $centralCompressedSize,
                $centralUncompressedSize
            );

            if ($crc32 !== $centralCrc32) {
                $issues[] = 'data-descriptor-crc32-mismatch';
            }
            if ($compressedSize !== $centralCompressedSize || $uncompressedSize !== $centralUncompressedSize) {
                $issues[] = 'data-descriptor-size-mismatch';
            }
        }

        return [
            'hasSignature' => $hasSignature,
            'descriptorOffset' => $descriptorOffset,
            'valueOffset' => $valueOffset,
            'descriptorLength' => $descriptorLength,
            'crc32' => $crc32,
            'crc32Hex' => $crc32 === null ? null : sprintf('%08x', $crc32),
            'compressedSize' => $compressedSize,
            'uncompressedSize' => $uncompressedSize,
            'usesZip64SizedDescriptor' => $usesZip64SizedDescriptor,
            'descriptorValuesMatchCentral' => $descriptorValuesMatchCentral,
            'issues' => array_values(array_unique($issues)),
        ];
    }

    /**
     * @return array{crc32:int, compressedSize:int, uncompressedSize:int}|null
     */
    private static function standardDataDescriptorValuesFromBytes(
        string $bytes,
        int $valuesOffset,
        int $centralDirectoryOffset
    ): ?array {
        if (
            !self::isRangeAvailable($bytes, $valuesOffset, 12)
            || $valuesOffset + 12 > $centralDirectoryOffset
        ) {
            return null;
        }

        return [
            'crc32' => self::readUInt32($bytes, $valuesOffset),
            'compressedSize' => self::readUInt32($bytes, $valuesOffset + 4),
            'uncompressedSize' => self::readUInt32($bytes, $valuesOffset + 8),
        ];
    }

    /**
     * @return array{crc32:int, compressedSize:int, uncompressedSize:int}|null
     */
    private static function zip64DataDescriptorValuesFromBytes(
        string $bytes,
        int $valuesOffset,
        int $centralDirectoryOffset
    ): ?array {
        if (
            !self::isRangeAvailable($bytes, $valuesOffset, 20)
            || $valuesOffset + 20 > $centralDirectoryOffset
        ) {
            return null;
        }

        return [
            'crc32' => self::readUInt32($bytes, $valuesOffset),
            'compressedSize' => self::readUInt64($bytes, $valuesOffset + 4),
            'uncompressedSize' => self::readUInt64($bytes, $valuesOffset + 12),
        ];
    }

    /**
     * @param array{crc32:int, compressedSize:int, uncompressedSize:int}|null $values
     */
    private static function dataDescriptorValuesMatchCentral(
        ?array $values,
        int $centralCrc32,
        int $centralCompressedSize,
        int $centralUncompressedSize
    ): bool {
        return $values !== null
            && $values['crc32'] === $centralCrc32
            && $values['compressedSize'] === $centralCompressedSize
            && $values['uncompressedSize'] === $centralUncompressedSize;
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

    /**
     * @return list<string>
     */
    private static function generalPurposeFlagNames(int $flags): array
    {
        $names = [];
        $deflateOptionName = self::deflateOptionFlagName($flags & self::DEFLATE_OPTION_GENERAL_PURPOSE_FLAGS);
        if ($deflateOptionName !== null) {
            $names[] = $deflateOptionName;
        }

        if (($flags & 0x0008) !== 0) {
            $names[] = 'data-descriptor';
        }

        if (($flags & self::UTF8_GENERAL_PURPOSE_FLAG) !== 0) {
            $names[] = 'utf-8-names';
        }

        $unsupportedFlags = $flags & ~self::SUPPORTED_GENERAL_PURPOSE_FLAGS;
        if ($unsupportedFlags !== 0) {
            $names[] = sprintf('unsupported-0x%04x', $unsupportedFlags);
        }

        return $names;
    }

    /**
     * @return array{
     *     generalPurposeFlags:int,
     *     compressionMethod:int,
     *     rawName:string,
     *     extraFieldData:string,
     *     dataOffset:int
     * }
     */
    private static function localHeaderMetadataForPolicy(string $bytes, int $offset, int $centralDirectoryIndex): array
    {
        if (substr($bytes, $offset, 4) !== self::LOCAL_FILE_SIGNATURE) {
            throw new \RuntimeException("Invalid ZIP local file header for central directory entry {$centralDirectoryIndex}");
        }

        self::assertRange($bytes, $offset, 30, 'local file header');
        $flags = self::readUInt16($bytes, $offset + 6);
        $method = self::readUInt16($bytes, $offset + 8);
        $nameLength = self::readUInt16($bytes, $offset + 26);
        $extraLength = self::readUInt16($bytes, $offset + 28);
        $variableStart = $offset + 30;
        self::assertRange($bytes, $variableStart, $nameLength + $extraLength, 'local file header variable fields');
        $rawName = substr($bytes, $variableStart, $nameLength);
        self::assertSafePartName($rawName);

        return [
            'generalPurposeFlags' => $flags,
            'compressionMethod' => $method,
            'rawName' => $rawName,
            'extraFieldData' => substr($bytes, $variableStart + $nameLength, $extraLength),
            'dataOffset' => $variableStart + $nameLength + $extraLength,
        ];
    }

    /**
     * @return array{text:string, encoding:string}
     */
    private static function decodeZipNameForPolicy(string $rawName, int $flags, string $label): array
    {
        self::assertSafePartName($rawName);
        if (($flags & self::UTF8_GENERAL_PURPOSE_FLAG) !== 0) {
            self::assertUtf8($rawName, "ZIP {$label}");
            $name = $rawName;
            $encoding = 'utf-8';
        } else {
            $name = self::decodeCp437($rawName);
            $encoding = 'cp437';
        }

        self::assertSafePartName($name);

        return [
            'text' => $name,
            'encoding' => $encoding,
        ];
    }

    /**
     * @return list<int>
     */
    private static function extraFieldIdsForPolicy(string $extraFieldData, string $label): array
    {
        $ids = [];
        $cursor = 0;
        $length = strlen($extraFieldData);
        while ($cursor < $length) {
            if ($cursor + 4 > $length) {
                throw new \RuntimeException("ZIP {$label} contains a truncated extra field header");
            }

            $id = self::readUInt16($extraFieldData, $cursor);
            $fieldLength = self::readUInt16($extraFieldData, $cursor + 2);
            $dataStart = $cursor + 4;
            if ($dataStart + $fieldLength > $length) {
                throw new \RuntimeException("ZIP {$label} contains a truncated extra field payload");
            }

            $ids[] = $id;
            $cursor = $dataStart + $fieldLength;
        }

        return $ids;
    }

    private static function deflateOptionFlagName(int $flags): ?string
    {
        return match ($flags) {
            0x0000 => null,
            0x0002 => 'deflate-maximum-compression',
            0x0004 => 'deflate-fast',
            0x0006 => 'deflate-super-fast',
            default => sprintf('deflate-options-0x%04x', $flags),
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
        $name = self::normalizeZipEntryNameForCollisionKey($name);
        if (function_exists('mb_strtolower')) {
            $name = mb_strtolower($name, 'UTF-8');
        } else {
            $name = strtr(strtolower($name), self::BOUNDED_UNICODE_CASE_FOLD_FALLBACKS);
        }

        return self::normalizeZipEntryNameForCollisionKey($name);
    }

    private static function normalizeZipEntryNameForCollisionKey(string $name): string
    {
        if (class_exists(\Normalizer::class)) {
            $normalized = \Normalizer::normalize($name, \Normalizer::FORM_C);
            if (is_string($normalized)) {
                return $normalized;
            }
        }

        return strtr($name, self::BOUNDED_LATIN_COMPOSITION_FALLBACKS);
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
        $hasUnicodeText = false;
        $unicodeText = null;

        foreach (ZipPackageEntry::extraFieldsFromData($extraFieldData, $label) as $field) {
            if ($field['id'] !== $id) {
                continue;
            }

            if ($hasUnicodeText) {
                throw new \RuntimeException("ZIP Unicode extra field for {$label} appears more than once");
            }
            $hasUnicodeText = true;

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

            $unicodeText = $text;
        }

        return $hasUnicodeText ? $unicodeText : null;
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
            preg_match('/[\x00-\x1f\x7f]/', $name) === 1
            || (preg_match('//u', $name) === 1 && preg_match('/\p{Cc}/u', $name) === 1)
        ) {
            throw new \RuntimeException('Unsafe ZIP package entry name contains control characters');
        }

        if (
            str_starts_with($name, '/')
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

    private static function isRangeAvailable(string $bytes, int $offset, int $length): bool
    {
        return $offset >= 0
            && $length >= 0
            && $offset <= strlen($bytes)
            && $offset + $length <= strlen($bytes);
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
        return self::unixFileTypeFromExternalAttributes($externalAttributes) === self::UNIX_SYMLINK_TYPE;
    }

    private static function isUnixSpecialFileExternalAttributes(int $externalAttributes): bool
    {
        $type = self::unixFileTypeFromExternalAttributes($externalAttributes);

        return $type === self::UNIX_FIFO_TYPE
            || $type === self::UNIX_CHARACTER_DEVICE_TYPE
            || $type === self::UNIX_BLOCK_DEVICE_TYPE
            || $type === self::UNIX_SOCKET_TYPE
            || (
                $type !== null
                && $type !== self::UNIX_DIRECTORY_TYPE
                && $type !== self::UNIX_REGULAR_FILE_TYPE
                && $type !== self::UNIX_SYMLINK_TYPE
            );
    }

    private static function assertUnixFileTypeMatchesEntryName(string $name, int $externalAttributes): void
    {
        $type = self::unixFileTypeFromExternalAttributes($externalAttributes);
        if (str_ends_with($name, '/')) {
            if ($type !== null && $type !== self::UNIX_DIRECTORY_TYPE) {
                throw new \RuntimeException(
                    "ZIP package directory entry {$name} has Unix "
                    . self::unixFileTypeName($type)
                    . ' external attributes'
                );
            }

            return;
        }

        if ($type === self::UNIX_DIRECTORY_TYPE) {
            throw new \RuntimeException(
                "ZIP package entry {$name} has Unix directory external attributes but is not named as a directory"
            );
        }
    }

    private static function unixFileTypeFromExternalAttributes(int $externalAttributes): ?int
    {
        $mode = ($externalAttributes >> 16) & 0xffff;
        $type = $mode & self::UNIX_FILE_TYPE_MASK;

        return $type === 0 ? null : $type;
    }

    private static function unixFileTypeName(int $type): string
    {
        return match ($type) {
            self::UNIX_FIFO_TYPE => 'fifo',
            self::UNIX_CHARACTER_DEVICE_TYPE => 'character-device',
            self::UNIX_DIRECTORY_TYPE => 'directory',
            self::UNIX_BLOCK_DEVICE_TYPE => 'block-device',
            self::UNIX_REGULAR_FILE_TYPE => 'regular-file',
            self::UNIX_SYMLINK_TYPE => 'symlink',
            self::UNIX_SOCKET_TYPE => 'socket',
            default => 'unknown',
        };
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
