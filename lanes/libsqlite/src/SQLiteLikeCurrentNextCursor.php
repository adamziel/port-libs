<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteLikeCurrentNextCursor
{
    /** @var list<array{key:string,rowid:int,payload:array<string,mixed>}> */
    private array $entries = [];
    private int $position = 0;

    /**
     * @param iterable<array{key?:mixed,rowid?:mixed,payload?:array<string,mixed>}> $entries
     */
    public function __construct(
        iterable $entries,
        private readonly string $pattern,
        private readonly string $collation = 'NOCASE',
        private readonly ?string $escape = null,
        private readonly bool $caseSensitiveLike = false,
    ) {
        SQLiteLikeCollationPlan::plan($pattern, $collation, $escape, $caseSensitiveLike);
        foreach ($entries as $entry) {
            if (!is_array($entry) || !isset($entry['payload']) || !is_array($entry['payload'])) {
                throw new \InvalidArgumentException('SQLite LIKE current/next cursor entries need payload arrays');
            }
            if (!array_key_exists('key', $entry) || !is_string($entry['key'])) {
                throw new \InvalidArgumentException('SQLite LIKE current/next cursor entries need text keys');
            }
            if (!array_key_exists('rowid', $entry) || !is_int($entry['rowid'])) {
                throw new \InvalidArgumentException('SQLite LIKE current/next cursor entries need integer rowids');
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
        $plan = SQLiteLikeCollationPlan::plan($this->pattern, $this->collation, $this->escape, $this->caseSensitiveLike);
        if ($plan['range'] === null) {
            return;
        }

        foreach ($this->entries as $position => $entry) {
            if ($this->compareText($entry['key'], $plan['range']['lowerInclusive']) >= 0) {
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
     *   comparisonToLower:?int,
     *   comparisonToUpper:?int,
     *   nextComparisonToLower:?int,
     *   nextComparisonToUpper:?int,
     *   patternMalformedUtf8:bool,
     *   currentMalformedUtf8:?bool,
     *   nextMalformedUtf8:?bool,
     *   rejectedReason:?string,
     *   range:?array{lowerInclusive:string,upperBound:?string},
     *   collation:string,
     *   caseSensitiveLike:bool
     * }
     */
    public function currentNextPlan(): array
    {
        $plan = SQLiteLikeCollationPlan::plan($this->pattern, $this->collation, $this->escape, $this->caseSensitiveLike);
        $current = $this->entries[$this->position] ?? null;
        $next = $this->entries[$this->position + 1] ?? null;

        return [
            'position' => $this->position,
            'eof' => $current === null,
            'currentRowid' => $current['rowid'] ?? null,
            'nextRowid' => $next['rowid'] ?? null,
            'currentKey' => $current['key'] ?? null,
            'nextKey' => $next['key'] ?? null,
            'inRange' => $current === null ? null : $this->inUsableRange($current['key'], $plan),
            'residualMatch' => $current === null ? null : SQLiteDatabase::likeMatches($current['key'], $this->pattern, $this->escape, $this->caseSensitiveLike),
            'nextInRange' => $next === null ? null : $this->inUsableRange($next['key'], $plan),
            'nextResidualMatch' => $next === null ? null : SQLiteDatabase::likeMatches($next['key'], $this->pattern, $this->escape, $this->caseSensitiveLike),
            'comparisonToLower' => $current === null || $plan['range'] === null ? null : $this->compareText($current['key'], $plan['range']['lowerInclusive']),
            'comparisonToUpper' => $current === null || $plan['range'] === null || $plan['range']['upperBound'] === null ? null : $this->compareText($current['key'], $plan['range']['upperBound']),
            'nextComparisonToLower' => $next === null || $plan['range'] === null ? null : $this->compareText($next['key'], $plan['range']['lowerInclusive']),
            'nextComparisonToUpper' => $next === null || $plan['range'] === null || $plan['range']['upperBound'] === null ? null : $this->compareText($next['key'], $plan['range']['upperBound']),
            'patternMalformedUtf8' => self::isMalformedUtf8($this->pattern),
            'currentMalformedUtf8' => $current === null ? null : self::isMalformedUtf8($current['key']),
            'nextMalformedUtf8' => $next === null ? null : self::isMalformedUtf8($next['key']),
            'rejectedReason' => $plan['rejectedReason'],
            'range' => $plan['range'],
            'collation' => $plan['collation'],
            'caseSensitiveLike' => $plan['caseSensitiveLike'],
        ];
    }

    /**
     * @return list<array{rowid:int,key:string,payload:array<string,mixed>,position:int,malformedUtf8:bool}>
     */
    public function matchedRows(): array
    {
        $plan = SQLiteLikeCollationPlan::plan($this->pattern, $this->collation, $this->escape, $this->caseSensitiveLike);
        $rows = [];
        foreach ($this->entries as $position => $entry) {
            if (!$this->inUsableRange($entry['key'], $plan)) {
                continue;
            }
            if (!SQLiteDatabase::likeMatches($entry['key'], $this->pattern, $this->escape, $this->caseSensitiveLike)) {
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

    private function inUsableRange(string $key, array $plan): bool
    {
        if ($plan['range'] === null) {
            return false;
        }
        if ($this->compareText($key, $plan['range']['lowerInclusive']) < 0) {
            return false;
        }
        if ($plan['range']['upperBound'] !== null && $this->compareText($key, $plan['range']['upperBound']) >= 0) {
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
            default => throw new \InvalidArgumentException("Unsupported SQLite LIKE current/next collation: {$this->collation}"),
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
