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

    public static function decodeTarBytes(
        string $bytes,
        string $format,
        ?int $maxUncompressedBytes = null
    ): string {
        self::assertLimit($maxUncompressedBytes, 'archive stream max uncompressed byte limit');

        return match ($format) {
            self::FORMAT_TAR => self::boundedPlainBytes($bytes, $maxUncompressedBytes),
            self::FORMAT_GZIP_TAR => GzipStream::decode($bytes, $maxUncompressedBytes),
            self::FORMAT_ZLIB_TAR => DeflateStream::decode($bytes, DeflateStream::FORMAT_ZLIB, $maxUncompressedBytes),
            self::FORMAT_RAW_DEFLATE_TAR => DeflateStream::decode($bytes, DeflateStream::FORMAT_RAW, $maxUncompressedBytes),
            self::FORMAT_LZ4_TAR => Lz4Frame::decode($bytes, $maxUncompressedBytes),
            default => throw new \RuntimeException("Unsupported archive compression stream format: {$format}"),
        };
    }

    private static function boundedPlainBytes(string $bytes, ?int $maxUncompressedBytes): string
    {
        if ($maxUncompressedBytes !== null && strlen($bytes) > $maxUncompressedBytes) {
            throw new \RuntimeException('Plain TAR stream exceeds the configured uncompressed byte limit');
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
            self::FORMAT_GZIP_TAR => self::gzipStreamInspection($bytes, $maxUncompressedBytes),
            self::FORMAT_ZLIB_TAR => self::zlibStreamInspection($bytes, $maxUncompressedBytes),
            self::FORMAT_RAW_DEFLATE_TAR => [
                'type' => 'raw-deflate',
                'compressedSize' => strlen($bytes),
                'memberCount' => 1,
            ],
            self::FORMAT_LZ4_TAR => self::lz4StreamInspection($bytes, $maxUncompressedBytes),
            default => throw new \RuntimeException("Unsupported archive compression stream format: {$format}"),
        };
    }

    /**
     * @return array{
     *     type:string,
     *     memberCount:int,
     *     compressedSize:int,
     *     members:list<array{
     *         filename:?string,
     *         comment:?string,
     *         modifiedAt:int,
     *         extraFlags:int,
     *         operatingSystem:int,
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
        $members = array_map(
            static fn (array $member): array => [
                'filename' => $member['filename'],
                'comment' => $member['comment'],
                'modifiedAt' => $member['modifiedAt'],
                'extraFlags' => $member['extraFlags'],
                'operatingSystem' => $member['operatingSystem'],
                'extraFieldData' => $member['extraFieldData'],
                'extraFields' => $member['extraFields'],
                'crc32' => $member['crc32'],
                'uncompressedSize' => $member['uncompressedSize'],
                'compressedSize' => $member['compressedSize'],
                'memberSize' => $member['memberSize'],
                'extraFieldCount' => count($member['extraFields']),
                'headerCrc16' => $member['headerCrc16'],
            ],
            GzipStream::members($bytes, $maxUncompressedBytes)
        );

        return [
            'type' => 'gzip',
            'memberCount' => count($members),
            'compressedSize' => strlen($bytes),
            'members' => $members,
        ];
    }

    /**
     * @return array{
     *     type:string,
     *     memberCount:int,
     *     compressedSize:int,
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
            'compressionMethod' => $metadata['compressionMethod'],
            'windowSize' => $metadata['windowSize'],
            'compressionLevelHint' => $metadata['compressionLevelHint'],
            'adler32' => $metadata['adler32'],
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
