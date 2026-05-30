<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteGlobCursor
{
    /** @var list<array{key:string,rowid:int,payload:array<string,mixed>}> */
    private array $entries = [];
    private int $position = 0;
    /** @var null|array{lowerInclusive:string,upperBound:?string} */
    private ?array $range;

    /**
     * @param iterable<array{key?:mixed,rowid?:mixed,payload?:array<string,mixed>}> $entries
     */
    public function __construct(
        iterable $entries,
        private readonly string $pattern,
        private readonly string $collation = 'BINARY',
    ) {
        $collation = strtoupper($collation);
        if (!in_array($collation, ['BINARY', 'NOCASE', 'RTRIM'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite GLOB current/next collation: {$this->collation}");
        }
        $this->range = SQLiteDatabase::globPrefixRangeBounds($pattern);
        foreach ($entries as $entry) {
            if (!is_array($entry) || !isset($entry['payload']) || !is_array($entry['payload'])) {
                throw new \InvalidArgumentException('SQLite GLOB current/next cursor entries need payload arrays');
            }
            if (!array_key_exists('key', $entry) || !is_string($entry['key'])) {
                throw new \InvalidArgumentException('SQLite GLOB current/next cursor entries need text keys');
            }
            if (!array_key_exists('rowid', $entry) || !is_int($entry['rowid'])) {
                throw new \InvalidArgumentException('SQLite GLOB current/next cursor entries need integer rowids');
            }
            $this->entries[] = [
                'key' => $entry['key'],
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
     *   inRange:?bool,
     *   residualMatch:?bool,
     *   nextInRange:?bool,
     *   nextResidualMatch:?bool,
     *   patternMalformedUtf8:bool,
     *   currentMalformedUtf8:?bool,
     *   nextMalformedUtf8:?bool,
     *   comparisonToLower:?int,
     *   comparisonToUpper:?int,
     *   nextComparisonToLower:?int,
     *   nextComparisonToUpper:?int,
     *   range:?array{lowerInclusive:string,upperBound:?string},
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
            'currentKey' => $current['key'] ?? null,
            'nextKey' => $next['key'] ?? null,
            'inRange' => $current === null ? null : $this->inUsableRange($current['key']),
            'residualMatch' => $current === null ? null : SQLiteDatabase::globMatches($current['key'], $this->pattern),
            'nextInRange' => $next === null ? null : $this->inUsableRange($next['key']),
            'nextResidualMatch' => $next === null ? null : SQLiteDatabase::globMatches($next['key'], $this->pattern),
            'patternMalformedUtf8' => self::isMalformedUtf8($this->pattern),
            'currentMalformedUtf8' => $current === null ? null : self::isMalformedUtf8($current['key']),
            'nextMalformedUtf8' => $next === null ? null : self::isMalformedUtf8($next['key']),
            'comparisonToLower' => $current === null || $this->range === null ? null : $this->compareText($current['key'], $this->range['lowerInclusive']),
            'comparisonToUpper' => $current === null || $this->range === null || $this->range['upperBound'] === null ? null : $this->compareText($current['key'], $this->range['upperBound']),
            'nextComparisonToLower' => $next === null || $this->range === null ? null : $this->compareText($next['key'], $this->range['lowerInclusive']),
            'nextComparisonToUpper' => $next === null || $this->range === null || $this->range['upperBound'] === null ? null : $this->compareText($next['key'], $this->range['upperBound']),
            'range' => $this->range,
            'collation' => strtoupper($this->collation),
        ];
    }

    /**
     * @return list<array{rowid:int,key:string,payload:array<string,mixed>,position:int,malformedUtf8:bool}>
     */
    public function matchedRows(): array
    {
        $rows = [];
        foreach ($this->entries as $position => $entry) {
            if (!$this->inUsableRange($entry['key'])) {
                continue;
            }
            if (!SQLiteDatabase::globMatches($entry['key'], $this->pattern)) {
                continue;
            }
            $rows[] = [
                'rowid' => $entry['rowid'],
                'key' => $entry['key'],
                'payload' => $entry['payload'],
                'position' => $position,
                'malformedUtf8' => self::isMalformedUtf8($entry['key']),
            ];
        }

        return $rows;
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

    private function compareText(string $left, string $right): int
    {
        return match (strtoupper($this->collation)) {
            'BINARY' => strcmp($left, $right),
            'NOCASE' => strcmp(self::asciiLower($left), self::asciiLower($right)),
            'RTRIM' => strcmp(rtrim($left, ' '), rtrim($right, ' ')),
            default => throw new \InvalidArgumentException("Unsupported SQLite GLOB current/next collation: {$this->collation}"),
        };
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    private static function isMalformedUtf8(string $value): bool
    {
        return preg_match('//u', $value) !== 1;
    }
}
