<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext160Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $predicate,
        array $orderBy,
        array $neededColumns
    ): array {
        self::validateNeededColumns($neededColumns);

        $preparedPlan = self::sourcePlan($preparedSource, $predicate, $orderBy, $neededColumns);
        $currentPlan = self::sourcePlan($currentSource, $predicate, $orderBy, $neededColumns);
        $preparedSignature = self::sourceSignature($preparedSource);
        $currentSignature = self::sourceSignature($currentSource);
        $stale = $preparedSignature !== $currentSignature;
        $selectedPlan = $stale ? $currentPlan : $preparedPlan;
        $selectedSource = $stale ? $currentSource : $preparedSource;
        $rows = $selectedPlan === null ? [] : self::rowUnion($selectedSource, $selectedPlan, $predicate, $neededColumns);
        $ready = $selectedPlan !== null
            && ($selectedPlan['partial'] ?? false) === true
            && ($selectedPlan['stat4Used'] ?? false) === true
            && ($selectedPlan['strategy'] ?? null) === 'or-rowid-union'
            && $rows !== [];

        return [
            'status' => $ready ? 'stat4-expression-partial-current-source-next160-ready' : 'requires-next-stage',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale,
            'schemaCookieChanged' => self::sourceInt($preparedSource, 'schemaCookie') !== self::sourceInt($currentSource, 'schemaCookie'),
            'stat4GenerationChanged' => self::sourceInt($preparedSource, 'stat4Generation') !== self::sourceInt($currentSource, 'stat4Generation'),
            'indexSignatureChanged' => self::indexSignature($preparedSource) !== self::indexSignature($currentSource),
            'rowSignatureChanged' => self::rowSignature(self::sourceRows($preparedSource)) !== self::rowSignature(self::sourceRows($currentSource)),
            'preparedSource' => self::sourceSummary($preparedSource, $preparedPlan, $preparedSignature),
            'currentSource' => self::sourceSummary($currentSource, $currentPlan, $currentSignature),
            'selectedPlan' => $selectedPlan === null ? null : array_replace($selectedPlan, [
                'currentSourceRowCount' => count($rows),
                'currentSourceRowids' => array_column($rows, 'rowid'),
                'currentSourceKeys' => array_column($rows, 'key'),
                'dedupedRowids' => array_values(array_unique(array_column($rows, 'rowid'))),
                'next160Ready' => $ready,
            ]),
            'unionRows' => $rows,
            'currentNextRows' => self::currentNextRows($rows),
            'cursorTape' => self::cursorTape($selectedPlan, $rows, $neededColumns, $stale ? 'current' : 'prepared'),
            'currentSourceFence' => [
                'name' => self::sourceString($selectedSource, 'name'),
                'schemaCookie' => self::sourceInt($selectedSource, 'schemaCookie'),
                'stat4Generation' => self::sourceInt($selectedSource, 'stat4Generation'),
                'sourceSignature' => $stale ? $currentSignature : $preparedSignature,
                'indexSignature' => self::indexSignature($selectedSource),
                'rowStreamSignature' => self::signature(array_column($rows, 'rowid')),
                'armSignature' => self::signature($selectedPlan['arms'] ?? []),
            ],
            'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT160 '
                . (string) ($selectedPlan['strategy'] ?? 'NO PLAN')
                . ' rows=' . count($rows),
            'dependencies' => [
                'SQLiteSelectExpressionIndexPlan::partialCoveringOrPlan',
                'sqlite-sqlplanner-stat4-expression-partial-current-source-next160',
            ],
            'dependency_closure' => 'no new support component needed; next160 reuses native OR partial-expression planning, STAT4 estimates, partial predicate proof, and current-source row materialization',
            'non_overlap' => 'avoids accepted next154 non-covering range row streams, next156 bounded range deferred seeks, next157 IN covering materialization, next145 skip-scan, expression ORDER BY, range-cost, JSON, VFS/WAL, and B-tree clusters; this slice covers OR-rowid-union current-source row dedupe for STAT4 partial expression indexes',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $predicate
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @return array<string,mixed>|null
     */
    private static function sourcePlan(array $source, array $predicate, array $orderBy, array $neededColumns): ?array
    {
        return SQLiteSelectExpressionIndexPlan::partialCoveringOrPlan(
            self::sourceIndexes($source),
            $predicate,
            $orderBy,
            $neededColumns,
        );
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $plan
     * @param array<string,mixed> $predicate
     * @param list<string> $neededColumns
     * @return list<array<string,mixed>>
     */
    private static function rowUnion(array $source, array $plan, array $predicate, array $neededColumns): array
    {
        if (($plan['strategy'] ?? null) !== 'or-rowid-union') {
            return [];
        }
        $arms = $predicate['terms'] ?? null;
        if (!is_array($arms) || !array_is_list($arms)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial next160 OR predicate needs terms');
        }

        $rows = [];
        $seen = [];
        foreach (self::sourceRows($source) as $offset => $row) {
            $rowid = (int) ($row['rowid'] ?? $row['_rowid_'] ?? $offset + 1);
            foreach ($arms as $position => $arm) {
                if (!is_array($arm)) {
                    throw new \InvalidArgumentException('SQLite STAT4 expression partial next160 OR arm must be a predicate');
                }
                if (!self::rowMatches($row, $arm)) {
                    continue;
                }
                if (isset($seen[$rowid])) {
                    $rows[$seen[$rowid]]['matchedArms'][] = $position;
                    $rows[$seen[$rowid]]['dedupedByRowid'] = true;
                    continue;
                }
                $seen[$rowid] = count($rows);
                $rows[] = [
                    'sourceOffset' => $offset,
                    'rowid' => $rowid,
                    'key' => self::primaryExpressionKey($row, $arm),
                    'payload' => self::payload($row, $neededColumns),
                    'matchedArms' => [$position],
                    'dedupedByRowid' => false,
                ];
            }
        }

        usort($rows, static function (array $left, array $right): int {
            return self::compare($left['key'] ?? null, $right['key'] ?? null)
                ?: ((int) ($left['rowid'] ?? 0) <=> (int) ($right['rowid'] ?? 0));
        });

        return $rows;
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
     * @param array<string,mixed>|null $plan
     * @param list<array<string,mixed>> $rows
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function cursorTape(?array $plan, array $rows, array $neededColumns, string $source): array
    {
        if ($plan === null || $rows === []) {
            return [
                'source' => $source,
                'status' => 'no-stat4-or-partial-current-source-plan',
                'program' => [['opcode' => 'Rewind', 'target' => 'table']],
            ];
        }

        $program = [[
            'opcode' => 'OpenEphemeral',
            'target' => 'rowid-union',
            'dedupe' => true,
        ]];
        foreach ($plan['arms'] ?? [] as $arm) {
            $program[] = [
                'opcode' => 'SeekStat4',
                'target' => 'index',
                'index' => $arm['name'] ?? null,
                'rootPage' => $arm['rootPage'] ?? null,
                'operator' => $arm['operator'] ?? null,
                'values' => $arm['values'] ?? null,
            ];
            $program[] = ['opcode' => 'IdxRowid', 'target' => 'rowid-union'];
        }
        foreach ($neededColumns as $column) {
            $program[] = ['opcode' => 'Column', 'source' => 'covering-expression-index', 'column' => $column];
        }
        $program[] = ['opcode' => 'ResultRow', 'rowCount' => count($rows)];
        $program[] = ['opcode' => 'Next', 'target' => 'rowid-union'];

        return [
            'source' => $source,
            'status' => 'stat4-or-partial-current-source-rowid-union',
            'strategy' => $plan['strategy'] ?? null,
            'armCount' => $plan['armCount'] ?? 0,
            'rowids' => array_column($rows, 'rowid'),
            'keys' => array_column($rows, 'key'),
            'dedupeRowidsRequired' => $plan['dedupeRowidsRequired'] ?? false,
            'program' => $program,
        ];
    }

    /**
     * @param array<string,mixed> $predicate
     */
    private static function rowMatches(array $row, array $predicate): bool
    {
        $operator = strtoupper(self::requiredString($predicate, 'operator'));
        if ($operator === 'AND') {
            foreach (self::terms($predicate) as $term) {
                if (!self::rowMatches($row, $term)) {
                    return false;
                }
            }

            return true;
        }
        if ($operator === 'OR') {
            foreach (self::terms($predicate) as $term) {
                if (self::rowMatches($row, $term)) {
                    return true;
                }
            }

            return false;
        }

        $left = $predicate['left'] ?? null;
        if (!is_array($left)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial next160 predicate needs left operand');
        }
        $value = self::operandValue($left, $row);
        if ($operator === '=') {
            return self::compare($value, $predicate['right'] ?? null) === 0;
        }
        if ($operator === '>=') {
            return self::compare($value, $predicate['right'] ?? null) >= 0;
        }
        if ($operator === '>') {
            return self::compare($value, $predicate['right'] ?? null) > 0;
        }
        if ($operator === '<=') {
            return self::compare($value, $predicate['right'] ?? null) <= 0;
        }
        if ($operator === '<') {
            return self::compare($value, $predicate['right'] ?? null) < 0;
        }

        throw new \InvalidArgumentException("SQLite STAT4 expression partial next160 unsupported operator {$operator}");
    }

    /**
     * @param array<string,mixed> $predicate
     * @return list<array<string,mixed>>
     */
    private static function terms(array $predicate): array
    {
        $terms = $predicate['terms'] ?? null;
        if (!is_array($terms) || !array_is_list($terms) || $terms === []) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial next160 predicate needs terms');
        }
        foreach ($terms as $term) {
            if (!is_array($term)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial next160 predicate terms must be arrays');
            }
        }

        return $terms;
    }

    /**
     * @param array<string,mixed> $predicate
     */
    private static function primaryExpressionKey(array $row, array $predicate): mixed
    {
        foreach (self::terms($predicate) as $term) {
            $left = $term['left'] ?? null;
            if (is_array($left) && isset($left['function'])) {
                return self::operandValue($left, $row);
            }
        }

        return $row['rowid'] ?? null;
    }

    /**
     * @param array<string,mixed> $operand
     */
    private static function operandValue(array $operand, array $row): mixed
    {
        if (isset($operand['column'])) {
            $value = $row[(string) $operand['column']] ?? null;
            $function = strtolower((string) ($operand['function'] ?? ''));
            if ($function === 'lower') {
                return is_string($value) ? strtolower($value) : $value;
            }
            if ($function === 'upper') {
                return is_string($value) ? strtoupper($value) : $value;
            }
            if ($function === 'length') {
                return is_string($value) ? strlen($value) : null;
            }

            return $value;
        }

        throw new \InvalidArgumentException('SQLite STAT4 expression partial next160 operand needs column');
    }

    /**
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    private static function payload(array $row, array $neededColumns): array
    {
        $payload = [];
        foreach ($neededColumns as $column) {
            $payload[$column] = $row[$column] ?? null;
        }

        return $payload;
    }

    private static function compare(mixed $left, mixed $right): int
    {
        if (is_numeric($left) && is_numeric($right)) {
            return (float) $left <=> (float) $right;
        }

        return strcmp((string) $left, (string) $right);
    }

    /**
     * @param array<string,mixed>|null $plan
     * @return array<string,mixed>
     */
    private static function sourceSummary(array $source, ?array $plan, string $signature): array
    {
        return [
            'name' => self::sourceString($source, 'name'),
            'schemaCookie' => self::sourceInt($source, 'schemaCookie'),
            'stat4Generation' => self::sourceInt($source, 'stat4Generation'),
            'sourceSignature' => $signature,
            'strategy' => $plan['strategy'] ?? null,
            'armCount' => $plan['armCount'] ?? 0,
            'estimatedRows' => $plan['estimatedRows'] ?? null,
            'estimatedCost' => $plan['estimatedCost'] ?? null,
            'ready' => $plan !== null && ($plan['strategy'] ?? null) === 'or-rowid-union',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @return list<array<string,mixed>>
     */
    private static function sourceIndexes(array $source): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes) || $indexes === []) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial next160 needs index list');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial next160 indexes must be arrays');
            }
        }

        return $indexes;
    }

    /**
     * @param array<string,mixed> $source
     * @return list<array<string,mixed>>
     */
    private static function sourceRows(array $source): array
    {
        $rows = $source['rows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial next160 needs row list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial next160 rows must be arrays');
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceSignature(array $source): string
    {
        return self::signature([
            self::sourceString($source, 'name'),
            self::sourceInt($source, 'schemaCookie'),
            self::sourceInt($source, 'stat4Generation'),
            self::sourceIndexes($source),
            self::sourceRows($source),
        ]);
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function indexSignature(array $source): string
    {
        return self::signature(self::sourceIndexes($source));
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function rowSignature(array $rows): string
    {
        return self::signature($rows);
    }

    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceString(array $source, string $key): string
    {
        $value = $source[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite STAT4 expression partial next160 needs {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceInt(array $source, string $key): int
    {
        $value = $source[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite STAT4 expression partial next160 needs non-negative integer {$key}");
        }

        return $value;
    }

    private static function requiredString(array $value, string $key): string
    {
        $found = $value[$key] ?? null;
        if (!is_string($found) || $found === '') {
            throw new \InvalidArgumentException("SQLite STAT4 expression partial next160 needs {$key}");
        }

        return $found;
    }

    /**
     * @param list<string> $neededColumns
     */
    private static function validateNeededColumns(array $neededColumns): void
    {
        if ($neededColumns === []) {
            throw new \InvalidArgumentException('SQLite STAT4 expression partial next160 needs output columns');
        }
        foreach ($neededColumns as $column) {
            if (!is_string($column) || $column === '') {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial next160 output columns must be names');
            }
        }
    }
}
