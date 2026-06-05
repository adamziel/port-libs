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

    private static function assertLimit(?int $limit, string $label): void
    {
        if ($limit !== null && $limit < 0) {
            throw new \RuntimeException("{$label} must not be negative");
        }
    }
}
