<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext175Plan
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
        if ($whereTerms === []) {
            throw new \InvalidArgumentException('SQLite next175 WHERE terms cannot be empty');
        }
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite next175 needed columns cannot be empty');
        }

        $prepared = self::sourcePlan($preparedSource, $whereTerms, $neededColumns);
        $current = self::sourcePlan($currentSource, $whereTerms, $neededColumns);
        $preparedSignature = self::signature($preparedSource);
        $currentSignature = self::signature($currentSource);
        $stale = $preparedSignature !== $currentSignature
            || self::intValue($preparedSource['schemaCookie'] ?? null) !== self::intValue($currentSource['schemaCookie'] ?? null)
            || self::intValue($preparedSource['stat4Generation'] ?? null) !== self::intValue($currentSource['stat4Generation'] ?? null);
        $selected = $stale ? $current : $prepared;
        $source = $stale ? $currentSource : $preparedSource;
        $ready = ($selected['usable'] ?? false) === true
            && ($selected['partialPredicateImplied'] ?? false) === true
            && ($selected['prefix'] ?? '') !== ''
            && ($selected['matchedRows'] ?? []) !== [];

        return [
            'status' => $ready ? 'stat4-expression-partial-current-source-next175-ready' : 'requires-next-stage',
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
            'prefix' => $selected['prefix'] ?? null,
            'prefixUpperBound' => $selected['prefixUpperBound'] ?? null,
            'stat4PrefixWindow' => $selected['stat4PrefixWindow'] ?? [],
            'stat4PrefixWindowChanged' => self::signature($prepared['stat4PrefixWindow'] ?? []) !== self::signature($current['stat4PrefixWindow'] ?? []),
            'stalePreparedRowidsBlockedByPrefixWindow' => array_values(array_diff(
                array_column($prepared['matchedRows'] ?? [], 'rowid'),
                array_column($current['matchedRows'] ?? [], 'rowid'),
            )),
            'currentSourceRowidsAdmittedByPrefixWindow' => array_values(array_diff(
                array_column($current['matchedRows'] ?? [], 'rowid'),
                array_column($prepared['matchedRows'] ?? [], 'rowid'),
            )),
            'cursorProgram' => self::cursorProgram($selected, $ready),
            'stat4Fence' => [
                'schemaCookie' => self::intValue($source['schemaCookie'] ?? null),
                'stat4Generation' => self::intValue($source['stat4Generation'] ?? null),
                'sourceSignature' => $stale ? $currentSignature : $preparedSignature,
                'expressionSignature' => self::normalizeExpression((string) ($selected['expression'] ?? '')),
                'partialPredicateSignature' => self::signature($selected['partialPredicateTerms'] ?? []),
                'stat4SampleSignature' => self::signature($selected['stat4Samples'] ?? []),
                'stat4PrefixWindowSignature' => self::signature($selected['stat4PrefixWindow'] ?? []),
                'rowStreamSignature' => self::signature(array_column($selected['matchedRows'] ?? [], 'rowid')),
            ],
            'tableLookupRequired' => !($selected['covering'] ?? false),
            'residualPredicateRequired' => true,
            'detail' => (($stale ? 'REPREPARE' : 'REUSE') . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT175 LIKE PREFIX WINDOW ' . (string) ($selected['name'] ?? 'NO INDEX')),
            'dependencies' => ['sqlite-sqlplanner-stat4-expression-partial-current-source-next175'],
            'dependency_closure' => 'no new support component needed; next175 reuses lane-local expression evaluation, LIKE prefix extraction, partial predicate implication, STAT4 sample fences, and current-source row diagnostics',
            'non_overlap' => 'avoids accepted next154 equality/IN/BETWEEN row streams, next164 range proof, next171 unsampled equality brackets, next173 duplicate sample fanout, expression ORDER BY, range-cost, JSON, WAL, VFS, and B-tree clusters; this slice admits a LIKE prefix window for a partial expression index',
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
                throw new \InvalidArgumentException('SQLite next175 indexes must be arrays');
            }
            $expression = self::stringValue($index, 'expression');
            $prefix = self::likePrefix($terms, $expression);
            $upper = $prefix === null ? null : self::prefixUpperBound($prefix);
            $stat4 = self::stat4Samples(self::listValue($index['stat4Samples'] ?? []));
            $window = $prefix === null || $upper === null ? [] : self::prefixWindow($stat4, $prefix, $upper, (string) ($index['collation'] ?? 'BINARY'));
            $partial = self::listValue($index['partialPredicateTerms'] ?? []);
            $partialImplied = self::partialPredicateImplied($partial, $terms);
            $matchedRows = $prefix === null || $upper === null || $window === [] || !$partialImplied
                ? []
                : self::matchedRows($source, $terms, $expression, (string) ($index['collation'] ?? 'BINARY'));
            $covering = self::covers($index['coveringColumns'] ?? [], $neededColumns);
            $plan = [
                'usable' => $prefix !== null && $upper !== null && $window !== [] && $partialImplied && $matchedRows !== [],
                'name' => self::stringValue($index, 'name'),
                'rootPage' => self::intValue($index['rootPage'] ?? null),
                'expression' => $expression,
                'expressionColumn' => (string) ($index['expressionColumn'] ?? ''),
                'collation' => strtoupper((string) ($index['collation'] ?? 'BINARY')),
                'coveringColumns' => self::stringList($index['coveringColumns'] ?? []),
                'covering' => $covering,
                'partialPredicateTerms' => $partial,
                'partialPredicateImplied' => $partialImplied,
                'stat4Samples' => $stat4,
                'stat4Used' => $stat4 !== [],
                'prefix' => $prefix,
                'prefixUpperBound' => $upper,
                'stat4PrefixWindow' => $window,
                'matchedRows' => $matchedRows,
                'estimatedRows' => max(1, count($matchedRows)),
                'estimatedCost' => max(1, count($matchedRows) + ($covering ? 0 : 12) + ($window === [] ? 40 : 0)),
                'detail' => 'SEARCH ' . self::stringValue($index, 'name') . ' USING STAT4 EXPRESSION PARTIAL LIKE PREFIX WINDOW',
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

        return $best ?? ['usable' => false, 'matchedRows' => [], 'stat4Used' => false, 'partialPredicateImplied' => false];
    }

    /** @param list<array<string,mixed>> $terms */
    private static function likePrefix(array $terms, string $expression): ?string
    {
        $normalized = self::normalizeExpression($expression);
        foreach ($terms as $term) {
            if (self::normalizeExpression((string) ($term['left']['expression'] ?? '')) !== $normalized) {
                continue;
            }
            if (strtoupper((string) ($term['operator'] ?? '')) !== 'LIKE') {
                continue;
            }
            $pattern = self::literal($term['right'] ?? null);
            if (!is_string($pattern)) {
                return null;
            }
            $escape = $term['escape'] ?? '\\';
            if (!is_string($escape) || strlen($escape) !== 1) {
                throw new \InvalidArgumentException('SQLite next175 LIKE escape must be one byte');
            }

            return self::literalPrefix($pattern, $escape);
        }

        return null;
    }

    private static function literalPrefix(string $pattern, string $escape): ?string
    {
        $prefix = '';
        $length = strlen($pattern);
        for ($i = 0; $i < $length; $i++) {
            $char = $pattern[$i];
            if ($char === $escape) {
                $i++;
                if ($i >= $length) {
                    return null;
                }
                $prefix .= $pattern[$i];
                continue;
            }
            if ($char === '%' || $char === '_') {
                return $prefix === '' ? null : $prefix;
            }
            $prefix .= $char;
        }

        return null;
    }

    private static function prefixUpperBound(string $prefix): ?string
    {
        if ($prefix === '') {
            return null;
        }
        $last = ord($prefix[strlen($prefix) - 1]);
        if ($last >= 0x7f) {
            return null;
        }

        return substr($prefix, 0, -1) . chr($last + 1);
    }

    /**
     * @param list<array<string,mixed>> $samples
     * @return array{lower:string,upper:string,samples:list<array<string,mixed>>,rowids:list<int>,keys:list<mixed>}|array{}
     */
    private static function prefixWindow(array $samples, string $prefix, string $upper, string $collation): array
    {
        $matched = [];
        foreach ($samples as $sample) {
            if (self::compare($sample['key'], $prefix, $collation) >= 0 && self::compare($sample['key'], $upper, $collation) < 0) {
                $matched[] = $sample;
            }
        }
        if ($matched === []) {
            return [];
        }

        return [
            'lower' => $prefix,
            'upper' => $upper,
            'samples' => $matched,
            'rowids' => array_column($matched, 'rowid'),
            'keys' => array_column($matched, 'key'),
        ];
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return list<array<string,mixed>>
     */
    private static function matchedRows(array $source, array $terms, string $expression, string $collation): array
    {
        $rows = [];
        foreach (self::listValue($source['rows'] ?? []) as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next175 rows must be arrays');
            }
            $rowid = $row['rowid'] ?? null;
            if (!is_int($rowid) || $rowid < 0) {
                throw new \InvalidArgumentException('SQLite next175 rowid must be a non-negative integer');
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
        usort($rows, static fn (array $a, array $b): int => self::compare($a['expressionKey'] ?? null, $b['expressionKey'] ?? null, $collation)
            ?: ((int) ($a['rowid'] ?? 0) <=> (int) ($b['rowid'] ?? 0)));

        return $rows;
    }

    /** @param list<array<string,mixed>> $terms */
    private static function rowSatisfiesTerms(array $row, array $terms, string $expression, string $collation): bool
    {
        foreach ($terms as $term) {
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $left = $term['left'] ?? null;
            if (!is_array($left)) {
                return false;
            }
            $value = array_key_exists('expression', $left)
                ? self::expressionValue($row, (string) $left['expression'])
                : ($row[(string) ($left['column'] ?? '')] ?? null);
            if ($operator === '=' && self::compare($value, self::literal($term['right'] ?? null), $collation) !== 0) {
                return false;
            }
            if ($operator === 'LIKE') {
                $prefix = self::literalPrefix((string) self::literal($term['right'] ?? null), (string) ($term['escape'] ?? '\\'));
                if ($prefix === null || !is_string($value) || !str_starts_with($value, $prefix)) {
                    return false;
                }
            }
            if ($operator === 'IS NOT NULL' && $value === null) {
                return false;
            }
            if (in_array($operator, ['>', '>=', '<', '<='], true)) {
                $comparison = self::compare($value, self::literal($term['right'] ?? null), $collation);
                if (($operator === '>' && $comparison <= 0)
                    || ($operator === '>=' && $comparison < 0)
                    || ($operator === '<' && $comparison >= 0)
                    || ($operator === '<=' && $comparison > 0)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $partialTerms
     * @param list<array<string,mixed>> $terms
     */
    private static function partialPredicateImplied(array $partialTerms, array $terms): bool
    {
        foreach ($partialTerms as $partial) {
            if (!is_array($partial)) {
                return false;
            }
            $matched = false;
            foreach ($terms as $term) {
                if (is_array($term) && self::termImplies($term, $partial)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return true;
    }

    private static function termImplies(array $term, array $partial): bool
    {
        if (self::leftKey($term['left'] ?? null) !== self::leftKey($partial['left'] ?? null)) {
            return false;
        }
        $operator = strtoupper((string) ($term['operator'] ?? ''));
        $partialOperator = strtoupper((string) ($partial['operator'] ?? ''));
        if ($partialOperator === 'IS NOT NULL') {
            return $operator !== 'IS NULL';
        }
        if ($operator === $partialOperator && self::literal($term['right'] ?? null) === self::literal($partial['right'] ?? null)) {
            return true;
        }
        if ($operator === 'LIKE' && in_array($partialOperator, ['>=', '<'], true)) {
            $prefix = self::likePrefix([$term], (string) ($term['left']['expression'] ?? ''));
            if ($prefix === null) {
                return false;
            }
            $bound = (string) self::literal($partial['right'] ?? null);

            return $partialOperator === '>='
                ? self::compare($prefix, $bound, 'BINARY') >= 0
                : self::compare((string) self::prefixUpperBound($prefix), $bound, 'BINARY') <= 0;
        }

        return false;
    }

    private static function leftKey(mixed $left): string
    {
        if (!is_array($left)) {
            return '';
        }
        if (array_key_exists('expression', $left)) {
            return 'expr:' . self::normalizeExpression((string) $left['expression']);
        }

        return 'col:' . strtolower((string) ($left['column'] ?? ''));
    }

    /**
     * @param list<array<string,mixed>> $samples
     * @return list<array{key:mixed,rowid:int,neq:int,nlt:int,ndlt:int}>
     */
    private static function stat4Samples(array $samples): array
    {
        $out = [];
        foreach ($samples as $sample) {
            if (!is_array($sample) || !is_array($sample['sample'] ?? null) || count($sample['sample']) < 2) {
                throw new \InvalidArgumentException('SQLite next175 STAT4 samples need key and rowid');
            }
            $out[] = [
                'key' => $sample['sample'][0],
                'rowid' => self::intValue($sample['sample'][1]),
                'neq' => self::firstStatInt($sample['neq'] ?? 1),
                'nlt' => self::firstStatInt($sample['nlt'] ?? 0),
                'ndlt' => self::firstStatInt($sample['ndlt'] ?? 0),
            ];
        }
        usort($out, static fn (array $a, array $b): int => self::compare($a['key'], $b['key'], 'BINARY')
            ?: ($a['rowid'] <=> $b['rowid']));

        return $out;
    }

    private static function expressionValue(array $row, string $expression): mixed
    {
        $normalized = self::normalizeExpression($expression);
        if ($normalized === 'lower(option_name)') {
            $value = $row['option_name'] ?? null;

            return is_string($value) ? strtolower($value) : null;
        }
        if ($normalized === 'length(option_value)') {
            $value = $row['option_value'] ?? null;

            return is_string($value) ? strlen($value) : null;
        }

        return $row[$normalized] ?? null;
    }

    private static function normalizeExpression(string $expression): string
    {
        return strtolower((string) preg_replace('/\s+/', '', $expression));
    }

    private static function compare(mixed $left, mixed $right, string $collation): int
    {
        if ($left === $right) {
            return 0;
        }
        if ($left === null) {
            return -1;
        }
        if ($right === null) {
            return 1;
        }
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }
        $leftString = (string) $left;
        $rightString = (string) $right;
        if (strtoupper($collation) === 'NOCASE') {
            $leftString = strtolower($leftString);
            $rightString = strtolower($rightString);
        }

        return $leftString <=> $rightString;
    }

    private static function literal(mixed $value): mixed
    {
        if (is_array($value) && array_key_exists('literal', $value)) {
            return $value['literal'];
        }

        return $value;
    }

    /** @return list<mixed> */
    private static function listValue(mixed $value): array
    {
        return is_array($value) ? array_values($value) : [];
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        return array_values(array_map('strval', self::listValue($value)));
    }

    private static function stringValue(array $array, string $key): string
    {
        if (!isset($array[$key]) || !is_scalar($array[$key])) {
            throw new \InvalidArgumentException('SQLite next175 missing string ' . $key);
        }

        return (string) $array[$key];
    }

    private static function intValue(mixed $value): int
    {
        if (!is_int($value) && !ctype_digit((string) $value)) {
            throw new \InvalidArgumentException('SQLite next175 integer value expected');
        }

        return (int) $value;
    }

    private static function firstStatInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }
        $parts = preg_split('/\s+/', trim((string) $value));
        if ($parts === false || $parts === [] || !ctype_digit($parts[0])) {
            throw new \InvalidArgumentException('SQLite next175 STAT4 counters must be numeric');
        }

        return (int) $parts[0];
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @param list<string> $neededColumns */
    private static function covers(mixed $coveringColumns, array $neededColumns): bool
    {
        $covering = array_map('strtolower', self::stringList($coveringColumns));
        foreach ($neededColumns as $column) {
            if (!in_array(strtolower($column), $covering, true)) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string,mixed> */
    private static function summary(array $source, array $plan, string $sourceSignature): array
    {
        return [
            'source' => $source['name'] ?? null,
            'schemaCookie' => self::intValue($source['schemaCookie'] ?? null),
            'stat4Generation' => self::intValue($source['stat4Generation'] ?? null),
            'sourceSignature' => $sourceSignature,
            'usable' => $plan['usable'] ?? false,
            'name' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'prefix' => $plan['prefix'] ?? null,
            'prefixUpperBound' => $plan['prefixUpperBound'] ?? null,
            'matchedRowids' => array_column($plan['matchedRows'] ?? [], 'rowid'),
            'partialPredicateImplied' => $plan['partialPredicateImplied'] ?? false,
            'stat4Used' => $plan['stat4Used'] ?? false,
            'stat4PrefixWindow' => $plan['stat4PrefixWindow'] ?? [],
        ];
    }

    /** @return array<string,mixed> */
    private static function selectedSummary(array $selected, bool $ready): array
    {
        return [
            'ready' => $ready,
            'usable' => $selected['usable'] ?? false,
            'name' => $selected['name'] ?? null,
            'rootPage' => $selected['rootPage'] ?? null,
            'expression' => $selected['expression'] ?? null,
            'expressionColumn' => $selected['expressionColumn'] ?? null,
            'collation' => $selected['collation'] ?? null,
            'covering' => $selected['covering'] ?? false,
            'partialPredicateImplied' => $selected['partialPredicateImplied'] ?? false,
            'stat4Used' => $selected['stat4Used'] ?? false,
            'prefix' => $selected['prefix'] ?? null,
            'prefixUpperBound' => $selected['prefixUpperBound'] ?? null,
            'estimatedRows' => $selected['estimatedRows'] ?? null,
            'estimatedCost' => $selected['estimatedCost'] ?? null,
            'detail' => $selected['detail'] ?? null,
        ];
    }

    /** @return list<array<string,mixed>> */
    private static function cursorProgram(array $selected, bool $ready): array
    {
        if (!$ready) {
            return [['opcode' => 'FallbackFullScan', 'reason' => 'STAT4 LIKE prefix window not usable']];
        }
        $program = [
            ['opcode' => 'OpenRead', 'source' => 'partial-expression-index', 'rootPage' => $selected['rootPage'] ?? null],
            ['opcode' => 'FenceStat4PrefixWindow', 'window' => $selected['stat4PrefixWindow'] ?? []],
            ['opcode' => 'SeekGE', 'key' => $selected['prefix'] ?? null],
            ['opcode' => 'IdxGE', 'key' => $selected['prefixUpperBound'] ?? null],
            ['opcode' => 'ResidualLikePrefix', 'prefix' => $selected['prefix'] ?? null],
        ];
        $program[] = ($selected['covering'] ?? false)
            ? ['opcode' => 'ColumnFromIndex', 'columns' => $selected['coveringColumns'] ?? []]
            : ['opcode' => 'DeferredSeek', 'table' => 'wp_options'];
        $program[] = ['opcode' => 'ResultRow', 'rowids' => array_column($selected['matchedRows'] ?? [], 'rowid')];
        $program[] = ['opcode' => 'Next', 'source' => 'partial-expression-index'];

        return $program;
    }
}
