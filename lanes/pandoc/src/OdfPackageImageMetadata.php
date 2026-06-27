<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class OdfPackageImageMetadata
{
    /**
     * @return array<string, mixed>|null
     */
    public static function headerFromBytes(string $bytes): ?array
    {
        return self::pngHeader($bytes)
            ?? self::jpegHeader($bytes)
            ?? self::gifHeader($bytes)
            ?? self::webpHeader($bytes);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function pngHeader(string $bytes): ?array
    {
        if (strlen($bytes) < 24 || !str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return null;
        }
        if (substr($bytes, 12, 4) !== 'IHDR') {
            return null;
        }

        return self::dimensions('png', 'png-ihdr', self::uint32be($bytes, 16), self::uint32be($bytes, 20), 24);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function jpegHeader(string $bytes): ?array
    {
        $length = strlen($bytes);
        if ($length < 4 || substr($bytes, 0, 2) !== "\xff\xd8") {
            return null;
        }

        $offset = 2;
        while ($offset + 3 < $length) {
            if (ord($bytes[$offset]) !== 0xff) {
                ++$offset;
                continue;
            }
            while ($offset < $length && ord($bytes[$offset]) === 0xff) {
                ++$offset;
            }
            if ($offset >= $length) {
                break;
            }

            $marker = ord($bytes[$offset]);
            ++$offset;
            if ($marker === 0xd9 || $marker === 0xda) {
                break;
            }
            if ($marker >= 0xd0 && $marker <= 0xd7) {
                continue;
            }
            if ($offset + 1 >= $length) {
                break;
            }

            $segmentLength = self::uint16be($bytes, $offset);
            if ($segmentLength < 2 || $offset + $segmentLength > $length) {
                break;
            }
            if (self::isJpegStartOfFrameMarker($marker) && $segmentLength >= 7) {
                return self::dimensions(
                    'jpeg',
                    sprintf('jpeg-sof-%02x', $marker),
                    self::uint16be($bytes, $offset + 5),
                    self::uint16be($bytes, $offset + 3),
                    $offset + $segmentLength
                );
            }

            $offset += $segmentLength;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function gifHeader(string $bytes): ?array
    {
        if (strlen($bytes) < 10 || !in_array(substr($bytes, 0, 6), ['GIF87a', 'GIF89a'], true)) {
            return null;
        }

        return self::dimensions('gif', strtolower(substr($bytes, 0, 6)) . '-logical-screen', self::uint16le($bytes, 6), self::uint16le($bytes, 8), 10);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function webpHeader(string $bytes): ?array
    {
        if (strlen($bytes) < 25 || substr($bytes, 0, 4) !== 'RIFF' || substr($bytes, 8, 4) !== 'WEBP') {
            return null;
        }

        $chunkType = substr($bytes, 12, 4);
        if ($chunkType === 'VP8X' && strlen($bytes) >= 30) {
            return self::dimensions('webp', 'webp-vp8x', self::uint24le($bytes, 24) + 1, self::uint24le($bytes, 27) + 1, 30);
        }
        if ($chunkType === 'VP8 ' && strlen($bytes) >= 30 && substr($bytes, 23, 3) === "\x9d\x01\x2a") {
            return self::dimensions(
                'webp',
                'webp-vp8',
                self::uint16le($bytes, 26) & 0x3fff,
                self::uint16le($bytes, 28) & 0x3fff,
                30
            );
        }
        if ($chunkType === 'VP8L' && strlen($bytes) >= 25 && ord($bytes[20]) === 0x2f) {
            $b1 = ord($bytes[21]);
            $b2 = ord($bytes[22]);
            $b3 = ord($bytes[23]);
            $b4 = ord($bytes[24]);

            return self::dimensions(
                'webp',
                'webp-vp8l',
                1 + ((($b2 & 0x3f) << 8) | $b1),
                1 + ((($b4 & 0x0f) << 10) | ($b3 << 2) | (($b2 & 0xc0) >> 6)),
                25
            );
        }

        return null;
    }

    private static function isJpegStartOfFrameMarker(int $marker): bool
    {
        return in_array($marker, [
            0xc0,
            0xc1,
            0xc2,
            0xc3,
            0xc5,
            0xc6,
            0xc7,
            0xc9,
            0xca,
            0xcb,
            0xcd,
            0xce,
            0xcf,
        ], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function dimensions(string $format, string $source, int $width, int $height, int $headerByteLength): ?array
    {
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $pixelCount = $width <= intdiv(PHP_INT_MAX, $height) ? $width * $height : null;

        return [
            'format' => $format,
            'source' => $source,
            'width' => $width,
            'height' => $height,
            'pixelCount' => $pixelCount,
            'headerByteLength' => $headerByteLength,
            'byteExposurePolicy' => 'package-thumbnail-image-header-metadata-only',
            'canExposeBytes' => false,
        ];
    }

    private static function uint16be(string $bytes, int $offset): int
    {
        return (ord($bytes[$offset]) << 8) | ord($bytes[$offset + 1]);
    }

    private static function uint16le(string $bytes, int $offset): int
    {
        return ord($bytes[$offset]) | (ord($bytes[$offset + 1]) << 8);
    }

    private static function uint24le(string $bytes, int $offset): int
    {
        return ord($bytes[$offset]) | (ord($bytes[$offset + 1]) << 8) | (ord($bytes[$offset + 2]) << 16);
    }

    private static function uint32be(string $bytes, int $offset): int
    {
        return (ord($bytes[$offset]) << 24)
            | (ord($bytes[$offset + 1]) << 16)
            | (ord($bytes[$offset + 2]) << 8)
            | ord($bytes[$offset + 3]);
    }
}
