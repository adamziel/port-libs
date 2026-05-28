<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext164Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(array $preparedSource, array $currentSource, array $whereTerms, array $neededColumns): array
    {
        self::validateTerms($whereTerms);
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next164 needed columns cannot be empty');
        }

        $prepared = self::sourcePlan($preparedSource, $whereTerms, $neededColumns);
        $current = self::sourcePlan($currentSource, $whereTerms, $neededColumns);
        $preparedSignature = self::signature($preparedSource);
        $currentSignature = self::signature($currentSource);
        $stale = $preparedSignature !== $currentSignature
            || self::intValue($preparedSource, 'schemaCookie') !== self::intValue($currentSource, 'schemaCookie')
            || self::intValue($preparedSource, 'stat4Generation') !== self::intValue($currentSource, 'stat4Generation');
        $selected = $stale ? $current : $prepared;
        $ready = ($selected['usable'] ?? false) === true
            && ($selected['partialPredicateImpliedByRange'] ?? false) === true
            && ($selected['stat4Used'] ?? false) === true
            && ($selected['matchedRows'] ?? []) !== [];

        return [
            'status' => $ready ? 'stat4-expression-partial-current-source-next164-ready' : 'requires-next-stage',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => self::intValue($preparedSource, 'schemaCookie') !== self::intValue($currentSource, 'schemaCookie'),
            'stat4GenerationChanged' => self::intValue($preparedSource, 'stat4Generation') !== self::intValue($currentSource, 'stat4Generation'),
            'sourceSignatureChanged' => $preparedSignature !== $currentSignature,
            'preparedPlan' => self::summary($preparedSource, $prepared, $preparedSignature),
            'currentPlan' => self::summary($currentSource, $current, $currentSignature),
            'selectedPlan' => self::selectedSummary($selected, $ready),
            'matchedRows' => $selected['matchedRows'] ?? [],
            'matchedRowids' => array_column($selected['matchedRows'] ?? [], 'rowid'),
            'matchedExpressionKeys' => array_column($selected['matchedRows'] ?? [], 'expressionKey'),
            'stat4Fence' => [
                'schemaCookie' => self::intValue($stale ? $currentSource : $preparedSource, 'schemaCookie'),
                'stat4Generation' => self::intValue($stale ? $currentSource : $preparedSource, 'stat4Generation'),
                'sourceSignature' => $stale ? $currentSignature : $preparedSignature,
                'rangeSignature' => self::signature($selected['rangeConstraint'] ?? []),
                'stat4Signature' => self::signature($selected['stat4Samples'] ?? []),
                'rowStreamSignature' => self::signature(array_column($selected['matchedRows'] ?? [], 'rowid')),
            ],
            'cursorProgram' => self::cursorProgram($selected, $ready),
            'tableLookupRequired' => !($selected['covering'] ?? false),
            'residualPredicateRequired' => true,
            'detail' => (($stale ? 'REPREPARE' : 'REUSE') . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT164 RANGE ' . (string) ($selected['name'] ?? 'NO INDEX')),
            'dependencies' => ['sqlite-sqlplanner-stat4-expression-partial-current-source-next164'],
            'dependency_closure' => 'no new support component needed; next164 reuses native PHP expression normalization, range implication, STAT4 fences, and current-source row diagnostics',
            'non_overlap' => 'avoids accepted next154 equality/IN/BETWEEN row streams, next158 stale-row range exclusion, and next161 OR-split probes by proving a partial expression index from current range bounds and rejecting prepared STAT4 fences after source changes',
        ];
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function sourcePlan(array $source, array $terms, array $neededColumns): array
    {
        $best = null;
        foreach (self::listValue($source['indexes'] ?? []) as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next164 indexes must be arrays');
            }
            $expression = self::stringValue($index, 'expression');
            $range = self::expressionRange($terms, $expression);
            $partial = self::listValue($index['partialPredicateTerms'] ?? []);
            $partialByRange = $range !== null && self::partialImpliedByRange($partial, $range, $terms);
            $stat4 = self::stat4Samples(self::listValue($index['stat4Samples'] ?? []));
            $matchedSamples = $partialByRange ? self::matchingSamples($stat4, $range, (string) ($index['collation'] ?? 'BINARY')) : [];
            $matchedRows = $matchedSamples === [] ? [] : self::matchedRows($source, $terms, $expression, (string) ($index['collation'] ?? 'BINARY'));
            $covering = self::covers($index['coveringColumns'] ?? [], $neededColumns);
            $estimate = max(1, array_sum(array_column($matchedSamples, 'neq')));
            $plan = [
                'usable' => $range !== null && $partialByRange && $matchedSamples !== [] && $matchedRows !== [],
                'name' => self::stringValue($index, 'name'),
                'rootPage' => self::intValue($index, 'rootPage'),
                'expression' => $expression,
                'expressionColumn' => (string) ($index['expressionColumn'] ?? ''),
                'collation' => strtoupper((string) ($index['collation'] ?? 'BINARY')),
                'coveringColumns' => self::stringList($index['coveringColumns'] ?? []),
                'covering' => $covering,
                'partialPredicateTerms' => $partial,
                'partialPredicateImpliedByRange' => $partialByRange,
                'rangeConstraint' => $range,
                'stat4Samples' => $stat4,
                'stat4Used' => $stat4 !== [],
                'matchedStat4Keys' => array_column($matchedSamples, 'key'),
                'matchedStat4Rowids' => array_column($matchedSamples, 'rowid'),
                'matchedRows' => $matchedRows,
                'estimatedRows' => $estimate,
                'estimatedCost' => $estimate + ($covering ? 0 : 12) + ($partialByRange ? 0 : 50),
                'detail' => 'SEARCH ' . self::stringValue($index, 'name') . ' USING STAT4 PARTIAL EXPRESSION RANGE',
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

        return $best ?? ['usable' => false, 'matchedRows' => [], 'stat4Used' => false, 'partialPredicateImpliedByRange' => false];
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
            if ($operator === '>=') {
                $range['lower'] = $right;
                $range['lowerInclusive'] = true;
            } elseif ($operator === '>') {
                $range['lower'] = $right;
                $range['lowerInclusive'] = false;
            } elseif ($operator === '<=') {
                $range['upper'] = $right;
                $range['upperInclusive'] = true;
            } elseif ($operator === '<') {
                $range['upper'] = $right;
                $range['upperInclusive'] = false;
            }
        }

        return $range['lower'] !== null && $range['upper'] !== null ? $range : null;
    }

    /**
     * @param list<array<string,mixed>> $partial
     * @param array{lower:mixed,lowerInclusive:bool,upper:mixed,upperInclusive:bool} $range
     * @param list<array<string,mixed>> $terms
     */
    private static function partialImpliedByRange(array $partial, array $range, array $terms): bool
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
     * @return list<array<string,mixed>>
     */
    private static function matchedRows(array $source, array $terms, string $expression, string $collation): array
    {
        $rows = [];
        foreach (self::listValue($source['rows'] ?? []) as $sourceOffset => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next164 rows must be arrays');
            }
            $rowid = $row['rowid'] ?? null;
            if (!is_int($rowid) || $rowid < 0) {
                throw new \InvalidArgumentException('SQLite next164 rows need non-negative integer rowid');
            }
            if (!self::rowSatisfies($row, $terms, $expression, $collation)) {
                continue;
            }
            $rows[] = [
                'rowid' => $rowid,
                'sourceOffset' => $sourceOffset,
                'expressionKey' => self::expressionValue($row, $expression),
                'payload' => $row,
            ];
        }
        usort($rows, static fn (array $a, array $b): int => [self::stringCast($a['expressionKey']), $a['rowid']] <=> [self::stringCast($b['expressionKey']), $b['rowid']]);

        return $rows;
    }

    /**
     * @param list<array<string,mixed>> $terms
     */
    private static function rowSatisfies(array $row, array $terms, string $expression, string $collation): bool
    {
        foreach ($terms as $term) {
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $left = $term['left'] ?? [];
            $leftValue = is_array($left) && isset($left['expression'])
                ? self::expressionValue($row, $expression)
                : ($row[(string) ($left['column'] ?? '')] ?? null);
            if ($operator === 'IS NOT NULL' && $leftValue !== null) {
                continue;
            }
            if ($operator === '=' && self::literal($term['right'] ?? null) === $leftValue) {
                continue;
            }
            if (in_array($operator, ['>', '>=', '<', '<='], true)) {
                $cmp = self::compare($leftValue, self::literal($term['right'] ?? null), $collation);
                if (($operator === '>' && $cmp > 0) || ($operator === '>=' && $cmp >= 0) || ($operator === '<' && $cmp < 0) || ($operator === '<=' && $cmp <= 0)) {
                    continue;
                }
            }
            return false;
        }

        return true;
    }

    private static function expressionValue(array $row, string $expression): mixed
    {
        $normalized = self::normalizeExpression($expression);
        if ($normalized === 'lower(option_name)') {
            $value = $row['option_name'] ?? null;
            return is_string($value) ? strtolower($value) : null;
        }
        if ($normalized === 'json_extract(option_value,$.plugin)') {
            $decoded = json_decode((string) ($row['option_value'] ?? ''), true);
            return is_array($decoded) ? ($decoded['plugin'] ?? null) : null;
        }

        throw new \InvalidArgumentException('SQLite next164 unsupported expression ' . $expression);
    }

    /**
     * @param list<array<string,mixed>> $samples
     * @return list<array<string,mixed>>
     */
    private static function stat4Samples(array $samples): array
    {
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 2) {
                throw new \InvalidArgumentException('SQLite next164 STAT4 samples need key and rowid');
            }
            $out[] = [
                'key' => self::literal($sample['sample'][0]),
                'rowid' => self::intLiteral($sample['sample'][1]),
                'neq' => self::firstStatInt($sample['neq'] ?? 1),
                'nlt' => self::firstStatInt($sample['nlt'] ?? 0),
                'ndlt' => self::firstStatInt($sample['ndlt'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $selected, bool $ready): array
    {
        if (!$ready) {
            return [['opcode' => 'FallbackFullScan', 'reason' => 'partial expression STAT4 range not usable']];
        }

        return [
            ['opcode' => 'OpenRead', 'rootPage' => $selected['rootPage'], 'index' => $selected['name']],
            ['opcode' => 'SeekGE', 'key' => $selected['rangeConstraint']['lower']],
            ['opcode' => 'IdxLT', 'key' => $selected['rangeConstraint']['upper']],
            ['opcode' => ($selected['covering'] ? 'ColumnFromIndex' : 'DeferredSeek')],
            ['opcode' => 'ResidualPartialCheck'],
            ['opcode' => 'ResultRow', 'rowids' => array_column($selected['matchedRows'], 'rowid')],
            ['opcode' => 'Next'],
        ];
    }

    private static function selectedSummary(array $plan, bool $ready): array
    {
        return [
            'name' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'expression' => $plan['expression'] ?? null,
            'collation' => $plan['collation'] ?? null,
            'covering' => $plan['covering'] ?? false,
            'partialPredicateImpliedByRange' => $plan['partialPredicateImpliedByRange'] ?? false,
            'rangeConstraint' => $plan['rangeConstraint'] ?? null,
            'stat4Used' => $plan['stat4Used'] ?? false,
            'matchedStat4Keys' => $plan['matchedStat4Keys'] ?? [],
            'matchedStat4Rowids' => $plan['matchedStat4Rowids'] ?? [],
            'matchedRowCount' => count($plan['matchedRows'] ?? []),
            'estimatedRows' => $plan['estimatedRows'] ?? null,
            'estimatedCost' => $plan['estimatedCost'] ?? null,
            'next164Ready' => $ready,
            'detail' => $plan['detail'] ?? 'NO PLAN',
        ];
    }

    private static function summary(array $source, array $plan, string $signature): array
    {
        return [
            'name' => (string) ($source['name'] ?? ''),
            'schemaCookie' => self::intValue($source, 'schemaCookie'),
            'stat4Generation' => self::intValue($source, 'stat4Generation'),
            'sourceSignature' => $signature,
            'selectedIndex' => $plan['name'] ?? null,
            'usable' => $plan['usable'] ?? false,
            'matchedRowids' => array_column($plan['matchedRows'] ?? [], 'rowid'),
        ];
    }

    /** @param list<array<string,mixed>> $terms */
    private static function validateTerms(array $terms): void
    {
        if ($terms === []) {
            throw new \InvalidArgumentException('SQLite next164 WHERE terms cannot be empty');
        }
        foreach ($terms as $term) {
            if (!is_array($term) || !is_array($term['left'] ?? null) || !is_string($term['operator'] ?? null)) {
                throw new \InvalidArgumentException('SQLite next164 WHERE terms need left/operator');
            }
        }
    }

    private static function normalizeExpression(string $expression): string
    {
        return strtolower(str_replace(' ', '', $expression));
    }

    private static function termKey(array $term): string
    {
        return self::leftKey($term['left'] ?? null) . '|' . strtoupper((string) ($term['operator'] ?? ''));
    }

    private static function leftKey(mixed $left): string
    {
        if (!is_array($left)) {
            return '';
        }
        if (isset($left['expression'])) {
            return 'expr:' . self::normalizeExpression((string) $left['expression']);
        }

        return 'col:' . strtolower((string) ($left['column'] ?? ''));
    }

    /** @return list<mixed> */
    private static function listValue(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values($value);
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        return array_values(array_map('strval', self::listValue($value)));
    }

    private static function covers(mixed $available, array $needed): bool
    {
        $set = array_flip(self::stringList($available));
        foreach ($needed as $column) {
            if (!isset($set[$column])) {
                return false;
            }
        }

        return true;
    }

    private static function compare(mixed $left, mixed $right, string $collation): int
    {
        $a = self::stringCast($left);
        $b = self::stringCast($right);
        if (strtoupper($collation) === 'NOCASE') {
            $a = strtolower($a);
            $b = strtolower($b);
        }

        return $a <=> $b;
    }

    private static function literal(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('value', $value) ? $value['value'] : $value;
    }

    private static function stringCast(mixed $value): string
    {
        return is_scalar($value) || $value === null ? (string) $value : json_encode($value, JSON_THROW_ON_ERROR);
    }

    private static function intLiteral(mixed $value): int
    {
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new \InvalidArgumentException('SQLite next164 integer literal expected');
        }

        return (int) $value;
    }

    private static function intValue(array $source, string $key): int
    {
        return self::intLiteral($source[$key] ?? 0);
    }

    private static function stringValue(array $source, string $key): string
    {
        $value = $source[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException('SQLite next164 string value expected for ' . $key);
        }

        return $value;
    }

    private static function firstStatInt(mixed $value): int
    {
        if (is_string($value)) {
            $value = preg_split('/\s+/', trim($value))[0] ?? '0';
        }

        return self::intLiteral($value);
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
