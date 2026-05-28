<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext178Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @param array{expression:string,direction?:string,collation?:string}|null $orderBy
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $whereTerms,
        array $neededColumns,
        ?array $orderBy = null
    ): array {
        if ($whereTerms === []) {
            throw new \InvalidArgumentException('SQLite next178 WHERE terms cannot be empty');
        }
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next178 needed columns cannot be empty');
        }
        $orderBy ??= ['expression' => 'lower(option_name)', 'direction' => 'DESC', 'collation' => 'BINARY'];

        $prepared = self::sourcePlan($preparedSource, $whereTerms, $neededColumns, $orderBy);
        $current = self::sourcePlan($currentSource, $whereTerms, $neededColumns, $orderBy);
        $preparedSignature = self::signature($preparedSource);
        $currentSignature = self::signature($currentSource);
        $stale = $preparedSignature !== $currentSignature
            || self::intValue($preparedSource['schemaCookie'] ?? null) !== self::intValue($currentSource['schemaCookie'] ?? null)
            || self::intValue($preparedSource['stat4Generation'] ?? null) !== self::intValue($currentSource['stat4Generation'] ?? null);
        $selected = $stale ? $current : $prepared;
        $source = $stale ? $currentSource : $preparedSource;
        $ready = ($selected['usable'] ?? false) === true
            && ($selected['partialPredicateImplied'] ?? false) === true
            && ($selected['orderBySatisfiedByIndex'] ?? false) === true
            && ($selected['matchedRows'] ?? []) !== [];

        return [
            'status' => $ready ? 'stat4-expression-partial-current-source-next178-ready' : 'requires-next-stage',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => self::intValue($preparedSource['schemaCookie'] ?? null) !== self::intValue($currentSource['schemaCookie'] ?? null),
            'stat4GenerationChanged' => self::intValue($preparedSource['stat4Generation'] ?? null) !== self::intValue($currentSource['stat4Generation'] ?? null),
            'sourceSignatureChanged' => $preparedSignature !== $currentSignature,
            'preparedPlan' => self::summary($preparedSource, $prepared, $preparedSignature),
            'currentPlan' => self::summary($currentSource, $current, $currentSignature),
            'selectedPlan' => self::selectedSummary($selected, $ready),
            'matchedRows' => $selected['matchedRows'] ?? [],
            'matchedRowids' => array_column($selected['matchedRows'] ?? [], 'rowid'),
            'matchedExpressionKeys' => array_column($selected['matchedRows'] ?? [], 'expressionKey'),
            'orderBy' => [
                'expression' => self::normalizeExpression((string) ($orderBy['expression'] ?? '')),
                'direction' => self::direction($orderBy['direction'] ?? 'ASC'),
                'collation' => strtoupper((string) ($orderBy['collation'] ?? 'BINARY')),
            ],
            'orderBySatisfiedByIndex' => (bool) ($selected['orderBySatisfiedByIndex'] ?? false),
            'stalePreparedRowidsBlockedByOrderFence' => array_values(array_diff(
                array_column($prepared['matchedRows'] ?? [], 'rowid'),
                array_column($current['matchedRows'] ?? [], 'rowid'),
            )),
            'currentSourceRowidsAdmittedByOrderFence' => array_values(array_diff(
                array_column($current['matchedRows'] ?? [], 'rowid'),
                array_column($prepared['matchedRows'] ?? [], 'rowid'),
            )),
            'orderFenceChanged' => self::signature($prepared['orderFence'] ?? []) !== self::signature($current['orderFence'] ?? []),
            'stat4Fence' => [
                'schemaCookie' => self::intValue($source['schemaCookie'] ?? null),
                'stat4Generation' => self::intValue($source['stat4Generation'] ?? null),
                'sourceSignature' => $stale ? $currentSignature : $preparedSignature,
                'rangeSignature' => self::signature($selected['rangeConstraint'] ?? []),
                'orderSignature' => self::signature($selected['orderFence'] ?? []),
                'stat4SampleSignature' => self::signature($selected['stat4Samples'] ?? []),
                'rowStreamSignature' => self::signature(array_column($selected['matchedRows'] ?? [], 'rowid')),
            ],
            'cursorProgram' => self::cursorProgram($selected, $ready),
            'tableLookupRequired' => !($selected['covering'] ?? false),
            'temporarySortRequired' => !($selected['orderBySatisfiedByIndex'] ?? false),
            'residualPredicateRequired' => true,
            'detail' => (($stale ? 'REPREPARE' : 'REUSE') . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT178 ORDER FENCE ' . (string) ($selected['name'] ?? 'NO INDEX')),
            'dependencies' => ['sqlite-sqlplanner-stat4-expression-partial-current-source-next178'],
            'dependency_closure' => 'no new support component needed; next178 reuses lane-local expression normalization, partial predicate proof, STAT4 fences, and current-source row diagnostics',
            'non_overlap' => 'avoids accepted next164 range proof, next169 full-index cost, next173 duplicate fanout, next175 LIKE prefix windows, expression ORDER BY text execution, and range-cost ranking; this slice is the current-source ORDER fence for partial expression STAT4 scans',
        ];
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @param list<string> $neededColumns
     * @param array<string,mixed> $orderBy
     * @return array<string,mixed>
     */
    private static function sourcePlan(array $source, array $terms, array $neededColumns, array $orderBy): array
    {
        $best = null;
        foreach (self::listValue($source['indexes'] ?? []) as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next178 indexes must be arrays');
            }
            $expression = self::stringValue($index, 'expression');
            $range = self::expressionRange($terms, $expression);
            $stat4 = self::stat4Samples(self::listValue($index['stat4Samples'] ?? []));
            $partial = self::listValue($index['partialPredicateTerms'] ?? []);
            $partialImplied = $range !== null && self::partialPredicateImplied($partial, $terms, $range);
            $matchedSamples = $range === null ? [] : self::matchingSamples($stat4, $range, (string) ($index['collation'] ?? 'BINARY'));
            $orderSatisfied = self::orderSatisfied($index, $orderBy);
            $matchedRows = $partialImplied && $matchedSamples !== [] && $orderSatisfied
                ? self::matchedRows($source, $terms, $expression, (string) ($index['collation'] ?? 'BINARY'), self::direction($orderBy['direction'] ?? 'ASC'))
                : [];
            $covering = self::covers($index['coveringColumns'] ?? [], $neededColumns);
            $estimate = max(1, array_sum(array_column($matchedSamples, 'neq')));
            $plan = [
                'usable' => $range !== null && $partialImplied && $matchedSamples !== [] && $orderSatisfied && $matchedRows !== [],
                'name' => self::stringValue($index, 'name'),
                'rootPage' => self::intValue($index['rootPage'] ?? null),
                'expression' => $expression,
                'expressionColumn' => (string) ($index['expressionColumn'] ?? ''),
                'collation' => strtoupper((string) ($index['collation'] ?? 'BINARY')),
                'direction' => self::direction($index['direction'] ?? 'ASC'),
                'coveringColumns' => self::stringList($index['coveringColumns'] ?? []),
                'covering' => $covering,
                'partialPredicateTerms' => $partial,
                'partialPredicateImplied' => $partialImplied,
                'rangeConstraint' => $range,
                'stat4Samples' => $stat4,
                'stat4Used' => $stat4 !== [],
                'matchedStat4Keys' => array_column($matchedSamples, 'key'),
                'matchedStat4Rowids' => array_column($matchedSamples, 'rowid'),
                'matchedRows' => $matchedRows,
                'orderBySatisfiedByIndex' => $orderSatisfied,
                'orderFence' => [
                    'direction' => self::direction($orderBy['direction'] ?? 'ASC'),
                    'collation' => strtoupper((string) ($orderBy['collation'] ?? 'BINARY')),
                    'rowids' => array_column($matchedRows, 'rowid'),
                    'keys' => array_column($matchedRows, 'expressionKey'),
                ],
                'estimatedRows' => $estimate,
                'estimatedCost' => $estimate + ($covering ? 0 : 12) + ($orderSatisfied ? 0 : 80),
                'detail' => 'SEARCH ' . self::stringValue($index, 'name') . ' USING STAT4 PARTIAL EXPRESSION ORDER FENCE',
            ];
            if ($best === null || [
                $plan['usable'] ? 0 : 1,
                $plan['estimatedCost'],
                $plan['name'],
            ] < [
                $best['usable'] ? 0 : 1,
                $best['estimatedCost'],
                $best['name'],
            ]) {
                $best = $plan;
            }
        }

        return $best ?? ['usable' => false, 'matchedRows' => [], 'stat4Used' => false, 'partialPredicateImplied' => false, 'orderBySatisfiedByIndex' => false];
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return array{lower:mixed,lowerInclusive:bool,upper:mixed,upperInclusive:bool}|null
     */
    private static function expressionRange(array $terms, string $expression): ?array
    {
        $normalized = self::normalizeExpression($expression);
        $range = ['lower' => null, 'lowerInclusive' => false, 'upper' => null, 'upperInclusive' => false];
        foreach ($terms as $term) {
            if (self::normalizeExpression((string) ($term['left']['expression'] ?? '')) !== $normalized) {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $right = self::literal($term['right'] ?? null);
            if (($operator === '>=' || $operator === '>') && ($range['lower'] === null || self::compare($right, $range['lower'], 'BINARY') > 0)) {
                $range['lower'] = $right;
                $range['lowerInclusive'] = $operator === '>=';
            }
            if (($operator === '<=' || $operator === '<') && ($range['upper'] === null || self::compare($right, $range['upper'], 'BINARY') < 0)) {
                $range['upper'] = $right;
                $range['upperInclusive'] = $operator === '<=';
            }
        }

        return $range['lower'] !== null && $range['upper'] !== null ? $range : null;
    }

    /**
     * @param list<array<string,mixed>> $partial
     * @param list<array<string,mixed>> $terms
     * @param array{lower:mixed,lowerInclusive:bool,upper:mixed,upperInclusive:bool} $range
     */
    private static function partialPredicateImplied(array $partial, array $terms, array $range): bool
    {
        foreach ($partial as $needed) {
            if (!is_array($needed)) {
                return false;
            }
            $operator = strtoupper((string) ($needed['operator'] ?? ''));
            $neededLeft = self::leftKey($needed['left'] ?? null);
            $found = false;
            foreach ($terms as $term) {
                if (self::termKey($needed) === self::termKey($term) && self::literal($needed['right'] ?? null) === self::literal($term['right'] ?? null)) {
                    $found = true;
                    break;
                }
                if ($operator === 'IS NOT NULL'
                    && strtoupper((string) ($term['operator'] ?? '')) !== 'IS NULL'
                    && $neededLeft === self::leftKey($term['left'] ?? null)) {
                    $found = true;
                    break;
                }
                if (($operator === '>=' || $operator === '>')
                    && $neededLeft === self::leftKey($term['left'] ?? null)
                    && self::compare($range['lower'], self::literal($needed['right'] ?? null), 'BINARY') >= 0) {
                    $found = true;
                    break;
                }
                if (($operator === '<' || $operator === '<=')
                    && $neededLeft === self::leftKey($term['left'] ?? null)
                    && self::compare($range['upper'], self::literal($needed['right'] ?? null), 'BINARY') <= 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,mixed> $index @param array<string,mixed> $orderBy */
    private static function orderSatisfied(array $index, array $orderBy): bool
    {
        return self::normalizeExpression((string) ($index['expression'] ?? '')) === self::normalizeExpression((string) ($orderBy['expression'] ?? ''))
            && self::direction($index['direction'] ?? 'ASC') === self::direction($orderBy['direction'] ?? 'ASC')
            && strtoupper((string) ($index['collation'] ?? 'BINARY')) === strtoupper((string) ($orderBy['collation'] ?? 'BINARY'));
    }

    /**
     * @param list<array<string,mixed>> $samples
     * @param array{lower:mixed,lowerInclusive:bool,upper:mixed,upperInclusive:bool} $range
     * @return list<array<string,mixed>>
     */
    private static function matchingSamples(array $samples, array $range, string $collation): array
    {
        return array_values(array_filter($samples, static function (array $sample) use ($range, $collation): bool {
            $lower = self::compare($sample['key'], $range['lower'], $collation);
            $upper = self::compare($sample['key'], $range['upper'], $collation);
            return ($range['lowerInclusive'] ? $lower >= 0 : $lower > 0)
                && ($range['upperInclusive'] ? $upper <= 0 : $upper < 0);
        }));
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return list<array{rowid:int,expressionKey:mixed,payload:array<string,mixed>}>
     */
    private static function matchedRows(array $source, array $terms, string $expression, string $collation, string $direction): array
    {
        $rows = [];
        foreach (self::listValue($source['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next178 rows must be arrays');
            }
            $rowid = $row['rowid'] ?? null;
            if (!is_int($rowid) || $rowid < 0) {
                throw new \InvalidArgumentException('SQLite next178 rowid must be a non-negative integer');
            }
            if (!self::rowSatisfiesTerms($row, $terms, $expression, $collation)) {
                continue;
            }
            $rows[] = [
                'rowid' => $rowid,
                'expressionKey' => self::expressionValue($row, $expression),
                'payload' => $row,
            ];
        }
        usort($rows, static function (array $a, array $b) use ($collation, $direction): int {
            $cmp = self::compare($a['expressionKey'] ?? null, $b['expressionKey'] ?? null, $collation);
            if ($cmp !== 0) {
                return $direction === 'DESC' ? -$cmp : $cmp;
            }

            return (int) ($a['rowid'] ?? 0) <=> (int) ($b['rowid'] ?? 0);
        });

        return $rows;
    }

    /** @param list<array<string,mixed>> $terms */
    private static function rowSatisfiesTerms(array $row, array $terms, string $expression, string $collation): bool
    {
        foreach ($terms as $term) {
            $left = $term['left'] ?? null;
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $right = self::literal($term['right'] ?? null);
            if (is_array($left) && isset($left['column'])) {
                $value = $row[(string) $left['column']] ?? null;
            } elseif (is_array($left) && self::normalizeExpression((string) ($left['expression'] ?? '')) === self::normalizeExpression($expression)) {
                $value = self::expressionValue($row, $expression);
            } else {
                continue;
            }
            $cmp = self::compare($value, $right, $collation);
            if ($operator === '=' && $value !== $right) {
                return false;
            }
            if ($operator === 'IS NOT NULL' && $value === null) {
                return false;
            }
            if ($operator === '>=' && $cmp < 0) {
                return false;
            }
            if ($operator === '>' && $cmp <= 0) {
                return false;
            }
            if ($operator === '<=' && $cmp > 0) {
                return false;
            }
            if ($operator === '<' && $cmp >= 0) {
                return false;
            }
        }

        return true;
    }

    private static function expressionValue(array $row, string $expression): mixed
    {
        $normalized = self::normalizeExpression($expression);
        if ($normalized === 'lower(option_name)') {
            return is_string($row['option_name'] ?? null) ? strtolower((string) $row['option_name']) : null;
        }

        return $row[$normalized] ?? null;
    }

    /** @param list<array<string,mixed>> $samples @return list<array{key:mixed,rowid:int,neq:int,nlt:string,ndlt:string}> */
    private static function stat4Samples(array $samples): array
    {
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite next178 STAT4 sample must be an array');
            }
            $payload = $sample['sample'] ?? null;
            if (!is_array($payload) || count($payload) < 2) {
                throw new \InvalidArgumentException('SQLite next178 STAT4 sample payload needs key and rowid');
            }
            $rowid = $payload[1] ?? null;
            if (!is_int($rowid) || $rowid < 0) {
                throw new \InvalidArgumentException('SQLite next178 STAT4 rowid must be a non-negative integer');
            }
            $neq = self::leadingInt((string) ($sample['neq'] ?? '1'));
            $out[] = [
                'key' => $payload[0] ?? null,
                'rowid' => $rowid,
                'neq' => $neq,
                'nlt' => (string) ($sample['nlt'] ?? ''),
                'ndlt' => (string) ($sample['ndlt'] ?? ''),
            ];
        }

        return $out;
    }

    private static function leadingInt(string $value): int
    {
        $parts = preg_split('/\s+/', trim($value));
        if ($parts === false || $parts === [] || !ctype_digit($parts[0])) {
            throw new \InvalidArgumentException('SQLite next178 STAT4 integer field is invalid');
        }

        return max(1, (int) $parts[0]);
    }

    /** @param mixed $value */
    private static function literal(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('literal', $value) ? $value['literal'] : $value;
    }

    private static function direction(mixed $value): string
    {
        $direction = strtoupper((string) $value);
        if ($direction !== 'ASC' && $direction !== 'DESC') {
            throw new \InvalidArgumentException('SQLite next178 order direction must be ASC or DESC');
        }

        return $direction;
    }

    private static function compare(mixed $left, mixed $right, string $collation): int
    {
        if ($left === null && $right === null) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }
        $a = (string) $left;
        $b = (string) $right;
        if (strtoupper($collation) === 'NOCASE') {
            $a = strtolower($a);
            $b = strtolower($b);
        }

        return $a <=> $b;
    }

    private static function normalizeExpression(string $expression): string
    {
        return strtolower((string) preg_replace('/\s+/', '', $expression));
    }

    /** @param mixed $value @return list<mixed> */
    private static function listValue(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next178 expected a list');
        }

        return $value;
    }

    /** @param array<string,mixed> $array */
    private static function stringValue(array $array, string $key): string
    {
        $value = $array[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite next178 ' . $key . ' must be a non-empty string');
        }

        return $value;
    }

    private static function intValue(mixed $value): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('SQLite next178 expected a non-negative integer');
        }

        return $value;
    }

    /** @param mixed $columns @return list<string> */
    private static function stringList(mixed $columns): array
    {
        $list = self::listValue($columns);
        foreach ($list as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite next178 covering columns must be strings');
            }
        }

        return $list;
    }

    /** @param mixed $columns @param list<string> $needed */
    private static function covers(mixed $columns, array $needed): bool
    {
        $available = self::stringList($columns);
        foreach ($needed as $column) {
            if (!is_string($column) || $column === '' || !in_array($column, $available, true)) {
                return false;
            }
        }

        return true;
    }

    /** @param mixed $left */
    private static function leftKey(mixed $left): string
    {
        if (!is_array($left)) {
            return '';
        }
        if (isset($left['column'])) {
            return 'column:' . strtolower((string) $left['column']);
        }
        if (isset($left['expression'])) {
            return 'expression:' . self::normalizeExpression((string) $left['expression']);
        }

        return '';
    }

    /** @param array<string,mixed> $term */
    private static function termKey(array $term): string
    {
        return self::leftKey($term['left'] ?? null) . ':' . strtoupper((string) ($term['operator'] ?? ''));
    }

    /** @param array<string,mixed> $source @param array<string,mixed> $plan */
    private static function summary(array $source, array $plan, string $signature): array
    {
        return [
            'name' => (string) ($source['name'] ?? ''),
            'schemaCookie' => self::intValue($source['schemaCookie'] ?? null),
            'stat4Generation' => self::intValue($source['stat4Generation'] ?? null),
            'sourceSignature' => $signature,
            'usable' => (bool) ($plan['usable'] ?? false),
            'indexName' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'matchedRowids' => array_column($plan['matchedRows'] ?? [], 'rowid'),
            'matchedExpressionKeys' => array_column($plan['matchedRows'] ?? [], 'expressionKey'),
            'orderBySatisfiedByIndex' => (bool) ($plan['orderBySatisfiedByIndex'] ?? false),
            'estimatedCost' => $plan['estimatedCost'] ?? null,
        ];
    }

    /** @param array<string,mixed> $plan */
    private static function selectedSummary(array $plan, bool $ready): array
    {
        return [
            'name' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'expression' => $plan['expression'] ?? null,
            'expressionColumn' => $plan['expressionColumn'] ?? null,
            'direction' => $plan['direction'] ?? null,
            'collation' => $plan['collation'] ?? null,
            'covering' => (bool) ($plan['covering'] ?? false),
            'partialPredicateImplied' => (bool) ($plan['partialPredicateImplied'] ?? false),
            'stat4Used' => (bool) ($plan['stat4Used'] ?? false),
            'matchedStat4Keys' => $plan['matchedStat4Keys'] ?? [],
            'matchedStat4Rowids' => $plan['matchedStat4Rowids'] ?? [],
            'matchedRowids' => array_column($plan['matchedRows'] ?? [], 'rowid'),
            'matchedExpressionKeys' => array_column($plan['matchedRows'] ?? [], 'expressionKey'),
            'orderBySatisfiedByIndex' => (bool) ($plan['orderBySatisfiedByIndex'] ?? false),
            'estimatedRows' => $plan['estimatedRows'] ?? null,
            'estimatedCost' => $plan['estimatedCost'] ?? null,
            'next178Ready' => $ready,
            'next178OrderFenceSignature' => self::signature($plan['orderFence'] ?? []),
        ];
    }

    /** @param array<string,mixed> $plan @return list<array<string,mixed>> */
    private static function cursorProgram(array $plan, bool $ready): array
    {
        if (!$ready) {
            return [['opcode' => 'Replan', 'reason' => 'stat4-expression-partial-order-fence']];
        }

        return [
            ['opcode' => 'OpenRead', 'target' => 'index', 'rootPage' => $plan['rootPage'] ?? null, 'direction' => $plan['direction'] ?? null],
            ['opcode' => 'FenceStat4Order', 'signature' => self::signature($plan['orderFence'] ?? [])],
            ['opcode' => $plan['direction'] === 'DESC' ? 'SeekLT' : 'SeekGE', 'key' => $plan['direction'] === 'DESC' ? ($plan['rangeConstraint']['upper'] ?? null) : ($plan['rangeConstraint']['lower'] ?? null)],
            ['opcode' => $plan['direction'] === 'DESC' ? 'IdxLE' : 'IdxGE', 'key' => $plan['direction'] === 'DESC' ? ($plan['rangeConstraint']['lower'] ?? null) : ($plan['rangeConstraint']['upper'] ?? null)],
            ['opcode' => 'RecheckPartialPredicate', 'implied' => (bool) ($plan['partialPredicateImplied'] ?? false)],
            ['opcode' => ($plan['covering'] ?? false) ? 'ColumnFromIndex' : 'DeferredSeek'],
            ['opcode' => 'ResultRow', 'rowids' => array_column($plan['matchedRows'] ?? [], 'rowid')],
            ['opcode' => $plan['direction'] === 'DESC' ? 'Prev' : 'Next'],
        ];
    }

    /** @param mixed $value */
    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }
}
