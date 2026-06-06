<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ArchiveCompressionStream
{
    public const FORMAT_TAR = 'tar';
    public const FORMAT_GZIP_TAR = 'gzip-tar';
    public const FORMAT_ZLIB_TAR = 'zlib-tar';
    public const FORMAT_RAW_DEFLATE_TAR = 'raw-deflate-tar';
    public const FORMAT_LZ4_TAR = 'lz4-tar';
    public const FORMAT_ZIP = 'zip';
    public const FORMAT_GZIP_ZIP = 'gzip-zip';
    public const FORMAT_ZLIB_ZIP = 'zlib-zip';
    public const FORMAT_RAW_DEFLATE_ZIP = 'raw-deflate-zip';
    public const FORMAT_LZ4_ZIP = 'lz4-zip';
    public const PACKAGE_KIND_TAR = 'tar';
    public const PACKAGE_KIND_ZIP = 'zip';

    /**
     * @return list<string>
     */
    public static function supportedTarFormats(): array
    {
        return [
            self::FORMAT_TAR,
            self::FORMAT_GZIP_TAR,
            self::FORMAT_ZLIB_TAR,
            self::FORMAT_RAW_DEFLATE_TAR,
            self::FORMAT_LZ4_TAR,
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportedZipFormats(): array
    {
        return [
            self::FORMAT_ZIP,
            self::FORMAT_GZIP_ZIP,
            self::FORMAT_ZLIB_ZIP,
            self::FORMAT_RAW_DEFLATE_ZIP,
            self::FORMAT_LZ4_ZIP,
        ];
    }

    public static function openTar(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): TarArchive {
        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);

        return TarArchive::fromString($tarBytes, $maxUnpackedBytes);
    }

    public static function openTarAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): TarArchive {
        return self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes)['archive'];
    }

    public static function detectTarFormat(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): string {
        return self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes)['format'];
    }

    public static function decodeTarBytesAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): string {
        return self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes)['tarBytes'];
    }

    public static function openZip(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): ZipPackage {
        return ZipPackage::fromString(self::decodeZipBytes($bytes, $format, $maxUncompressedBytes));
    }

    public static function openZipAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null
    ): ZipPackage {
        return self::detectZipCandidate($bytes, $maxUncompressedBytes)['package'];
    }

    public static function detectZipFormat(
        string $bytes,
        ?int $maxUncompressedBytes = null
    ): string {
        return self::detectZipCandidate($bytes, $maxUncompressedBytes)['format'];
    }

    public static function decodeZipBytesAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null
    ): string {
        return self::detectZipCandidate($bytes, $maxUncompressedBytes)['zipBytes'];
    }

    public static function detectPackageKindAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): string {
        return self::detectPackageCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes)['kind'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function inspectPackageStreamAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        $candidate = self::detectPackageCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);

        if ($candidate['kind'] === self::PACKAGE_KIND_TAR) {
            return [
                'kind' => self::PACKAGE_KIND_TAR,
            ] + self::tarStreamInspection(
                $bytes,
                $candidate['format'],
                $candidate['tarBytes'],
                $candidate['archive'],
                $maxUncompressedBytes
            );
        }

        return [
            'kind' => self::PACKAGE_KIND_ZIP,
        ] + self::zipStreamInspection(
            $bytes,
            $candidate['format'],
            $candidate['zipBytes'],
            $candidate['package'],
            $maxUncompressedBytes
        );
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     archive:TarArchive,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     uncompressedSize:int,
     *     unpackedSize:int,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarStream(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');

        $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
        $archive = TarArchive::fromString($tarBytes, $maxUnpackedBytes);

        return self::tarStreamInspection($bytes, $format, $tarBytes, $archive, $maxUncompressedBytes);
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     archive:TarArchive,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     uncompressedSize:int,
     *     unpackedSize:int,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectTarStreamAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null,
        ?int $maxUnpackedBytes = null
    ): array {
        $candidate = self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);

        return self::tarStreamInspection(
            $bytes,
            $candidate['format'],
            $candidate['tarBytes'],
            $candidate['archive'],
            $maxUncompressedBytes
        );
    }

    /**
     * @return array{
     *     format:string,
     *     zipBytes:string,
     *     package:ZipPackage,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     packageByteSize:int,
     *     entryUncompressedSize:int,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectZipStream(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
        $package = ZipPackage::fromString($zipBytes);

        return self::zipStreamInspection($bytes, $format, $zipBytes, $package, $maxUncompressedBytes);
    }

    /**
     * @return array{
     *     format:string,
     *     zipBytes:string,
     *     package:ZipPackage,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     packageByteSize:int,
     *     entryUncompressedSize:int,
     *     stream:array<string, mixed>
     * }
     */
    public static function inspectZipStreamAuto(
        string $bytes,
        ?int $maxUncompressedBytes = null
    ): array {
        $candidate = self::detectZipCandidate($bytes, $maxUncompressedBytes);

        return self::zipStreamInspection(
            $bytes,
            $candidate['format'],
            $candidate['zipBytes'],
            $candidate['package'],
            $maxUncompressedBytes
        );
    }

    public static function decodeTarBytes(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        return match ($format) {
            self::FORMAT_TAR => self::boundedPlainBytes($bytes, $maxUncompressedBytes, 'Plain TAR stream'),
            self::FORMAT_GZIP_TAR => GzipStream::decode($bytes, $maxUncompressedBytes),
            self::FORMAT_ZLIB_TAR => DeflateStream::decode($bytes, DeflateStream::FORMAT_ZLIB, $maxUncompressedBytes),
            self::FORMAT_RAW_DEFLATE_TAR => DeflateStream::decode($bytes, DeflateStream::FORMAT_RAW, $maxUncompressedBytes),
            self::FORMAT_LZ4_TAR => Lz4Frame::decode($bytes, $maxUncompressedBytes),
            default => throw new \RuntimeException("Unsupported archive compression stream format: {$format}"),
        };
    }

    public static function decodeZipBytes(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        return match ($format) {
            self::FORMAT_ZIP => self::boundedPlainBytes($bytes, $maxUncompressedBytes, 'Plain ZIP package stream'),
            self::FORMAT_GZIP_ZIP => GzipStream::decode($bytes, $maxUncompressedBytes),
            self::FORMAT_ZLIB_ZIP => DeflateStream::decode($bytes, DeflateStream::FORMAT_ZLIB, $maxUncompressedBytes),
            self::FORMAT_RAW_DEFLATE_ZIP => DeflateStream::decode($bytes, DeflateStream::FORMAT_RAW, $maxUncompressedBytes),
            self::FORMAT_LZ4_ZIP => Lz4Frame::decode($bytes, $maxUncompressedBytes),
            default => throw new \RuntimeException("Unsupported archive compression stream format: {$format}"),
        };
    }

    private static function boundedPlainBytes(string $bytes, ?int $maxUncompressedBytes, string $label): string
    {
        if ($maxUncompressedBytes !== null && strlen($bytes) > $maxUncompressedBytes) {
            throw new \RuntimeException("{$label} exceeds the configured uncompressed byte limit");
        }

        return $bytes;
    }

    /**
     * @return array{format:string, tarBytes:string, archive:TarArchive}
     */
    private static function detectTarCandidate(
        string $bytes,
        ?int $maxUncompressedBytes,
        ?int $maxUnpackedBytes
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');
        self::assertLimit($maxUnpackedBytes, 'archive stream max unpacked byte limit');

        $matches = [];
        $errors = [];
        foreach (self::candidateTarFormats($bytes) as $format) {
            try {
                $tarBytes = self::decodeTarBytes($bytes, $format, $maxUncompressedBytes);
                $matches[] = [
                    'format' => $format,
                    'tarBytes' => $tarBytes,
                    'archive' => TarArchive::fromString($tarBytes, $maxUnpackedBytes),
                ];
            } catch (\RuntimeException $exception) {
                $errors[$format] = $exception->getMessage();
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            $formats = implode(', ', array_map(
                static fn (array $match): string => $match['format'],
                $matches
            ));

            throw new \RuntimeException("Ambiguous archive compression stream format; matched TAR candidates: {$formats}");
        }

        $details = self::formatDetectionDetails($errors);
        throw new \RuntimeException('Unable to detect archive compression stream format as TAR' . ($details === '' ? '' : ": {$details}"));
    }

    /**
     * @return array{format:string, zipBytes:string, package:ZipPackage}
     */
    private static function detectZipCandidate(
        string $bytes,
        ?int $maxUncompressedBytes
    ): array {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        $matches = [];
        $errors = [];
        foreach (self::candidateZipFormats($bytes) as $format) {
            try {
                $zipBytes = self::decodeZipBytes($bytes, $format, $maxUncompressedBytes);
                $matches[] = [
                    'format' => $format,
                    'zipBytes' => $zipBytes,
                    'package' => ZipPackage::fromString($zipBytes),
                ];
            } catch (\RuntimeException $exception) {
                $errors[$format] = $exception->getMessage();
            }
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            $formats = implode(', ', array_map(
                static fn (array $match): string => $match['format'],
                $matches
            ));

            throw new \RuntimeException("Ambiguous archive compression stream format; matched ZIP candidates: {$formats}");
        }

        $details = self::formatDetectionDetails($errors);
        throw new \RuntimeException('Unable to detect archive compression stream format as ZIP' . ($details === '' ? '' : ": {$details}"));
    }

    /**
     * @return array<string, mixed>
     */
    private static function detectPackageCandidate(
        string $bytes,
        ?int $maxUncompressedBytes,
        ?int $maxUnpackedBytes
    ): array {
        $matches = [];
        $errors = [];

        try {
            $tar = self::detectTarCandidate($bytes, $maxUncompressedBytes, $maxUnpackedBytes);
            $matches[] = [
                'kind' => self::PACKAGE_KIND_TAR,
            ] + $tar;
        } catch (\RuntimeException $exception) {
            $errors[self::PACKAGE_KIND_TAR] = $exception->getMessage();
        }

        try {
            $zip = self::detectZipCandidate($bytes, $maxUncompressedBytes);
            $matches[] = [
                'kind' => self::PACKAGE_KIND_ZIP,
            ] + $zip;
        } catch (\RuntimeException $exception) {
            $errors[self::PACKAGE_KIND_ZIP] = $exception->getMessage();
        }

        if (count($matches) === 1) {
            return $matches[0];
        }

        if (count($matches) > 1) {
            $descriptions = implode(', ', array_map(
                static fn (array $match): string => $match['kind'] . '/' . $match['format'],
                $matches
            ));

            throw new \RuntimeException("Ambiguous archive package stream; matched candidates: {$descriptions}");
        }

        $details = self::formatDetectionDetails($errors);
        throw new \RuntimeException('Unable to detect archive package stream as TAR or ZIP' . ($details === '' ? '' : ": {$details}"));
    }

    /**
     * @return array{
     *     format:string,
     *     tarBytes:string,
     *     archive:TarArchive,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     uncompressedSize:int,
     *     unpackedSize:int,
     *     stream:array<string, mixed>
     * }
     */
    private static function tarStreamInspection(
        string $bytes,
        string $format,
        string $tarBytes,
        TarArchive $archive,
        ?int $maxUncompressedBytes
    ): array {
        $entryNames = $archive->names();

        return [
            'format' => $format,
            'tarBytes' => $tarBytes,
            'archive' => $archive,
            'entryNames' => $entryNames,
            'entryCount' => count($entryNames),
            'uncompressedSize' => strlen($tarBytes),
            'unpackedSize' => self::archiveUnpackedSize($archive),
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    /**
     * @return array{
     *     format:string,
     *     zipBytes:string,
     *     package:ZipPackage,
     *     entryNames:list<string>,
     *     entryCount:int,
     *     packageByteSize:int,
     *     entryUncompressedSize:int,
     *     stream:array<string, mixed>
     * }
     */
    private static function zipStreamInspection(
        string $bytes,
        string $format,
        string $zipBytes,
        ZipPackage $package,
        ?int $maxUncompressedBytes
    ): array {
        $entryNames = $package->names();

        return [
            'format' => $format,
            'zipBytes' => $zipBytes,
            'package' => $package,
            'entryNames' => $entryNames,
            'entryCount' => count($entryNames),
            'packageByteSize' => strlen($zipBytes),
            'entryUncompressedSize' => self::zipPackageUncompressedSize($package),
            'stream' => self::streamInspection($bytes, $format, $maxUncompressedBytes),
        ];
    }

    private static function archiveUnpackedSize(TarArchive $archive): int
    {
        $size = 0;
        foreach ($archive->entries() as $entry) {
            if ($entry->isRegularFile()) {
                $size += $entry->size;
            }
        }

        return $size;
    }

    private static function zipPackageUncompressedSize(ZipPackage $package): int
    {
        $size = 0;
        foreach ($package->entries() as $entry) {
            if (!$entry->isDirectory()) {
                $size += $entry->uncompressedSize;
            }
        }

        return $size;
    }

    /**
     * @return array<string, mixed>
     */
    private static function streamInspection(string $bytes, string $format, ?int $maxUncompressedBytes): array
    {
        return match ($format) {
            self::FORMAT_TAR => [
                'type' => 'plain-tar',
                'compressedSize' => strlen($bytes),
                'memberCount' => 0,
                'frameCount' => 0,
            ],
            self::FORMAT_ZIP => [
                'type' => 'plain-zip',
                'compressedSize' => strlen($bytes),
                'memberCount' => 0,
                'frameCount' => 0,
            ],
            self::FORMAT_GZIP_TAR, self::FORMAT_GZIP_ZIP => self::gzipStreamInspection($bytes, $maxUncompressedBytes),
            self::FORMAT_ZLIB_TAR, self::FORMAT_ZLIB_ZIP => self::zlibStreamInspection($bytes, $maxUncompressedBytes),
            self::FORMAT_RAW_DEFLATE_TAR, self::FORMAT_RAW_DEFLATE_ZIP => self::rawDeflateStreamInspection($bytes, $maxUncompressedBytes),
            self::FORMAT_LZ4_TAR, self::FORMAT_LZ4_ZIP => self::lz4StreamInspection($bytes, $maxUncompressedBytes),
            default => throw new \RuntimeException("Unsupported archive compression stream format: {$format}"),
        };
    }

    /**
     * @return array{
     *     type:string,
     *     memberCount:int,
     *     compressedSize:int,
     *     uncompressedSize:int,
     *     trailingPaddingBytes:int,
     *     members:list<array{
     *         filename:?string,
     *         filenameText:?string,
     *         filenameEncoding:?string,
     *         comment:?string,
     *         commentText:?string,
     *         commentEncoding:?string,
     *         modifiedAt:int,
     *         modifiedAtKnown:bool,
     *         modifiedAtText:?string,
     *         flags:int,
     *         textHint:bool,
     *         extraFlags:int,
     *         extraFlagsMeaning:string,
     *         operatingSystem:int,
     *         operatingSystemName:string,
     *         extraFieldData:?string,
     *         extraFields:list<array{identifier:string,id1:int,id2:int,length:int,data:string}>,
     *         crc32:int,
     *         uncompressedSize:int,
     *         compressedSize:int,
     *         memberSize:int,
     *         extraFieldCount:int,
     *         headerCrc16:?int
     *     }>
     * }
     */
    private static function gzipStreamInspection(string $bytes, ?int $maxUncompressedBytes): array
    {
        $inspection = GzipStream::inspect($bytes, $maxUncompressedBytes);
        $members = array_map(
            static fn (array $member): array => [
                'filename' => $member['filename'],
                'filenameText' => $member['filenameText'],
                'filenameEncoding' => $member['filenameEncoding'],
                'comment' => $member['comment'],
                'commentText' => $member['commentText'],
                'commentEncoding' => $member['commentEncoding'],
                'modifiedAt' => $member['modifiedAt'],
                'modifiedAtKnown' => $member['modifiedAtKnown'],
                'modifiedAtText' => $member['modifiedAtText'],
                'flags' => $member['flags'],
                'textHint' => $member['textHint'],
                'extraFlags' => $member['extraFlags'],
                'extraFlagsMeaning' => $member['extraFlagsMeaning'],
                'operatingSystem' => $member['operatingSystem'],
                'operatingSystemName' => $member['operatingSystemName'],
                'extraFieldData' => $member['extraFieldData'],
                'extraFields' => $member['extraFields'],
                'crc32' => $member['crc32'],
                'uncompressedSize' => $member['uncompressedSize'],
                'compressedSize' => $member['compressedSize'],
                'memberSize' => $member['memberSize'],
                'extraFieldCount' => count($member['extraFields']),
                'headerCrc16' => $member['headerCrc16'],
            ],
            $inspection['members']
        );

        return [
            'type' => 'gzip',
            'memberCount' => $inspection['memberCount'],
            'compressedSize' => strlen($bytes),
            'uncompressedSize' => $inspection['uncompressedSize'],
            'trailingPaddingBytes' => $inspection['trailingPaddingBytes'],
            'members' => $members,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     memberCount:int,
     *     compressedSize:int,
     *     compressedPayloadSize:int,
     *     uncompressedSize:int,
     *     compressionMethod:int,
     *     windowSize:int,
     *     compressionLevelHint:string,
     *     adler32:int
     * }
     */
    private static function zlibStreamInspection(string $bytes, ?int $maxUncompressedBytes): array
    {
        $metadata = DeflateStream::inspectZlib($bytes, $maxUncompressedBytes);

        return [
            'type' => 'zlib-deflate',
            'memberCount' => 1,
            'compressedSize' => strlen($bytes),
            'compressedPayloadSize' => $metadata['compressedSize'],
            'uncompressedSize' => $metadata['uncompressedSize'],
            'compressionMethod' => $metadata['compressionMethod'],
            'windowSize' => $metadata['windowSize'],
            'compressionLevelHint' => $metadata['compressionLevelHint'],
            'adler32' => $metadata['adler32'],
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     memberCount:int,
     *     compressedSize:int,
     *     compressedPayloadSize:int,
     *     uncompressedSize:int
     * }
     */
    private static function rawDeflateStreamInspection(string $bytes, ?int $maxUncompressedBytes): array
    {
        $metadata = DeflateStream::inspectRaw($bytes, $maxUncompressedBytes);

        return [
            'type' => 'raw-deflate',
            'memberCount' => 1,
            'compressedSize' => strlen($bytes),
            'compressedPayloadSize' => $metadata['compressedSize'],
            'uncompressedSize' => $metadata['uncompressedSize'],
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     frameCount:int,
     *     dataFrameCount:int,
     *     skippableFrameCount:int,
     *     blockCount:int,
     *     compressedSize:int,
     *     frames:list<array<string, mixed>>
     * }
     */
    private static function lz4StreamInspection(string $bytes, ?int $maxUncompressedBytes): array
    {
        $frames = [];
        $dataFrameCount = 0;
        $skippableFrameCount = 0;
        $blockCount = 0;

        foreach (Lz4Frame::frames($bytes, $maxUncompressedBytes) as $frame) {
            if ($frame['type'] === 'skippable') {
                $skippableFrameCount++;
                $frames[] = [
                    'type' => 'skippable',
                    'id' => $frame['id'],
                    'data' => $frame['data'],
                    'frameSize' => $frame['frameSize'],
                ];
                continue;
            }

            $dataFrameCount++;
            $blockCount += $frame['blockCount'];
            $frames[] = [
                'type' => 'frame',
                'contentSize' => $frame['contentSize'],
                'blockMaxSize' => $frame['blockMaxSize'],
                'blockIndependent' => $frame['blockIndependent'],
                'blockChecksum' => $frame['blockChecksum'],
                'contentChecksum' => $frame['contentChecksum'],
                'blockCount' => $frame['blockCount'],
                'blockTypes' => $frame['blockTypes'],
                'compressedSize' => $frame['compressedSize'],
                'frameSize' => $frame['frameSize'],
            ];
        }

        return [
            'type' => 'lz4',
            'frameCount' => count($frames),
            'dataFrameCount' => $dataFrameCount,
            'skippableFrameCount' => $skippableFrameCount,
            'blockCount' => $blockCount,
            'compressedSize' => strlen($bytes),
            'frames' => $frames,
        ];
    }

    /**
     * @return list<string>
     */
    private static function candidateTarFormats(string $bytes): array
    {
        $formats = [];
        if (self::startsWithGzipHeader($bytes)) {
            $formats[] = self::FORMAT_GZIP_TAR;
        }

        if (self::startsWithZlibHeader($bytes)) {
            $formats[] = self::FORMAT_ZLIB_TAR;
        }

        if (self::startsWithLz4Header($bytes)) {
            $formats[] = self::FORMAT_LZ4_TAR;
        }

        $formats[] = self::FORMAT_TAR;
        $formats[] = self::FORMAT_RAW_DEFLATE_TAR;

        return array_values(array_unique($formats));
    }

    /**
     * @return list<string>
     */
    private static function candidateZipFormats(string $bytes): array
    {
        $formats = [];
        if (self::startsWithGzipHeader($bytes)) {
            $formats[] = self::FORMAT_GZIP_ZIP;
        }

        if (self::startsWithZlibHeader($bytes)) {
            $formats[] = self::FORMAT_ZLIB_ZIP;
        }

        if (self::startsWithLz4Header($bytes)) {
            $formats[] = self::FORMAT_LZ4_ZIP;
        }

        if (self::startsWithZipHeader($bytes)) {
            $formats[] = self::FORMAT_ZIP;
        }

        $formats[] = self::FORMAT_RAW_DEFLATE_ZIP;

        return array_values(array_unique($formats));
    }

    private static function startsWithZipHeader(string $bytes): bool
    {
        return str_starts_with($bytes, "PK\x03\x04")
            || str_starts_with($bytes, "PK\x05\x06");
    }

    private static function startsWithGzipHeader(string $bytes): bool
    {
        return strlen($bytes) >= 2 && ord($bytes[0]) === 0x1f && ord($bytes[1]) === 0x8b;
    }

    private static function startsWithZlibHeader(string $bytes): bool
    {
        if (strlen($bytes) < 2) {
            return false;
        }

        $cmf = ord($bytes[0]);
        $flg = ord($bytes[1]);

        return ($cmf & 0x0f) === 8
            && (($cmf >> 4) & 0x0f) <= 7
            && (($cmf << 8) + $flg) % 31 === 0;
    }

    private static function startsWithLz4Header(string $bytes): bool
    {
        if (strlen($bytes) < 4) {
            return false;
        }

        $values = unpack('Vmagic', substr($bytes, 0, 4));
        if (!is_array($values)) {
            return false;
        }

        $magic = (int) $values['magic'];

        return $magic === 0x184d2204 || ($magic >= 0x184d2a50 && $magic <= 0x184d2a5f);
    }

    /**
     * @param array<string, string> $errors
     */
    private static function formatDetectionDetails(array $errors): string
    {
        if ($errors === []) {
            return '';
        }

        $parts = [];
        foreach ($errors as $format => $message) {
            $parts[] = "{$format}: {$message}";
        }

        return implode('; ', $parts);
    }

    private static function assertLimit(?int $limit, string $label): void
    {
        if ($limit !== null && $limit < 0) {
            throw new \RuntimeException("{$label} must not be negative");
        }
    }
}
