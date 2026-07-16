<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteRecord
{
    /**
     * @param list<mixed> $values
     * @param list<int> $serialTypes
     */
    public function __construct(
        public readonly array $values,
        public readonly array $serialTypes,
        public readonly int $bytesRead,
    ) {
    }

    public static function blob(string $bytes): SQLiteBlobValue
    {
        return new SQLiteBlobValue($bytes);
    }

    /**
     * @param list<mixed> $values
     */
    public static function encode(array $values, int $textEncoding = 1): string
    {
        $serialTypeBytes = '';
        $body = '';
        foreach ($values as $value) {
            [$serialType, $bytes] = self::serialTypeAndBytes($value, $textEncoding);
            $serialTypeBytes .= SQLiteVarint::encode($serialType);
            $body .= $bytes;
        }

        $headerSize = strlen($serialTypeBytes) + 1;
        while (true) {
            $encodedHeaderSize = SQLiteVarint::encode($headerSize);
            $actualHeaderSize = strlen($encodedHeaderSize) + strlen($serialTypeBytes);
            if ($actualHeaderSize === $headerSize) {
                return $encodedHeaderSize . $serialTypeBytes . $body;
            }
            $headerSize = $actualHeaderSize;
        }
    }

    /**
     * @param list<mixed> $values
     * @param list<string> $affinities
     */
    public static function encodeWithColumnAffinities(array $values, array $affinities, int $textEncoding = 1): string
    {
        $storedValues = [];
        foreach ($values as $index => $value) {
            $affinity = self::columnAffinity($affinities[$index] ?? 'NONE');
            $storedValue = SQLiteAffinityComparison::applyAffinity($value, $affinity);
            if ($affinity === 'REAL' && is_float($storedValue) && self::realCanUseIntegerSerialType($storedValue)) {
                $storedValue = (int) $storedValue;
            }
            $storedValues[] = $storedValue;
        }

        return self::encode($storedValues, $textEncoding);
    }

    public static function parse(string $payload, int $textEncoding = 1): self
    {
        [$headerSize, $headerSizeBytes] = SQLiteVarint::decode($payload, 0);
        if ($headerSize < $headerSizeBytes || $headerSize > strlen($payload)) {
            throw new \InvalidArgumentException('SQLite record header size is outside the payload');
        }

        $serialTypes = [];
        $offset = $headerSizeBytes;
        while ($offset < $headerSize) {
            [$serialType, $bytesRead] = SQLiteVarint::decode($payload, $offset);
            $serialTypes[] = $serialType;
            $offset += $bytesRead;
        }
        if ($offset !== $headerSize) {
            throw new \InvalidArgumentException('SQLite record serial type header is malformed');
        }

        $values = [];
        $dataOffset = $headerSize;
        foreach ($serialTypes as $serialType) {
            [$value, $bytesRead] = self::readValue($payload, $dataOffset, $serialType, $textEncoding);
            $values[] = $value;
            $dataOffset += $bytesRead;
        }
        if ($dataOffset !== strlen($payload)) {
            throw new \InvalidArgumentException('SQLite record body does not match payload length');
        }

        return new self($values, $serialTypes, $dataOffset);
    }

    /**
     * @param list<string> $affinities
     */
    public static function parseWithColumnAffinities(string $payload, array $affinities, int $textEncoding = 1): self
    {
        $record = self::parse($payload, $textEncoding);
        $values = [];
        foreach ($record->values as $index => $value) {
            $affinity = self::columnAffinity($affinities[$index] ?? 'NONE');
            $values[] = $affinity === 'REAL' && is_int($value) ? (float) $value : $value;
        }

        return new self($values, $record->serialTypes, $record->bytesRead);
    }

    /**
     * @return array{0:int,1:string}
     */
    private static function serialTypeAndBytes(mixed $value, int $textEncoding): array
    {
        if ($value === null) {
            return [0, ''];
        }
        if ($value instanceof SQLiteBlobValue) {
            return [12 + (strlen($value->bytes) * 2), $value->bytes];
        }
        if (is_int($value)) {
            return self::integerSerialTypeAndBytes($value);
        }
        if (is_float($value)) {
            return [7, pack('E', $value)];
        }
        if (is_string($value)) {
            $bytes = self::encodeText($value, $textEncoding);

            return [13 + (strlen($bytes) * 2), $bytes];
        }

        throw new \InvalidArgumentException('Unsupported SQLite record value type');
    }

    /**
     * @return array{0:int,1:string}
     */
    private static function integerSerialTypeAndBytes(int $value): array
    {
        if ($value === 0) {
            return [8, ''];
        }
        if ($value === 1) {
            return [9, ''];
        }

        $magnitude = $value < 0 ? ~$value : $value;
        if ($magnitude <= 127) {
            return [1, self::signedIntegerBytes($value, 1)];
        }
        if ($magnitude <= 32767) {
            return [2, self::signedIntegerBytes($value, 2)];
        }
        if ($magnitude <= 8388607) {
            return [3, self::signedIntegerBytes($value, 3)];
        }
        if ($magnitude <= 2147483647) {
            return [4, self::signedIntegerBytes($value, 4)];
        }
        if ($magnitude <= 140737488355327) {
            return [5, self::signedIntegerBytes($value, 6)];
        }

        return [6, self::signedIntegerBytes($value, 8)];
    }

    private static function realCanUseIntegerSerialType(float $value): bool
    {
        if (!is_finite($value)) {
            return false;
        }
        if ($value < (float) PHP_INT_MIN || $value > (float) PHP_INT_MAX) {
            return false;
        }

        $integerValue = (int) $value;

        return (float) $integerValue === $value;
    }

    private static function columnAffinity(string $affinity): string
    {
        $normalized = strtoupper($affinity);

        if ($normalized === '' || $normalized === 'NONE' || str_contains($normalized, 'BLOB')) {
            return 'NONE';
        }
        if (str_contains($normalized, 'INT')) {
            return 'INTEGER';
        }
        if (str_contains($normalized, 'CHAR') || str_contains($normalized, 'CLOB') || str_contains($normalized, 'TEXT')) {
            return 'TEXT';
        }
        if (str_contains($normalized, 'REAL') || str_contains($normalized, 'FLOA') || str_contains($normalized, 'DOUB')) {
            return 'REAL';
        }

        return 'NUMERIC';
    }

    private static function signedIntegerBytes(int $value, int $bytes): string
    {
        $encoded = '';
        for ($i = 0; $i < $bytes; $i++) {
            $encoded = chr($value & 0xff) . $encoded;
            $value >>= 8;
        }

        return $encoded;
    }

    /**
     * @return array{0:mixed,1:int}
     */
    private static function readValue(string $payload, int $offset, int $serialType, int $textEncoding): array
    {
        return match (true) {
            $serialType === 0 => [null, 0],
            $serialType === 1 => [self::readSignedInteger($payload, $offset, 1), 1],
            $serialType === 2 => [self::readSignedInteger($payload, $offset, 2), 2],
            $serialType === 3 => [self::readSignedInteger($payload, $offset, 3), 3],
            $serialType === 4 => [self::readSignedInteger($payload, $offset, 4), 4],
            $serialType === 5 => [self::readSignedInteger($payload, $offset, 6), 6],
            $serialType === 6 => [self::readSignedInteger($payload, $offset, 8), 8],
            $serialType === 7 => [self::readFloat64($payload, $offset), 8],
            $serialType === 8 => [0, 0],
            $serialType === 9 => [1, 0],
            $serialType === 10 || $serialType === 11 => throw new \InvalidArgumentException('SQLite record serial type 10 and 11 are reserved'),
            $serialType >= 12 && $serialType % 2 === 0 => [self::readBytes($payload, $offset, intdiv($serialType - 12, 2)), intdiv($serialType - 12, 2)],
            $serialType >= 13 && $serialType % 2 === 1 => [self::decodeText(self::readBytes($payload, $offset, intdiv($serialType - 13, 2)), $textEncoding), intdiv($serialType - 13, 2)],
            default => throw new \InvalidArgumentException("Unsupported SQLite record serial type: {$serialType}"),
        };
    }

    private static function readBytes(string $payload, int $offset, int $length): string
    {
        if ($length < 0 || $offset < 0 || $offset + $length > strlen($payload)) {
            throw new \InvalidArgumentException('SQLite record field is truncated');
        }

        return substr($payload, $offset, $length);
    }

    private static function readSignedInteger(string $payload, int $offset, int $bytes): int
    {
        $raw = self::readBytes($payload, $offset, $bytes);
        if ($bytes === 8) {
            return self::readSignedInteger64($raw);
        }

        $value = 0;
        for ($i = 0; $i < $bytes; $i++) {
            $value = ($value << 8) | ord($raw[$i]);
        }

        $signBit = 1 << (($bytes * 8) - 1);
        if (($value & $signBit) !== 0) {
            $value -= 1 << ($bytes * 8);
        }

        return $value;
    }

    private static function readSignedInteger64(string $raw): int
    {
        $parts = unpack('Nhigh/Nlow', $raw);
        $high = $parts['high'];
        $low = $parts['low'];

        if (($high & 0x80000000) === 0) {
            return ($high << 32) | $low;
        }

        if ($high === 0x80000000 && $low === 0) {
            return PHP_INT_MIN;
        }

        $magnitudeHigh = (~$high) & 0xffffffff;
        $magnitudeLow = ((~$low) & 0xffffffff) + 1;
        if ($magnitudeLow > 0xffffffff) {
            $magnitudeLow = 0;
            $magnitudeHigh++;
        }

        return -(($magnitudeHigh << 32) | $magnitudeLow);
    }

    private static function readFloat64(string $payload, int $offset): float
    {
        $raw = self::readBytes($payload, $offset, 8);

        return unpack('E', $raw)[1];
    }

    private static function decodeText(string $bytes, int $textEncoding): string
    {
        if ($textEncoding === 1) {
            return $bytes;
        }
        if ($textEncoding !== 2 && $textEncoding !== 3) {
            throw new \InvalidArgumentException("Unsupported SQLite text encoding: {$textEncoding}");
        }
        if ($bytes === '') {
            return '';
        }
        self::assertValidUtf16Text($bytes, $textEncoding);
        if (function_exists('mb_convert_encoding')) {
            return match ($textEncoding) {
                2 => mb_convert_encoding($bytes, 'UTF-8', 'UTF-16LE'),
                3 => mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE'),
            };
        }

        return self::decodeUtf16TextWithoutMbstring($bytes, $textEncoding);
    }

    private static function encodeText(string $value, int $textEncoding): string
    {
        if ($textEncoding === 1) {
            return $value;
        }
        if ($textEncoding !== 2 && $textEncoding !== 3) {
            throw new \InvalidArgumentException("Unsupported SQLite text encoding: {$textEncoding}");
        }
        if ($value === '') {
            return '';
        }
        self::assertValidUtf8Text($value);
        if (function_exists('mb_convert_encoding')) {
            return match ($textEncoding) {
                2 => mb_convert_encoding($value, 'UTF-16LE', 'UTF-8'),
                3 => mb_convert_encoding($value, 'UTF-16BE', 'UTF-8'),
            };
        }

        return self::encodeUtf16TextWithoutMbstring($value, $textEncoding);
    }

    private static function assertValidUtf8Text(string $value): void
    {
        if (preg_match('//u', $value) !== 1) {
            throw new \InvalidArgumentException('Malformed UTF-8 SQLite text cannot be encoded as UTF-16');
        }
    }

    private static function assertValidUtf16Text(string $bytes, int $textEncoding): void
    {
        if ((strlen($bytes) % 2) !== 0) {
            throw new \InvalidArgumentException('Malformed UTF-16 SQLite text has an odd byte length');
        }

        $encoding = $textEncoding === 2 ? 'UTF-16LE' : 'UTF-16BE';
        if (function_exists('mb_check_encoding') && !mb_check_encoding($bytes, $encoding)) {
            throw new \InvalidArgumentException("Malformed {$encoding} SQLite text");
        }

        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 2) {
            $unit = self::readUtf16Unit($bytes, $offset, $textEncoding);
            if ($unit >= 0xd800 && $unit <= 0xdbff) {
                if ($offset + 2 >= $length) {
                    throw new \InvalidArgumentException("Malformed {$encoding} SQLite text");
                }
                $trail = self::readUtf16Unit($bytes, $offset + 2, $textEncoding);
                if ($trail < 0xdc00 || $trail > 0xdfff) {
                    throw new \InvalidArgumentException("Malformed {$encoding} SQLite text");
                }
                $offset += 2;
                continue;
            }

            if ($unit >= 0xdc00 && $unit <= 0xdfff) {
                throw new \InvalidArgumentException("Malformed {$encoding} SQLite text");
            }
        }
    }

    private static function decodeUtf16TextWithoutMbstring(string $bytes, int $textEncoding): string
    {
        $text = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 2) {
            $unit = self::readUtf16Unit($bytes, $offset, $textEncoding);
            if ($unit >= 0xd800 && $unit <= 0xdbff) {
                $trail = self::readUtf16Unit($bytes, $offset + 2, $textEncoding);
                $codepoint = 0x10000 + ((($unit - 0xd800) << 10) | ($trail - 0xdc00));
                $offset += 2;
            } else {
                $codepoint = $unit;
            }

            $text .= self::utf8FromCodepoint($codepoint);
        }

        return $text;
    }

    private static function encodeUtf16TextWithoutMbstring(string $value, int $textEncoding): string
    {
        self::assertValidUtf8Text($value);

        preg_match_all('/./us', $value, $matches);
        $bytes = '';
        foreach ($matches[0] as $character) {
            $codepoint = self::utf8Codepoint($character);
            if ($codepoint < 0x10000) {
                $bytes .= self::writeUtf16Unit($codepoint, $textEncoding);
                continue;
            }

            $surrogateValue = $codepoint - 0x10000;
            $bytes .= self::writeUtf16Unit(0xd800 + ($surrogateValue >> 10), $textEncoding);
            $bytes .= self::writeUtf16Unit(0xdc00 + ($surrogateValue & 0x3ff), $textEncoding);
        }

        return $bytes;
    }

    private static function readUtf16Unit(string $bytes, int $offset, int $textEncoding): int
    {
        return $textEncoding === 2
            ? ord($bytes[$offset]) | (ord($bytes[$offset + 1]) << 8)
            : (ord($bytes[$offset]) << 8) | ord($bytes[$offset + 1]);
    }

    private static function writeUtf16Unit(int $unit, int $textEncoding): string
    {
        return $textEncoding === 2
            ? chr($unit & 0xff) . chr(($unit >> 8) & 0xff)
            : chr(($unit >> 8) & 0xff) . chr($unit & 0xff);
    }

    private static function utf8Codepoint(string $character): int
    {
        $first = ord($character[0]);
        if ($first < 0x80) {
            return $first;
        }
        if (($first & 0xe0) === 0xc0) {
            return (($first & 0x1f) << 6) | (ord($character[1]) & 0x3f);
        }
        if (($first & 0xf0) === 0xe0) {
            return (($first & 0x0f) << 12) | ((ord($character[1]) & 0x3f) << 6) | (ord($character[2]) & 0x3f);
        }

        return (($first & 0x07) << 18)
            | ((ord($character[1]) & 0x3f) << 12)
            | ((ord($character[2]) & 0x3f) << 6)
            | (ord($character[3]) & 0x3f);
    }

    private static function utf8FromCodepoint(int $codepoint): string
    {
        if ($codepoint < 0x80) {
            return chr($codepoint);
        }
        if ($codepoint < 0x800) {
            return chr(0xc0 | ($codepoint >> 6)) . chr(0x80 | ($codepoint & 0x3f));
        }
        if ($codepoint < 0x10000) {
            return chr(0xe0 | ($codepoint >> 12))
                . chr(0x80 | (($codepoint >> 6) & 0x3f))
                . chr(0x80 | ($codepoint & 0x3f));
        }

        return chr(0xf0 | ($codepoint >> 18))
            . chr(0x80 | (($codepoint >> 12) & 0x3f))
            . chr(0x80 | (($codepoint >> 6) & 0x3f))
            . chr(0x80 | ($codepoint & 0x3f));
    }
}
