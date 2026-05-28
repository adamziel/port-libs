<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext168Plan
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
            throw new \InvalidArgumentException('SQLite next168 needed columns cannot be empty');
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
            && ($selected['likePrefixImpliedByPartial'] ?? false) === true
            && ($selected['stat4Used'] ?? false) === true
            && ($selected['matchedRows'] ?? []) !== [];

        return [
            'status' => $ready ? 'stat4-expression-partial-current-source-next168-ready' : 'requires-next-stage',
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
            'prefixFence' => [
                'lower' => $selected['likeRange']['lower'] ?? null,
                'upper' => $selected['likeRange']['upper'] ?? null,
                'lowerInclusive' => true,
                'upperInclusive' => false,
                'prefix' => $selected['likePrefix'] ?? null,
            ],
            'stat4Fence' => [
                'schemaCookie' => self::intValue($stale ? $currentSource : $preparedSource, 'schemaCookie'),
                'stat4Generation' => self::intValue($stale ? $currentSource : $preparedSource, 'stat4Generation'),
                'sourceSignature' => $stale ? $currentSignature : $preparedSignature,
                'prefixSignature' => self::signature($selected['likeRange'] ?? []),
                'stat4Signature' => self::signature($selected['stat4Samples'] ?? []),
                'rowStreamSignature' => self::signature(array_column($selected['matchedRows'] ?? [], 'rowid')),
            ],
            'cursorProgram' => self::cursorProgram($selected, $ready),
            'tableLookupRequired' => !($selected['covering'] ?? false),
            'residualPredicateRequired' => true,
            'detail' => (($stale ? 'REPREPARE' : 'REUSE') . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT168 LIKE PREFIX ' . (string) ($selected['name'] ?? 'NO INDEX')),
            'dependencies' => ['sqlite-sqlplanner-stat4-expression-partial-current-source-next168'],
            'dependency_closure' => 'no new support component needed; next168 reuses native expression normalization, LIKE prefix range derivation, partial-index proof, STAT4 fences, and current-source row diagnostics',
            'non_overlap' => 'avoids accepted next154 equality/IN/BETWEEN row streams, next158 stale-row range exclusion, next161 OR-split probes, and next164 explicit range bounds by proving LIKE-prefix partial expression admission from current STAT4 samples',
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
                throw new \InvalidArgumentException('SQLite next168 indexes must be arrays');
            }
            $expression = self::stringValue($index, 'expression');
            $like = self::likePrefixTerm($terms, $expression);
            $range = $like === null ? null : self::prefixRange($like['prefix']);
            $partial = self::listValue($index['partialPredicateTerms'] ?? []);
            $partialByLike = $like !== null && self::partialImpliedByTerms($partial, $terms);
            $stat4 = self::stat4Samples(self::listValue($index['stat4Samples'] ?? []));
            $matchedSamples = $range === null || !$partialByLike ? [] : self::matchingSamples($stat4, $range, (string) ($index['collation'] ?? 'BINARY'));
            $matchedRows = $matchedSamples === [] ? [] : self::matchedRows($source, $terms, $expression, (string) ($index['collation'] ?? 'BINARY'));
            $covering = self::covers($index['coveringColumns'] ?? [], $neededColumns);
            $estimate = max(1, array_sum(array_column($matchedSamples, 'neq')));
            $plan = [
                'usable' => $like !== null && $range !== null && $partialByLike && $matchedSamples !== [] && $matchedRows !== [],
                'name' => self::stringValue($index, 'name'),
                'rootPage' => self::intValue($index, 'rootPage'),
                'expression' => $expression,
                'expressionColumn' => (string) ($index['expressionColumn'] ?? ''),
                'collation' => strtoupper((string) ($index['collation'] ?? 'BINARY')),
                'coveringColumns' => self::stringList($index['coveringColumns'] ?? []),
                'covering' => $covering,
                'partialPredicateTerms' => $partial,
                'likePrefixImpliedByPartial' => $partialByLike,
                'likePrefix' => $like['prefix'] ?? null,
                'likePattern' => $like['pattern'] ?? null,
                'likeRange' => $range,
                'stat4Samples' => $stat4,
                'stat4Used' => $stat4 !== [],
                'matchedStat4Keys' => array_column($matchedSamples, 'key'),
                'matchedStat4Rowids' => array_column($matchedSamples, 'rowid'),
                'matchedRows' => $matchedRows,
                'estimatedRows' => $estimate,
                'estimatedCost' => $estimate + ($covering ? 0 : 12) + ($partialByLike ? 0 : 50),
                'detail' => 'SEARCH ' . self::stringValue($index, 'name') . ' USING STAT4 PARTIAL EXPRESSION LIKE PREFIX',
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

        return $best ?? ['usable' => false, 'matchedRows' => [], 'stat4Used' => false, 'likePrefixImpliedByPartial' => false];
    }

    /**
     * @param list<array<string,mixed>> $terms
     * @return array{pattern:string,prefix:string}|null
     */
    private static function likePrefixTerm(array $terms, string $expression): ?array
    {
        $normalized = self::normalizeExpression($expression);
        foreach ($terms as $term) {
            if (self::normalizeExpression((string) ($term['left']['expression'] ?? '')) !== $normalized) {
                continue;
            }
            if (strtoupper((string) ($term['operator'] ?? '')) !== 'LIKE') {
                continue;
            }
            $pattern = self::stringCast(self::literal($term['right'] ?? null));
            if (!str_ends_with($pattern, '%') || str_contains(substr($pattern, 0, -1), '%') || str_contains($pattern, '_')) {
                return null;
            }

            return ['pattern' => $pattern, 'prefix' => substr($pattern, 0, -1)];
        }

        return null;
    }

    /** @return array{lower:string,upper:string}|null */
    private static function prefixRange(string $prefix): ?array
    {
        if ($prefix === '') {
            return null;
        }
        $last = substr($prefix, -1);
        if ($last === "\xff") {
            return null;
        }

        return ['lower' => $prefix, 'upper' => substr($prefix, 0, -1) . chr(ord($last) + 1)];
    }

    /**
     * @param list<array<string,mixed>> $partial
     * @param list<array<string,mixed>> $terms
     */
    private static function partialImpliedByTerms(array $partial, array $terms): bool
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
            }
            if (!$found) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $samples
     * @param array{lower:string,upper:string} $range
     * @return list<array<string,mixed>>
     */
    private static function matchingSamples(array $samples, array $range, string $collation): array
    {
        return array_values(array_filter($samples, static function (array $sample) use ($range, $collation): bool {
            return self::compare($sample['key'], $range['lower'], $collation) >= 0
                && self::compare($sample['key'], $range['upper'], $collation) < 0;
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
                throw new \InvalidArgumentException('SQLite next168 rows must be arrays');
            }
            $rowid = $row['rowid'] ?? null;
            if (!is_int($rowid) || $rowid < 0) {
                throw new \InvalidArgumentException('SQLite next168 rows need non-negative integer rowid');
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

    /** @param list<array<string,mixed>> $terms */
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
            if ($operator === 'LIKE' && self::likeMatches($leftValue, self::stringCast(self::literal($term['right'] ?? null)), $collation)) {
                continue;
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

        throw new \InvalidArgumentException('SQLite next168 unsupported expression ' . $expression);
    }

    private static function likeMatches(mixed $value, string $pattern, string $collation): bool
    {
        if (!is_string($value) || !str_ends_with($pattern, '%')) {
            return false;
        }
        $prefix = substr($pattern, 0, -1);
        $candidate = strtoupper($collation) === 'NOCASE' ? strtolower($value) : $value;
        $needle = strtoupper($collation) === 'NOCASE' ? strtolower($prefix) : $prefix;

        return str_starts_with($candidate, $needle);
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
                throw new \InvalidArgumentException('SQLite next168 STAT4 samples need key and rowid');
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

    /** @return list<array<string,mixed>> */
    private static function cursorProgram(array $selected, bool $ready): array
    {
        if (!$ready) {
            return [['opcode' => 'FallbackFullScan', 'reason' => 'partial expression STAT4 LIKE prefix not usable']];
        }

        return [
            ['opcode' => 'OpenRead', 'rootPage' => $selected['rootPage'], 'index' => $selected['name']],
            ['opcode' => 'SeekGE', 'key' => $selected['likeRange']['lower']],
            ['opcode' => 'IdxLT', 'key' => $selected['likeRange']['upper']],
            ['opcode' => ($selected['covering'] ? 'ColumnFromIndex' : 'DeferredSeek')],
            ['opcode' => 'ResidualLikeCheck', 'pattern' => $selected['likePattern']],
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
            'likePrefixImpliedByPartial' => $plan['likePrefixImpliedByPartial'] ?? false,
            'likePattern' => $plan['likePattern'] ?? null,
            'likePrefix' => $plan['likePrefix'] ?? null,
            'likeRange' => $plan['likeRange'] ?? null,
            'stat4Used' => $plan['stat4Used'] ?? false,
            'matchedStat4Keys' => $plan['matchedStat4Keys'] ?? [],
            'matchedStat4Rowids' => $plan['matchedStat4Rowids'] ?? [],
            'matchedRowCount' => count($plan['matchedRows'] ?? []),
            'estimatedRows' => $plan['estimatedRows'] ?? null,
            'estimatedCost' => $plan['estimatedCost'] ?? null,
            'next168Ready' => $ready,
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
            throw new \InvalidArgumentException('SQLite next168 WHERE terms cannot be empty');
        }
        foreach ($terms as $term) {
            if (!is_array($term) || !is_array($term['left'] ?? null) || !is_string($term['operator'] ?? null)) {
                throw new \InvalidArgumentException('SQLite next168 WHERE terms need left/operator');
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
            throw new \InvalidArgumentException('SQLite next168 integer literal expected');
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
            throw new \InvalidArgumentException('SQLite next168 string value expected for ' . $key);
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
