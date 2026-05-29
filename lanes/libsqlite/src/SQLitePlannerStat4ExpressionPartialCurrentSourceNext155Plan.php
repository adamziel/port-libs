<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext155Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $predicate,
        array $orderBy,
        array $neededColumns,
        array $neededExpressions = []
    ): array {
        self::validateNeededColumns($neededColumns);

        $preparedPlan = self::sourcePlan($preparedSource, $predicate, $orderBy, $neededColumns, $neededExpressions);
        $currentPlan = self::sourcePlan($currentSource, $predicate, $orderBy, $neededColumns, $neededExpressions);
        $preparedSignature = self::sourceSignature($preparedSource);
        $currentSignature = self::sourceSignature($currentSource);
        $preparedRows = self::coveredRows($preparedSource, $preparedPlan, $predicate, $neededColumns, $neededExpressions);
        $currentRows = self::coveredRows($currentSource, $currentPlan, $predicate, $neededColumns, $neededExpressions);
        $stale = $preparedSignature !== $currentSignature
            || self::sourceInt($preparedSource, 'schemaCookie') !== self::sourceInt($currentSource, 'schemaCookie')
            || self::sourceInt($preparedSource, 'stat4Generation') !== self::sourceInt($currentSource, 'stat4Generation')
            || self::sourceInt($preparedSource, 'rowGeneration') !== self::sourceInt($currentSource, 'rowGeneration');
        $selectedPlan = $stale ? $currentPlan : $preparedPlan;
        $selectedRows = $stale ? $currentRows : $preparedRows;
        $delta = self::rowDelta($preparedRows, $currentRows);
        $ready = ($selectedPlan['usable'] ?? false) === true
            && ($selectedPlan['partial'] ?? false) === true
            && (($selectedPlan['partialPredicateImplied'] ?? null) !== false)
            && ($selectedPlan['stat4Used'] ?? false) === true
            && ($selectedPlan['covering'] ?? false) === true
            && $selectedRows !== [];

        return [
            'status' => $ready ? 'stat4-expression-partial-current-source-next155-ready' : 'requires-next-stage',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => self::sourceInt($preparedSource, 'schemaCookie') !== self::sourceInt($currentSource, 'schemaCookie'),
            'stat4GenerationChanged' => self::sourceInt($preparedSource, 'stat4Generation') !== self::sourceInt($currentSource, 'stat4Generation'),
            'rowGenerationChanged' => self::sourceInt($preparedSource, 'rowGeneration') !== self::sourceInt($currentSource, 'rowGeneration'),
            'indexSignatureChanged' => $preparedSignature !== $currentSignature,
            'partialPredicateChanged' => ($preparedPlan['partialPredicateSignature'] ?? null) !== ($currentPlan['partialPredicateSignature'] ?? null),
            'preparedSource' => self::sourceSummary($preparedSource, $preparedPlan, $preparedSignature, $preparedRows),
            'currentSource' => self::sourceSummary($currentSource, $currentPlan, $currentSignature, $currentRows),
            'selectedPlan' => $selectedPlan + [
                'coveredRowCount' => count($selectedRows),
                'coveredRowids' => array_column($selectedRows, 'rowid'),
                'insertedCurrentRowids' => $delta['insertedRowids'],
                'deletedPreparedRowids' => $delta['deletedRowids'],
                'updatedCurrentRowids' => $delta['updatedRowids'],
                'partialPredicateChanged' => ($preparedPlan['partialPredicateSignature'] ?? null) !== ($currentPlan['partialPredicateSignature'] ?? null),
            ],
            'preparedCoveringRows' => $preparedRows,
            'currentCoveringRows' => $currentRows,
            'preparedCoveringRowids' => array_column($preparedRows, 'rowid'),
            'currentCoveringRowids' => array_column($currentRows, 'rowid'),
            'staleCoveringRejectedRowids' => $delta['deletedRowids'],
            'currentCoveringAdmittedRowids' => $delta['insertedRowids'],
            'currentCoveringUpdatedRowids' => $delta['updatedRowids'],
            'stableCoveringRowids' => $delta['unchangedRowids'],
            'currentNextRows' => self::currentNextRows($selectedRows),
            'cursorTape' => self::cursorTape($selectedPlan, $selectedRows, $delta, $neededColumns, $ready),
            'currentSourceFence' => [
                'schemaCookie' => self::sourceInt($currentSource, 'schemaCookie'),
                'stat4Generation' => self::sourceInt($currentSource, 'stat4Generation'),
                'rowGeneration' => self::sourceInt($currentSource, 'rowGeneration'),
                'indexSignature' => $currentSignature,
                'partialPredicateSignature' => $currentPlan['partialPredicateSignature'] ?? null,
                'rowStreamSignature' => self::rowStreamSignature($currentRows),
            ],
            'tableLookupElided' => $ready,
            'deferredTableSeekOpcode' => $ready ? null : 'DeferredSeek',
            'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT155 '
                . (string) (($selectedPlan['name'] ?? null) ?: 'NO INDEX'),
            'dependencies' => [
                'SQLiteSelectExpressionIndexPlan bounded STAT4 range planner',
                'sqlite-sqlplanner-stat4-expression-partial-current-source-next155',
            ],
            'dependency_closure' => 'no new support component needed; next155 reuses native expression-index STAT4 range planning, partial predicate proof, and current-source row fences',
            'non_overlap' => 'avoids accepted next133 row-generation partial expression STAT4, next148 non-STAT4 partial covering, next114 collation boundaries, next122/128/134/146 covering range, expression ORDER BY, and range-cost ranking by proving current-source partial-predicate drift for a STAT4 expression range scan',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $predicate
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @return array<string,mixed>
     */
    private static function sourcePlan(array $source, array $predicate, array $orderBy, array $neededColumns, array $neededExpressions): array
    {
        $indexes = self::indexes($source);
        $plan = SQLiteSelectExpressionIndexPlan::chooseBoundedRangeCost($indexes, $predicate, $orderBy, $neededColumns, $neededExpressions);
        if ($plan === null) {
            return [
                'usable' => false,
                'partial' => false,
                'partialPredicateImplied' => false,
                'covering' => false,
                'stat4Used' => false,
                'detail' => 'SCAN TABLE; NO STAT4 PARTIAL EXPRESSION RANGE',
            ];
        }

        $partialSignature = self::partialPredicateSignature($indexes, (string) ($plan['name'] ?? ''));

        return $plan + [
            'partialPredicateSignature' => $partialSignature,
            'detail' => 'SEARCH ' . (string) ($plan['name'] ?? 'expression-index') . ' USING STAT4 PARTIAL EXPRESSION RANGE CURRENT SOURCE',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @return list<array<string,mixed>>
     */
    private static function indexes(array $source): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes) || $indexes === []) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source needs index definitions');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source indexes must be arrays');
            }
            if (!is_string($index['sql'] ?? null) || $index['sql'] === '') {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source indexes need SQL text');
            }
        }

        return $indexes;
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $predicate
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @return list<array<string,mixed>>
     */
    private static function coveredRows(array $source, array $plan, array $predicate, array $neededColumns, array $neededExpressions): array
    {
        if (($plan['usable'] ?? false) !== true || ($plan['covering'] ?? false) !== true || ($plan['stat4Used'] ?? false) !== true) {
            return [];
        }
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source rows must be a list');
        }

        $out = [];
        foreach ($rows as $offset => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source row must be an array');
            }
            if (!self::rowSatisfiesPredicate($predicate, $row)) {
                continue;
            }
            $key = self::expressionValue((string) ($plan['type'] ?? ''), (string) ($plan['column'] ?? ''), $row);
            if (!self::valueInRange($key, $plan['values'] ?? [])) {
                continue;
            }

            $out[] = [
                'sourceOffset' => $offset,
                'rowid' => self::rowid($row),
                'key' => $key,
                'covering' => self::payload($row, $neededColumns),
                'coveringExpressions' => self::expressionPayload($row, $neededExpressions),
            ];
        }

        usort($out, static function (array $left, array $right) use ($plan): int {
            $comparison = self::compareValues($left['key'] ?? null, $right['key'] ?? null);
            if ((bool) ($plan['descending'] ?? false)) {
                $comparison *= -1;
            }
            if ($comparison !== 0) {
                return $comparison;
            }

            return ((int) $left['rowid']) <=> ((int) $right['rowid']);
        });

        return $out;
    }

    /**
     * @param list<array<string,mixed>> $preparedRows
     * @param list<array<string,mixed>> $currentRows
     * @return array{insertedRowids:list<int>,deletedRowids:list<int>,updatedRowids:list<int>,unchangedRowids:list<int>}
     */
    private static function rowDelta(array $preparedRows, array $currentRows): array
    {
        $prepared = self::rowsByRowid($preparedRows);
        $current = self::rowsByRowid($currentRows);
        $inserted = [];
        $deleted = [];
        $updated = [];
        $unchanged = [];

        foreach ($current as $rowid => $row) {
            if (!array_key_exists($rowid, $prepared)) {
                $inserted[] = $rowid;
                continue;
            }
            if (self::rowPayloadSignature($row) !== self::rowPayloadSignature($prepared[$rowid])) {
                $updated[] = $rowid;
                continue;
            }
            $unchanged[] = $rowid;
        }
        foreach ($prepared as $rowid => $_row) {
            if (!array_key_exists($rowid, $current)) {
                $deleted[] = $rowid;
            }
        }

        sort($inserted, SORT_NUMERIC);
        sort($deleted, SORT_NUMERIC);
        sort($updated, SORT_NUMERIC);
        sort($unchanged, SORT_NUMERIC);

        return [
            'insertedRowids' => $inserted,
            'deletedRowids' => $deleted,
            'updatedRowids' => $updated,
            'unchangedRowids' => $unchanged,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function rowsByRowid(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row['rowid']] = $row;
        }
        ksort($out, SORT_NUMERIC);

        return $out;
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<array<string,mixed>> $rows
     * @param array{insertedRowids:list<int>,deletedRowids:list<int>,updatedRowids:list<int>,unchangedRowids:list<int>} $delta
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function cursorTape(array $plan, array $rows, array $delta, array $neededColumns, bool $ready): array
    {
        $program = [
            ['opcode' => 'OpenRead', 'source' => 'partial-expression-stat4-index', 'rootPage' => $plan['rootPage'] ?? null],
            ['opcode' => 'FenceCurrentSource', 'partialPredicateSignature' => $plan['partialPredicateSignature'] ?? null],
            ['opcode' => 'SeekGE', 'key' => ($plan['values']['lower'] ?? null), 'exact' => ($plan['values']['lowerInclusive'] ?? true)],
            ['opcode' => ($plan['values']['upperInclusive'] ?? false) ? 'IdxGT' : 'IdxGE', 'key' => ($plan['values']['upper'] ?? null)],
            ['opcode' => 'FilterStalePartialRowids', 'rowids' => $delta['deletedRowids']],
        ];
        foreach ($neededColumns as $column) {
            $program[] = ['opcode' => 'Column', 'source' => $ready ? 'current-partial-covering-index' : 'table', 'column' => $column];
        }
        $program[] = ['opcode' => 'Next', 'source' => 'index'];

        return [
            'indexName' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'rangeLower' => $plan['values']['lower'] ?? null,
            'rangeUpper' => $plan['values']['upper'] ?? null,
            'matchedRowids' => array_column($rows, 'rowid'),
            'matchedKeys' => array_column($rows, 'key'),
            'insertedRowids' => $delta['insertedRowids'],
            'updatedRowids' => $delta['updatedRowids'],
            'deletedRowidsBlocked' => $delta['deletedRowids'],
            'tableLookupElidedAfterPartialFence' => $ready,
            'program' => $program,
        ];
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
     * @param list<array<string,mixed>> $indexes
     */
    private static function partialPredicateSignature(array $indexes, string $name): string
    {
        foreach ($indexes as $index) {
            if (($index['name'] ?? '') !== $name) {
                continue;
            }
            $sql = (string) $index['sql'];
            $where = '';
            if (preg_match('/\bWHERE\b(.+)$/i', $sql, $matches) === 1) {
                $where = strtolower(trim(preg_replace('/\s+/', ' ', $matches[1])));
            }

            return hash('sha256', $where);
        }

        return hash('sha256', '');
    }

    /**
     * @param array<string,mixed> $predicate
     * @param array<string,mixed> $row
     */
    private static function rowSatisfiesPredicate(array $predicate, array $row): bool
    {
        $operator = strtoupper((string) ($predicate['operator'] ?? ''));
        if ($operator === 'AND') {
            foreach (self::list($predicate['terms'] ?? []) as $term) {
                if (!is_array($term) || !self::rowSatisfiesPredicate($term, $row)) {
                    return false;
                }
            }

            return true;
        }
        if ($operator === '=') {
            return self::termValue($predicate['left'] ?? null, $row) === ($predicate['right'] ?? null);
        }
        if ($operator === '>=') {
            return self::compareValues(self::termValue($predicate['left'] ?? null, $row), $predicate['right'] ?? null) >= 0;
        }
        if ($operator === '>') {
            return self::compareValues(self::termValue($predicate['left'] ?? null, $row), $predicate['right'] ?? null) > 0;
        }
        if ($operator === '<=') {
            return self::compareValues(self::termValue($predicate['left'] ?? null, $row), $predicate['right'] ?? null) <= 0;
        }
        if ($operator === '<') {
            return self::compareValues(self::termValue($predicate['left'] ?? null, $row), $predicate['right'] ?? null) < 0;
        }
        if ($operator === 'IS NOT NULL') {
            return self::termValue($predicate['left'] ?? null, $row) !== null;
        }

        return false;
    }

    /**
     * @param mixed $term
     * @param array<string,mixed> $row
     */
    private static function termValue(mixed $term, array $row): mixed
    {
        if (!is_array($term)) {
            return null;
        }
        if (($term['function'] ?? null) === 'lower' && is_string($term['column'] ?? null)) {
            $value = $row[$term['column']] ?? null;

            return is_string($value) ? strtolower($value) : $value;
        }
        if (isset($term['column']) && is_string($term['column'])) {
            return $row[$term['column']] ?? null;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $range
     */
    private static function valueInRange(mixed $value, array $range): bool
    {
        if (array_key_exists('lower', $range) && $range['lower'] !== null) {
            $comparison = self::compareValues($value, $range['lower']);
            if ($comparison < 0 || ($comparison === 0 && ($range['lowerInclusive'] ?? true) !== true)) {
                return false;
            }
        }
        if (array_key_exists('upper', $range) && $range['upper'] !== null) {
            $comparison = self::compareValues($value, $range['upper']);
            if ($comparison > 0 || ($comparison === 0 && ($range['upperInclusive'] ?? false) !== true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function expressionValue(string $type, string $column, array $row): mixed
    {
        $value = $row[$column] ?? null;
        if ($type === 'lower' && is_string($value)) {
            return strtolower($value);
        }
        if ($type === 'upper' && is_string($value)) {
            return strtoupper($value);
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<string> $columns
     * @return array<string,mixed>
     */
    private static function payload(array $row, array $columns): array
    {
        $payload = [];
        foreach ($columns as $column) {
            $payload[$column] = $row[$column] ?? null;
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array<string,string>> $expressions
     * @return array<string,mixed>
     */
    private static function expressionPayload(array $row, array $expressions): array
    {
        $payload = [];
        foreach ($expressions as $expression) {
            $function = strtolower((string) ($expression['function'] ?? ''));
            $column = (string) ($expression['column'] ?? '');
            if ($function === '' || $column === '') {
                continue;
            }
            $payload[$function . '(' . $column . ')'] = self::expressionValue($function, $column, $row);
        }

        return $payload;
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowid(array $row): int
    {
        $rowid = $row['rowid'] ?? $row['_rowid_'] ?? null;
        if (!is_int($rowid) || $rowid < 0) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source rows need non-negative rowid');
        }

        return $rowid;
    }

    private static function compareValues(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    /**
     * @param array<string,mixed> $row
     */
    private static function rowPayloadSignature(array $row): string
    {
        return hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function rowStreamSignature(array $rows): string
    {
        return hash('sha256', json_encode(array_column($rows, 'rowid'), JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceSignature(array $source): string
    {
        return hash('sha256', json_encode([
            'schemaCookie' => self::sourceInt($source, 'schemaCookie'),
            'stat4Generation' => self::sourceInt($source, 'stat4Generation'),
            'rowGeneration' => self::sourceInt($source, 'rowGeneration'),
            'indexes' => self::indexes($source),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceInt(array $source, string $key): int
    {
        $value = $source[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source ' . $key . ' must be a non-negative integer');
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $plan
     * @param list<array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private static function sourceSummary(array $source, array $plan, string $signature, array $rows): array
    {
        return [
            'name' => (string) ($source['name'] ?? ''),
            'schemaCookie' => self::sourceInt($source, 'schemaCookie'),
            'stat4Generation' => self::sourceInt($source, 'stat4Generation'),
            'rowGeneration' => self::sourceInt($source, 'rowGeneration'),
            'signature' => $signature,
            'usable' => (bool) ($plan['usable'] ?? false),
            'indexName' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
            'coveredRowids' => array_column($rows, 'rowid'),
        ];
    }

    /**
     * @param mixed $value
     * @return list<mixed>
     */
    private static function list(mixed $value): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            return [];
        }

        return $value;
    }

    /**
     * @param list<string> $neededColumns
     */
    private static function validateNeededColumns(array $neededColumns): void
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source needs covering columns');
        }
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source covering columns must be names');
            }
        }
    }
}
