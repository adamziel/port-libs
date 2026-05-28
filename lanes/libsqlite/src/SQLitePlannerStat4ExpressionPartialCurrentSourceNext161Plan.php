<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext161Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $orArms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $orArms, array $neededColumns): array
    {
        self::validateArms($orArms);
        self::validateNeeded($neededColumns);

        $prepared = self::sourcePlan($preparedSource, $orArms, $neededColumns);
        $current = self::sourcePlan($currentSource, $orArms, $neededColumns);
        $preparedSignature = self::sourceSignature($preparedSource);
        $currentSignature = self::sourceSignature($currentSource);
        $stale = $preparedSignature !== $currentSignature
            || self::intValue($preparedSource, 'schemaCookie') !== self::intValue($currentSource, 'schemaCookie')
            || self::intValue($preparedSource, 'stat4Generation') !== self::intValue($currentSource, 'stat4Generation');
        $selected = $stale ? $current : $prepared;
        $ready = ($selected['usable'] ?? false) === true
            && ($selected['orArmCount'] ?? 0) > 1
            && ($selected['allArmsPartialImplied'] ?? false) === true
            && ($selected['stat4Used'] ?? false) === true
            && ($selected['matchedRows'] ?? []) !== [];

        return [
            'status' => $ready ? 'stat4-expression-partial-current-source-next161-ready' : 'requires-next-stage',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => self::intValue($preparedSource, 'schemaCookie') !== self::intValue($currentSource, 'schemaCookie'),
            'stat4GenerationChanged' => self::intValue($preparedSource, 'stat4Generation') !== self::intValue($currentSource, 'stat4Generation'),
            'sourceSignatureChanged' => $preparedSignature !== $currentSignature,
            'preparedSource' => self::summary($preparedSource, $prepared, $preparedSignature),
            'currentSource' => self::summary($currentSource, $current, $currentSignature),
            'selectedPlan' => self::selectedSummary($selected, $ready),
            'orArmPlans' => $selected['orArmPlans'] ?? [],
            'matchedRows' => $selected['matchedRows'] ?? [],
            'matchedRowids' => array_column($selected['matchedRows'] ?? [], 'rowid'),
            'matchedExpressionKeys' => array_column($selected['matchedRows'] ?? [], 'expressionKey'),
            'currentNextRows' => self::currentNext($selected['matchedRows'] ?? []),
            'cursorProgram' => self::cursorProgram($selected, $ready),
            'stat4Fence' => [
                'schemaCookie' => self::intValue($stale ? $currentSource : $preparedSource, 'schemaCookie'),
                'stat4Generation' => self::intValue($stale ? $currentSource : $preparedSource, 'stat4Generation'),
                'sourceSignature' => $stale ? $currentSignature : $preparedSignature,
                'orArmSignature' => self::signature($orArms),
                'stat4Signature' => self::signature($selected['stat4Samples'] ?? []),
                'rowStreamSignature' => self::signature(array_column($selected['matchedRows'] ?? [], 'rowid')),
            ],
            'tableLookupRequired' => !($selected['covering'] ?? false),
            'residualPredicateRequired' => true,
            'tempUnionBtreeRequired' => ($selected['duplicateRowidsRemoved'] ?? []) !== [],
            'detail' => (($stale ? 'REPREPARE' : 'REUSE') . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT161 OR-SPLIT ' . (string) ($selected['name'] ?? 'NO INDEX')),
            'dependencies' => ['sqlite-sqlplanner-stat4-expression-partial-current-source-next161'],
            'dependency_closure' => 'no new support component needed; next161 reuses lane-local expression term matching, partial predicate proof, STAT4 sample fences, and current-source row diagnostics',
            'non_overlap' => 'avoids accepted next154 equality/IN/BETWEEN row stream and next158 range-window stale-row exclusion by covering OR-split partial expression probes whose every arm must imply the current partial predicate before STAT4 admission',
        ];
    }

    /**
     * @param list<array<string,mixed>> $orArms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function sourcePlan(array $source, array $orArms, array $neededColumns): array
    {
        $best = null;
        foreach (self::listValue($source['indexes'] ?? []) as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next161 indexes must be arrays');
            }
            $expression = self::stringValue($index, 'expression');
            $partial = self::listValue($index['partialPredicateTerms'] ?? []);
            $stat4 = self::stat4Samples(self::listValue($index['stat4Samples'] ?? []));
            $covering = self::covers($index['coveringColumns'] ?? [], $neededColumns);
            $armPlans = [];
            $allPartial = true;
            $allConstrained = true;
            foreach ($orArms as $armOffset => $terms) {
                $armTerms = self::listValue($terms);
                $constraint = self::expressionConstraint($armTerms, $expression);
                $partialImplied = self::partialImplied($partial, $armTerms);
                $matchedSamples = $constraint === null ? [] : self::matchingSamples($stat4, $constraint, (string) ($index['collation'] ?? 'BINARY'));
                $allPartial = $allPartial && $partialImplied;
                $allConstrained = $allConstrained && $constraint !== null && $matchedSamples !== [];
                $armPlans[] = [
                    'arm' => $armOffset,
                    'constraint' => $constraint,
                    'partialPredicateImplied' => $partialImplied,
                    'matchedStat4Keys' => array_column($matchedSamples, 'key'),
                    'matchedStat4Rowids' => array_column($matchedSamples, 'rowid'),
                    'stat4Estimate' => max(1, array_sum(array_column($matchedSamples, 'neq'))),
                ];
            }
            $matchedRows = $allPartial && $allConstrained ? self::matchedRows($source, $orArms, $expression, $neededColumns, (string) ($index['collation'] ?? 'BINARY')) : [];
            $duplicateRowids = self::duplicates(array_column($matchedRows, 'rowid'));
            $stat4Rows = array_sum(array_map(static fn (array $arm): int => (int) $arm['stat4Estimate'], $armPlans));
            $plan = [
                'usable' => $allPartial && $allConstrained && $stat4 !== [] && $matchedRows !== [],
                'name' => self::stringValue($index, 'name'),
                'rootPage' => self::intValue($index, 'rootPage'),
                'expression' => $expression,
                'expressionColumn' => (string) ($index['expressionColumn'] ?? ''),
                'collation' => strtoupper((string) ($index['collation'] ?? 'BINARY')),
                'partialPredicateTerms' => $partial,
                'coveringColumns' => self::stringList($index['coveringColumns'] ?? []),
                'covering' => $covering,
                'stat4Samples' => $stat4,
                'stat4Used' => $stat4 !== [],
                'orArmCount' => count($orArms),
                'orArmPlans' => $armPlans,
                'allArmsPartialImplied' => $allPartial,
                'allArmsConstrainedByStat4' => $allConstrained,
                'duplicateRowidsRemoved' => $duplicateRowids,
                'matchedRows' => $matchedRows,
                'estimatedRows' => max(1, $stat4Rows),
                'estimatedCost' => max(1, $stat4Rows + ($covering ? 0 : 10) + (count($orArms) * 2)),
                'detail' => 'SEARCH ' . self::stringValue($index, 'name') . ' USING STAT4 PARTIAL EXPRESSION OR-SPLIT',
            ];
            if ($best === null) {
                $best = $plan;
                continue;
            }
            $ranking = [
                $plan['usable'] ? 0 : 1,
                $plan['estimatedCost'],
                $plan['name'],
            ] <=> [
                $best['usable'] ? 0 : 1,
                $best['estimatedCost'],
                $best['name'],
            ];
            if ($ranking < 0) {
                $best = $plan;
            }
        }

        return $best ?? ['usable' => false, 'orArmCount' => count($orArms), 'allArmsPartialImplied' => false, 'stat4Used' => false, 'matchedRows' => []];
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return array{operator:string,values:list<mixed>}|null
     */
    private static function expressionConstraint(array $terms, string $expression): ?array
    {
        $normalized = self::normalizeExpression($expression);
        foreach ($terms as $term) {
            if (!is_array($term) || self::normalizeExpression((string) (($term['left']['expression'] ?? null))) !== $normalized) {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if ($operator === '=') {
                return ['operator' => '=', 'values' => [self::literal($term['right'] ?? null)]];
            }
            if ($operator === 'IN' && is_array($term['values'] ?? null)) {
                return ['operator' => 'IN', 'values' => array_map(self::literal(...), array_values($term['values']))];
            }
        }

        return null;
    }

    /**
     * @param list<array<string,mixed>> $partial
     * @param list<array<string,mixed>> $terms
     */
    private static function partialImplied(array $partial, array $terms): bool
    {
        foreach ($partial as $needed) {
            if (!is_array($needed)) {
                return false;
            }
            $found = false;
            foreach ($terms as $term) {
                if (!is_array($term)) {
                    continue;
                }
                if (self::termKey($needed) === self::termKey($term) && self::literal($needed['right'] ?? null) === self::literal($term['right'] ?? null)) {
                    $found = true;
                    break;
                }
                if (strtoupper((string) ($needed['operator'] ?? '')) === 'IS NOT NULL'
                    && strtoupper((string) ($term['operator'] ?? '')) !== 'IS NULL'
                    && self::leftKey($needed['left'] ?? null) === self::leftKey($term['left'] ?? null)) {
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

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{current:array<string,mixed>,next:?array<string,mixed>}>
     */
    private static function currentNext(array $rows): array
    {
        $out = [];
        foreach ($rows as $i => $row) {
            $out[] = ['current' => $row, 'next' => $rows[$i + 1] ?? null];
        }

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $orArms
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function matchedRows(array $source, array $orArms, string $expression, array $neededColumns, string $collation): array
    {
        $rows = [];
        $seen = [];
        foreach (self::listValue($source['rows'] ?? []) as $sourceOffset => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next161 source rows must be arrays');
            }
            $rowid = $row['rowid'] ?? null;
            if (!is_int($rowid) || $rowid < 0) {
                throw new \InvalidArgumentException('SQLite next161 rows need non-negative integer rowid');
            }
            foreach ($orArms as $armOffset => $arm) {
                if (self::rowSatisfies($row, self::listValue($arm), $expression, $collation)) {
                    if (!isset($seen[$rowid])) {
                        $seen[$rowid] = true;
                        $rows[] = [
                            'sourceOffset' => $sourceOffset,
                            'arm' => $armOffset,
                            'rowid' => $rowid,
                            'expressionKey' => self::expressionValue($expression, $row),
                            'payload' => self::payload($row, $neededColumns),
                        ];
                    }
                    break;
                }
            }
        }
        usort($rows, static fn (array $a, array $b): int => self::compare($a['expressionKey'], $b['expressionKey'], $collation) ?: ($a['rowid'] <=> $b['rowid']));

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $terms
     */
    private static function rowSatisfies(array $row, array $terms, string $expression, string $collation): bool
    {
        foreach ($terms as $term) {
            if (!is_array($term)) {
                return false;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $left = $term['left'] ?? null;
            $value = is_array($left) && isset($left['expression'])
                ? self::expressionValue((string) $left['expression'], $row)
                : $row[(string) ($left['column'] ?? '')] ?? null;
            if (($operator === '=' || $operator === 'IS') && self::compare($value, self::literal($term['right'] ?? null), $collation) !== 0) {
                return false;
            }
            if ($operator === 'IN') {
                $matched = false;
                foreach (is_array($term['values'] ?? null) ? $term['values'] : [] as $expected) {
                    if (self::compare($value, self::literal($expected), $collation) === 0) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched) {
                    return false;
                }
            }
            if ($operator === 'IS NOT NULL' && $value === null) {
                return false;
            }
        }

        return true;
    }

    private static function expressionValue(string $expression, array $row): mixed
    {
        if (self::normalizeExpression($expression) === 'lower(option_name)') {
            $value = $row['option_name'] ?? null;
            return is_string($value) ? strtolower($value) : null;
        }

        return $row[$expression] ?? null;
    }

    /** @return list<array{key:mixed,neq:int,nlt:int,ndlt:int,rowid:int}> */
    private static function stat4Samples(array $samples): array
    {
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || $sample['sample'] === []) {
                throw new \InvalidArgumentException('SQLite next161 STAT4 samples need sample keys');
            }
            $out[] = [
                'key' => $sample['sample'][0],
                'neq' => self::firstInt($sample['neq'] ?? 1),
                'nlt' => self::firstInt($sample['nlt'] ?? 0),
                'ndlt' => self::firstInt($sample['ndlt'] ?? 0),
                'rowid' => (int) ($sample['sample'][1] ?? 0),
            ];
        }

        return $out;
    }

    /** @return list<array{key:mixed,neq:int,nlt:int,ndlt:int,rowid:int}> */
    private static function matchingSamples(array $samples, array $constraint, string $collation): array
    {
        return array_values(array_filter($samples, static function (array $sample) use ($constraint, $collation): bool {
            foreach ($constraint['values'] as $value) {
                if (self::compare($sample['key'], $value, $collation) === 0) {
                    return true;
                }
            }

            return false;
        }));
    }

    /** @param list<mixed> $rowids @return list<mixed> */
    private static function duplicates(array $rowids): array
    {
        $seen = [];
        $dupes = [];
        foreach ($rowids as $rowid) {
            if (isset($seen[$rowid])) {
                $dupes[] = $rowid;
            }
            $seen[$rowid] = true;
        }

        return array_values(array_unique($dupes));
    }

    /** @param array<string,mixed> $plan @return array<string,mixed> */
    private static function selectedSummary(array $plan, bool $ready): array
    {
        return $plan + [
            'next161Ready' => $ready,
            'matchedRowCount' => count($plan['matchedRows'] ?? []),
            'matchedRowids' => array_column($plan['matchedRows'] ?? [], 'rowid'),
        ];
    }

    /** @param array<string,mixed> $plan @return list<array<string,mixed>> */
    private static function cursorProgram(array $plan, bool $ready): array
    {
        $program = [
            ['opcode' => 'OpenRead', 'source' => 'partial-expression-index', 'rootPage' => $plan['rootPage'] ?? null],
            ['opcode' => 'RewindOrArm', 'armCount' => $plan['orArmCount'] ?? 0],
        ];
        foreach ($plan['orArmPlans'] ?? [] as $arm) {
            $program[] = ['opcode' => 'SeekStat4Expression', 'arm' => $arm['arm'] ?? null, 'keys' => $arm['matchedStat4Keys'] ?? []];
        }
        $program[] = ['opcode' => ($plan['duplicateRowidsRemoved'] ?? []) === [] ? 'Noop' : 'DistinctRowid', 'rowids' => $plan['duplicateRowidsRemoved'] ?? []];
        $program[] = ['opcode' => $ready && ($plan['covering'] ?? false) ? 'ResultRow' : 'DeferredSeek', 'source' => $ready && ($plan['covering'] ?? false) ? 'current-covering-index' : 'table'];
        $program[] = ['opcode' => 'NextOrArm', 'source' => 'partial-expression-index'];

        return $program;
    }

    /** @param mixed $columns */
    private static function covers(mixed $columns, array $needed): bool
    {
        return array_diff(array_map('strtolower', $needed), array_map('strtolower', self::stringList($columns))) === [];
    }

    /** @return array<string,mixed> */
    private static function summary(array $source, array $plan, string $signature): array
    {
        return [
            'name' => (string) ($source['name'] ?? ''),
            'schemaCookie' => self::intValue($source, 'schemaCookie'),
            'stat4Generation' => self::intValue($source, 'stat4Generation'),
            'signature' => $signature,
            'usable' => $plan['usable'] ?? false,
            'rootPage' => $plan['rootPage'] ?? null,
        ];
    }

    private static function sourceSignature(array $source): string
    {
        return self::signature([
            $source['schemaCookie'] ?? null,
            $source['stat4Generation'] ?? null,
            $source['indexes'] ?? [],
            $source['rows'] ?? [],
        ]);
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }

    private static function compare(mixed $left, mixed $right, string $collation): int
    {
        if ($left === null || $right === null) {
            return $left <=> $right;
        }
        $a = (string) $left;
        $b = (string) $right;
        return strtoupper($collation) === 'NOCASE' ? strcasecmp($a, $b) : strcmp($a, $b);
    }

    private static function normalizeExpression(string $expression): string
    {
        return strtolower((string) preg_replace('/\s+/', '', $expression));
    }

    private static function termKey(array $term): string
    {
        return self::leftKey($term['left'] ?? null) . '|' . strtoupper((string) ($term['operator'] ?? ''));
    }

    private static function leftKey(mixed $left): string
    {
        return is_array($left) ? strtolower((string) ($left['column'] ?? $left['expression'] ?? '')) : '';
    }

    private static function literal(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('literal', $value) ? $value['literal'] : $value;
    }

    /** @return list<array<string,mixed>> */
    private static function listValue(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next161 expected list value');
        }

        return $value;
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        return array_map(static function (mixed $item): string {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException('SQLite next161 expected string list');
            }

            return $item;
        }, self::listValue($value));
    }

    private static function stringValue(array $value, string $key): string
    {
        if (!is_string($value[$key] ?? null) || $value[$key] === '') {
            throw new \InvalidArgumentException('SQLite next161 ' . $key . ' must be a string');
        }

        return $value[$key];
    }

    private static function intValue(array $value, string $key): int
    {
        if (!is_int($value[$key] ?? null) || $value[$key] < 0) {
            throw new \InvalidArgumentException('SQLite next161 ' . $key . ' must be a non-negative integer');
        }

        return $value[$key];
    }

    private static function firstInt(mixed $value): int
    {
        $first = is_string($value) ? strtok($value, ' ') : $value;
        return max(0, (int) $first);
    }

    /** @return array<string,mixed> */
    private static function payload(array $row, array $neededColumns): array
    {
        $payload = [];
        foreach ($neededColumns as $column) {
            $payload[$column] = $row[$column] ?? null;
        }

        return $payload;
    }

    /** @param list<array<string,mixed>> $orArms */
    private static function validateArms(array $orArms): void
    {
        if (count($orArms) < 2) {
            throw new \InvalidArgumentException('SQLite next161 needs at least two OR arms');
        }
    }

    /** @param list<string> $needed */
    private static function validateNeeded(array $needed): void
    {
        if ($needed === []) {
            throw new \InvalidArgumentException('SQLite next161 needs output columns');
        }
        self::stringList($needed);
    }
}
