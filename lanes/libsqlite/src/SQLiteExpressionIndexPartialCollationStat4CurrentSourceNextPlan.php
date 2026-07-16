<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteExpressionIndexPartialCollationStat4CurrentSourceNextPlan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @param list<array{expression:string,direction?:string,collation?:string}> $orderBy
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $predicate, array $orderBy): array
    {
        $preparedPlan = self::sourcePlan($preparedSource, $predicate, $orderBy);
        $currentPlan = self::sourcePlan($currentSource, $predicate, $orderBy);

        $preparedCookie = self::nonNegativeInt($preparedSource, 'schemaCookie');
        $currentCookie = self::nonNegativeInt($currentSource, 'schemaCookie');
        $preparedStat4 = self::nonNegativeInt($preparedSource, 'stat4Generation');
        $currentStat4 = self::nonNegativeInt($currentSource, 'stat4Generation');
        $preparedSignature = self::indexSignature($preparedSource);
        $currentSignature = self::indexSignature($currentSource);
        $stale = $preparedCookie !== $currentCookie
            || $preparedStat4 !== $currentStat4
            || $preparedSignature !== $currentSignature;
        $selected = $stale ? $currentPlan : $preparedPlan;
        $ready = ($selected['usable'] ?? false) === true
            && ($selected['partialPredicateImplied'] ?? false) === true
            && ($selected['orderBySatisfied'] ?? false) === true
            && ($selected['stat4Used'] ?? false) === true;

        return [
            'status' => $ready ? 'expression-index-partial-collation-stat4-current-source-ready' : 'requires-next-stage',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => $preparedCookie !== $currentCookie,
            'stat4GenerationChanged' => $preparedStat4 !== $currentStat4,
            'indexSignatureChanged' => $preparedSignature !== $currentSignature,
            'preparedSource' => self::sourceSummary($preparedSource, $preparedPlan, $preparedSignature),
            'currentSource' => self::sourceSummary($currentSource, $currentPlan, $currentSignature),
            'selectedPlan' => $selected,
            'partialCollationStat4Plan' => $ready,
            'tableLookupDeferred' => true,
            'tempSortElided' => $ready,
            'residualPredicateRequired' => true,
            'cursorTape' => [
                'source' => $stale ? 'current' : 'prepared',
                'indexName' => $selected['name'] ?? null,
                'rootPage' => $selected['rootPage'] ?? null,
                'expression' => $selected['expression'] ?? null,
                'collation' => $selected['collation'] ?? null,
                'seekOpcode' => self::seekOpcode($selected),
                'stopOpcode' => self::stopOpcode($selected),
                'nextOpcode' => self::nextOpcode($orderBy),
                'scanDirection' => self::scanDirection($orderBy),
                'rangeLower' => $selected['rangeLower'] ?? null,
                'rangeUpper' => $selected['rangeUpper'] ?? null,
                'rangeLowerExact' => ($selected['lowerInclusive'] ?? false) === true,
                'rangeUpperExact' => ($selected['upperInclusive'] ?? false) === true,
                'stat4CurrentNext' => $selected['stat4CurrentNext'] ?? [],
                'stat4MatchedCurrentNext' => $selected['stat4MatchedCurrentNext'] ?? [],
                'stat4RangeCurrentNext' => $selected['stat4RangeCurrentNext'] ?? null,
                'matchedKeys' => $selected['matchedKeys'] ?? [],
                'matchedRowids' => $selected['matchedRowids'] ?? [],
                'program' => self::program($ready, $selected),
            ],
            'currentSourceFence' => [
                'schemaCookie' => $currentCookie,
                'stat4Generation' => $currentStat4,
                'indexSignature' => $currentSignature,
                'orderSignature' => self::orderSignature($orderBy),
            ],
            'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                . ' PARTIAL COLLATION STAT4 EXPRESSION INDEX '
                . (string) ($selected['name'] ?? 'NO INDEX'),
            'dependencies' => [
                'SQLiteCreateIndex expression/collation parsing',
                'SQLiteAffinityComparison collation comparisons',
                'sqlite-expression-index-partial-collation-stat4-current-source-next114',
            ],
            'dependency_closure' => 'no new support component needed; next114 composes native expression-index parsing, partial-index proof, built-in collation comparison, and STAT4 sample diagnostics',
            'non_overlap' => 'avoids accepted expression-index range-cost ranking and covering ORDER BY current-source materialization by asserting partial expression-index STAT4 boundaries under per-index collation after current-source reprepare',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $predicate
     * @param list<array{expression:string,direction?:string,collation?:string}> $orderBy
     * @return array<string,mixed>
     */
    private static function sourcePlan(array $source, array $predicate, array $orderBy): array
    {
        self::validateOrderBy($orderBy);
        $plans = [];
        foreach (self::listValue($source, 'indexes') as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite partial collation STAT4 source indexes must be arrays');
            }
            $sql = self::stringValue($index, 'sql');
            $expressionKind = 'lower';
            $expression = SQLiteCreateIndex::firstLowerExpression($sql);
            if ($expression === null) {
                $expressionKind = 'upper';
                $expression = SQLiteCreateIndex::firstUpperExpression($sql);
            }
            if ($expression === null) {
                continue;
            }
            $expressionSql = $expressionKind . '(' . $expression->columnName . ')';
            $range = self::expressionRange($predicate, strtolower($expression->columnName), strtolower($expressionSql));
            $partialImplied = $expression->partialPredicate !== null && self::partialPredicateImplied($expression->partialPredicate, $predicate);
            if ($range === null || !$partialImplied) {
                continue;
            }

            $samples = self::stat4Samples(self::listValue($index, 'stat4Samples'), $expression->collation, $range);
            $orderSatisfied = self::orderSatisfied($orderBy, $expression, $expressionSql);
            $rows = max(1, array_sum(array_map(static fn (array $sample): int => $sample['neq'], $samples['matched'])));
            $plans[] = [
                'usable' => true,
                'name' => self::stringValue($index, 'name', self::indexName($sql)),
                'rootPage' => self::nonNegativeInt($index, 'rootPage'),
                'expression' => $expressionSql,
                'expressionColumn' => $expression->columnName,
                'collation' => strtoupper($expression->collation),
                'descending' => $expression->descending,
                'partial' => true,
                'partialPredicateImplied' => true,
                'rangeLower' => $range['lower'],
                'rangeUpper' => $range['upper'],
                'lowerInclusive' => $range['lowerInclusive'],
                'upperInclusive' => $range['upperInclusive'],
                'orderBySatisfied' => $orderSatisfied,
                'stat4Used' => $samples['all'] !== [],
                'stat4MatchedSamples' => count($samples['matched']),
                'stat4Estimate' => $rows,
                'estimatedRows' => $rows,
                'estimatedCost' => max(1, $rows + ($orderSatisfied ? 0 : 25)),
                'stat4CurrentNext' => self::currentNext($samples['all']),
                'stat4MatchedCurrentNext' => self::currentNext($samples['matched']),
                'stat4RangeCurrentNext' => self::rangeCurrentNext($samples['all'], $range, $expression->collation),
                'matchedKeys' => array_map(static fn (array $sample): mixed => $sample['key'], $samples['matched']),
                'matchedRowids' => array_map(static fn (array $sample): int => $sample['rowid'], $samples['matched']),
                'detail' => 'SEARCH ' . self::stringValue($index, 'name', self::indexName($sql)) . ' USING PARTIAL EXPRESSION STAT4 COLLATION RANGE',
            ];
        }

        usort($plans, static fn (array $left, array $right): int => [
            $left['estimatedCost'],
            $left['name'],
        ] <=> [
            $right['estimatedCost'],
            $right['name'],
        ]);

        return $plans[0] ?? [
            'usable' => false,
            'partialPredicateImplied' => false,
            'orderBySatisfied' => false,
            'stat4Used' => false,
            'detail' => 'SCAN TABLE; NO PARTIAL COLLATION STAT4 EXPRESSION INDEX',
        ];
    }

    /**
     * @return array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool}|null
     */
    private static function expressionRange(array $predicate, string $column, string $expression): ?array
    {
        $range = ['lower' => null, 'upper' => null, 'lowerInclusive' => false, 'upperInclusive' => false];
        foreach (self::flattenAndTerms($predicate) as $term) {
            $left = self::predicateExpression($term['left'] ?? null);
            if ($left !== $expression) {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if ($operator === '>=') {
                $range['lower'] = self::literal($term['right'] ?? null);
                $range['lowerInclusive'] = true;
            } elseif ($operator === '>') {
                $range['lower'] = self::literal($term['right'] ?? null);
                $range['lowerInclusive'] = false;
            } elseif ($operator === '<=') {
                $range['upper'] = self::literal($term['right'] ?? null);
                $range['upperInclusive'] = true;
            } elseif ($operator === '<') {
                $range['upper'] = self::literal($term['right'] ?? null);
                $range['upperInclusive'] = false;
            } elseif ($operator === 'BETWEEN') {
                $range['lower'] = self::literal($term['lower'] ?? null);
                $range['upper'] = self::literal($term['upper'] ?? null);
                $range['lowerInclusive'] = true;
                $range['upperInclusive'] = true;
            }
        }

        return $range['lower'] === null && $range['upper'] === null ? null : $range;
    }

    /**
     * @param list<array<string,mixed>> $samples
     * @param array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool} $range
     * @return array{all:list<array{key:mixed,neq:int,nlt:int,ndlt:int,rowid:int}>,matched:list<array{key:mixed,neq:int,nlt:int,ndlt:int,rowid:int}>}
     */
    private static function stat4Samples(array $samples, string $collation, array $range): array
    {
        $normalized = [];
        foreach ($samples as $offset => $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite partial collation STAT4 samples must be arrays');
            }
            $sampleValues = $sample['sample'] ?? null;
            if (!is_array($sampleValues) || !array_is_list($sampleValues) || $sampleValues === []) {
                throw new \InvalidArgumentException('SQLite partial collation STAT4 sample must contain key values');
            }
            $normalized[] = [
                'key' => self::literal($sampleValues[0]),
                'neq' => self::firstStatInt($sample['neq'] ?? null, 'neq'),
                'nlt' => self::firstStatInt($sample['nlt'] ?? null, 'nlt', true),
                'ndlt' => self::firstStatInt($sample['ndlt'] ?? 0, 'ndlt', true),
                'rowid' => (int) ($sampleValues[1] ?? $offset + 1),
            ];
        }
        usort($normalized, static fn (array $left, array $right): int => self::compare($left['key'], $right['key'], $collation) ?: ($left['rowid'] <=> $right['rowid']));

        $matched = array_values(array_filter($normalized, static fn (array $sample): bool => self::insideRange($sample['key'], $range, $collation)));

        return ['all' => $normalized, 'matched' => $matched];
    }

    /**
     * @param array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool} $range
     */
    private static function insideRange(mixed $key, array $range, string $collation): bool
    {
        $lower = $range['lower'] === null ? 1 : self::compare($key, $range['lower'], $collation);
        $upper = $range['upper'] === null ? -1 : self::compare($key, $range['upper'], $collation);
        $lowerOk = $range['lowerInclusive'] ? $lower >= 0 : $lower > 0;
        $upperOk = $range['upperInclusive'] ? $upper <= 0 : $upper < 0;

        return $lowerOk && $upperOk;
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,rowid:int}> $samples
     * @return list<array{current:array<string,mixed>,next:array<string,mixed>|null}>
     */
    private static function currentNext(array $samples): array
    {
        $pairs = [];
        foreach ($samples as $offset => $sample) {
            $pairs[] = [
                'current' => self::sampleSummary($sample),
                'next' => isset($samples[$offset + 1]) ? self::sampleSummary($samples[$offset + 1]) : null,
            ];
        }

        return $pairs;
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,rowid:int}> $samples
     * @param array{lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool} $range
     * @return array<string,mixed>
     */
    private static function rangeCurrentNext(array $samples, array $range, string $collation): array
    {
        return [
            'lower' => self::boundary($samples, $range['lower'], 'lower', $collation),
            'upper' => self::boundary($samples, $range['upper'], 'upper', $collation),
            'lowerInclusive' => $range['lowerInclusive'],
            'upperInclusive' => $range['upperInclusive'],
            'collation' => strtoupper($collation),
        ];
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,rowid:int}> $samples
     * @return array{current:array<string,mixed>|null,next:array<string,mixed>|null,side:string,value:mixed,exact:bool}|null
     */
    private static function boundary(array $samples, mixed $value, string $side, string $collation): ?array
    {
        if ($value === null) {
            return null;
        }
        $previous = null;
        foreach ($samples as $offset => $sample) {
            $comparison = self::compare($sample['key'], $value, $collation);
            if ($comparison >= 0) {
                return [
                    'current' => $comparison === 0 ? self::sampleSummary($sample) : ($previous === null ? null : self::sampleSummary($previous)),
                    'next' => $comparison === 0
                        ? (isset($samples[$offset + 1]) ? self::sampleSummary($samples[$offset + 1]) : null)
                        : self::sampleSummary($sample),
                    'side' => $side,
                    'value' => $value,
                    'exact' => $comparison === 0,
                ];
            }
            $previous = $sample;
        }

        return [
            'current' => $previous === null ? null : self::sampleSummary($previous),
            'next' => null,
            'side' => $side,
            'value' => $value,
            'exact' => false,
        ];
    }

    /**
     * @param array{key:mixed,neq:int,nlt:int,ndlt:int,rowid:int} $sample
     * @return array{key:mixed,neq:int,nlt:int,ndlt:int,rowid:int}
     */
    private static function sampleSummary(array $sample): array
    {
        return [
            'key' => $sample['key'],
            'neq' => $sample['neq'],
            'nlt' => $sample['nlt'],
            'ndlt' => $sample['ndlt'],
            'rowid' => $sample['rowid'],
        ];
    }

    private static function compare(mixed $left, mixed $right, string $collation): int
    {
        return SQLiteAffinityComparison::compare($left, $right, 'TEXT', 'TEXT', $collation) ?? 0;
    }

    private static function firstStatInt(mixed $value, string $field, bool $allowZero = false): int
    {
        if (is_string($value) && preg_match('/^\d+(?:\s+\d+)*$/', trim($value)) === 1) {
            $parts = preg_split('/\s+/', trim($value));
            $value = (int) ($parts[0] ?? 0);
        } elseif (is_array($value) && array_is_list($value)) {
            $value = $value[0] ?? null;
        }
        if (!is_int($value) || $value < ($allowZero ? 0 : 1)) {
            throw new \InvalidArgumentException("SQLite partial collation STAT4 {$field} must start with an integer");
        }

        return $value;
    }

    private static function partialPredicateImplied(SQLiteIndexPredicate $partial, array $predicate): bool
    {
        foreach (self::flattenAndTerms($predicate) as $term) {
            $left = $term['left'] ?? null;
            if (!is_array($left) || !isset($left['column']) || !is_string($left['column'])) {
                continue;
            }
            $operator = (string) ($term['operator'] ?? '');
            if (
                strcasecmp($left['column'], $partial->columnName) === 0
                && (($operator === '=' && $partial->operator === SQLiteIndexPredicate::EQUALS) || $operator === $partial->operator)
                && ($term['right'] ?? null) === $partial->value
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function flattenAndTerms(array $predicate): array
    {
        if (strtoupper((string) ($predicate['operator'] ?? '')) !== 'AND') {
            return [$predicate];
        }
        $terms = $predicate['terms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite partial collation STAT4 predicate needs AND terms');
        }
        $flattened = [];
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite partial collation STAT4 predicates must be arrays');
            }
            array_push($flattened, ...self::flattenAndTerms($term));
        }

        return $flattened;
    }

    private static function predicateExpression(mixed $value): ?string
    {
        if (!is_array($value)) {
            return null;
        }
        if (isset($value['expression']) && is_string($value['expression'])) {
            return strtolower(preg_replace('/\s+/', '', $value['expression']));
        }
        if (($value['function'] ?? null) === 'lower' && isset($value['column']) && is_string($value['column'])) {
            return 'lower(' . strtolower($value['column']) . ')';
        }
        if (($value['function'] ?? null) === 'upper' && isset($value['column']) && is_string($value['column'])) {
            return 'upper(' . strtolower($value['column']) . ')';
        }

        return null;
    }

    /**
     * @param list<array{expression:string,direction?:string,collation?:string}> $orderBy
     */
    private static function orderSatisfied(array $orderBy, SQLiteIndexColumn $expression, string $expressionSql): bool
    {
        if ($orderBy === []) {
            return false;
        }
        $first = $orderBy[0];
        $term = strtolower(preg_replace('/\s+/', '', (string) ($first['expression'] ?? '')));
        $direction = strtoupper((string) ($first['direction'] ?? 'ASC'));
        $collation = strtoupper((string) ($first['collation'] ?? $expression->collation));

        return $term === strtolower($expressionSql)
            && $direction === ($expression->descending ? 'DESC' : 'ASC')
            && $collation === strtoupper($expression->collation);
    }

    /**
     * @param list<array{expression:string,direction?:string,collation?:string}> $orderBy
     */
    private static function validateOrderBy(array $orderBy): void
    {
        foreach ($orderBy as $term) {
            if (!isset($term['expression']) || !is_string($term['expression']) || $term['expression'] === '') {
                throw new \InvalidArgumentException('SQLite partial collation STAT4 ORDER BY needs expression terms');
            }
            $direction = strtoupper((string) ($term['direction'] ?? 'ASC'));
            if ($direction !== 'ASC' && $direction !== 'DESC') {
                throw new \InvalidArgumentException('SQLite partial collation STAT4 ORDER BY direction must be ASC or DESC');
            }
        }
    }

    /**
     * @param array<string,mixed> $plan
     * @return list<array<string,mixed>>
     */
    private static function program(bool $ready, array $plan): array
    {
        $program = [
            ['opcode' => 'OpenRead', 'rootPage' => $plan['rootPage'] ?? null, 'index' => $plan['name'] ?? null],
            ['opcode' => self::seekOpcode($plan), 'key' => $plan['rangeLower'] ?? null, 'collation' => $plan['collation'] ?? 'BINARY'],
            ['opcode' => self::stopOpcode($plan), 'key' => $plan['rangeUpper'] ?? null, 'collation' => $plan['collation'] ?? 'BINARY'],
            ['opcode' => 'Column', 'source' => 'index', 'column' => $plan['expressionColumn'] ?? null],
            ['opcode' => self::nextOpcode([['expression' => (string) ($plan['expression'] ?? ''), 'direction' => ($plan['descending'] ?? false) ? 'DESC' : 'ASC']])],
        ];
        if (!$ready) {
            $program[] = ['opcode' => 'SorterOpen', 'reason' => 'order-by-not-satisfied'];
        }

        return $program;
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function seekOpcode(array $plan): string
    {
        if (($plan['rangeLower'] ?? null) === null) {
            return 'Rewind';
        }

        return ($plan['lowerInclusive'] ?? false) === true ? 'SeekGE' : 'SeekGT';
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function stopOpcode(array $plan): string
    {
        if (($plan['rangeUpper'] ?? null) === null) {
            return 'Eof';
        }

        return ($plan['upperInclusive'] ?? false) === true ? 'IdxGT' : 'IdxGE';
    }

    /**
     * @param list<array{expression:string,direction?:string,collation?:string}> $orderBy
     */
    private static function nextOpcode(array $orderBy): string
    {
        return strtoupper((string) ($orderBy[0]['direction'] ?? 'ASC')) === 'DESC' ? 'Prev' : 'Next';
    }

    /**
     * @param list<array{expression:string,direction?:string,collation?:string}> $orderBy
     */
    private static function scanDirection(array $orderBy): string
    {
        return strtoupper((string) ($orderBy[0]['direction'] ?? 'ASC')) === 'DESC' ? 'descending' : 'ascending';
    }

    /**
     * @param list<array{expression:string,direction?:string,collation?:string}> $orderBy
     */
    private static function orderSignature(array $orderBy): string
    {
        return implode(', ', array_map(static fn (array $term): string => (string) $term['expression'] . ' ' . strtoupper((string) ($term['direction'] ?? 'ASC')) . ' COLLATE ' . strtoupper((string) ($term['collation'] ?? 'BINARY')), $orderBy));
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function indexSignature(array $source): string
    {
        $parts = [];
        foreach (self::listValue($source, 'indexes') as $index) {
            if (!is_array($index)) {
                continue;
            }
            $parts[] = self::stringValue($index, 'name', '') . '|' . self::nonNegativeInt($index, 'rootPage') . '|' . self::stringValue($index, 'sql') . '|' . hash('sha256', serialize($index['stat4Samples'] ?? []));
        }
        sort($parts, SORT_STRING);

        return hash('sha256', implode("\n", $parts));
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $plan
     * @return array<string,mixed>
     */
    private static function sourceSummary(array $source, array $plan, string $signature): array
    {
        return [
            'name' => self::stringValue($source, 'name'),
            'schemaCookie' => self::nonNegativeInt($source, 'schemaCookie'),
            'stat4Generation' => self::nonNegativeInt($source, 'stat4Generation'),
            'indexSignature' => $signature,
            'indexName' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'estimatedRows' => $plan['estimatedRows'] ?? null,
            'stat4MatchedSamples' => $plan['stat4MatchedSamples'] ?? 0,
            'detail' => $plan['detail'] ?? null,
        ];
    }

    private static function literal(mixed $value): mixed
    {
        if (is_array($value) && array_key_exists('value', $value)) {
            return $value['value'];
        }

        return $value;
    }

    private static function indexName(string $sql): string
    {
        if (preg_match('/CREATE\s+(?:UNIQUE\s+)?INDEX\s+(?:IF\s+NOT\s+EXISTS\s+)?(?:"([^"]+)"|`([^`]+)`|\[([^\]]+)\]|([A-Za-z_][A-Za-z0-9_]*))/i', $sql, $matches) !== 1) {
            return 'expression-index';
        }

        return $matches[1] ?: ($matches[2] ?: ($matches[3] ?: $matches[4]));
    }

    /**
     * @return list<mixed>
     */
    private static function listValue(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException("SQLite partial collation STAT4 needs list {$key}");
        }

        return $value;
    }

    private static function stringValue(array $data, string $key, ?string $default = null): string
    {
        $value = $data[$key] ?? $default;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite partial collation STAT4 needs string {$key}");
        }

        return $value;
    }

    private static function nonNegativeInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite partial collation STAT4 needs non-negative integer {$key}");
        }

        return $value;
    }
}
