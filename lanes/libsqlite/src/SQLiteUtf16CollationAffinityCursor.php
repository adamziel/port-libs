<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16CollationAffinityCursor
{
    /** @var list<array{value:mixed,valueBytes:?string,textEncoding:?int,rowid:int,payload:array<string,mixed>}> */
    private array $entries = [];
    private int $position = 0;
    private mixed $probe;
    private string $leftAffinity;
    private string $rightAffinity;
    private string $collation;

    /**
     * @param iterable<array{value?:mixed,valueBytes?:mixed,textEncoding?:mixed,rowid?:mixed,payload?:array<string,mixed>}> $entries
     */
    public function __construct(
        iterable $entries,
        mixed $probe,
        string $leftAffinity = 'TEXT',
        string $rightAffinity = 'TEXT',
        string $collation = 'BINARY',
    ) {
        $this->probe = self::decodeValue($probe, 'probe')['value'];
        $this->leftAffinity = strtoupper($leftAffinity);
        $this->rightAffinity = strtoupper($rightAffinity);
        $this->collation = strtoupper($collation);

        SQLiteAffinityComparison::compare(null, null, $this->leftAffinity, $this->rightAffinity, $this->collation);

        foreach ($entries as $entry) {
            if (!is_array($entry) || !isset($entry['payload']) || !is_array($entry['payload'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 affinity cursor entries need payload arrays');
            }
            if (!array_key_exists('rowid', $entry) || !is_int($entry['rowid'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 affinity cursor entries need integer rowids');
            }

            $decoded = self::decodeValue($entry, 'entry');
            $this->entries[] = [
                'value' => $decoded['value'],
                'valueBytes' => $decoded['valueBytes'],
                'textEncoding' => $decoded['textEncoding'],
                'rowid' => $entry['rowid'],
                'payload' => $entry['payload'],
            ];
        }

        usort($this->entries, fn (array $left, array $right): int => $this->compareEntryValues($left, $right));
        $this->seek($this->probe);
    }

    public function seek(mixed $probe): void
    {
        $this->probe = self::decodeValue($probe, 'probe')['value'];
        $this->position = count($this->entries);
        foreach ($this->entries as $position => $entry) {
            $comparison = $this->compareForCursor($entry['value'], $this->probe);
            if ($comparison === null || $comparison >= 0) {
                $this->position = $position;
                return;
            }
        }
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
     *   currentValue:mixed,
     *   nextValue:mixed,
     *   currentStorage:?string,
     *   nextStorage:?string,
     *   currentEncoding:?string,
     *   nextEncoding:?string,
     *   currentBytesHex:?string,
     *   nextBytesHex:?string,
     *   comparisonToProbe:?int,
     *   nextComparisonToProbe:?int,
     *   currentCoerced:mixed,
     *   probeCoerced:mixed,
     *   currentCoercedStorage:?string,
     *   probeCoercedStorage:?string,
     *   currentEqualsProbe:?bool,
     *   leftAffinity:string,
     *   rightAffinity:string,
     *   collation:string,
     *   dependencies:list<string>
     * }
     */
    public function currentNextPlan(): array
    {
        $current = $this->entries[$this->position] ?? null;
        $next = $this->entries[$this->position + 1] ?? null;
        $currentCoerced = $current === null ? null : self::applyCursorAffinity($current['value'], $this->leftAffinity);
        $probeCoerced = $current === null ? null : self::applyCursorAffinity($this->probe, $this->leftAffinity);
        $comparison = $current === null ? null : $this->compareForCursor($current['value'], $this->probe);

        return [
            'position' => $this->position,
            'eof' => $current === null,
            'currentRowid' => $current['rowid'] ?? null,
            'nextRowid' => $next['rowid'] ?? null,
            'currentValue' => $current['value'] ?? null,
            'nextValue' => $next['value'] ?? null,
            'currentStorage' => $current === null ? null : SQLiteAffinityComparison::storageClass($current['value']),
            'nextStorage' => $next === null ? null : SQLiteAffinityComparison::storageClass($next['value']),
            'currentEncoding' => $current === null ? null : self::encodingName($current['textEncoding']),
            'nextEncoding' => $next === null ? null : self::encodingName($next['textEncoding']),
            'currentBytesHex' => $current === null || $current['valueBytes'] === null ? null : bin2hex($current['valueBytes']),
            'nextBytesHex' => $next === null || $next['valueBytes'] === null ? null : bin2hex($next['valueBytes']),
            'comparisonToProbe' => $comparison,
            'nextComparisonToProbe' => $next === null ? null : $this->compareForCursor($next['value'], $this->probe),
            'currentCoerced' => $currentCoerced,
            'probeCoerced' => $probeCoerced,
            'currentCoercedStorage' => $currentCoerced === null ? null : SQLiteAffinityComparison::storageClass($currentCoerced),
            'probeCoercedStorage' => $probeCoerced === null ? null : SQLiteAffinityComparison::storageClass($probeCoerced),
            'currentEqualsProbe' => $comparison === null ? null : $comparison === 0,
            'leftAffinity' => $this->leftAffinity,
            'rightAffinity' => $this->rightAffinity,
            'collation' => $this->collation,
            'dependencies' => ['sqlite-utf16-decode', 'sqlite-affinity-comparison'],
        ];
    }

    /**
     * @return list<array{rowid:int,value:mixed,storage:string,encoding:?string,valueBytesHex:?string,comparisonToProbe:?int,payload:array<string,mixed>,position:int}>
     */
    public function remaining(): array
    {
        $rows = [];
        foreach (array_slice($this->entries, $this->position, null, true) as $position => $entry) {
            $rows[] = [
                'rowid' => $entry['rowid'],
                'value' => $entry['value'],
                'storage' => SQLiteAffinityComparison::storageClass($entry['value']),
                'encoding' => self::encodingName($entry['textEncoding']),
                'valueBytesHex' => $entry['valueBytes'] === null ? null : bin2hex($entry['valueBytes']),
                'comparisonToProbe' => $this->compareForCursor($entry['value'], $this->probe),
                'payload' => $entry['payload'],
                'position' => $position,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{rowid:int,value:mixed,storage:string,encoding:?string,valueBytesHex:?string,comparisonToProbe:?int,payload:array<string,mixed>,position:int}>
     */
    public static function optionRowValueSeek(
        array $rows,
        mixed $probe,
        string $leftAffinity = 'TEXT',
        string $rightAffinity = 'TEXT',
        string $collation = 'BINARY',
    ): array {
        $entries = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id']) || !is_int($row['option_id'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 affinity Application seek requires integer option_id');
            }
            if (!array_key_exists('option_value_bytes', $row) && !array_key_exists('option_value', $row)) {
                throw new \InvalidArgumentException('SQLite UTF-16 affinity Application seek requires option_value or option_value_bytes');
            }
            $entry = [
                'rowid' => $row['option_id'],
                'payload' => $row,
            ];
            if (array_key_exists('option_value_bytes', $row)) {
                if (!is_string($row['option_value_bytes'])) {
                    throw new \InvalidArgumentException('SQLite UTF-16 affinity Application seek requires string option_value_bytes');
                }
                if (!isset($row['text_encoding']) || !is_int($row['text_encoding'])) {
                    throw new \InvalidArgumentException('SQLite UTF-16 affinity Application seek requires integer text_encoding');
                }
                $entry['valueBytes'] = $row['option_value_bytes'];
                $entry['textEncoding'] = $row['text_encoding'];
            } else {
                $entry['value'] = $row['option_value'];
            }
            $entries[] = $entry;
        }

        return (new self($entries, $probe, $leftAffinity, $rightAffinity, $collation))->remaining();
    }

    public static function encodeText(string $text, int|string $encoding): string
    {
        $encoding = self::normalizeEncoding($encoding);
        if (preg_match('//u', $text) !== 1) {
            throw new \InvalidArgumentException('SQLite UTF-16 affinity encoder requires well-formed UTF-8');
        }
        if ($encoding === 1) {
            return $text;
        }

        $bytes = '';
        foreach (self::utf8Characters($text) as $character) {
            $codepoint = self::utf8Codepoint($character);
            if ($codepoint <= 0xffff) {
                if ($codepoint >= 0xd800 && $codepoint <= 0xdfff) {
                    throw new \InvalidArgumentException('SQLite UTF-16 affinity encoder rejects surrogate codepoints');
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

    private function compareEntryValues(array $left, array $right): int
    {
        $comparison = $this->compareForCursor($left['value'], $right['value']);
        return $comparison !== 0 ? (int) $comparison : $left['rowid'] <=> $right['rowid'];
    }

    private function compareForCursor(mixed $left, mixed $right): ?int
    {
        $comparison = SQLiteAffinityComparison::compare(
            self::applyCursorAffinity($left, $this->leftAffinity),
            self::applyCursorAffinity($right, $this->leftAffinity),
            'NONE',
            'NONE',
            $this->collation,
        );

        return $comparison === null ? null : $comparison <=> 0;
    }

    private static function applyCursorAffinity(mixed $value, string $affinity): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }
        $normalized = strtoupper($affinity);
        if (in_array($normalized, ['INT', 'INTEGER', 'REAL', 'FLOAT', 'DOUBLE', 'NUM', 'NUMERIC', 'BOOLEAN', 'DATE', 'DATETIME'], true)) {
            $text = $value instanceof SQLiteBlobValue ? $value->bytes : $value;
            $trimmed = trim($text);
            if (preg_match('/^[+-]?(?:(?:[0-9]+(?:\.[0-9]*)?)|(?:\.[0-9]+))(?:[eE][+-]?[0-9]+)?$/', $trimmed) !== 1) {
                return $value;
            }
            if (preg_match('/^[+-]?[0-9]+$/', $trimmed) === 1 && self::integerLiteralFitsInt64($trimmed)) {
                return (int) $trimmed;
            }
            $real = (float) $trimmed;

            return is_finite($real) && floor($real) === $real && preg_match('/[.eE]/', $trimmed) === 1 && self::integerLiteralFitsInt64(sprintf('%.0F', $real)) ? (int) $real : $real;
        }
        if (in_array($normalized, ['CHAR', 'CLOB', 'VARCHAR', 'TEXT'], true)) {
            if ($value instanceof SQLiteBlobValue || is_string($value)) {
                return $value;
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }

            return (string) $value;
        }
        if (in_array($normalized, ['BLOB', 'NONE', ''], true)) {
            return $value;
        }

        throw new \InvalidArgumentException("SQLite comparison affinity {$affinity} is not supported");
    }

    /**
     * @return array{value:mixed,valueBytes:?string,textEncoding:?int}
     */
    private static function decodeValue(mixed $value, string $context): array
    {
        if (is_array($value)) {
            if (array_key_exists('valueBytes', $value)) {
                if (!is_string($value['valueBytes'])) {
                    throw new \InvalidArgumentException("SQLite UTF-16 affinity {$context} valueBytes must be a string");
                }
                if (!array_key_exists('textEncoding', $value) || !is_int($value['textEncoding'])) {
                    throw new \InvalidArgumentException("SQLite UTF-16 affinity {$context} textEncoding must be an integer");
                }
                $encoding = self::normalizeEncoding($value['textEncoding']);
                return [
                    'value' => self::decodeText($value['valueBytes'], $encoding),
                    'valueBytes' => $value['valueBytes'],
                    'textEncoding' => $encoding,
                ];
            }
            if (array_key_exists('value', $value)) {
                return ['value' => $value['value'], 'valueBytes' => null, 'textEncoding' => null];
            }
        }

        return ['value' => $value, 'valueBytes' => null, 'textEncoding' => null];
    }

    private static function decodeText(string $bytes, int $encoding): string
    {
        $encoding = self::normalizeEncoding($encoding);
        if ($encoding === 1) {
            if (preg_match('//u', $bytes) !== 1) {
                throw new \InvalidArgumentException('SQLite UTF-16 affinity UTF-8 text payload is malformed');
            }
            return $bytes;
        }
        if (strlen($bytes) % 2 !== 0) {
            throw new \InvalidArgumentException('SQLite UTF-16 affinity text payload has an odd byte length');
        }

        $text = '';
        $length = strlen($bytes);
        for ($offset = 0; $offset < $length; $offset += 2) {
            $unit = self::unpackUint16(substr($bytes, $offset, 2), $encoding);
            if ($unit >= 0xd800 && $unit <= 0xdbff) {
                if ($offset + 2 >= $length) {
                    throw new \InvalidArgumentException('SQLite UTF-16 affinity text payload ends with a high surrogate');
                }
                $low = self::unpackUint16(substr($bytes, $offset + 2, 2), $encoding);
                if ($low < 0xdc00 || $low > 0xdfff) {
                    throw new \InvalidArgumentException('SQLite UTF-16 affinity text payload has an unpaired high surrogate');
                }
                $text .= self::utf8FromCodepoint(0x10000 + (($unit - 0xd800) << 10) + ($low - 0xdc00));
                $offset += 2;
                continue;
            }
            if ($unit >= 0xdc00 && $unit <= 0xdfff) {
                throw new \InvalidArgumentException('SQLite UTF-16 affinity text payload has an unpaired low surrogate');
            }
            $text .= self::utf8FromCodepoint($unit);
        }

        return $text;
    }

    private static function normalizeEncoding(int|string|null $encoding): ?int
    {
        if ($encoding === null) {
            return null;
        }
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

    private static function integerLiteralFitsInt64(string $literal): bool
    {
        $literal = trim($literal);
        $negative = str_starts_with($literal, '-');
        $digits = ltrim($literal, '+-');
        $digits = ltrim($digits, '0');
        if ($digits === '') {
            return true;
        }

        $limit = $negative ? '9223372036854775808' : '9223372036854775807';
        $length = strlen($digits);
        $limitLength = strlen($limit);
        if ($length !== $limitLength) {
            return $length < $limitLength;
        }

        return strcmp($digits, $limit) <= 0;
    }

    private static function encodingName(?int $encoding): ?string
    {
        return match ($encoding) {
            null => null,
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    /**
     * @return list<string>
     */
    private static function utf8Characters(string $text): array
    {
        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            throw new \InvalidArgumentException('SQLite UTF-16 affinity encoder requires well-formed UTF-8');
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
}
