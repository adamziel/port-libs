<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext157Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $predicate
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @param array<string,mixed>|null $nextSource
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $predicate,
        array $orderBy,
        array $neededColumns,
        array $neededExpressions = [],
        ?array $nextSource = null
    ): array {
        $prepared = self::sourcePlan($preparedSource, $predicate, $orderBy, $neededColumns, $neededExpressions);
        $current = self::sourcePlan($currentSource, $predicate, $orderBy, $neededColumns, $neededExpressions);
        $preparedSignature = self::sourceSignature($preparedSource);
        $currentSignature = self::sourceSignature($currentSource);
        $stale = $preparedSignature !== $currentSignature;
        $selected = $stale ? $current : $prepared;
        $selectedSource = $stale ? $currentSource : $preparedSource;
        $nextSummary = $nextSource === null ? null : self::nextSourceSummary($currentSource, $nextSource);
        $nextAdmitted = $nextSummary === null || $nextSummary['replanReasons'] === [];
        $ready = $selected !== null && $nextAdmitted;
        $rows = is_array($selected['currentNextRows'] ?? null) ? array_column($selected['currentNextRows'], 'current') : [];

        return [
            'status' => $ready ? 'stat4-expression-partial-current-source-next157-ready' : 'requires-current-source-reprepare',
            'selectedSource' => $stale ? 'current' : 'prepared',
            'stalePreparedStatement' => $stale,
            'reprepareRequired' => $stale || !$nextAdmitted,
            'schemaCookieChanged' => self::sourceInt($preparedSource, 'schemaCookie') !== self::sourceInt($currentSource, 'schemaCookie'),
            'stat4GenerationChanged' => self::sourceInt($preparedSource, 'stat4Generation') !== self::sourceInt($currentSource, 'stat4Generation'),
            'indexSignatureChanged' => self::indexSignature($preparedSource) !== self::indexSignature($currentSource),
            'rowSignatureChanged' => self::rowSignature(self::sourceRows($preparedSource)) !== self::rowSignature(self::sourceRows($currentSource)),
            'stat4SignatureChanged' => self::stat4Signature($preparedSource) !== self::stat4Signature($currentSource),
            'nextSourceAdmitted' => $nextAdmitted,
            'nextSource' => $nextSummary,
            'preparedPlan' => $prepared,
            'currentPlan' => $current,
            'selectedPlan' => $selected === null ? null : array_replace($selected, [
                'next157Ready' => $ready,
                'next157Source' => $stale ? 'current-stat4-expression-partial' : 'prepared-stat4-expression-partial',
                'next157Rowids' => array_column($rows, 'rowid'),
                'next157Keys' => array_column($rows, 'key'),
                'next157CoveringNames' => array_map(
                    static fn (array $row): mixed => $row['covering']['option_name'] ?? null,
                    $rows,
                ),
                'next157CursorProgram' => self::cursorProgram($selected, $neededColumns, $neededExpressions),
            ]),
            'coveredRows' => $rows,
            'currentNextRows' => is_array($selected['currentNextRows'] ?? null) ? $selected['currentNextRows'] : [],
            'currentSourceFence' => [
                'name' => self::sourceString($selectedSource, 'name'),
                'schemaCookie' => self::sourceInt($selectedSource, 'schemaCookie'),
                'stat4Generation' => self::sourceInt($selectedSource, 'stat4Generation'),
                'sourceSignature' => $stale ? $currentSignature : $preparedSignature,
                'indexSignature' => self::indexSignature($selectedSource),
                'rowStreamSignature' => hash('sha256', json_encode(array_column($rows, 'rowid'), JSON_THROW_ON_ERROR)),
                'matchedKeySignature' => hash('sha256', json_encode(array_column($rows, 'key'), JSON_THROW_ON_ERROR)),
            ],
            'detail' => ($stale ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT157 '
                . (string) ($selected['name'] ?? 'NO INDEX')
                . ' rows=' . count($rows),
            'dependencies' => [
                'SQLiteSelectExpressionIndexPlan::stat4ExpressionCoveringCurrentSourcePlan',
                'sqlite-sqlplanner-stat4-expression-partial-current-source-next157',
            ],
            'dependency_closure' => 'no new support component needed; next157 reuses native expression-index parsing, partial predicate proof, STAT4 samples, and current-source covering row materialization',
            'non_overlap' => 'avoids accepted STAT4 partial covering order, expression partial skip-scan, range-cost ranking, expression ORDER BY, JSON table, WAL, VFS, and B-tree clusters; this slice fences STAT4 expression partial current-source row materialization and next-source admission',
        ];
    }

    /**
     * @param array<string,mixed> $source
     * @param array<string,mixed> $predicate
     * @param list<array<string,string>> $orderBy
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @return array<string,mixed>|null
     */
    private static function sourcePlan(array $source, array $predicate, array $orderBy, array $neededColumns, array $neededExpressions): ?array
    {
        return SQLiteSelectExpressionIndexPlan::stat4ExpressionCoveringCurrentSourcePlan(
            self::sourceIndexes($source),
            $predicate,
            self::sourceRows($source),
            $orderBy,
            $neededColumns,
            $neededExpressions,
        );
    }

    /**
     * @param array<string,mixed> $plan
     * @param list<string> $neededColumns
     * @param list<array<string,string>> $neededExpressions
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $plan, array $neededColumns, array $neededExpressions): array
    {
        $program = [[
            'opcode' => 'OpenRead',
            'target' => 'index',
            'index' => $plan['name'] ?? null,
            'rootPage' => $plan['rootPage'] ?? null,
        ], [
            'opcode' => 'SeekStat4',
            'operator' => $plan['operator'] ?? null,
            'keys' => array_values(array_unique(array_column(array_column($plan['currentNextRows'] ?? [], 'current'), 'key'), SORT_REGULAR)),
        ]];
        foreach ($neededColumns as $column) {
            $program[] = ['opcode' => 'Column', 'source' => 'covering-expression-index', 'column' => $column];
        }
        foreach ($neededExpressions as $expression) {
            $program[] = ['opcode' => 'Column', 'source' => 'covering-expression-index', 'expression' => self::expressionName($expression)];
        }
        $program[] = ['opcode' => 'Next', 'target' => 'index'];

        return $program;
    }

    /**
     * @param array<string,string> $expression
     */
    private static function expressionName(array $expression): string
    {
        if (($expression['function'] ?? null) === 'lower') {
            return 'lower(' . (string) ($expression['column'] ?? '') . ')';
        }
        if (($expression['function'] ?? null) === 'upper') {
            return 'upper(' . (string) ($expression['column'] ?? '') . ')';
        }

        return (string) ($expression['function'] ?? 'expression');
    }

    /**
     * @param array<string,mixed> $currentSource
     * @param array<string,mixed> $nextSource
     * @return array<string,mixed>
     */
    private static function nextSourceSummary(array $currentSource, array $nextSource): array
    {
        $reasons = [];
        if (self::sourceInt($currentSource, 'schemaCookie') !== self::sourceInt($nextSource, 'schemaCookie')) {
            $reasons[] = 'schema-cookie';
        }
        if (self::sourceInt($currentSource, 'stat4Generation') !== self::sourceInt($nextSource, 'stat4Generation')) {
            $reasons[] = 'stat4-generation';
        }
        if (self::indexSignature($currentSource) !== self::indexSignature($nextSource)) {
            $reasons[] = 'index-signature';
        }
        if (self::rowSignature(self::sourceRows($currentSource)) !== self::rowSignature(self::sourceRows($nextSource))) {
            $reasons[] = 'row-signature';
        }
        if (self::stat4Signature($currentSource) !== self::stat4Signature($nextSource)) {
            $reasons[] = 'stat4-signature';
        }

        return [
            'name' => self::sourceString($nextSource, 'name'),
            'schemaCookie' => self::sourceInt($nextSource, 'schemaCookie'),
            'stat4Generation' => self::sourceInt($nextSource, 'stat4Generation'),
            'sourceSignature' => self::sourceSignature($nextSource),
            'replanReasons' => $reasons,
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
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next157 needs index list');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next157 indexes must be arrays');
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
            throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next157 needs row list');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite STAT4 expression partial current-source next157 rows must be arrays');
            }
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceSignature(array $source): string
    {
        return hash('sha256', implode('|', [
            self::sourceString($source, 'name'),
            (string) self::sourceInt($source, 'schemaCookie'),
            (string) self::sourceInt($source, 'stat4Generation'),
            self::indexSignature($source),
            self::rowSignature(self::sourceRows($source)),
            self::stat4Signature($source),
        ]));
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function indexSignature(array $source): string
    {
        return hash('sha256', json_encode(self::sourceIndexes($source), JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function rowSignature(array $rows): string
    {
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function stat4Signature(array $source): string
    {
        $samples = [];
        foreach (self::sourceIndexes($source) as $index) {
            $samples[] = $index['stat4Samples'] ?? [];
        }

        return hash('sha256', json_encode($samples, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string,mixed> $source
     */
    private static function sourceString(array $source, string $key): string
    {
        $value = $source[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite STAT4 expression partial current-source next157 needs {$key}");
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
            throw new \InvalidArgumentException("SQLite STAT4 expression partial current-source next157 needs non-negative integer {$key}");
        }

        return $value;
    }
}
