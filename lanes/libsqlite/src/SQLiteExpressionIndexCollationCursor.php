<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteExpressionIndexCollationCursor
{
    /** @var list<array{key:list<mixed>,rowid:int,payload:array<string,mixed>,sequence:int}> */
    private array $entries;
    private int $position = 0;

    /**
     * @param list<array{key:list<mixed>,rowid?:int,payload?:array<string,mixed>}> $entries
     * @param list<array{expression:string,collation?:string,affinity?:string,descending?:bool}> $terms
     * @param array<string, callable(string, string): int> $customCollations
     */
    public function __construct(
        array $entries,
        private array $terms,
        private array $customCollations = [],
    ) {
        if (!array_is_list($entries)) {
            throw new \InvalidArgumentException('SQLite expression index cursor entries must be a list');
        }
        if ($terms === [] || !array_is_list($terms)) {
            throw new \InvalidArgumentException('SQLite expression index cursor terms must be a non-empty list');
        }

        $normalizedTerms = [];
        foreach ($terms as $term) {
            if (!isset($term['expression']) || !is_string($term['expression']) || $term['expression'] === '') {
                throw new \InvalidArgumentException('SQLite expression index cursor term needs an expression name');
            }
            $normalizedTerms[] = [
                'expression' => $term['expression'],
                'collation' => strtoupper((string) ($term['collation'] ?? 'BINARY')),
                'affinity' => strtoupper((string) ($term['affinity'] ?? 'NONE')),
                'descending' => (bool) ($term['descending'] ?? false),
            ];
        }
        $this->terms = $normalizedTerms;
        $this->customCollations = self::normalizeCustomCollations($customCollations);

        $normalized = [];
        foreach ($entries as $sequence => $entry) {
            if (!isset($entry['key']) || !array_is_list($entry['key'])) {
                throw new \InvalidArgumentException('SQLite expression index cursor entry key must be a list');
            }
            if (count($entry['key']) < count($this->terms)) {
                throw new \InvalidArgumentException('SQLite expression index cursor entry key is narrower than expression terms');
            }

            $normalized[] = [
                'key' => $entry['key'],
                'rowid' => (int) ($entry['rowid'] ?? $sequence + 1),
                'payload' => $entry['payload'] ?? [],
                'sequence' => $sequence,
            ];
        }

        usort($normalized, function (array $left, array $right): int {
            $comparison = $this->compareKeys($left['key'], $right['key']);
            if ($comparison !== 0) {
                return $comparison;
            }

            $rowid = $left['rowid'] <=> $right['rowid'];

            return $rowid !== 0 ? $rowid : ($left['sequence'] <=> $right['sequence']);
        });

        $this->entries = $normalized;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function next(): void
    {
        if ($this->position < count($this->entries)) {
            $this->position++;
        }
    }

    public function eof(): bool
    {
        return $this->position >= count($this->entries);
    }

    /**
     * @param list<mixed> $probe
     */
    public function seekGreaterOrEqual(array $probe): bool
    {
        if (!array_is_list($probe) || $probe === []) {
            throw new \InvalidArgumentException('SQLite expression index cursor probe must be a non-empty list');
        }
        if (count($probe) > count($this->terms)) {
            throw new \InvalidArgumentException('SQLite expression index cursor probe is wider than expression terms');
        }

        foreach ($this->entries as $position => $entry) {
            if ($this->comparePrefix($entry['key'], $probe) >= 0) {
                $this->position = $position;

                return true;
            }
        }

        $this->position = count($this->entries);

        return false;
    }

    /**
     * @param list<mixed> $probe
     * @return list<array{key:list<mixed>,rowid:int,payload:array<string,mixed>,sequence:int}>
     */
    public function yieldEqual(array $probe): array
    {
        if (!$this->seekGreaterOrEqual($probe)) {
            return [];
        }

        $rows = [];
        while (!$this->eof() && $this->comparePrefix($this->entries[$this->position]['key'], $probe) === 0) {
            $rows[] = $this->entries[$this->position];
            $this->next();
        }

        return $rows;
    }

    /**
     * @return array{position:int,eof:bool,current:array<string,mixed>|null,next:array<string,mixed>|null,comparison:int|null,decidingExpression:string|null,decidingCollation:string|null,decidingDescending:bool|null,currentRowid:int|null,nextRowid:int|null,currentKey:list<mixed>|null,nextKey:list<mixed>|null}
     */
    public function currentNextPlan(): array
    {
        $current = $this->entries[$this->position] ?? null;
        $next = $this->entries[$this->position + 1] ?? null;
        $decision = $current !== null && $next !== null ? $this->compareKeysWithDecision($current['key'], $next['key']) : null;

        return [
            'position' => $this->position,
            'eof' => $current === null,
            'current' => $current,
            'next' => $next,
            'comparison' => $decision['comparison'] ?? null,
            'decidingExpression' => $decision['expression'] ?? null,
            'decidingCollation' => $decision['collation'] ?? null,
            'decidingDescending' => $decision['descending'] ?? null,
            'currentRowid' => $current['rowid'] ?? null,
            'nextRowid' => $next['rowid'] ?? null,
            'currentKey' => $current['key'] ?? null,
            'nextKey' => $next['key'] ?? null,
        ];
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     */
    private function compareKeys(array $left, array $right): int
    {
        return $this->compareKeysWithDecision($left, $right)['comparison'];
    }

    /**
     * @param list<mixed> $left
     * @param list<mixed> $right
     * @return array{comparison:int,expression:string|null,collation:string|null,descending:bool|null}
     */
    private function compareKeysWithDecision(array $left, array $right): array
    {
        foreach ($this->terms as $index => $term) {
            $comparison = $this->compareValues($left[$index] ?? null, $right[$index] ?? null, $term);
            if ($comparison !== 0) {
                return [
                    'comparison' => $term['descending'] ? -$comparison : $comparison,
                    'expression' => $term['expression'],
                    'collation' => $term['collation'],
                    'descending' => $term['descending'],
                ];
            }
        }

        return ['comparison' => 0, 'expression' => null, 'collation' => null, 'descending' => null];
    }

    /**
     * @param list<mixed> $key
     * @param list<mixed> $probe
     */
    private function comparePrefix(array $key, array $probe): int
    {
        foreach ($probe as $index => $value) {
            $term = $this->terms[$index];
            $comparison = $this->compareValues($key[$index] ?? null, $value, $term);
            if ($comparison !== 0) {
                return $term['descending'] ? -$comparison : $comparison;
            }
        }

        return 0;
    }

    /**
     * @param array{expression:string,collation:string,affinity:string,descending:bool} $term
     */
    private function compareValues(mixed $left, mixed $right, array $term): int
    {
        [$left, $right] = $this->applyAffinity($left, $right, $term['affinity']);
        $leftRank = self::storageRank($left);
        $rightRank = self::storageRank($right);
        if ($leftRank !== $rightRank) {
            return $leftRank <=> $rightRank;
        }
        if ($left === null && $right === null) {
            return 0;
        }
        if (is_int($left) || is_float($left)) {
            return $left <=> $right;
        }
        if (is_string($left) && is_string($right)) {
            return $this->compareText($left, $right, $term['collation']);
        }

        throw new \InvalidArgumentException('SQLite expression index cursor values use an unsupported storage class');
    }

    /**
     * @return array{0:mixed,1:mixed}
     */
    private function applyAffinity(mixed $left, mixed $right, string $affinity): array
    {
        if ($affinity !== 'INTEGER' && $affinity !== 'NUMERIC') {
            return [$left, $right];
        }

        return [self::numericIfPossible($left), self::numericIfPossible($right)];
    }

    private static function numericIfPossible(mixed $value): mixed
    {
        if (!is_string($value) || !is_numeric($value)) {
            return $value;
        }

        return str_contains($value, '.') ? (float) $value : (int) $value;
    }

    private function compareText(string $left, string $right, string $collation): int
    {
        if (isset($this->customCollations[$collation])) {
            $comparison = ($this->customCollations[$collation])($left, $right);
            if (!is_int($comparison)) {
                throw new \InvalidArgumentException("SQLite custom collation {$collation} must return an integer");
            }

            return $comparison <=> 0;
        }

        return match ($collation) {
            'BINARY' => strcmp($left, $right),
            'NOCASE' => strcmp(self::asciiLower($left), self::asciiLower($right)),
            'RTRIM' => strcmp(rtrim($left, ' '), rtrim($right, ' ')),
            default => throw new \InvalidArgumentException("Unsupported SQLite expression index collation: {$collation}"),
        };
    }

    private static function storageRank(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }
        if (is_int($value) || is_float($value)) {
            return 1;
        }
        if (is_string($value)) {
            return 2;
        }

        return 3;
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /**
     * @param array<string, callable(string, string): int> $customCollations
     * @return array<string, callable(string, string): int>
     */
    private static function normalizeCustomCollations(array $customCollations): array
    {
        $normalized = [];
        foreach ($customCollations as $name => $compare) {
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('SQLite expression index custom collation names must be non-empty strings');
            }
            if (!is_callable($compare)) {
                throw new \InvalidArgumentException("SQLite expression index custom collation {$name} must be callable");
            }
            $normalized[strtoupper($name)] = $compare;
        }

        return $normalized;
    }
}
