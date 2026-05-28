<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext154Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns
    ): array {
        self::validateQueryTerms($queryTerms);
        self::validateNeededColumns($neededColumns);

        $prepared = self::sourcePlan($preparedSource, $queryTerms, $neededColumns);
        $current = self::sourcePlan($currentSource, $queryTerms, $neededColumns);
        $preparedSignature = self::sourceSignature($preparedSource);
        $currentSignature = self::sourceSignature($currentSource);
        $stale = $preparedSignature !== $currentSignature;
        $selected = $stale ? $current : $prepared;
        $source = $stale ? $currentSource : $preparedSource;
        $rows = self::matchedRows($source, $selected, $queryTerms, $neededColumns);
        $ready = ($selected['usable'] ?? false) === true
            && ($selected['partialPredicateImplied'] ?? false) === true
            && ($selected['stat4Used'] ?? false) === true
            && $rows !== [];

        return [
            'status' => $ready ? 'stat4-expression-partial-current-source-next154-ready' : 'requires-next-stage',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => self::sourceInt($preparedSource, 'schemaCookie') !== self::sourceInt($currentSource, 'schemaCookie'),
            'stat4GenerationChanged' => self::sourceInt($preparedSource, 'stat4Generation') !== self::sourceInt($currentSource, 'stat4Generation'),
            'indexSignatureChanged' => $preparedSignature !== $currentSignature,
            'preparedSource' => self::sourceSummary($preparedSource, $prepared, $preparedSignature),
            'currentSource' => self::sourceSummary($currentSource, $current, $currentSignature),
            'selectedPlan' => $selected + [
                'matchedRowCount' => count($rows),
                'matchedRowids' => array_column($rows, 'rowid'),
                'rowStreamSignature' => self::signature(array_column($rows, 'rowid')),
            ],
            'matchedRows' => $rows,
            'currentNextRows' => self::currentNextRows($rows),
            'cursorProgram' => self::cursorProgram($selected, $rows, $neededColumns, $ready),
            'tableLookupRequired' => !($selected['covering'] ?? false),
            'residualPredicateRequired' => true,
            'stat4Fence' => [
                'schemaCookie' => self::sourceInt($source, 'schemaCookie'),
                'stat4Generation' => self::sourceInt($source, 'stat4Generation'),
                'sourceSignature' => $stale ? $currentSignature : $preparedSignature,
                'expressionSignature' => self::normalizeExpression((string) ($selected['expression'] ?? '')),
                'partialPredicateSignature' => self::signature($selected['partialPredicateTerms'] ?? []),
                'stat4SampleSignature' => self::signature($selected['stat4Samples'] ?? []),
                'rowStreamSignature' => self::signature(array_column($rows, 'rowid')),
            ],
            'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT154 '
                . (string) ($selected['name'] ?? 'NO INDEX')
                . ($ready ? ' WITH STAT4 ROW STREAM' : ' FALLBACK SCAN'),
            'dependencies' => ['sqlite-sqlplanner-stat4-expression-partial-current-source-next154'],
            'dependency_closure' => 'no new support component needed; next154 reuses lane-local expression terms, partial predicate implication, STAT4 sample fences, and current-source row diagnostics',
            'non_overlap' => 'avoids accepted STAT4 collation boundaries, expression partial covering next148, skip-scan current-source next141, expression ORDER BY, range-cost, JSON, VFS/WAL, and B-tree clusters by testing STAT4 row-stream selection for a non-covering partial expression index after current-source reprepare',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function sourcePlan(array $source, array $queryTerms, array $neededColumns): array
    {
        $best = null;
        foreach (self::indexes($source) as $index) {
            $expression = self::string($index, 'expression');
            $stat4 = self::stat4Samples(self::list($index['stat4Samples'] ?? []));
            $constraint = self::expressionConstraint($queryTerms, $expression);
            $partialImplied = self::partialPredicateImplied(self::list($index['partialPredicateTerms'] ?? []), $queryTerms);
            $matchedSamples = $constraint === null ? [] : self::samplesMatching($stat4, $constraint, (string) ($index['collation'] ?? 'BINARY'));
            $coveringColumns = self::stringList($index['coveringColumns'] ?? []);
            $covering = count(array_diff(array_map('strtolower', $neededColumns), array_map('strtolower', $coveringColumns))) === 0;
            $stat4Rows = array_sum(array_map(static fn (array $sample): int => $sample['neq'], $matchedSamples));
            $plan = [
                'name' => self::string($index, 'name'),
                'rootPage' => self::int($index, 'rootPage'),
                'expression' => $expression,
                'expressionColumn' => (string) ($index['expressionColumn'] ?? ''),
                'collation' => strtoupper((string) ($index['collation'] ?? 'BINARY')),
                'partialPredicateTerms' => self::list($index['partialPredicateTerms'] ?? []),
                'coveringColumns' => $coveringColumns,
                'stat4Samples' => $stat4,
                'constraint' => $constraint,
                'partialPredicateImplied' => $partialImplied,
                'covering' => $covering,
                'stat4Used' => $stat4 !== [],
                'stat4MatchedSamples' => count($matchedSamples),
                'stat4Estimate' => max(1, $stat4Rows),
                'estimatedRows' => max(1, $stat4Rows),
                'estimatedCost' => max(1, $stat4Rows + ($covering ? 0 : 12)),
                'usable' => $constraint !== null && $partialImplied && $matchedSamples !== [],
                'matchedStat4Keys' => array_column($matchedSamples, 'key'),
                'matchedStat4Rowids' => array_column($matchedSamples, 'rowid'),
                'stat4CurrentNext' => self::currentNextRows($stat4),
                'stat4MatchedCurrentNext' => self::currentNextRows($matchedSamples),
                'detail' => 'SEARCH ' . self::string($index, 'name') . ' USING STAT4 EXPRESSION PARTIAL CURRENT SOURCE',
            ];
            if (
                $best === null
                || [
                    ($plan['usable'] ?? false) ? 0 : 1,
                    $plan['estimatedCost'],
                    $plan['name'],
                ] < [
                    ($best['usable'] ?? false) ? 0 : 1,
                    ($best['estimatedCost'] ?? PHP_INT_MAX),
                    ($best['name'] ?? ''),
                ]
            ) {
                $best = $plan;
            }
        }

        return $best ?? [
            'usable' => false,
            'partialPredicateImplied' => false,
            'covering' => false,
            'stat4Used' => false,
            'detail' => 'SCAN TABLE; NO STAT4 EXPRESSION PARTIAL INDEX',
        ];
    }

    /**
     * @param list<array<string,mixed>> $queryTerms
     * @return array{operator:string,values:list<mixed>,lower?:mixed,upper?:mixed,lowerInclusive?:bool,upperInclusive?:bool}|null
     */
    private static function expressionConstraint(array $queryTerms, string $expression): ?array
    {
        $normalized = self::normalizeExpression($expression);
        foreach ($queryTerms as $term) {
            $left = $term['left'] ?? null;
            if (!is_array($left) || self::normalizeExpression((string) ($left['expression'] ?? '')) !== $normalized) {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            if ($operator === '=') {
                return ['operator' => '=', 'values' => [self::literal($term['right'] ?? null)]];
            }
            if ($operator === 'IN' && isset($term['values']) && is_array($term['values'])) {
                return ['operator' => 'IN', 'values' => array_map(self::literal(...), array_values($term['values']))];
            }
            if ($operator === 'BETWEEN') {
                return [
                    'operator' => 'BETWEEN',
                    'values' => [],
                    'lower' => self::literal($term['lower'] ?? null),
                    'upper' => self::literal($term['upper'] ?? null),
                    'lowerInclusive' => true,
                    'upperInclusive' => true,
                ];
            }
        }

        return null;
    }

    /**
     * @param list<array{key:mixed,neq:int,nlt:int,ndlt:int,rowid:int}> $samples
     * @param array<string,mixed> $constraint
     * @return list<array{key:mixed,neq:int,nlt:int,ndlt:int,rowid:int}>
     */
    private static function samplesMatching(array $samples, array $constraint, string $collation): array
    {
        return array_values(array_filter($samples, static function (array $sample) use ($constraint, $collation): bool {
            $operator = $constraint['operator'] ?? null;
            if ($operator === '=' || $operator === 'IN') {
                foreach (($constraint['values'] ?? []) as $value) {
                    if (self::compare($sample['key'], $value, $collation) === 0) {
                        return true;
                    }
                }

                return false;
            }
            if ($operator === 'BETWEEN') {
                return self::compare($sample['key'], $constraint['lower'] ?? null, $collation) >= 0
                    && self::compare($sample['key'], $constraint['upper'] ?? null, $collation) <= 0;
            }

            return false;
        }));
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $plan
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function matchedRows(array $source, array $plan, array $queryTerms, array $neededColumns): array
    {
        if (($plan['usable'] ?? false) !== true) {
            return [];
        }
        $rows = self::list($source['rows'] ?? []);
        $out = [];
        foreach ($rows as $offset => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial rows must be arrays');
            }
            if (!self::rowSatisfiesTerms($row, $queryTerms, (string) ($plan['expression'] ?? ''))) {
                continue;
            }
            $out[] = [
                'sourceOffset' => $offset,
                'rowid' => (int) ($row['rowid'] ?? $row['_rowid_'] ?? 0),
                'expressionKey' => self::expressionValue((string) ($plan['expression'] ?? ''), $row),
                'payload' => self::payload($row, $neededColumns),
            ];
        }
        usort($out, static fn (array $left, array $right): int => self::compare($left['expressionKey'] ?? null, $right['expressionKey'] ?? null, (string) ($plan['collation'] ?? 'BINARY'))
            ?: ((int) ($left['rowid'] ?? 0) <=> (int) ($right['rowid'] ?? 0)));

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $terms
     */
    private static function rowSatisfiesTerms(array $row, array $terms, string $expression): bool
    {
        foreach ($terms as $term) {
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $left = $term['left'] ?? null;
            if (is_array($left) && isset($left['expression']) && self::normalizeExpression((string) $left['expression']) === self::normalizeExpression($expression)) {
                $value = self::expressionValue($expression, $row);
                if ($operator === '=' && self::compare($value, self::literal($term['right'] ?? null), 'BINARY') !== 0) {
                    return false;
                }
                if ($operator === 'IN') {
                    $values = isset($term['values']) && is_array($term['values']) ? array_map(self::literal(...), $term['values']) : [];
                    $matched = false;
                    foreach ($values as $expected) {
                        if (self::compare($value, $expected, 'BINARY') === 0) {
                            $matched = true;
                            break;
                        }
                    }
                    if (!$matched) {
                        return false;
                    }
                }
                if ($operator === 'BETWEEN' && (self::compare($value, self::literal($term['lower'] ?? null), 'BINARY') < 0 || self::compare($value, self::literal($term['upper'] ?? null), 'BINARY') > 0)) {
                    return false;
                }
                continue;
            }

            if (!is_array($left) || !isset($left['column'])) {
                continue;
            }
            $column = (string) $left['column'];
            $value = $row[$column] ?? null;
            $right = self::literal($term['right'] ?? null);
            if ($operator === '=' && self::compare($value, $right, 'BINARY') !== 0) {
                return false;
            }
            if (($operator === '!=' || $operator === '<>') && self::compare($value, $right, 'BINARY') === 0) {
                return false;
            }
            if ($operator === 'IS NOT NULL' && $value === null) {
                return false;
            }
        }

        return true;
    }

    private static function expressionValue(string $expression, array $row): mixed
    {
        if (preg_match('/^lower\(([^)]+)\)$/i', self::normalizeExpression($expression), $match) === 1) {
            $value = $row[$match[1]] ?? null;
            return is_string($value) ? strtolower($value) : $value;
        }
        if (preg_match('/^upper\(([^)]+)\)$/i', self::normalizeExpression($expression), $match) === 1) {
            $value = $row[$match[1]] ?? null;
            return is_string($value) ? strtoupper($value) : $value;
        }

        return $row[$expression] ?? null;
    }

    /**
     * @param list<array<string,mixed>> $partialTerms
     * @param list<array<string,mixed>> $queryTerms
     */
    private static function partialPredicateImplied(array $partialTerms, array $queryTerms): bool
    {
        foreach ($partialTerms as $partial) {
            if (!is_array($partial)) {
                return false;
            }
            $matched = false;
            foreach ($queryTerms as $query) {
                if (self::termImplies($query, $partial)) {
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

    private static function termImplies(array $query, array $partial): bool
    {
        $queryLeft = $query['left'] ?? null;
        $partialLeft = $partial['left'] ?? null;
        if (!is_array($queryLeft) || !is_array($partialLeft)) {
            return false;
        }
        if (($queryLeft['column'] ?? null) !== ($partialLeft['column'] ?? null)) {
            return false;
        }
        $queryOperator = strtoupper((string) ($query['operator'] ?? ''));
        $partialOperator = strtoupper((string) ($partial['operator'] ?? ''));
        if ($partialOperator === 'IS NOT NULL') {
            return in_array($queryOperator, ['=', '!=', '<>', 'IS NOT NULL'], true);
        }

        return $queryOperator === $partialOperator
            && self::literal($query['right'] ?? null) === self::literal($partial['right'] ?? null);
    }

    /**
     * @param list<array<string,mixed>> $samples
     * @return list<array{key:mixed,neq:int,nlt:int,ndlt:int,rowid:int}>
     */
    private static function stat4Samples(array $samples): array
    {
        $out = [];
        foreach ($samples as $offset => $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial samples must be arrays');
            }
            $values = $sample['sample'] ?? null;
            if (!is_array($values) || !array_is_list($values) || $values === []) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial sample needs key values');
            }
            $out[] = [
                'key' => self::literal($values[0]),
                'neq' => self::statInt($sample['neq'] ?? null, 'neq'),
                'nlt' => self::statInt($sample['nlt'] ?? null, 'nlt', true),
                'ndlt' => self::statInt($sample['ndlt'] ?? 0, 'ndlt', true),
                'rowid' => (int) ($values[1] ?? $offset + 1),
            ];
        }
        usort($out, static fn (array $left, array $right): int => self::compare($left['key'], $right['key'], 'BINARY') ?: ($left['rowid'] <=> $right['rowid']));

        return $out;
    }

    private static function statInt(mixed $value, string $field, bool $allowZero = false): int
    {
        $first = is_string($value) ? strtok($value, ' ') : $value;
        if (!is_int($first) && !(is_string($first) && ctype_digit($first))) {
            throw new \InvalidArgumentException("SQLite STAT4 expression partial {$field} must be an integer");
        }
        $int = (int) $first;
        if ($int < 0 || (!$allowZero && $int === 0)) {
            throw new \InvalidArgumentException("SQLite STAT4 expression partial {$field} is out of range");
        }

        return $int;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<array{current:array<string,mixed>,next:?array<string,mixed>}>
     */
    private static function currentNextRows(array $rows): array
    {
        $pairs = [];
        foreach ($rows as $offset => $row) {
            $pairs[] = ['current' => $row, 'next' => $rows[$offset + 1] ?? null];
        }

        return $pairs;
    }

    /**
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $plan, array $rows, array $neededColumns, bool $ready): array
    {
        if (!$ready) {
            return [['opcode' => 'Rewind', 'source' => 'table'], ['opcode' => 'DeferredSeek', 'source' => 'table']];
        }
        $program = [
            ['opcode' => 'OpenRead', 'source' => 'index', 'index' => $plan['name'] ?? null, 'rootPage' => $plan['rootPage'] ?? null],
            ['opcode' => 'SeekStat4Expression', 'expression' => $plan['expression'] ?? null, 'constraint' => $plan['constraint'] ?? null],
            ['opcode' => 'IdxRowid', 'source' => 'index'],
            ['opcode' => ($plan['covering'] ?? false) ? 'Noop' : 'DeferredSeek', 'source' => ($plan['covering'] ?? false) ? 'covering-index' : 'table'],
        ];
        foreach ($neededColumns as $column) {
            $program[] = ['opcode' => 'Column', 'source' => ($plan['covering'] ?? false) ? 'covering-index' : 'table', 'column' => $column];
        }
        $program[] = ['opcode' => 'ResultRow', 'rowCount' => count($rows)];
        $program[] = ['opcode' => 'Next', 'source' => 'index'];

        return $program;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function indexes(array $source): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes) || $indexes === []) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial source needs index definitions');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial indexes must be arrays');
            }
        }

        return $indexes;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function list(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial value must be a list');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        $list = self::list($value);
        foreach ($list as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial string list contains invalid value');
            }
        }

        return $list;
    }

    private static function validateQueryTerms(array $queryTerms): void
    {
        if (!array_is_list($queryTerms) || $queryTerms === []) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial query terms must be a non-empty list');
        }
    }

    private static function validateNeededColumns(array $neededColumns): void
    {
        if (!array_is_list($neededColumns) || $neededColumns === []) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial needed columns must be a non-empty list');
        }
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial needed columns must be names');
            }
        }
    }

    private static function payload(array $row, array $columns): array
    {
        $payload = [];
        foreach ($columns as $column) {
            $payload[$column] = $row[$column] ?? null;
        }

        return $payload;
    }

    private static function sourceSummary(array $source, array $plan, string $signature): array
    {
        return [
            'name' => (string) ($source['name'] ?? ''),
            'schemaCookie' => self::sourceInt($source, 'schemaCookie'),
            'stat4Generation' => self::sourceInt($source, 'stat4Generation'),
            'sourceSignature' => $signature,
            'usable' => (bool) ($plan['usable'] ?? false),
            'nameSelected' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'estimatedRows' => $plan['estimatedRows'] ?? null,
        ];
    }

    private static function sourceSignature(array $source): string
    {
        return self::signature([
            'schemaCookie' => self::sourceInt($source, 'schemaCookie'),
            'stat4Generation' => self::sourceInt($source, 'stat4Generation'),
            'indexes' => $source['indexes'] ?? [],
            'rows' => $source['rows'] ?? [],
        ]);
    }

    private static function sourceInt(array $source, string $key): int
    {
        return self::int($source, $key);
    }

    private static function int(array $source, string $key): int
    {
        $value = $source[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite STAT4 expression partial {$key} must be a non-negative integer");
        }

        return $value;
    }

    private static function string(array $source, string $key): string
    {
        $value = $source[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite STAT4 expression partial {$key} must be a non-empty string");
        }

        return $value;
    }

    private static function normalizeExpression(string $expression): string
    {
        return strtolower((string) preg_replace('/\s+/', '', $expression));
    }

    private static function literal(mixed $value): mixed
    {
        return is_array($value) && array_key_exists('literal', $value) ? $value['literal'] : $value;
    }

    private static function compare(mixed $left, mixed $right, string $collation): int
    {
        if (is_string($left) && is_string($right)) {
            return strtoupper($collation) === 'NOCASE' ? strcasecmp($left, $right) : strcmp($left, $right);
        }

        return $left <=> $right;
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
