<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16GlobCurrentNextCursor
{
    private SQLiteGlobCursor $cursor;
    private int $encoding;

    /**
     * @var list<array{key:string,rowid:int,payload:array<string,mixed>,encodedKeyHex:string,byteLength:int}>
     */
    private array $decodedEntries = [];

    /**
     * @param iterable<array{encodedKey?:mixed,rowid?:mixed,payload?:array<string,mixed>}> $entries
     */
    public function __construct(
        iterable $entries,
        string $pattern,
        int|string $encoding,
        string $collation = 'BINARY',
    ) {
        $this->encoding = self::normalizeEncoding($encoding);

        foreach ($entries as $entry) {
            if (!is_array($entry) || !isset($entry['payload']) || !is_array($entry['payload'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 GLOB cursor entries need payload arrays');
            }
            if (!array_key_exists('encodedKey', $entry) || !is_string($entry['encodedKey'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 GLOB cursor entries need encoded text keys');
            }
            if (!array_key_exists('rowid', $entry) || !is_int($entry['rowid'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 GLOB cursor entries need integer rowids');
            }

            $decoded = self::decodeUtf16($entry['encodedKey'], $this->encoding);
            $this->decodedEntries[] = [
                'key' => $decoded,
                'rowid' => $entry['rowid'],
                'payload' => $entry['payload'],
                'encodedKeyHex' => bin2hex($entry['encodedKey']),
                'byteLength' => strlen($entry['encodedKey']),
            ];
        }

        $this->cursor = new SQLiteGlobCursor(
            array_map(
                static fn (array $entry): array => [
                    'key' => $entry['key'],
                    'rowid' => $entry['rowid'],
                    'payload' => $entry['payload'] + [
                        '_utf16_key_hex' => $entry['encodedKeyHex'],
                        '_utf16_key_bytes' => $entry['byteLength'],
                    ],
                ],
                $this->decodedEntries,
            ),
            $pattern,
            $collation,
        );
    }

    public function rewind(): void
    {
        $this->cursor->rewind();
    }

    public function next(): void
    {
        $this->cursor->next();
    }

    /**
     * @return array<string,mixed>
     */
    public function currentNextPlan(): array
    {
        $plan = $this->cursor->currentNextPlan();
        $plan['textEncoding'] = $this->encoding === 2 ? 'UTF-16le' : 'UTF-16be';
        $plan['decodedEntryCount'] = count($this->decodedEntries);
        $plan['currentEncodedKeyHex'] = $this->encodedHexForRowid($plan['currentRowid']);
        $plan['nextEncodedKeyHex'] = $this->encodedHexForRowid($plan['nextRowid']);
        $plan['dependencies'] = ['sqlite-utf16-glob-current-next-cursor', 'sqlite-glob-current-next-cursor'];

        return $plan;
    }

    /**
     * @return list<array{rowid:int,key:string,payload:array<string,mixed>,position:int,malformedUtf8:bool,encodedKeyHex:string,byteLength:int,textEncoding:string}>
     */
    public function matchedRows(): array
    {
        return array_map(
            function (array $row): array {
                $row['encodedKeyHex'] = is_string($row['payload']['_utf16_key_hex'] ?? null) ? $row['payload']['_utf16_key_hex'] : '';
                $row['byteLength'] = is_int($row['payload']['_utf16_key_bytes'] ?? null) ? $row['payload']['_utf16_key_bytes'] : 0;
                $row['textEncoding'] = $this->encoding === 2 ? 'UTF-16le' : 'UTF-16be';
                unset($row['payload']['_utf16_key_hex'], $row['payload']['_utf16_key_bytes']);

                return $row;
            },
            $this->cursor->matchedRows(),
        );
    }

    public static function encodeUtf16(string $value, int|string $encoding): string
    {
        $encoding = self::normalizeEncoding($encoding);
        self::assertValidUtf8($value);

        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($value, $encoding === 2 ? 'UTF-16LE' : 'UTF-16BE', 'UTF-8');
        }

        $bytes = '';
        foreach (self::utf8Characters($value) as $character) {
            $codepoint = self::codepoint($character);
            if ($codepoint <= 0xffff) {
                $bytes .= self::writeUtf16Unit($codepoint, $encoding);
                continue;
            }

            $surrogate = $codepoint - 0x10000;
            $bytes .= self::writeUtf16Unit(0xd800 + ($surrogate >> 10), $encoding);
            $bytes .= self::writeUtf16Unit(0xdc00 + ($surrogate & 0x3ff), $encoding);
        }

        return $bytes;
    }

    private function encodedHexForRowid(mixed $rowid): ?string
    {
        if (!is_int($rowid)) {
            return null;
        }

        foreach ($this->decodedEntries as $entry) {
            if ($entry['rowid'] === $rowid) {
                return $entry['encodedKeyHex'];
            }
        }

        return null;
    }

    private static function decodeUtf16(string $bytes, int $encoding): string
    {
        self::assertValidUtf16($bytes, $encoding);
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($bytes, 'UTF-8', $encoding === 2 ? 'UTF-16LE' : 'UTF-16BE');
        }

        $text = '';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset += 2) {
            $unit = self::readUtf16Unit($bytes, $offset, $encoding);
            if ($unit >= 0xd800 && $unit <= 0xdbff) {
                $trail = self::readUtf16Unit($bytes, $offset + 2, $encoding);
                $offset += 2;
                $codepoint = 0x10000 + (($unit - 0xd800) << 10) + ($trail - 0xdc00);
            } else {
                $codepoint = $unit;
            }

            $text .= self::utf8FromCodepoint($codepoint);
        }

        return $text;
    }

    private static function normalizeEncoding(int|string $encoding): int
    {
        if (is_int($encoding)) {
            if (in_array($encoding, [2, 3], true)) {
                return $encoding;
            }
            throw new \InvalidArgumentException('SQLite UTF-16 GLOB cursor encoding must be UTF-16le or UTF-16be');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-16LE', 'UTF16LE' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 GLOB cursor encoding must be UTF-16le or UTF-16be'),
        };
    }

    private static function assertValidUtf8(string $value): void
    {
        if (preg_match('//u', $value) !== 1) {
            throw new \InvalidArgumentException('Malformed UTF-8 SQLite text cannot be encoded as UTF-16 GLOB key');
        }
    }

    private static function assertValidUtf16(string $bytes, int $encoding): void
    {
        if (strlen($bytes) % 2 !== 0) {
            throw new \InvalidArgumentException('Malformed UTF-16 SQLite GLOB key has an odd byte length');
        }

        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset += 2) {
            $unit = self::readUtf16Unit($bytes, $offset, $encoding);
            if ($unit >= 0xd800 && $unit <= 0xdbff) {
                if ($offset + 2 >= $length) {
                    throw new \InvalidArgumentException('Malformed UTF-16 SQLite GLOB key has an unpaired high surrogate');
                }
                $trail = self::readUtf16Unit($bytes, $offset + 2, $encoding);
                if ($trail < 0xdc00 || $trail > 0xdfff) {
                    throw new \InvalidArgumentException('Malformed UTF-16 SQLite GLOB key has an unpaired high surrogate');
                }
                $offset += 2;
                continue;
            }

            if ($unit >= 0xdc00 && $unit <= 0xdfff) {
                throw new \InvalidArgumentException('Malformed UTF-16 SQLite GLOB key has an unpaired low surrogate');
            }
        }
    }

    private static function readUtf16Unit(string $bytes, int $offset, int $encoding): int
    {
        $pair = substr($bytes, $offset, 2);
        $unpacked = unpack($encoding === 2 ? 'v' : 'n', $pair);

        return (int) ($unpacked[1] ?? 0);
    }

    private static function writeUtf16Unit(int $unit, int $encoding): string
    {
        return pack($encoding === 2 ? 'v' : 'n', $unit);
    }

    /**
     * @return list<string>
     */
    private static function utf8Characters(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            throw new \InvalidArgumentException('Malformed UTF-8 SQLite text cannot be encoded as UTF-16 GLOB key');
        }

        return $characters;
    }

    private static function codepoint(string $character): int
    {
        $bytes = array_values(unpack('C*', $character) ?: []);
        $length = count($bytes);
        if ($length === 1) {
            return $bytes[0];
        }
        if ($length === 2) {
            return (($bytes[0] & 0x1f) << 6) | ($bytes[1] & 0x3f);
        }
        if ($length === 3) {
            return (($bytes[0] & 0x0f) << 12) | (($bytes[1] & 0x3f) << 6) | ($bytes[2] & 0x3f);
        }

        return (($bytes[0] & 0x07) << 18) | (($bytes[1] & 0x3f) << 12) | (($bytes[2] & 0x3f) << 6) | ($bytes[3] & 0x3f);
    }

    private static function utf8FromCodepoint(int $codepoint): string
    {
        if ($codepoint <= 0x7f) {
            return chr($codepoint);
        }
        if ($codepoint <= 0x7ff) {
            return chr(0xc0 | ($codepoint >> 6)) . chr(0x80 | ($codepoint & 0x3f));
        }
        if ($codepoint <= 0xffff) {
            return chr(0xe0 | ($codepoint >> 12)) . chr(0x80 | (($codepoint >> 6) & 0x3f)) . chr(0x80 | ($codepoint & 0x3f));
        }

        return chr(0xf0 | ($codepoint >> 18)) . chr(0x80 | (($codepoint >> 12) & 0x3f)) . chr(0x80 | (($codepoint >> 6) & 0x3f)) . chr(0x80 | ($codepoint & 0x3f));
    }
}
