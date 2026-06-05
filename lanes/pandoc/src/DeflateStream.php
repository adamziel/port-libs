<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DeflateStream
{
    public const FORMAT_ZLIB = 'zlib';
    public const FORMAT_RAW = 'raw';

    private const ZLIB_COMPRESSION_METHOD_DEFLATE = 8;
    private const ZLIB_PRESET_DICTIONARY_FLAG = 0x20;

    /**
     * @param array{format?:string, compressionLevel?:int} $options
     */
    public static function build(string $data, array $options = []): string
    {
        $format = self::normalizeFormat($options['format'] ?? self::FORMAT_ZLIB);
        $compressionLevel = $options['compressionLevel'] ?? -1;
        if (!is_int($compressionLevel) || $compressionLevel < -1 || $compressionLevel > 9) {
            throw new \RuntimeException('DEFLATE compression level must be between -1 and 9');
        }

        if ($format === self::FORMAT_RAW) {
            $encoded = gzdeflate($data, $compressionLevel);
            if ($encoded === false) {
                throw new \RuntimeException('Unable to encode raw DEFLATE stream');
            }

            return $encoded;
        }

        $encoded = zlib_encode($data, ZLIB_ENCODING_DEFLATE, $compressionLevel);
        if ($encoded === false) {
            throw new \RuntimeException('Unable to encode zlib-wrapped DEFLATE stream');
        }

        return $encoded;
    }

    public static function decode(
        string $bytes,
        string $format = self::FORMAT_ZLIB,
        ?int $maxUncompressedBytes = null
    ): string {
        $format = self::normalizeFormat($format);
        self::assertLimit($maxUncompressedBytes, 'DEFLATE max uncompressed byte limit');

        if ($format === self::FORMAT_ZLIB) {
            return self::inspectZlib($bytes, $maxUncompressedBytes)['data'];
        }

        $data = @gzinflate($bytes);
        if ($data === false) {
            throw new \RuntimeException('Unable to decode raw DEFLATE stream');
        }

        self::assertDecodedSize($data, $maxUncompressedBytes, 'DEFLATE stream');

        return $data;
    }

    /**
     * @return array{
     *     format:string,
     *     data:string,
     *     compressionMethod:int,
     *     windowSize:int,
     *     compressionLevelHint:string,
     *     hasPresetDictionary:bool,
     *     adler32:int,
     *     uncompressedSize:int,
     *     compressedSize:int
     * }
     */
    public static function inspectZlib(string $bytes, ?int $maxUncompressedBytes = null): array
    {
        self::assertLimit($maxUncompressedBytes, 'DEFLATE max uncompressed byte limit');
        if (strlen($bytes) < 6) {
            throw new \RuntimeException('ZLIB stream is too short to contain a DEFLATE header and trailer');
        }

        $cmf = ord($bytes[0]);
        $flg = ord($bytes[1]);
        if ((($cmf << 8) + $flg) % 31 !== 0) {
            throw new \RuntimeException('ZLIB stream header check bits do not match');
        }

        $compressionMethod = $cmf & 0x0f;
        if ($compressionMethod !== self::ZLIB_COMPRESSION_METHOD_DEFLATE) {
            throw new \RuntimeException("Unsupported ZLIB compression method {$compressionMethod}");
        }

        $windowCode = ($cmf >> 4) & 0x0f;
        if ($windowCode > 7) {
            throw new \RuntimeException('ZLIB DEFLATE window size is outside the supported range');
        }

        $hasPresetDictionary = ($flg & self::ZLIB_PRESET_DICTIONARY_FLAG) !== 0;
        if ($hasPresetDictionary) {
            throw new \RuntimeException('Preset-dictionary ZLIB streams are not supported by the pandoc archive reader');
        }

        $data = @zlib_decode($bytes);
        if ($data === false) {
            throw new \RuntimeException('Unable to decode zlib-wrapped DEFLATE stream');
        }

        $adler32 = self::readUInt32BE($bytes, strlen($bytes) - 4);
        if (self::adler32($data) !== $adler32) {
            throw new \RuntimeException('ZLIB stream Adler-32 trailer does not match decoded payload');
        }

        self::assertDecodedSize($data, $maxUncompressedBytes, 'ZLIB stream');

        return [
            'format' => self::FORMAT_ZLIB,
            'data' => $data,
            'compressionMethod' => $compressionMethod,
            'windowSize' => 1 << ($windowCode + 8),
            'compressionLevelHint' => self::compressionLevelHint(($flg >> 6) & 0x03),
            'hasPresetDictionary' => false,
            'adler32' => $adler32,
            'uncompressedSize' => strlen($data),
            'compressedSize' => strlen($bytes) - 6,
        ];
    }

    private static function normalizeFormat(mixed $format): string
    {
        if ($format !== self::FORMAT_ZLIB && $format !== self::FORMAT_RAW) {
            throw new \RuntimeException('DEFLATE format must be either zlib or raw');
        }

        return $format;
    }

    private static function compressionLevelHint(int $flag): string
    {
        return match ($flag) {
            0 => 'fastest',
            1 => 'fast',
            2 => 'default',
            3 => 'maximum',
        };
    }

    private static function readUInt32BE(string $bytes, int $offset): int
    {
        if ($offset < 0 || $offset + 4 > strlen($bytes)) {
            throw new \RuntimeException('ZLIB uint32 field extends beyond available bytes');
        }

        $values = unpack('Nvalue', substr($bytes, $offset, 4));
        if (!is_array($values)) {
            throw new \RuntimeException('Unable to read ZLIB uint32 value');
        }

        return (int) $values['value'];
    }

    private static function adler32(string $bytes): int
    {
        if (!in_array('adler32', hash_algos(), true)) {
            throw new \RuntimeException('ZLIB stream checksums require PHP hash algorithm adler32');
        }

        return intval(hash('adler32', $bytes), 16);
    }

    private static function assertLimit(?int $maxUncompressedBytes, string $label): void
    {
        if ($maxUncompressedBytes !== null && $maxUncompressedBytes < 0) {
            throw new \RuntimeException("{$label} must not be negative");
        }
    }

    private static function assertDecodedSize(string $data, ?int $maxUncompressedBytes, string $label): void
    {
        if ($maxUncompressedBytes !== null && strlen($data) > $maxUncompressedBytes) {
            throw new \RuntimeException("{$label} exceeds the configured uncompressed byte limit");
        }
    }
}
