<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteStat4ExpressionPartialCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLiteStat4ExpressionPartialCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param list<array<string,mixed>> $queryTerms
         * @return array<string,mixed>
         */
        public static function materializeNext163(array $preparedSource, array $currentSource, array $queryTerms): array
        {
            self::validateTermsNext163($queryTerms);

            $prepared = self::sourcePlanNext163($preparedSource, $queryTerms);
            $current = self::sourcePlanNext163($currentSource, $queryTerms);
            $preparedSignature = self::sourceSignatureNext163($preparedSource);
            $currentSignature = self::sourceSignatureNext163($currentSource);
            $stale = self::sourceIntNext163($preparedSource, 'schemaCookie') !== self::sourceIntNext163($currentSource, 'schemaCookie')
                || self::sourceIntNext163($preparedSource, 'stat4Generation') !== self::sourceIntNext163($currentSource, 'stat4Generation')
                || $preparedSignature !== $currentSignature;
            $selected = $stale ? $current : $prepared;
            $ready = ($selected['usable'] ?? false) === true
                && ($selected['partialPredicateImplied'] ?? false) === true
                && ($selected['stat4Used'] ?? false) === true
                && ($selected['matchedSampleCount'] ?? 0) > 0;

            return [
                'status' => $ready ? 'stat4-expression-partial-current-source-next163-ready' : 'requires-next-stage',
                'selectedSource' => $stale ? 'current' : 'prepared',
                'stalePreparedStatement' => $stale,
                'reprepareRequired' => $stale,
                'schemaCookieChanged' => self::sourceIntNext163($preparedSource, 'schemaCookie') !== self::sourceIntNext163($currentSource, 'schemaCookie'),
                'stat4GenerationChanged' => self::sourceIntNext163($preparedSource, 'stat4Generation') !== self::sourceIntNext163($currentSource, 'stat4Generation'),
                'indexSignatureChanged' => $preparedSignature !== $currentSignature,
                'preparedSource' => self::summaryNext163($preparedSource, $prepared, $preparedSignature),
                'currentSource' => self::summaryNext163($currentSource, $current, $currentSignature),
                'selectedPlan' => $selected,
                'cursorTape' => self::cursorTapeNext163($selected, $ready, $stale ? 'current' : 'prepared'),
                'currentSourceFence' => [
                    'schemaCookie' => self::sourceIntNext163($currentSource, 'schemaCookie'),
                    'stat4Generation' => self::sourceIntNext163($currentSource, 'stat4Generation'),
                    'indexSignature' => $currentSignature,
                    'querySignature' => hash('sha256', json_encode($queryTerms, JSON_THROW_ON_ERROR)),
                    'sampleSignature' => hash('sha256', json_encode($selected['matchedSamples'] ?? [], JSON_THROW_ON_ERROR)),
                ],
                'residualPredicateRequired' => true,
                'tableLookupDeferred' => $ready,
                'stat4PartialExpressionPlan' => $ready,
                'detail' => ($stale ? 'REPREPARE' : 'REUSE') . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT163 '
                    . (string) ($selected['name'] ?? 'NO INDEX'),
                'dependencies' => [
                    'SQLiteCreateIndex expression partial metadata',
                    'SQLiteAnalyzeStatPlanner STAT4 sample semantics',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next163',
                ],
                'dependency_closure' => 'no new support component needed; next163 composes lane-local expression metadata, partial predicate implication, and STAT4 sample selectivity diagnostics',
                'non_overlap' => 'avoids accepted expression-index range-cost, expression ORDER BY, expression partial covering, STAT4 collation boundaries, and JSON/VFS/B-tree clusters by choosing a stale-current partial expression index from equality+range STAT4 samples',
            ];
        }

        /**
         * @param array<string,mixed> $source
         * @param list<array<string,mixed>> $queryTerms
         * @return array<string,mixed>
         */
        private static function sourcePlanNext163(array $source, array $queryTerms): array
        {
            $plans = [];
            foreach (self::indexesNext163($source) as $index) {
                $expression = self::stringNext163($index, 'expression');
                $expressionTerm = self::expressionTermNext163($queryTerms, $expression);
                $range = self::rangeForNext163($queryTerms, $expression);
                $partialTerms = self::listNext163($index['partialPredicateTerms'] ?? []);
                $partialImplied = self::partialPredicateImpliedNext163($partialTerms, $queryTerms);
                if ($expressionTerm === null || $range === null || !$partialImplied) {
                    $plans[] = self::unusableNext163($index, $expression, $partialImplied);
                    continue;
                }

                $samples = self::stat4SamplesNext163(self::listNext163($index['stat4Samples'] ?? []));
                $matched = array_values(array_filter(
                    $samples,
                    static fn (array $sample): bool => self::sampleMatchesNext163($sample, $expressionTerm['right'] ?? null, $range)
                ));
                $estimate = max(1, array_sum(array_map(static fn (array $sample): int => $sample['neq'], $matched)));
                $plans[] = [
                    'usable' => $matched !== [],
                    'name' => self::stringNext163($index, 'name'),
                    'rootPage' => self::intNext163($index, 'rootPage'),
                    'expression' => $expression,
                    'expressionColumn' => self::stringNext163($index, 'expressionColumn', $expression),
                    'partialPredicateTerms' => $partialTerms,
                    'partialPredicateImplied' => true,
                    'equalityValue' => $expressionTerm['right'] ?? null,
                    'rangeColumn' => $range['column'],
                    'rangeLower' => $range['lower'],
                    'rangeUpper' => $range['upper'],
                    'lowerInclusive' => $range['lowerInclusive'],
                    'upperInclusive' => $range['upperInclusive'],
                    'stat4Used' => $samples !== [],
                    'stat4Samples' => $samples,
                    'matchedSamples' => $matched,
                    'matchedSampleCount' => count($matched),
                    'matchedRowids' => array_column($matched, 'rowid'),
                    'matchedKeys' => array_map(static fn (array $sample): array => [$sample['expr'], $sample['range']], $matched),
                    'estimatedRows' => $estimate,
                    'estimatedCost' => $estimate + (int) ($index['baseCost'] ?? 0),
                    'detail' => 'SEARCH ' . self::stringNext163($index, 'name') . ' USING STAT4 EXPRESSION PARTIAL CURRENT SOURCE',
                ];
            }

            usort($plans, static fn (array $left, array $right): int => [
                (bool) ($right['usable'] ?? false),
                (int) ($left['estimatedCost'] ?? PHP_INT_MAX),
                (string) ($left['name'] ?? ''),
            ] <=> [
                (bool) ($left['usable'] ?? false),
                (int) ($right['estimatedCost'] ?? PHP_INT_MAX),
                (string) ($right['name'] ?? ''),
            ]);

            return $plans[0] ?? [
                'usable' => false,
                'partialPredicateImplied' => false,
                'stat4Used' => false,
                'matchedSampleCount' => 0,
                'detail' => 'SCAN TABLE; NO STAT4 EXPRESSION PARTIAL INDEX',
            ];
        }

        /**
         * @return list<array<string,mixed>>
         */
        private static function indexesNext163(array $source): array
        {
            $indexes = $source['indexes'] ?? null;
            if (!is_array($indexes) || !array_is_list($indexes) || $indexes === []) {
                throw new \InvalidArgumentException('SQLite next163 source needs index definitions');
            }
            foreach ($indexes as $index) {
                if (!is_array($index)) {
                    throw new \InvalidArgumentException('SQLite next163 indexes must be arrays');
                }
            }

            return $indexes;
        }

        /**
         * @param list<array<string,mixed>> $samples
         * @return list<array{expr:mixed,range:mixed,neq:int,nlt:int,rowid:int}>
         */
        private static function stat4SamplesNext163(array $samples): array
        {
            $out = [];
            foreach ($samples as $offset => $sample) {
                if (!is_array($sample)) {
                    throw new \InvalidArgumentException('SQLite next163 STAT4 samples must be arrays');
                }
                $values = $sample['sample'] ?? null;
                if (!is_array($values) || count($values) < 2) {
                    throw new \InvalidArgumentException('SQLite next163 STAT4 sample must include expression and range keys');
                }
                $out[] = [
                    'expr' => self::literalNext163($values[0]),
                    'range' => self::literalNext163($values[1]),
                    'neq' => self::firstIntNext163($sample['neq'] ?? 1, 'neq'),
                    'nlt' => self::firstIntNext163($sample['nlt'] ?? 0, 'nlt', true),
                    'rowid' => (int) ($values[2] ?? $offset + 1),
                ];
            }
            usort($out, static fn (array $left, array $right): int => self::compareNext163($left['expr'], $right['expr'])
                ?: self::compareNext163($left['range'], $right['range'])
                ?: ($left['rowid'] <=> $right['rowid']));

            return $out;
        }

        /**
         * @param array{column:string,lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool} $range
         */
        private static function sampleMatchesNext163(array $sample, mixed $exprValue, array $range): bool
        {
            if (self::compareNext163($sample['expr'] ?? null, $exprValue) !== 0) {
                return false;
            }
            $lower = self::compareNext163($sample['range'] ?? null, $range['lower']);
            $upper = self::compareNext163($sample['range'] ?? null, $range['upper']);

            return ($range['lowerInclusive'] ? $lower >= 0 : $lower > 0)
                && ($range['upperInclusive'] ? $upper <= 0 : $upper < 0);
        }

        /**
         * @param list<array<string,mixed>> $terms
         * @return array<string,mixed>|null
         */
        private static function expressionTermNext163(array $terms, string $expression): ?array
        {
            $normalized = self::normalizeExpressionNext163($expression);
            foreach ($terms as $term) {
                if (($term['operator'] ?? null) !== '=') {
                    continue;
                }
                $left = $term['left'] ?? null;
                if (is_array($left) && self::normalizeExpressionNext163((string) ($left['expression'] ?? '')) === $normalized) {
                    return $term;
                }
            }

            return null;
        }

        /**
         * @param list<array<string,mixed>> $terms
         * @return array{column:string,lower:mixed,upper:mixed,lowerInclusive:bool,upperInclusive:bool}|null
         */
        private static function rangeForNext163(array $terms, string $expression): ?array
        {
            $rangeColumn = null;
            $range = ['column' => '', 'lower' => null, 'upper' => null, 'lowerInclusive' => false, 'upperInclusive' => false];
            foreach ($terms as $term) {
                $left = $term['left'] ?? null;
                if (!is_array($left) || isset($left['expression'])) {
                    continue;
                }
                $column = strtolower((string) ($left['column'] ?? ''));
                if ($column === '' || str_contains(strtolower($expression), $column)) {
                    continue;
                }
                $operator = strtoupper((string) ($term['operator'] ?? ''));
                if ($operator === '>=') {
                    $rangeColumn = $column;
                    $range['lower'] = self::literalNext163($term['right'] ?? null);
                    $range['lowerInclusive'] = true;
                } elseif ($operator === '>') {
                    $rangeColumn = $column;
                    $range['lower'] = self::literalNext163($term['right'] ?? null);
                    $range['lowerInclusive'] = false;
                } elseif ($operator === '<=') {
                    $rangeColumn = $column;
                    $range['upper'] = self::literalNext163($term['right'] ?? null);
                    $range['upperInclusive'] = true;
                } elseif ($operator === '<') {
                    $rangeColumn = $column;
                    $range['upper'] = self::literalNext163($term['right'] ?? null);
                    $range['upperInclusive'] = false;
                } elseif ($operator === 'BETWEEN') {
                    $rangeColumn = $column;
                    $range['lower'] = self::literalNext163($term['lower'] ?? null);
                    $range['upper'] = self::literalNext163($term['upper'] ?? null);
                    $range['lowerInclusive'] = true;
                    $range['upperInclusive'] = true;
                }
            }
            if ($rangeColumn === null || $range['lower'] === null || $range['upper'] === null) {
                return null;
            }
            $range['column'] = $rangeColumn;

            return $range;
        }

        /**
         * @param list<array<string,mixed>> $partialTerms
         * @param list<array<string,mixed>> $queryTerms
         */
        private static function partialPredicateImpliedNext163(array $partialTerms, array $queryTerms): bool
        {
            foreach ($partialTerms as $partial) {
                $matched = false;
                foreach ($queryTerms as $query) {
                    if (self::termKeyNext163($partial) === self::termKeyNext163($query) && self::literalNext163($partial['right'] ?? null) === self::literalNext163($query['right'] ?? null)) {
                        $matched = true;
                        break;
                    }
                    if (strtoupper((string) ($partial['operator'] ?? '')) === 'IS NOT NULL' && self::termKeyNext163($partial) === self::termKeyNext163($query)) {
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

        private static function termKeyNext163(array $term): string
        {
            $left = $term['left'] ?? null;
            if (!is_array($left)) {
                return '';
            }
            if (isset($left['expression'])) {
                return 'expr:' . self::normalizeExpressionNext163((string) $left['expression']);
            }

            return 'column:' . strtolower((string) ($left['column'] ?? ''));
        }

        private static function cursorTapeNext163(array $plan, bool $ready, string $source): array
        {
            if (!$ready) {
                return [
                    'source' => $source,
                    'indexName' => $plan['name'] ?? null,
                    'program' => [['opcode' => 'Rewind', 'source' => 'table'], ['opcode' => 'DeferredSeek', 'source' => 'table']],
                ];
            }

            return [
                'source' => $source,
                'indexName' => $plan['name'],
                'rootPage' => $plan['rootPage'],
                'seekOpcode' => $plan['lowerInclusive'] ? 'SeekGE' : 'SeekGT',
                'stopOpcode' => $plan['upperInclusive'] ? 'IdxGT' : 'IdxGE',
                'equalityValue' => $plan['equalityValue'],
                'rangeLower' => $plan['rangeLower'],
                'rangeUpper' => $plan['rangeUpper'],
                'matchedCurrentNext' => self::currentNextNext163($plan['matchedSamples']),
                'program' => [
                    ['opcode' => 'OpenRead', 'source' => 'index', 'index' => $plan['name'], 'rootPage' => $plan['rootPage']],
                    ['opcode' => $plan['lowerInclusive'] ? 'SeekGE' : 'SeekGT', 'expression' => $plan['expression'], 'key' => $plan['equalityValue'], 'range' => $plan['rangeLower']],
                    ['opcode' => $plan['upperInclusive'] ? 'IdxGT' : 'IdxGE', 'range' => $plan['rangeUpper']],
                    ['opcode' => 'Column', 'source' => 'index', 'column' => $plan['expressionColumn']],
                    ['opcode' => 'Next', 'source' => 'index'],
                ],
            ];
        }

        /**
         * @param list<array<string,mixed>> $rows
         * @return list<array{current:array<string,mixed>,next:?array<string,mixed>}>
         */
        private static function currentNextNext163(array $rows): array
        {
            $out = [];
            foreach ($rows as $offset => $row) {
                $out[] = ['current' => $row, 'next' => $rows[$offset + 1] ?? null];
            }

            return $out;
        }

        private static function unusableNext163(array $index, string $expression, bool $partialImplied): array
        {
            return [
                'usable' => false,
                'name' => (string) ($index['name'] ?? ''),
                'rootPage' => (int) ($index['rootPage'] ?? 0),
                'expression' => $expression,
                'partialPredicateImplied' => $partialImplied,
                'stat4Used' => is_array($index['stat4Samples'] ?? null) && $index['stat4Samples'] !== [],
                'matchedSampleCount' => 0,
                'estimatedCost' => PHP_INT_MAX,
                'detail' => 'SCAN TABLE; STAT4 EXPRESSION PARTIAL INDEX UNUSABLE',
            ];
        }

        private static function summaryNext163(array $source, array $plan, string $signature): array
        {
            return [
                'schemaCookie' => self::sourceIntNext163($source, 'schemaCookie'),
                'stat4Generation' => self::sourceIntNext163($source, 'stat4Generation'),
                'indexSignature' => $signature,
                'usable' => (bool) ($plan['usable'] ?? false),
                'name' => $plan['name'] ?? null,
                'rootPage' => $plan['rootPage'] ?? null,
                'estimatedRows' => $plan['estimatedRows'] ?? null,
                'matchedSampleCount' => $plan['matchedSampleCount'] ?? 0,
            ];
        }

        private static function sourceSignatureNext163(array $source): string
        {
            return hash('sha256', json_encode([
                'indexes' => $source['indexes'] ?? [],
                'schemaCookie' => self::sourceIntNext163($source, 'schemaCookie'),
                'stat4Generation' => self::sourceIntNext163($source, 'stat4Generation'),
            ], JSON_THROW_ON_ERROR));
        }

        private static function validateTermsNext163(array $terms): void
        {
            foreach ($terms as $term) {
                if (!is_array($term)) {
                    throw new \InvalidArgumentException('SQLite next163 query terms must be arrays');
                }
            }
        }

        private static function normalizeExpressionNext163(string $expression): string
        {
            return strtolower((string) preg_replace('/\s+/', '', $expression));
        }

        private static function compareNext163(mixed $left, mixed $right): int
        {
            if (is_numeric($left) && is_numeric($right)) {
                return (float) $left <=> (float) $right;
            }

            return strcmp(strtolower((string) $left), strtolower((string) $right));
        }

        private static function literalNext163(mixed $value): mixed
        {
            if (is_array($value) && array_key_exists('value', $value)) {
                return $value['value'];
            }

            return $value;
        }

        private static function firstIntNext163(mixed $value, string $field, bool $allowZero = false): int
        {
            if (is_string($value)) {
                $value = preg_split('/\s+/', trim($value))[0] ?? '';
            } elseif (is_array($value)) {
                $value = $value[0] ?? null;
            }
            if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/', $value))) {
                throw new \InvalidArgumentException('SQLite next163 STAT4 ' . $field . ' must start with an integer');
            }
            $int = (int) $value;
            if ($int < 0 || (!$allowZero && $int === 0)) {
                throw new \InvalidArgumentException('SQLite next163 STAT4 ' . $field . ' must be positive');
            }

            return $int;
        }

        private static function sourceIntNext163(array $source, string $key): int
        {
            return self::intNext163($source, $key);
        }

        private static function intNext163(array $array, string $key): int
        {
            $value = $array[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException('SQLite next163 ' . $key . ' must be a non-negative integer');
            }

            return $value;
        }

        private static function stringNext163(array $array, string $key, ?string $default = null): string
        {
            $value = $array[$key] ?? $default;
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException('SQLite next163 ' . $key . ' must be a non-empty string');
            }

            return $value;
        }

        /**
         * @return list<array<string,mixed>>
         */
        private static function listNext163(mixed $value): array
        {
            if (!is_array($value) || !array_is_list($value)) {
                throw new \InvalidArgumentException('SQLite next163 list fields must be lists');
            }

            return $value;
        }

}
