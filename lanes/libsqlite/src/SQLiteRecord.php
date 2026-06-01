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
        if (!function_exists('mb_convert_encoding')) {
            throw new \InvalidArgumentException('UTF-16 SQLite text requires mbstring for decoding');
        }

        return match ($textEncoding) {
            2 => mb_convert_encoding($bytes, 'UTF-8', 'UTF-16LE'),
            3 => mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE'),
        };
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
        if (!function_exists('mb_convert_encoding')) {
            throw new \InvalidArgumentException('UTF-16 SQLite text requires mbstring for encoding');
        }

        return match ($textEncoding) {
            2 => mb_convert_encoding($value, 'UTF-16LE', 'UTF-8'),
            3 => mb_convert_encoding($value, 'UTF-16BE', 'UTF-8'),
        };
    }
}
