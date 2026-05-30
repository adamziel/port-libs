<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteEncodingCollationSourceCursor
{
    /** @var list<array{key:string,keyBytes:string,textEncoding:int,rowid:int,payload:array<string,mixed>}> */
    private array $entries = [];
    private int $position = 0;
    /** @var null|array{lowerInclusive:string,upperBound:?string} */
    private ?array $range;
    private string $operator;
    private string $collation;

    /**
     * @param iterable<array{keyBytes?:mixed,textEncoding?:mixed,rowid?:mixed,payload?:array<string,mixed>}> $entries
     */
    public function __construct(
        iterable $entries,
        private readonly string $pattern,
        string $operator = 'LIKE',
        string $collation = 'BINARY',
        private readonly ?string $escape = null,
        private readonly bool $caseSensitiveLike = false,
    ) {
        $this->operator = strtoupper($operator);
        if (!in_array($this->operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite encoding source cursor operator must be LIKE or GLOB');
        }

        $this->collation = strtoupper($collation);
        if (!in_array($this->collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite encoding source cursor collation: {$collation}");
        }

        $this->range = $this->operator === 'LIKE'
            ? SQLiteLikeCollationPlan::plan($pattern, $this->collation, $escape, $caseSensitiveLike)['range']
            : SQLiteDatabase::globPrefixRangeBounds($pattern);

        foreach ($entries as $entry) {
            if (!is_array($entry) || !isset($entry['payload']) || !is_array($entry['payload'])) {
                throw new \InvalidArgumentException('SQLite encoding source cursor entries need payload arrays');
            }
            if (!array_key_exists('keyBytes', $entry) || !is_string($entry['keyBytes'])) {
                throw new \InvalidArgumentException('SQLite encoding source cursor entries need keyBytes strings');
            }
            if (!array_key_exists('textEncoding', $entry) || !is_int($entry['textEncoding'])) {
                throw new \InvalidArgumentException('SQLite encoding source cursor entries need integer textEncoding');
            }
            if (!array_key_exists('rowid', $entry) || !is_int($entry['rowid'])) {
                throw new \InvalidArgumentException('SQLite encoding source cursor entries need integer rowids');
            }

            $this->entries[] = [
                'key' => self::decodeText($entry['keyBytes'], $entry['textEncoding']),
                'keyBytes' => $entry['keyBytes'],
                'textEncoding' => $entry['textEncoding'],
                'rowid' => $entry['rowid'],
                'payload' => $entry['payload'],
            ];
        }

        usort($this->entries, fn (array $left, array $right): int => $this->compareEntryKeys($left, $right));
        $this->rewind();
    }

    public function rewind(): void
    {
        $this->position = 0;
        if ($this->range === null) {
            return;
        }

        foreach ($this->entries as $position => $entry) {
            if ($this->compareText($entry['key'], $this->range['lowerInclusive']) >= 0) {
                $this->position = $position;
                return;
            }
        }

        $this->position = count($this->entries);
    }

    public function next(): void
    {
        if ($this->position < count($this->entries)) {
            $this->position++;
        }
    }

    /**
     * @return array{
     *   position:int,
     *   eof:bool,
     *   currentRowid:?int,
     *   nextRowid:?int,
     *   currentKey:?string,
     *   nextKey:?string,
     *   currentEncoding:?string,
     *   nextEncoding:?string,
     *   currentBytesHex:?string,
     *   nextBytesHex:?string,
     *   inRange:?bool,
     *   residualMatch:?bool,
     *   nextInRange:?bool,
     *   nextResidualMatch:?bool,
     *   comparisonToLower:?int,
     *   comparisonToUpper:?int,
     *   range:?array{lowerInclusive:string,upperBound:?string},
     *   operator:string,
     *   collation:string,
     *   caseSensitiveLike:bool,
     *   dependencies:list<string>
     * }
     */
    public function currentNextPlan(): array
    {
        $current = $this->entries[$this->position] ?? null;
        $next = $this->entries[$this->position + 1] ?? null;

        return [
            'position' => $this->position,
            'eof' => $current === null,
            'currentRowid' => $current['rowid'] ?? null,
            'nextRowid' => $next['rowid'] ?? null,
            'currentKey' => $current['key'] ?? null,
            'nextKey' => $next['key'] ?? null,
            'currentEncoding' => $current === null ? null : self::encodingName($current['textEncoding']),
            'nextEncoding' => $next === null ? null : self::encodingName($next['textEncoding']),
            'currentBytesHex' => $current === null ? null : bin2hex($current['keyBytes']),
            'nextBytesHex' => $next === null ? null : bin2hex($next['keyBytes']),
            'inRange' => $current === null ? null : $this->inUsableRange($current['key']),
            'residualMatch' => $current === null ? null : $this->matches($current['key']),
            'nextInRange' => $next === null ? null : $this->inUsableRange($next['key']),
            'nextResidualMatch' => $next === null ? null : $this->matches($next['key']),
            'comparisonToLower' => $current === null || $this->range === null ? null : $this->compareText($current['key'], $this->range['lowerInclusive']),
            'comparisonToUpper' => $current === null || $this->range === null || $this->range['upperBound'] === null ? null : $this->compareText($current['key'], $this->range['upperBound']),
            'range' => $this->range,
            'operator' => $this->operator,
            'collation' => $this->collation,
            'caseSensitiveLike' => $this->caseSensitiveLike,
            'dependencies' => ['sqlite-encoding-source-cursor', 'sqlite-like-glob-collation'],
        ];
    }

    /**
     * @return list<array{rowid:int,key:string,keyBytesHex:string,textEncoding:string,payload:array<string,mixed>,position:int}>
     */
    public function matchedRows(): array
    {
        $rows = [];
        foreach ($this->entries as $position => $entry) {
            if (!$this->inUsableRange($entry['key'])) {
                continue;
            }
            if (!$this->matches($entry['key'])) {
                continue;
            }
            $rows[] = self::formatEntryRow($entry, $position);
        }

        return $rows;
    }

    /**
     * @return list<array{rowid:int,key:string,keyBytesHex:string,textEncoding:string,payload:array<string,mixed>,position:int,residualMatch:bool}>
     */
    public function rangedRows(): array
    {
        $rows = [];
        foreach ($this->entries as $position => $entry) {
            if (!$this->inUsableRange($entry['key'])) {
                continue;
            }
            $row = self::formatEntryRow($entry, $position);
            $row['residualMatch'] = $this->matches($entry['key']);
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{rowid:int,key:string,keyBytesHex:string,textEncoding:string,payload:array<string,mixed>,position:int}>
     */
    public static function keyValueRowKeyScan(
        array $rows,
        string $pattern,
        string $operator = 'LIKE',
        string $collation = 'BINARY',
        ?string $escape = null,
        bool $caseSensitiveLike = false,
    ): array {
        $entries = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite encoding source Application scan requires integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite encoding source Application scan requires option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite encoding source Application scan requires integer text_encoding');
            }
            $entries[] = [
                'keyBytes' => $row['option_name_bytes'],
                'textEncoding' => $row['text_encoding'],
                'rowid' => $row['option_id'],
                'payload' => $row,
            ];
        }

        return (new self($entries, $pattern, $operator, $collation, $escape, $caseSensitiveLike))->matchedRows();
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{rowid:int,key:string,keyBytesHex:string,textEncoding:string,payload:array<string,mixed>,position:int,residualMatch:bool}>
     */
    public static function keyValueRowKeyRangeScan(
        array $rows,
        string $pattern,
        string $operator = 'LIKE',
        string $collation = 'BINARY',
        ?string $escape = null,
        bool $caseSensitiveLike = false,
    ): array {
        $entries = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite encoding source Application scan requires integer option_id');
            }
            if (!array_key_exists('option_name_bytes', $row) || !is_string($row['option_name_bytes'])) {
                throw new \InvalidArgumentException('SQLite encoding source Application scan requires option_name_bytes');
            }
            if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                throw new \InvalidArgumentException('SQLite encoding source Application scan requires integer text_encoding');
            }
            $entries[] = [
                'keyBytes' => $row['option_name_bytes'],
                'textEncoding' => $row['text_encoding'],
                'rowid' => $row['option_id'],
                'payload' => $row,
            ];
        }

        return (new self($entries, $pattern, $operator, $collation, $escape, $caseSensitiveLike))->rangedRows();
    }

    public static function encodeText(string $text, int|string $encoding): string
    {
        $encoding = self::normalizeEncoding($encoding);
        if (preg_match('//u', $text) !== 1) {
            throw new \InvalidArgumentException('SQLite encoding source text encoder requires well-formed UTF-8');
        }
        if ($encoding === 1) {
            return $text;
        }

        return self::encodeUtf16($text, $encoding);
    }

    private function compareEntryKeys(array $left, array $right): int
    {
        $comparison = $this->compareText($left['key'], $right['key']);
        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    private function inUsableRange(string $key): bool
    {
        if ($this->range === null) {
            return false;
        }
        if ($this->compareText($key, $this->range['lowerInclusive']) < 0) {
            return false;
        }
        if ($this->range['upperBound'] !== null && $this->compareText($key, $this->range['upperBound']) >= 0) {
            return false;
        }

        return true;
    }

    private function matches(string $key): bool
    {
        return $this->operator === 'LIKE'
            ? SQLiteDatabase::likeMatches($key, $this->pattern, $this->escape, $this->caseSensitiveLike)
            : SQLiteDatabase::globMatches($key, $this->pattern);
    }

    private function compareText(string $left, string $right): int
    {
        return match ($this->collation) {
            'BINARY' => strcmp($left, $right),
            'NOCASE' => strcmp(self::asciiLower($left), self::asciiLower($right)),
            'RTRIM' => strcmp(rtrim($left, ' '), rtrim($right, ' ')),
        };
    }

    /**
     * @param array{key:string,keyBytes:string,textEncoding:int,rowid:int,payload:array<string,mixed>} $entry
     * @return array{rowid:int,key:string,keyBytesHex:string,textEncoding:string,payload:array<string,mixed>,position:int}
     */
    private static function formatEntryRow(array $entry, int $position): array
    {
        return [
            'rowid' => $entry['rowid'],
            'key' => $entry['key'],
            'keyBytesHex' => bin2hex($entry['keyBytes']),
            'textEncoding' => self::encodingName($entry['textEncoding']),
            'payload' => $entry['payload'],
            'position' => $position,
        ];
    }

    public static function decodeText(string $bytes, int $encoding): string
    {
        $encoding = self::normalizeEncoding($encoding);
        if ($encoding === 1) {
            if (preg_match('//u', $bytes) !== 1) {
                throw new \InvalidArgumentException('SQLite encoding source UTF-8 text payload is malformed');
            }
            return $bytes;
        }

        return self::decodeUtf16($bytes, $encoding);
    }

    private static function normalizeEncoding(int|string $encoding): int
    {
        if (is_int($encoding)) {
            if (in_array($encoding, [1, 2, 3], true)) {
                return $encoding;
            }
            throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 1,
            'UTF-16LE', 'UTF16LE' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private static function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    public static function encodingNameForCode(int $encoding): string
    {
        return self::encodingName($encoding);
    }

    private static function encodeUtf16(string $text, int $encoding): string
    {
        $bytes = '';
        foreach (self::utf8Characters($text) as $character) {
            $codepoint = self::utf8Codepoint($character);
            if ($codepoint <= 0xffff) {
                if ($codepoint >= 0xd800 && $codepoint <= 0xdfff) {
                    throw new \InvalidArgumentException('SQLite encoding source rejects surrogate codepoints');
                }
                $bytes .= self::packUint16($codepoint, $encoding);
                continue;
            }

            $offset = $codepoint - 0x10000;
            $bytes .= self::packUint16(0xd800 + ($offset >> 10), $encoding);
            $bytes .= self::packUint16(0xdc00 + ($offset & 0x3ff), $encoding);
        }

        return $bytes;
    }

    private static function decodeUtf16(string $bytes, int $encoding): string
    {
        if (strlen($bytes) % 2 !== 0) {
            throw new \InvalidArgumentException('SQLite encoding source UTF-16 text payload has an odd byte length');
        }

        $text = '';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset += 2) {
            $unit = self::unpackUint16(substr($bytes, $offset, 2), $encoding);
            if ($unit >= 0xd800 && $unit <= 0xdbff) {
                if ($offset + 2 >= $length) {
                    throw new \InvalidArgumentException('SQLite encoding source UTF-16 text payload ends with a high surrogate');
                }
                $low = self::unpackUint16(substr($bytes, $offset + 2, 2), $encoding);
                if ($low < 0xdc00 || $low > 0xdfff) {
                    throw new \InvalidArgumentException('SQLite encoding source UTF-16 text payload has an unpaired high surrogate');
                }
                $text .= self::utf8FromCodepoint(0x10000 + (($unit - 0xd800) << 10) + ($low - 0xdc00));
                $offset += 2;
                continue;
            }
            if ($unit >= 0xdc00 && $unit <= 0xdfff) {
                throw new \InvalidArgumentException('SQLite encoding source UTF-16 text payload has an unpaired low surrogate');
            }
            $text .= self::utf8FromCodepoint($unit);
        }

        return $text;
    }

    /**
     * @return list<string>
     */
    private static function utf8Characters(string $text): array
    {
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            throw new \InvalidArgumentException('SQLite encoding source text encoder requires well-formed UTF-8');
        }

        return $characters;
    }

    private static function packUint16(int $unit, int $encoding): string
    {
        return $encoding === 2
            ? chr($unit & 0xff) . chr($unit >> 8)
            : chr($unit >> 8) . chr($unit & 0xff);
    }

    private static function unpackUint16(string $bytes, int $encoding): int
    {
        $first = ord($bytes[0]);
        $second = ord($bytes[1]);

        return $encoding === 2
            ? $first | ($second << 8)
            : ($first << 8) | $second;
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

    private static function utf8Codepoint(string $character): int
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

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
}
