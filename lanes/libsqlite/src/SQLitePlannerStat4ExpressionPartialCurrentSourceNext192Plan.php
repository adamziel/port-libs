<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4ExpressionPartialCurrentSourceNext192Plan
{
    /**
     * @param array<string,mixed> $preparedSource
     * @param array<string,mixed> $currentSource
     * @param list<array<string,mixed>> $whereTerms
     * @param list<string> $neededColumns
     * @return array<string,mixed>
     */
    public static function materialize(
        array $preparedSource,
        array $currentSource,
        array $whereTerms,
        array $neededColumns,
        int $limit,
        int $offset = 0
    ): array {
        $firstIndex = self::firstIndex($currentSource);
        $baseCoveringColumns = self::stringList($firstIndex['coveringColumns'] ?? null, 'coveringColumns');
        $baseExpressionColumn = (string) ($firstIndex['expressionColumn'] ?? '');
        $baseAvailableColumns = array_values(array_unique(array_filter(array_merge($baseCoveringColumns, [$baseExpressionColumn]))));
        $baseNeededColumns = array_values(array_intersect($neededColumns, $baseAvailableColumns));
        if ($baseNeededColumns === [] && $baseCoveringColumns !== []) {
            $baseNeededColumns = [$baseCoveringColumns[0]];
        }
        $base = SQLitePlannerStat4ExpressionPartialCurrentSourceNext189Plan::materialize(
            $preparedSource,
            $currentSource,
            $whereTerms,
            $baseNeededColumns,
            $limit,
            $offset,
        );
        $selectedName = (string) (($base['selectedPlan']['name'] ?? ''));
        $currentIndex = self::indexByName($currentSource, $selectedName);
        $coveringColumns = self::stringList($currentIndex['coveringColumns'] ?? null, 'coveringColumns');
        $expressionColumn = (string) ($currentIndex['expressionColumn'] ?? '');
        $availableColumns = array_values(array_unique(array_filter(array_merge($coveringColumns, [$expressionColumn]))));
        $missingColumns = array_values(array_diff($neededColumns, $availableColumns));
        $rowChecks = self::rowChecks($base, $neededColumns, $availableColumns);
        $rowMissing = [];
        foreach ($rowChecks as $check) {
            $rowMissing = array_values(array_unique(array_merge($rowMissing, $check['missingColumns'])));
        }

        $ready = ($base['status'] ?? null) === 'stat4-expression-partial-current-source-next189-ready'
            && $neededColumns !== []
            && $missingColumns === []
            && $rowMissing === [];

        $coveringFence = [
            'ready' => $ready,
            'selectedIndex' => $selectedName,
            'neededColumns' => array_values($neededColumns),
            'coveringColumns' => $coveringColumns,
            'expressionColumn' => $expressionColumn,
            'availableColumns' => $availableColumns,
            'missingColumns' => $missingColumns,
            'rowMissingColumns' => $rowMissing,
            'rowChecks' => $rowChecks,
            'tableLookupRequired' => !$ready,
            'deferredSeekOpcode' => $ready ? null : 'DeferredSeek',
            'signature' => self::signature([$selectedName, $neededColumns, $availableColumns, $rowChecks]),
        ];

        return array_replace($base, [
            'status' => $ready ? 'stat4-expression-partial-current-source-next192-ready' : 'requires-current-source-covering-reprepare',
            'coveringColumnFence' => $coveringFence,
            'tableLookupElided' => $ready,
            'deferredSeekOpcode' => $coveringFence['deferredSeekOpcode'],
            'selectedPlan' => array_replace(is_array($base['selectedPlan'] ?? null) ? $base['selectedPlan'] : [], [
                'next192Ready' => $ready,
                'next192CoveringColumns' => $coveringColumns,
                'next192NeededColumns' => array_values($neededColumns),
                'next192MissingColumns' => array_values(array_unique(array_merge($missingColumns, $rowMissing))),
                'next192TableLookupRequired' => !$ready,
            ]),
            'stat4Fence' => array_replace(is_array($base['stat4Fence'] ?? null) ? $base['stat4Fence'] : [], [
                'next192CoveringSignature' => $coveringFence['signature'],
            ]),
            'cursorProgram' => self::cursorProgram(
                is_array($base['cursorProgram'] ?? null) ? $base['cursorProgram'] : [],
                $ready,
                $neededColumns,
                array_values(array_unique(array_merge($missingColumns, $rowMissing))),
            ),
            'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                . ' STAT4 EXPRESSION PARTIAL CURRENT SOURCE NEXT192 COVERING COLUMN FENCE '
                . $selectedName
                . ($ready ? ' TABLE LOOKUP ELIDED' : ' REQUIRES CURRENT SOURCE COVERING REPREPARE'),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($base['dependencies'] ?? null) ? $base['dependencies'] : [],
                [
                    'SQLitePlannerStat4ExpressionPartialCurrentSourceNext189Plan',
                    'sqlite-sqlplanner-stat4-expression-partial-current-source-next192',
                ],
            ))),
            'dependency_closure' => 'no new support component needed; next192 reuses current-source STAT4 expression partial payload checks and adds a covering-column admission fence before eliding table lookups',
            'non_overlap' => 'avoids accepted next189 payload partial predicate checks, next188 peer fences, next186 IN windows, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, and trigger clusters; this slice only admits current-source STAT4 partial expression scans when requested payload columns are still covered',
        ]);
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function firstIndex(array $source): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes) || !isset($indexes[0]) || !is_array($indexes[0])) {
            throw new \InvalidArgumentException('SQLite next192 needs source indexes');
        }

        return $indexes[0];
    }

    /**
     * @param array<string,mixed> $source
     * @return array<string,mixed>
     */
    private static function indexByName(array $source, string $name): array
    {
        $indexes = $source['indexes'] ?? null;
        if (!is_array($indexes) || !array_is_list($indexes)) {
            throw new \InvalidArgumentException('SQLite next192 needs source indexes');
        }
        foreach ($indexes as $index) {
            if (!is_array($index)) {
                throw new \InvalidArgumentException('SQLite next192 index entries must be arrays');
            }
            if ((string) ($index['name'] ?? '') === $name) {
                return $index;
            }
        }

        throw new \InvalidArgumentException('SQLite next192 selected index missing from current source');
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList(mixed $value, string $name): array
    {
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException('SQLite next192 needs ' . $name . ' list');
        }
        $out = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException('SQLite next192 ' . $name . ' entries must be non-empty strings');
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $base
     * @param list<string> $neededColumns
     * @param list<string> $availableColumns
     * @return list<array<string,mixed>>
     */
    private static function rowChecks(array $base, array $neededColumns, array $availableColumns): array
    {
        $rows = $base['projectedRows'] ?? null;
        if (!is_array($rows) || !array_is_list($rows)) {
            throw new \InvalidArgumentException('SQLite next192 needs projected rows');
        }
        $checks = [];
        foreach ($rows as $position => $row) {
            if (!is_array($row)) {
                throw new \InvalidArgumentException('SQLite next192 projected rows must be arrays');
            }
            $present = [];
            foreach ($neededColumns as $column) {
                if (array_key_exists($column, $row) && in_array($column, $availableColumns, true)) {
                    $present[] = $column;
                }
            }
            $missing = array_values(array_diff($neededColumns, $present));
            $checks[] = [
                'position' => $position,
                'rowid' => is_int($row['rowid'] ?? null) ? $row['rowid'] : null,
                'presentColumns' => $present,
                'missingColumns' => $missing,
                'ready' => $missing === [],
                'payloadSignature' => self::signature($row),
            ];
        }

        return $checks;
    }

    /**
     * @param list<array<string,mixed>> $program
     * @param list<string> $neededColumns
     * @param list<string> $missingColumns
     * @return list<array<string,mixed>>
     */
    private static function cursorProgram(array $program, bool $ready, array $neededColumns, array $missingColumns): array
    {
        $program[] = [
            'opcode' => $ready ? 'CoveringColumnFence' : 'DeferredSeek',
            'mode' => 'next192-current-source-stat4-expression-partial-covering',
            'ready' => $ready,
            'neededColumns' => array_values($neededColumns),
            'missingColumns' => $missingColumns,
        ];

        return $program;
    }

    /**
     * @param mixed $value
     */
    private static function signature(mixed $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }
}
