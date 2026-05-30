<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16LikeGlobCurrentNextCursor
{
    /** @var list<array{keyBytes:string,keyText:string,rowid:int,payload:array<string,mixed>}> */
    private array $entries = [];
    private int $position = 0;
    /** @var null|array{lowerInclusive:string,upperBound:?string} */
    private ?array $range;
    private string $operator;
    private string $encoding;
    private string $collation;

    /**
     * @param iterable<array{keyBytes?:mixed,rowid?:mixed,payload?:array<string,mixed>}> $entries
     */
    public function __construct(
        iterable $entries,
        private readonly string $pattern,
        string $operator = 'LIKE',
        string $encoding = 'UTF-16LE',
        string $collation = 'BINARY',
        private readonly ?string $escape = null,
        private readonly bool $caseSensitiveLike = false,
    ) {
        $this->operator = strtoupper($operator);
        if (!in_array($this->operator, ['LIKE', 'GLOB'], true)) {
            throw new \InvalidArgumentException('SQLite UTF-16 current/next cursor operator must be LIKE or GLOB');
        }

        $this->encoding = strtoupper($encoding);
        if (!in_array($this->encoding, ['UTF-16LE', 'UTF-16BE'], true)) {
            throw new \InvalidArgumentException('SQLite UTF-16 current/next cursor encoding must be UTF-16LE or UTF-16BE');
        }

        $this->collation = strtoupper($collation);
        if (!in_array($this->collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite UTF-16 current/next collation: {$collation}");
        }

        $this->range = $this->operator === 'LIKE'
            ? $this->likeRange($pattern)
            : SQLiteDatabase::globPrefixRangeBounds($pattern);

        foreach ($entries as $entry) {
            if (!is_array($entry) || !isset($entry['payload']) || !is_array($entry['payload'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 current/next cursor entries need payload arrays');
            }
            if (!array_key_exists('keyBytes', $entry) || !is_string($entry['keyBytes'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 current/next cursor entries need keyBytes strings');
            }
            if (!array_key_exists('rowid', $entry) || !is_int($entry['rowid'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 current/next cursor entries need integer rowids');
            }

            $this->entries[] = [
                'keyBytes' => $entry['keyBytes'],
                'keyText' => self::decodeUtf16($entry['keyBytes'], $this->encoding),
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
            if ($this->compareText($entry['keyText'], $this->range['lowerInclusive']) >= 0) {
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
     *   currentText:?string,
     *   nextText:?string,
     *   currentComparisonKey:?string,
     *   nextComparisonKey:?string,
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
     *   encoding:string,
     *   collation:string
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
            'currentText' => $current['keyText'] ?? null,
            'nextText' => $next['keyText'] ?? null,
            'currentComparisonKey' => $current === null ? null : $this->comparisonKey($current['keyText']),
            'nextComparisonKey' => $next === null ? null : $this->comparisonKey($next['keyText']),
            'currentBytesHex' => $current === null ? null : bin2hex($current['keyBytes']),
            'nextBytesHex' => $next === null ? null : bin2hex($next['keyBytes']),
            'inRange' => $current === null ? null : $this->inUsableRange($current['keyText']),
            'residualMatch' => $current === null ? null : $this->matches($current['keyText']),
            'nextInRange' => $next === null ? null : $this->inUsableRange($next['keyText']),
            'nextResidualMatch' => $next === null ? null : $this->matches($next['keyText']),
            'comparisonToLower' => $current === null || $this->range === null ? null : $this->compareText($current['keyText'], $this->range['lowerInclusive']),
            'comparisonToUpper' => $current === null || $this->range === null || $this->range['upperBound'] === null ? null : $this->compareText($current['keyText'], $this->range['upperBound']),
            'range' => $this->range,
            'operator' => $this->operator,
            'encoding' => $this->encoding,
            'collation' => $this->collation,
        ];
    }

    /**
     * @return list<array{rowid:int,keyText:string,keyBytesHex:string,payload:array<string,mixed>,position:int}>
     */
    public function matchedRows(): array
    {
        $rows = [];
        foreach ($this->entries as $position => $entry) {
            if (!$this->inUsableRange($entry['keyText'])) {
                continue;
            }
            if (!$this->matches($entry['keyText'])) {
                continue;
            }
            $rows[] = [
                'rowid' => $entry['rowid'],
                'keyText' => $entry['keyText'],
                'keyBytesHex' => bin2hex($entry['keyBytes']),
                'payload' => $entry['payload'],
                'position' => $position,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{rowid:int,keyText:string,keyBytesHex:string,payload:array<string,mixed>,position:int}>
     */
    public static function optionRowNameScan(
        array $rows,
        string $pattern,
        string $operator = 'LIKE',
        string $encoding = 'UTF-16LE',
        string $collation = 'BINARY',
        ?string $escape = null,
        bool $caseSensitiveLike = false,
    ): array {
        $entries = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 Application option scan requires integer option_id');
            }
            if (!array_key_exists('option_name_utf16', $row) || !is_string($row['option_name_utf16'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 Application option scan requires option_name_utf16 bytes');
            }
            $entries[] = [
                'keyBytes' => $row['option_name_utf16'],
                'rowid' => $row['option_id'],
                'payload' => $row,
            ];
        }

        return (new self($entries, $pattern, $operator, $encoding, $collation, $escape, $caseSensitiveLike))->matchedRows();
    }

    public static function encodeUtf16(string $text, string $encoding): string
    {
        $encoding = strtoupper($encoding);
        if (!in_array($encoding, ['UTF-16LE', 'UTF-16BE'], true)) {
            throw new \InvalidArgumentException('SQLite UTF-16 encoder requires UTF-16LE or UTF-16BE');
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            throw new \InvalidArgumentException('SQLite UTF-16 encoder requires well-formed UTF-8 text');
        }

        $bytes = '';
        foreach ($characters as $character) {
            $codepoint = self::utf8Codepoint($character);
            if ($codepoint <= 0xffff) {
                if ($codepoint >= 0xd800 && $codepoint <= 0xdfff) {
                    throw new \InvalidArgumentException('SQLite UTF-16 encoder rejects surrogate codepoints');
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

    private function likeRange(string $pattern): ?array
    {
        $plan = SQLiteLikeCollationPlan::plan($pattern, $this->collation, $this->escape, $this->caseSensitiveLike);
        return $plan['range'];
    }

    private function matches(string $text): bool
    {
        return $this->operator === 'LIKE'
            ? SQLiteDatabase::likeMatches($text, $this->pattern, $this->escape, $this->caseSensitiveLike)
            : SQLiteDatabase::globMatches($text, $this->pattern);
    }

    private function compareEntryKeys(array $left, array $right): int
    {
        $comparison = $this->compareText($left['keyText'], $right['keyText']);
        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    private function inUsableRange(string $text): bool
    {
        if ($this->range === null) {
            return false;
        }
        if ($this->compareText($text, $this->range['lowerInclusive']) < 0) {
            return false;
        }
        if ($this->range['upperBound'] !== null && $this->compareText($text, $this->range['upperBound']) >= 0) {
            return false;
        }

        return true;
    }

    private function compareText(string $left, string $right): int
    {
        return strcmp($this->comparisonKey($left), $this->comparisonKey($right));
    }

    private function comparisonKey(string $text): string
    {
        return match ($this->collation) {
            'BINARY' => $text,
            'NOCASE' => self::asciiLower($text),
            'RTRIM' => rtrim($text, ' '),
        };
    }

    private static function decodeUtf16(string $bytes, string $encoding): string
    {
        if (strlen($bytes) % 2 !== 0) {
            throw new \InvalidArgumentException('SQLite UTF-16 text payload has an odd byte length');
        }

        $text = '';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset += 2) {
            $unit = self::unpackUint16(substr($bytes, $offset, 2), $encoding);
            if ($unit >= 0xd800 && $unit <= 0xdbff) {
                if ($offset + 2 >= $length) {
                    throw new \InvalidArgumentException('SQLite UTF-16 text payload ends with a high surrogate');
                }
                $low = self::unpackUint16(substr($bytes, $offset + 2, 2), $encoding);
                if ($low < 0xdc00 || $low > 0xdfff) {
                    throw new \InvalidArgumentException('SQLite UTF-16 text payload has an unpaired high surrogate');
                }
                $text .= self::utf8FromCodepoint(0x10000 + (($unit - 0xd800) << 10) + ($low - 0xdc00));
                $offset += 2;
                continue;
            }
            if ($unit >= 0xdc00 && $unit <= 0xdfff) {
                throw new \InvalidArgumentException('SQLite UTF-16 text payload has an unpaired low surrogate');
            }
            $text .= self::utf8FromCodepoint($unit);
        }

        return $text;
    }

    private static function packUint16(int $unit, string $encoding): string
    {
        return $encoding === 'UTF-16LE'
            ? chr($unit & 0xff) . chr($unit >> 8)
            : chr($unit >> 8) . chr($unit & 0xff);
    }

    private static function unpackUint16(string $bytes, string $encoding): int
    {
        $first = ord($bytes[0]);
        $second = ord($bytes[1]);

        return $encoding === 'UTF-16LE'
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
