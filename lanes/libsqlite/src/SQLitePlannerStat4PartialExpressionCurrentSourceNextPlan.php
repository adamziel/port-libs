<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePlannerStat4PartialExpressionCurrentSourceNextPlan
{

    /* Variant formerly implemented by SQLitePlannerStat4PartialExpressionCurrentSourceNextPlan. */

    /**
         * @param array<string,mixed> $preparedSource
         * @param array<string,mixed> $currentSource
         * @param array<string,mixed> $predicate
         * @param list<array<string,mixed>> $preparedRows
         * @param list<array<string,mixed>> $currentRows
         * @param list<array<string,string>> $orderBy
         * @param list<string> $neededColumns
         * @param list<array<string,string>> $neededExpressions
         * @return array<string,mixed>
         */
        public static function materialize(
            array $preparedSource,
            array $currentSource,
            array $predicate,
            array $preparedRows,
            array $currentRows,
            array $orderBy,
            array $neededColumns,
            array $neededExpressions = []
        ): array {
            $base = SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan::materialize(
                $preparedSource,
                $currentSource,
                $predicate,
                $currentRows,
                $orderBy,
                $neededColumns,
                $neededExpressions,
            );

            $preparedFingerprint = self::rowFingerprint($preparedRows);
            $currentFingerprint = self::rowFingerprint($currentRows);
            $rowDelta = self::rowDelta($preparedRows, $currentRows, $neededColumns);
            $ready = ($base['status'] ?? null) === 'covering-expression-stat4-current-source-ready'
                && (($base['selectedPlan']['partial'] ?? false) === true)
                && (($base['selectedPlan']['stat4Used'] ?? false) === true)
                && (($base['selectedPlan']['covering'] ?? false) === true)
                && ($rowDelta['deletedRowids'] ?? []) !== ($base['cursorTape']['matchedRowids'] ?? []);

            $cursorTape = self::cursorTape($base, $rowDelta, $neededColumns, $ready);

            return array_replace($base, [
                'status' => $ready ? 'partial-expression-stat4-current-source-ready' : 'requires-next-stage',
                'preparedRowSignature' => $preparedFingerprint,
                'currentRowSignature' => $currentFingerprint,
                'rowSignatureChanged' => $preparedFingerprint !== $currentFingerprint,
                'rowGenerationFence' => [
                    'preparedRows' => count($preparedRows),
                    'currentRows' => count($currentRows),
                    'insertedRowids' => $rowDelta['insertedRowids'],
                    'deletedRowids' => $rowDelta['deletedRowids'],
                    'updatedRowids' => $rowDelta['updatedRowids'],
                    'unchangedRowids' => $rowDelta['unchangedRowids'],
                ],
                'selectedPlan' => array_replace($base['selectedPlan'] ?? [], [
                    'partialExpressionCurrentSource' => true,
                    'rowGenerationChanged' => $preparedFingerprint !== $currentFingerprint,
                    'deletedPreparedRowidsBlocked' => $rowDelta['deletedRowids'],
                    'insertedCurrentRowidsAdmitted' => $rowDelta['insertedRowids'],
                    'updatedCurrentRowidsRefreshed' => $rowDelta['updatedRowids'],
                ]),
                'cursorTape' => $cursorTape,
                'currentNextRows' => $cursorTape['currentNextRows'],
                'currentSourceFence' => array_replace($base['currentSourceFence'] ?? [], [
                    'rowSignature' => $currentFingerprint,
                    'rowGeneration' => self::nonNegativeInt($currentSource, 'rowGeneration'),
                ]),
                'detail' => (($base['reprepareRequired'] ?? false) ? 'REPREPARE' : 'REUSE')
                    . ' PARTIAL EXPRESSION STAT4 CURRENT SOURCE '
                    . (string) (($base['selectedPlan']['name'] ?? null) ?: 'NO INDEX'),
                'dependencies' => [
                    'SQLitePlannerCoveringExpressionStat4CurrentSourceNextPlan',
                    'SQLiteSelectExpressionIndexPlan',
                    'sqlite-sqlplanner-stat4-partial-expression-current-source',
                ],
                'dependency_closure' => 'no new support component needed; current-source composes native expression-index STAT4 planning with current-source row generation fences',
                'non_overlap' => 'avoids accepted range-cost, expression ORDER BY, subquery-covering, and canonical covering-row materialization by blocking stale prepared payload rows deleted from the current source while admitting inserted and updated current rows',
            ]);
        }

        /**
         * @param list<array<string,mixed>> $preparedRows
         * @param list<array<string,mixed>> $currentRows
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function rowDelta(array $preparedRows, array $currentRows, array $neededColumns): array
        {
            $prepared = self::rowsByRowid($preparedRows);
            $current = self::rowsByRowid($currentRows);

            $inserted = [];
            $deleted = [];
            $updated = [];
            $unchanged = [];
            foreach ($current as $rowid => $row) {
                if (!array_key_exists($rowid, $prepared)) {
                    $inserted[] = (int) $rowid;
                    continue;
                }
                if (self::payloadSignature($prepared[$rowid], $neededColumns) !== self::payloadSignature($row, $neededColumns)) {
                    $updated[] = (int) $rowid;
                    continue;
                }
                $unchanged[] = (int) $rowid;
            }
            foreach ($prepared as $rowid => $_row) {
                if (!array_key_exists($rowid, $current)) {
                    $deleted[] = (int) $rowid;
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
            $byRowid = [];
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new \InvalidArgumentException('SQLite partial expression current-source rows must be arrays');
                }
                $rowid = $row['rowid'] ?? $row['_rowid_'] ?? null;
                if (!is_int($rowid) || $rowid < 0) {
                    throw new \InvalidArgumentException('SQLite partial expression current-source rows need non-negative rowid');
                }
                $byRowid[$rowid] = $row;
            }

            ksort($byRowid, SORT_NUMERIC);

            return $byRowid;
        }

        /**
         * @param list<array<string,mixed>> $rows
         */
        private static function rowFingerprint(array $rows): string
        {
            return hash('sha256', json_encode(self::rowsByRowid($rows), JSON_THROW_ON_ERROR));
        }

        /**
         * @param array<string,mixed> $row
         * @param list<string> $neededColumns
         */
        private static function payloadSignature(array $row, array $neededColumns): string
        {
            $payload = [];
            foreach ($neededColumns as $column) {
                if (!is_string($column) || $column === '') {
                    throw new \InvalidArgumentException('SQLite partial expression current-source output columns must be names');
                }
                $payload[$column] = $row[$column] ?? null;
            }

            return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
        }

        /**
         * @param array<string,mixed> $base
         * @param array<string,mixed> $rowDelta
         * @param list<string> $neededColumns
         * @return array<string,mixed>
         */
        private static function cursorTape(array $base, array $rowDelta, array $neededColumns, bool $ready): array
        {
            $rows = [];
            foreach (($base['currentNextRows'] ?? []) as $pair) {
                if (!is_array($pair) || !is_array($pair['current'] ?? null)) {
                    continue;
                }
                $rows[] = $pair['current'];
            }

            $deleted = array_flip($rowDelta['deletedRowids']);
            $currentRows = [];
            foreach ($rows as $row) {
                $rowid = $row['rowid'] ?? null;
                if (is_int($rowid) && isset($deleted[$rowid])) {
                    continue;
                }
                $currentRows[] = $row;
            }

            $program = [
                ['opcode' => 'OpenRead', 'source' => 'partial-expression-index', 'rootPage' => $base['selectedPlan']['rootPage'] ?? null],
                ['opcode' => 'FenceCurrentSource', 'rowSignature' => $base['currentRowSignature'] ?? null],
                ['opcode' => 'SeekGE', 'source' => 'index', 'key' => $base['cursorTape']['rangeLower'] ?? null],
                ['opcode' => 'IdxGE', 'source' => 'index', 'key' => $base['cursorTape']['rangeUpper'] ?? null],
                ['opcode' => 'FilterDeletedRowids', 'rowids' => $rowDelta['deletedRowids']],
            ];
            foreach ($neededColumns as $column) {
                $program[] = ['opcode' => 'Column', 'source' => $ready ? 'current-covering-index' : 'table', 'column' => $column];
            }
            $program[] = ['opcode' => 'Next', 'source' => 'index'];

            return array_replace($base['cursorTape'] ?? [], [
                'currentNextRows' => self::currentNextRows($currentRows),
                'matchedRowids' => array_values(array_map(static fn (array $row): mixed => $row['rowid'] ?? null, $currentRows)),
                'insertedRowids' => $rowDelta['insertedRowids'],
                'updatedRowids' => $rowDelta['updatedRowids'],
                'deletedRowidsBlocked' => $rowDelta['deletedRowids'],
                'tableLookupElidedAfterRowFence' => $ready,
                'program' => $program,
            ]);
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
         * @param array<string,mixed> $source
         */
        private static function nonNegativeInt(array $source, string $key): int
        {
            $value = $source[$key] ?? null;
            if (!is_int($value) || $value < 0) {
                throw new \InvalidArgumentException('SQLite partial expression current-source ' . $key . ' must be a non-negative integer');
            }

            return $value;
        }

}
