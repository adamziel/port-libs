<?php

declare(strict_types=1);

use PortLibs\Dolt\PreviewMergeConflictsTable;

return [
    'preview merge conflicts summary matches upstream row shape' => static function (TestRunner $t): void {
        $table = new PreviewMergeConflictsTable();

        $rows = $table->summaryRows(
            [
                ['table' => 'test', 'num_data_conflicts' => 1],
                ['name' => 'test', 'numConflicts' => 2],
                ['table' => 'schema_and_data', 'count' => 4],
            ],
            [
                ['table' => 'schema_only', 'num_schema_conflicts' => 1],
                ['table' => 'schema_and_data', 'numSchemaConflicts' => 3],
            ],
        );

        $t->same([
            ['table' => 'test', 'num_data_conflicts' => 3, 'num_schema_conflicts' => 0],
            ['table' => 'schema_and_data', 'num_data_conflicts' => null, 'num_schema_conflicts' => 3],
            ['table' => 'schema_only', 'num_data_conflicts' => null, 'num_schema_conflicts' => 1],
        ], $rows);
    },
    'preview merge conflicts projects divergent keyed rows' => static function (TestRunner $t): void {
        $table = new PreviewMergeConflictsTable();

        $rows = $table->conflictRows(
            [
                ['pk' => 0, 'val' => 0],
                ['pk' => 2, 'val' => 'keep'],
                ['pk' => 3, 'val' => 'base'],
            ],
            [
                ['pk' => 0, 'val' => 1001],
                ['pk' => 1, 'val' => 2],
                ['pk' => 2, 'val' => 'ours only'],
            ],
            [
                ['pk' => 0, 'val' => 1000],
                ['pk' => 1, 'val' => 1],
                ['pk' => 2, 'val' => 'keep'],
                ['pk' => 3, 'val' => 'theirs'],
            ],
            'pk',
            ['pk', 'val'],
            'right-root-hash',
        );

        foreach ($rows as $row) {
            $t->same(22, strlen((string) $row['dolt_conflict_id']));
        }

        $withoutIds = array_map(static function (array $row): array {
            unset($row['dolt_conflict_id']);
            return $row;
        }, $rows);

        $t->same([
            [
                'from_root_ish' => 'right-root-hash',
                'base_pk' => 0,
                'base_val' => 0,
                'our_pk' => 0,
                'our_val' => 1001,
                'our_diff_type' => PreviewMergeConflictsTable::DIFF_MODIFIED,
                'their_pk' => 0,
                'their_val' => 1000,
                'their_diff_type' => PreviewMergeConflictsTable::DIFF_MODIFIED,
            ],
            [
                'from_root_ish' => 'right-root-hash',
                'base_pk' => null,
                'base_val' => null,
                'our_pk' => 1,
                'our_val' => 2,
                'our_diff_type' => PreviewMergeConflictsTable::DIFF_ADDED,
                'their_pk' => 1,
                'their_val' => 1,
                'their_diff_type' => PreviewMergeConflictsTable::DIFF_ADDED,
            ],
            [
                'from_root_ish' => 'right-root-hash',
                'base_pk' => 3,
                'base_val' => 'base',
                'our_pk' => null,
                'our_val' => null,
                'our_diff_type' => PreviewMergeConflictsTable::DIFF_REMOVED,
                'their_pk' => 3,
                'their_val' => 'theirs',
                'their_diff_type' => PreviewMergeConflictsTable::DIFF_MODIFIED,
            ],
        ], $withoutIds);
    },
    'preview merge conflicts skips non-conflicting branch changes' => static function (TestRunner $t): void {
        $table = new PreviewMergeConflictsTable();

        $rows = $table->conflictRows(
            [
                ['pk' => 1, 'val' => 'base'],
                ['pk' => 2, 'val' => 'base'],
            ],
            [
                ['pk' => 1, 'val' => 'same change'],
                ['pk' => 2, 'val' => 'ours only'],
                ['pk' => 3, 'val' => 'new same'],
            ],
            [
                ['pk' => 1, 'val' => 'same change'],
                ['pk' => 2, 'val' => 'base'],
                ['pk' => 3, 'val' => 'new same'],
            ],
            'pk',
            ['pk', 'val'],
        );

        $t->same([], $rows);
    },
    'preview merge conflicts maps upstream schema-conflict error boundary' => static function (TestRunner $t): void {
        $table = new PreviewMergeConflictsTable();

        $t->same('schema conflicts found: 2', $table->schemaConflictError(2));
        $t->throws(InvalidArgumentException::class, static fn () => $table->conflictRows(
            [['pk' => 1, 'val' => 'base']],
            [['pk' => 1, 'val' => 'ours']],
            [['pk' => 1, 'val' => 'theirs']],
            'pk',
            ['pk', 'val'],
            'right-root-hash',
            1,
        ));
        $t->throws(InvalidArgumentException::class, static fn () => $table->summaryRows(['']));
        $t->throws(InvalidArgumentException::class, static fn () => $table->conflictRows(
            [['pk' => 1]],
            [['pk' => 1]],
            [['pk' => 1]],
            'pk',
            [],
        ));
    },
];
