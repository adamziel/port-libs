<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext174Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $queryTerms
     * @param list<string> $neededColumns
     * @param array<string,mixed>|null $nextSource
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $queryTerms,
        array $neededColumns,
        ?array $nextSource = null
    ): array {
        self::assertNeededColumns($neededColumns);

        $index = self::selectedExpressionIndex($currentSource);
        $range = self::expressionRange($queryTerms);
        $partialTerms = self::partialTerms($index);
        $currentRows = self::matchingRows($currentSource, $partialTerms, $range, $neededColumns);
        $currentSignature = self::signature($currentRows);
        $nextRows = $nextSource === null ? $currentRows : self::matchingRows($nextSource, $partialTerms, $range, $neededColumns);
        $nextSignature = $nextSource === null ? $currentSignature : self::signature($nextRows);

        $schemaChanged = (int) ($preparedSource['schemaCookie'] ?? 0) !== (int) ($currentSource['schemaCookie'] ?? 0);
        $stat4Changed = (int) ($preparedSource['stat4Generation'] ?? 0) !== (int) ($currentSource['stat4Generation'] ?? 0);
        $nextSchemaChanged = $nextSource !== null && (int) ($nextSource['schemaCookie'] ?? 0) !== (int) ($currentSource['schemaCookie'] ?? 0);
        $nextStat4Changed = $nextSource !== null && (int) ($nextSource['stat4Generation'] ?? 0) !== (int) ($currentSource['stat4Generation'] ?? 0);
        $rangeStable = $currentSignature === $nextSignature;
        $admitted = !$nextSchemaChanged && !$nextStat4Changed && $rangeStable;
        $stat4 = self::stat4Range($index, $range);

        $reasons = [];
        if ($nextSchemaChanged) {
            $reasons[] = 'schema-cookie';
        }
        if ($nextStat4Changed) {
            $reasons[] = 'stat4-generation';
        }
        if (!$rangeStable) {
            $reasons[] = 'range-row-signature';
        }

        return [
            'status' => $admitted ? 'stat4-expression-partial-current-source-next174-ready' : 'requires-current-source-reprepare',
            'selectedSource' => ($schemaChanged || $stat4Changed) ? 'current' : 'prepared',
            'stalePreparedStatement' => $schemaChanged || $stat4Changed,
            'reprepareRequired' => $schemaChanged || $stat4Changed,
            'schemaCookieChanged' => $schemaChanged,
            'stat4GenerationChanged' => $stat4Changed,
            'selectedPlan' => [
                'name' => (string) ($index['name'] ?? ''),
                'rootPage' => (int) ($index['rootPage'] ?? 0),
                'expression' => self::normalExpression((string) ($index['expression'] ?? '')),
                'partial' => true,
                'partialPredicateImpliedByQuery' => self::partialPredicateImplied($partialTerms, $queryTerms),
                'rangeLower' => $range['lower'],
                'rangeUpper' => $range['upper'],
                'lowerInclusive' => $range['lowerInclusive'],
                'upperInclusive' => $range['upperInclusive'],
                'stat4Used' => true,
                'stat4MatchedKeys' => $stat4['keys'],
                'stat4MatchedRowids' => $stat4['rowids'],
                'estimatedRows' => $stat4['estimatedRows'],
                'covering' => self::covering($index, $neededColumns),
                'matchedRowids' => array_map(static fn (array $row): int => (int) $row['rowid'], $currentRows),
                'next174Ready' => $admitted,
                'next174RangeRowsStable' => $rangeStable,
            ],
            'matchedRows' => $currentRows,
            'matchedRowids' => array_map(static fn (array $row): int => (int) $row['rowid'], $currentRows),
            'matchedExpressionKeys' => array_map(static fn (array $row): string => (string) $row['expressionKey'], $currentRows),
            'stat4Fence' => [
                'schemaCookie' => (int) ($currentSource['schemaCookie'] ?? 0),
                'stat4Generation' => (int) ($currentSource['stat4Generation'] ?? 0),
                'rangeLower' => $range['lower'],
                'rangeUpper' => $range['upper'],
                'matchedStat4Keys' => $stat4['keys'],
                'currentRangeRowids' => array_map(static fn (array $row): int => (int) $row['rowid'], $currentRows),
                'nextRangeRowids' => array_map(static fn (array $row): int => (int) $row['rowid'], $nextRows),
                'currentRangeSignature' => $currentSignature,
                'nextRangeSignature' => $nextSignature,
                'rangeRowsStable' => $rangeStable,
            ],
            'next174Source' => [
                'admitted' => $admitted,
                'replanReasons' => $admitted ? [] : $reasons,
                'rangeRowsStable' => $rangeStable,
                'nextSchemaChanged' => $nextSchemaChanged,
                'nextStat4Changed' => $nextStat4Changed,
            ],
            'cursorProgram' => self::cursorProgram($index, $range, $currentRows, $admitted),
            'detail' => 'STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT174 '
                . (string) ($index['name'] ?? 'NO INDEX')
                . ($admitted ? ' RANGE ROWS STABLE' : ' RANGE ROWS REQUIRE REPREPARE'),
            'dependencies' => ['sqlite-sqlplanner-stat4-expression-partial-current-source-next174'],
            'dependency_closure' => 'no new support component needed; next174 reuses bounded STAT4 expression partial planner metadata and adds only current-source range-row invalidation checks',
            'non_overlap' => 'avoids accepted next170 IN-bucket row churn, next169 competing full expression-index cost, expression ORDER BY, JSON, WAL, VFS, and B-tree clusters; this slice is only STAT4 range-row stability for partial expression indexes',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function selectedExpressionIndex(array $source): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes) || $indexes === []) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next174 needs indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next174 indexes must be arrays');
            }
            if (self::normalExpression((string) ($index['expression'] ?? '')) === 'lower(option_name)' && self::partialTerms($index) !== []) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next174 needs lower(option_name) partial index');
    }

    /**
     * @param list<array<string,mixed>> $queryTerms
     * @return array{lower:string,upper:string,lowerInclusive:bool,upperInclusive:bool}
     */
    private static function expressionRange(array $queryTerms): array
    {
        $lower = null;
        $upper = null;
        $lowerInclusive = false;
        $upperInclusive = false;
        foreach ($queryTerms as $term) {
            $left = $term['left'] ?? null;
            if (!is_array($left) || self::normalExpression((string) ($left['expression'] ?? '')) !== 'lower(option_name)') {
                continue;
            }
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $right = $term['right'] ?? null;
            if (!is_string($right)) {
                continue;
            }
            if ($operator === '>' || $operator === '>=') {
                if ($lower === null || strcmp($right, $lower) > 0) {
                    $lower = $right;
                    $lowerInclusive = $operator === '>=';
                }
            }
            if ($operator === '<' || $operator === '<=') {
                if ($upper === null || strcmp($right, $upper) < 0) {
                    $upper = $right;
                    $upperInclusive = $operator === '<=';
                }
            }
        }
        if ($lower === null || $upper === null) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next174 needs bounded lower(option_name) range');
        }

        return ['lower' => $lower, 'upper' => $upper, 'lowerInclusive' => $lowerInclusive, 'upperInclusive' => $upperInclusive];
    }

    /**
     * @param array<string,mixed> $index
     * @return list<array<string,mixed>>
     */
    private static function partialTerms(array $index): array
    {
        $terms = $index['partialPredicateTerms'] ?? [];
        if (!is_array($terms) || !array_is_list($terms)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next174 partial terms must be a list');
        }

        return $terms;
    }

    /**
     * @param list<array<string,mixed>> $partialTerms
     * @param array{lower:string,upper:string,lowerInclusive:bool,upperInclusive:bool} $range
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function matchingRows(array $source, array $partialTerms, array $range, array $neededColumns): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next174 needs row list');
        }

        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next174 rows must be arrays');
            }
            if (!self::rowMatchesTerms($row, $partialTerms)) {
                continue;
            }
            $key = is_string($row['option_name'] ?? null) ? strtolower((string) $row['option_name']) : null;
            if (!is_string($key) || !self::keyInRange($key, $range)) {
                continue;
            }
            $payload = [];
            foreach ($neededColumns as $column) {
                $payload[$column] = $row[$column] ?? null;
            }
            $out[] = [
                'rowid' => (int) ($row['rowid'] ?? 0),
                'expressionKey' => $key,
                'payload' => $payload,
            ];
        }
        usort($out, static fn (array $left, array $right): int => [$left['expressionKey'], $left['rowid']] <=> [$right['expressionKey'], $right['rowid']]);

        return $out;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,mixed>> $terms
     */
    private static function rowMatchesTerms(array $row, array $terms): bool
    {
        foreach ($terms as $term) {
            $left = $term['left'] ?? null;
            if (!is_array($left) || !isset($left['column'])) {
                continue;
            }
            $column = (string) $left['column'];
            $operator = strtoupper((string) ($term['operator'] ?? ''));
            $value = $row[$column] ?? null;
            if ($operator === '=' && $value !== ($term['right'] ?? null)) {
                return false;
            }
            if ($operator === 'IS NOT NULL' && $value === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<array<string,mixed>> $partialTerms
     * @param list<array<string,mixed>> $queryTerms
     */
    private static function partialPredicateImplied(array $partialTerms, array $queryTerms): bool
    {
        foreach ($partialTerms as $partialTerm) {
            $matched = false;
            foreach ($queryTerms as $queryTerm) {
                if (self::termSignature($partialTerm) === self::termSignature($queryTerm)) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }

        return $partialTerms !== [];
    }

    /**
     * @param array<string,mixed> $index
     * @param array{lower:string,upper:string,lowerInclusive:bool,upperInclusive:bool} $range
     * @return array{keys:list<string>,rowids:list<int>,estimatedRows:int}
     */
    private static function stat4Range(array $index, array $range): array
    {
        $samples = $index['stat4Samples'] ?? null;
        if (!is_array($samples) || !array_is_list($samples)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next174 needs stat4 samples');
        }
        $keys = [];
        $rowids = [];
        $estimatedRows = 0;
        foreach ($samples as $sample) {
            if (!is_array($sample)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next174 stat4 samples must be arrays');
            }
            $values = $sample['sample'] ?? null;
            if (!is_array($values) || !array_is_list($values) || !is_string($values[0] ?? null)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next174 stat4 samples need key text');
            }
            $key = strtolower((string) $values[0]);
            if (!self::keyInRange($key, $range)) {
                continue;
            }
            $keys[] = $key;
            $rowids[] = (int) ($values[1] ?? 0);
            $estimatedRows += self::firstStat4Integer($sample['neq'] ?? 1);
        }

        return ['keys' => $keys, 'rowids' => $rowids, 'estimatedRows' => max(1, $estimatedRows)];
    }

    /**
     * @param array{lower:string,upper:string,lowerInclusive:bool,upperInclusive:bool} $range
     */
    private static function keyInRange(string $key, array $range): bool
    {
        $lower = strcmp($key, $range['lower']);
        $upper = strcmp($key, $range['upper']);

        return ((bool) $range['lowerInclusive'] ? $lower >= 0 : $lower > 0)
            && ((bool) $range['upperInclusive'] ? $upper <= 0 : $upper < 0);
    }

    private static function firstStat4Integer(mixed $value): int
    {
        if (is_string($value)) {
            $parts = preg_split('/\s+/', trim($value));
            $value = (int) ($parts[0] ?? 0);
        } elseif (is_array($value) && array_is_list($value)) {
            $value = $value[0] ?? null;
        }
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next174 stat4 integer must be positive');
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $index
     * @param list<string> $neededColumns
     */
    private static function covering(array $index, array $neededColumns): bool
    {
        $covering = $index['coveringColumns'] ?? [];
        if (!is_array($covering) || !array_is_list($covering)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next174 coveringColumns must be a list');
        }

        return count(array_diff($neededColumns, $covering)) === 0;
    }

    /**
     * @param list<string> $neededColumns
     */
    private static function assertNeededColumns(array $neededColumns): void
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next174 needs covering columns');
        }
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next174 covering columns must be names');
            }
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $index, array $range, array $rows, bool $admitted): array
    {
        return [
            ['opcode' => 'OpenRead', 'rootPage' => (int) ($index['rootPage'] ?? 0), 'index' => (string) ($index['name'] ?? '')],
            ['opcode' => 'SeekGE', 'key' => $range['lower'], 'inclusive' => $range['lowerInclusive']],
            ['opcode' => 'IdxLT', 'key' => $range['upper'], 'inclusive' => $range['upperInclusive']],
            ['opcode' => 'DeferredSeek', 'source' => 'table', 'next174RangeRowsStable' => $admitted],
            ['opcode' => 'ResultRow', 'rowids' => array_map(static fn (array $row): int => (int) $row['rowid'], $rows), 'rowCount' => count($rows)],
        ];
    }

    /**
     * @param array<string,mixed> $term
     */
    private static function termSignature(array $term): string
    {
        $left = $term['left'] ?? null;
        $column = is_array($left) ? strtolower((string) ($left['column'] ?? '')) : '';

        return $column . '|' . strtoupper((string) ($term['operator'] ?? '')) . '|' . serialize($term['right'] ?? null);
    }

    private static function normalExpression(string $expression): string
    {
        return strtolower((string) preg_replace('/\s+/', '', $expression));
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }
}
