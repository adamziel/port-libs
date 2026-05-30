<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16LikeGlobAffinityCurrentSourceCursor
{
    /** @var list<array{key:mixed,text:?string,bytes:?string,textEncoding:int,rowid:int,payload:array<string,mixed>}> */
    private array $entries = [];
    private int $position = 0;
    /** @var null|array{lowerInclusive:string,upperBound:?string} */
    private ?array $range;
    private string $operator;
    private string $collation;

    /**
     * @param iterable<array{key?:mixed,textEncoding?:mixed,rowid?:mixed,payload?:array<string,mixed>}> $entries
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
            throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity cursor operator must be LIKE or GLOB');
        }
        $this->collation = strtoupper($collation);
        if (!in_array($this->collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite UTF-16 LIKE/GLOB affinity collation: {$collation}");
        }

        $this->range = $this->operator === 'LIKE'
            ? SQLiteLikeCollationPlan::plan($pattern, $this->collation, $escape, $caseSensitiveLike)['range']
            : SQLiteDatabase::globPrefixRangeBounds($pattern);

        foreach ($entries as $entry) {
            if (!is_array($entry) || !array_key_exists('key', $entry) || !array_key_exists('rowid', $entry)) {
                throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity cursor entries require key and rowid');
            }
            if (!is_int($entry['rowid'])) {
                throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity cursor rowid must be an integer');
            }
            $payload = $entry['payload'] ?? [];
            if (!is_array($payload)) {
                throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity cursor payload must be an array');
            }
            $encoding = $this->normalizeEncoding($entry['textEncoding'] ?? 2);
            $text = $this->coerceTextLikeOperand($entry['key']);
            $this->entries[] = [
                'key' => $entry['key'],
                'text' => $text,
                'bytes' => $text === null ? null : SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding),
                'textEncoding' => $encoding,
                'rowid' => $entry['rowid'],
                'payload' => $payload,
            ];
        }

        usort($this->entries, fn (array $left, array $right): int => $this->compareEntryKeys($left, $right));
        $this->rewind();
    }

    public function rewind(): void
    {
        $this->position = count($this->entries);
        if ($this->range === null) {
            return;
        }

        foreach ($this->entries as $position => $entry) {
            if ($entry['text'] !== null && $this->compareText($entry['text'], $this->range['lowerInclusive']) >= 0) {
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
     * @return array<string,mixed>
     */
    public function currentNextPlan(): array
    {
        $current = $this->entries[$this->position] ?? null;
        $next = $this->entries[$this->position + 1] ?? null;

        return [
            'position' => $this->position,
            'eof' => $current === null,
            'operator' => $this->operator,
            'collation' => $this->collation,
            'caseSensitiveLike' => $this->caseSensitiveLike,
            'range' => $this->range,
            'currentRowid' => $current['rowid'] ?? null,
            'currentOriginalStorage' => $current === null ? null : SQLiteAffinityComparison::storageClass($current['key']),
            'currentText' => $current['text'] ?? null,
            'currentEncoding' => $current === null ? null : $this->encodingName($current['textEncoding']),
            'currentBytesHex' => $current === null || $current['bytes'] === null ? null : bin2hex($current['bytes']),
            'currentInRange' => $current === null ? null : $this->inUsableRange($current['text']),
            'currentResidualMatch' => $current === null ? null : $this->matches($current['text']),
            'currentLowerComparison' => $current === null || $current['text'] === null || $this->range === null ? null : $this->sign($this->compareText($current['text'], $this->range['lowerInclusive'])),
            'currentUpperComparison' => $current === null || $current['text'] === null || $this->range === null || $this->range['upperBound'] === null ? null : $this->sign($this->compareText($current['text'], $this->range['upperBound'])),
            'nextRowid' => $next['rowid'] ?? null,
            'nextOriginalStorage' => $next === null ? null : SQLiteAffinityComparison::storageClass($next['key']),
            'nextText' => $next['text'] ?? null,
            'nextEncoding' => $next === null ? null : $this->encodingName($next['textEncoding']),
            'nextBytesHex' => $next === null || $next['bytes'] === null ? null : bin2hex($next['bytes']),
            'nextInRange' => $next === null ? null : $this->inUsableRange($next['text']),
            'nextResidualMatch' => $next === null ? null : $this->matches($next['text']),
            'dependencies' => ['sqlite-text-affinity', 'sqlite-utf16-encoding', 'sqlite-like-glob-collation'],
        ];
    }

    /**
     * @return list<array{rowid:int,text:string,originalStorage:string,bytesHex:string,textEncoding:string,payload:array<string,mixed>,position:int}>
     */
    public function matchedRows(): array
    {
        $rows = [];
        foreach ($this->entries as $position => $entry) {
            if (!$this->inUsableRange($entry['text']) || !$this->matches($entry['text'])) {
                continue;
            }
            $rows[] = [
                'rowid' => $entry['rowid'],
                'text' => $entry['text'],
                'originalStorage' => SQLiteAffinityComparison::storageClass($entry['key']),
                'bytesHex' => bin2hex($entry['bytes'] ?? ''),
                'textEncoding' => $this->encodingName($entry['textEncoding']),
                'payload' => $entry['payload'],
                'position' => $position,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{rowid:int,text:string,originalStorage:string,bytesHex:string,textEncoding:string,payload:array<string,mixed>,position:int}>
     */
    public static function keyValueRowValueScan(
        array $rows,
        string $column,
        string $pattern,
        string $operator = 'LIKE',
        string $collation = 'BINARY',
        ?string $escape = null,
        bool $caseSensitiveLike = false,
        int|string $textEncoding = 'UTF-16LE',
    ): array {
        $entries = [];
        foreach ($rows as $index => $row) {
            if (!array_key_exists($column, $row)) {
                throw new \InvalidArgumentException("SQLite UTF-16 Application option scan row is missing {$column}");
            }
            $entries[] = [
                'key' => $row[$column],
                'textEncoding' => $textEncoding,
                'rowid' => is_int($row['option_id'] ?? null) ? $row['option_id'] : $index + 1,
                'payload' => $row,
            ];
        }

        return (new self($entries, $pattern, $operator, $collation, $escape, $caseSensitiveLike))->matchedRows();
    }

    private function compareEntryKeys(array $left, array $right): int
    {
        if ($left['text'] === null || $right['text'] === null) {
            return ($left['text'] === null ? 0 : 1) <=> ($right['text'] === null ? 0 : 1)
                ?: $left['rowid'] <=> $right['rowid'];
        }
        $comparison = $this->compareText($left['text'], $right['text']);

        return $comparison !== 0 ? $comparison : $left['rowid'] <=> $right['rowid'];
    }

    private function inUsableRange(?string $text): bool
    {
        if ($text === null || $this->range === null) {
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

    private function matches(?string $text): bool
    {
        if ($text === null) {
            return false;
        }

        return $this->operator === 'LIKE'
            ? SQLiteDatabase::likeMatches($text, $this->pattern, $this->escape, $this->caseSensitiveLike)
            : SQLiteDatabase::globMatches($text, $this->pattern);
    }

    private function compareText(string $left, string $right): int
    {
        return match ($this->collation) {
            'BINARY' => strcmp($left, $right),
            'NOCASE' => strcmp($this->asciiLower($left), $this->asciiLower($right)),
            'RTRIM' => strcmp(rtrim($left, ' '), rtrim($right, ' ')),
        };
    }

    private function coerceTextLikeOperand(mixed $value): ?string
    {
        if ($value === null || $value instanceof SQLiteBlobValue) {
            return null;
        }
        if (is_string($value)) {
            if (preg_match('//u', $value) !== 1) {
                throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity cursor text requires well-formed UTF-8 before encoding');
            }

            return $value;
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return $this->coerceFloatTextLikeOperand($value);
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity cursor key must be scalar text-affinity input');
    }

    private function normalizeEncoding(int|string $encoding): int
    {
        if (is_int($encoding)) {
            if (in_array($encoding, [1, 2, 3], true)) {
                return $encoding;
            }
            throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity cursor encoding must be UTF-8, UTF-16LE, or UTF-16BE');
        }

        return match (strtoupper(str_replace('_', '-', $encoding))) {
            'UTF-8', 'UTF8' => 1,
            'UTF-16LE', 'UTF16LE' => 2,
            'UTF-16BE', 'UTF16BE' => 3,
            default => throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity cursor encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private function encodingName(int $encoding): string
    {
        return match ($encoding) {
            1 => 'UTF-8',
            2 => 'UTF-16LE',
            3 => 'UTF-16BE',
            default => throw new \InvalidArgumentException('SQLite UTF-16 LIKE/GLOB affinity cursor encoding must be UTF-8, UTF-16LE, or UTF-16BE'),
        };
    }

    private function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private function coerceFloatTextLikeOperand(float $value): string
    {
        $text = sprintf('%.15G', $value);
        if (str_contains($text, '.')) {
            $text = rtrim(rtrim($text, '0'), '.');
        }

        return $text;
    }

    private function sign(int $comparison): int
    {
        return $comparison <=> 0;
    }
}
