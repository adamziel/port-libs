<?php

declare(strict_types=1);

use PortLibs\Dolt\TableDiff;
use PortLibs\Dolt\TableDeltaMatcher;
use PortLibs\Dolt\TableSchema;

$dataColumns = static function (array $row): array {
    $columns = [];
    foreach (array_keys($row) as $name) {
        if (in_array($name, ['to_commit', 'to_commit_date', 'from_commit', 'from_commit_date', 'diff_type'], true)) {
            continue;
        }
        if (str_starts_with($name, 'to_')) {
            $column = substr($name, 3);
        } elseif (str_starts_with($name, 'from_')) {
            $column = substr($name, 5);
        } else {
            continue;
        }
        if (!in_array($column, $columns, true)) {
            $columns[] = $column;
        }
    }

    return $columns;
};

return [
    'table diff classifies added removed and modified rows by primary key' => static function (TestRunner $t): void {
        $old = [
            ['id' => 1, 'title' => 'Draft'],
            ['id' => 2, 'title' => 'Remove me'],
        ];
        $new = [
            ['id' => 1, 'title' => 'Published'],
            ['id' => 3, 'title' => 'New'],
        ];
        $diff = (new TableDiff())->diff($old, $new, 'id');
        $t->same(1, count($diff['added']));
        $t->same(1, count($diff['removed']));
        $t->same('Published', $diff['modified'][0]['new']['title']);
    },
    'dolt diff table rows match upstream to/from column projection' => static function (TestRunner $t): void {
        $from = [
            ['pk' => 1, 'c1' => 2, 'c2' => 3],
            ['pk' => 2, 'c1' => 4, 'c2' => 5],
        ];
        $to = [
            ['pk' => 1, 'c1' => 2, 'c2' => 0],
            ['pk' => 3, 'c1' => 6, 'c2' => 7],
        ];

        $rows = (new TableDiff())->diffTableRows(
            $from,
            $to,
            'pk',
            ['pk', 'c1', 'c2'],
            'from-hash',
            '2026-05-21 12:00:00',
            'to-hash',
            '2026-05-22 12:00:00',
        );

        $t->same(['modified', 'removed', 'added'], array_column($rows, 'diff_type'));
        $t->same([1, 2, 0, 'to-hash', '2026-05-22 12:00:00', 1, 2, 3, 'from-hash', '2026-05-21 12:00:00', 'modified'], array_values($rows[0]));
        $t->same([null, null, null, 'to-hash', '2026-05-22 12:00:00', 2, 4, 5, 'from-hash', '2026-05-21 12:00:00', 'removed'], array_values($rows[1]));
        $t->same([3, 6, 7, 'to-hash', '2026-05-22 12:00:00', null, null, null, 'from-hash', '2026-05-21 12:00:00', 'added'], array_values($rows[2]));
    },
    'composite primary keys are encoded without string collisions' => static function (TestRunner $t): void {
        $from = [
            ['site_id' => 1, 'option_name' => 'a:bc', 'option_value' => 'old'],
            ['site_id' => 1, 'option_name' => 'ab:c', 'option_value' => 'same'],
        ];
        $to = [
            ['site_id' => 1, 'option_name' => 'a:bc', 'option_value' => 'new'],
            ['site_id' => 1, 'option_name' => 'ab:c', 'option_value' => 'same'],
        ];

        $diff = (new TableDiff())->diff($from, $to, ['site_id', 'option_name']);
        $t->same(1, count($diff['modified']));
        $t->same('a:bc', $diff['modified'][0]['old']['option_name']);
        $t->same('new', $diff['modified'][0]['new']['option_value']);
    },
    'primary key validation rejects missing null and duplicate keys' => static function (TestRunner $t): void {
        $differ = new TableDiff();

        $t->throws(InvalidArgumentException::class, static fn () => $differ->diff([['title' => 'missing']], [], 'id'));
        $t->throws(InvalidArgumentException::class, static fn () => $differ->diff([['id' => null]], [], 'id'));
        $t->throws(InvalidArgumentException::class, static fn () => $differ->diff([['id' => 1], ['id' => 1]], [], 'id'));
    },
    'wordpress posts fixture projects migration changes as dolt diff rows' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-posts-diff.php';
        $rows = (new TableDiff())->diffTableRows(
            $fixture['fromRows'],
            $fixture['toRows'],
            'ID',
            $fixture['columns'],
            $fixture['fromCommit'],
            null,
            $fixture['toCommit'],
            null,
        );

        $t->same($fixture['expectedDiffTypes'], array_column($rows, 'diff_type'));
        $t->same($fixture['expectedChangedIds'], array_map(
            static fn (array $row): int => (int) ($row['to_ID'] ?? $row['from_ID']),
            $rows,
        ));
        $t->same('Published landing', $rows[0]['to_post_title']);
        $t->same('Legacy page', $rows[1]['from_post_title']);
        $t->same('Imported resource', $rows[2]['to_post_title']);
    },
    'schema column diff is driven by stable Dolt column tags' => static function (TestRunner $t): void {
        $from = TableSchema::fromColumns([
            ['name' => 'unchanged', 'tag' => 0, 'type' => 'string', 'primaryKey' => true, 'constraints' => ['not_null']],
            ['name' => 'dropped', 'tag' => 1, 'type' => 'string', 'primaryKey' => true],
            ['name' => 'renamed', 'tag' => 2, 'type' => 'string'],
            ['name' => 'type_changed', 'tag' => 3, 'type' => 'string'],
            ['name' => 'moved_to_pk', 'tag' => 4, 'type' => 'string'],
            ['name' => 'constraint_added', 'tag' => 5, 'type' => 'string'],
        ]);
        $to = TableSchema::fromColumns([
            ['name' => 'unchanged', 'tag' => 0, 'type' => 'string', 'primaryKey' => true, 'constraints' => ['not_null']],
            ['name' => 'renamed_new', 'tag' => 2, 'type' => 'string'],
            ['name' => 'type_changed', 'tag' => 3, 'type' => 'int'],
            ['name' => 'moved_to_pk', 'tag' => 4, 'type' => 'string', 'primaryKey' => true],
            ['name' => 'constraint_added', 'tag' => 5, 'type' => 'string', 'constraints' => ['not_null']],
            ['name' => 'added', 'tag' => 6, 'type' => 'string'],
        ]);

        $diffs = TableSchema::diffColumns($from, $to);

        $t->same([0, 1, 2, 3, 4, 5, 6], array_column($diffs, 'tag'));
        $t->same([
            TableSchema::DIFF_NONE,
            TableSchema::DIFF_REMOVED,
            TableSchema::DIFF_MODIFIED,
            TableSchema::DIFF_MODIFIED,
            TableSchema::DIFF_MODIFIED,
            TableSchema::DIFF_MODIFIED,
            TableSchema::DIFF_ADDED,
        ], array_column($diffs, 'diff_type'));
        $t->same('renamed', $diffs[2]['from']['name']);
        $t->same('renamed_new', $diffs[2]['to']['name']);
    },
    'primary key diffability follows Dolt tag order and type semantics' => static function (TestRunner $t): void {
        $basic = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 0, 'type' => 'int', 'primaryKey' => true],
        ]);
        $renamedPk = TableSchema::fromColumns([
            ['name' => 'pk2', 'tag' => 0, 'type' => 'int', 'primaryKey' => true],
        ]);
        $extraNonPk = TableSchema::fromColumns([
            ['name' => 'pk2', 'tag' => 0, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'meta', 'tag' => 9, 'type' => 'string'],
        ]);
        $differentTag = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ]);
        $differentType = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 0, 'type' => 'string', 'primaryKey' => true],
        ]);
        $compound = TableSchema::fromColumns([
            ['name' => 'pk1', 'tag' => 0, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'pk2', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ]);
        $compoundReordered = TableSchema::fromColumns([
            ['name' => 'pk2', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'pk1', 'tag' => 0, 'type' => 'int', 'primaryKey' => true],
        ]);

        $t->true(TableSchema::primaryKeySetsDiffable($basic, $renamedPk));
        $t->same([0], TableSchema::primaryKeyOrdinalMapping($basic, $renamedPk));
        $t->true(TableSchema::primaryKeySetsDiffable($renamedPk, $extraNonPk));
        $t->true(!TableSchema::primaryKeySetsDiffable($basic, $differentTag));
        $t->true(!TableSchema::primaryKeySetsDiffable($basic, $differentType));
        $t->true(!TableSchema::primaryKeySetsDiffable($compound, $compoundReordered));
    },
    'table delta matcher maps same names before tag-overlap renames' => static function (TestRunner $t): void {
        $schemas = [
            'sch' => TableSchema::fromColumns([['name' => 'pk', 'tag' => 0, 'type' => 'string', 'primaryKey' => true]]),
            'sch2' => TableSchema::fromColumns([['name' => 'pk2', 'tag' => 1, 'type' => 'string', 'primaryKey' => true]]),
            'sch3' => TableSchema::fromColumns([['name' => 'pk3', 'tag' => 2, 'type' => 'string', 'primaryKey' => true]]),
            'sch4' => TableSchema::fromColumns([['name' => 'pk4', 'tag' => 3, 'type' => 'string', 'primaryKey' => true]]),
            'sch5' => TableSchema::fromColumns([['name' => 'pk5', 'tag' => 4, 'type' => 'string', 'primaryKey' => true]]),
        ];

        $summaries = (new TableDeltaMatcher())->summaries(
            [
                ['name' => 'should_match_on_name', 'schema' => $schemas['sch'], 'rowHash' => 'old'],
                ['name' => 'dropped', 'schema' => $schemas['sch'], 'rowHash' => 'old', 'rowCount' => 1],
                ['name' => 'dropped2', 'schema' => $schemas['sch3'], 'rowHash' => 'old2', 'rowCount' => 1],
                ['name' => 'renamed_before', 'schema' => $schemas['sch5'], 'rowHash' => 'old5'],
            ],
            [
                ['name' => 'should_match_on_name', 'schema' => $schemas['sch'], 'rowHash' => 'new'],
                ['name' => 'added', 'schema' => $schemas['sch2'], 'rowHash' => 'new', 'rowCount' => 1],
                ['name' => 'added2', 'schema' => $schemas['sch4'], 'rowHash' => 'new2', 'rowCount' => 1],
                ['name' => 'renamed_after', 'schema' => $schemas['sch5'], 'rowHash' => 'old5'],
            ],
        );

        $byPair = [];
        foreach ($summaries as $summary) {
            $byPair[($summary['from_table_name'] ?? '') . '->' . ($summary['to_table_name'] ?? '')] = $summary;
        }

        $t->same(TableDeltaMatcher::DIFF_MODIFIED, $byPair['should_match_on_name->should_match_on_name']['diff_type']);
        $t->same(TableDeltaMatcher::DIFF_RENAMED, $byPair['renamed_before->renamed_after']['diff_type']);
        $t->same(TableDeltaMatcher::DIFF_DROPPED, $byPair['dropped->']['diff_type']);
        $t->same(TableDeltaMatcher::DIFF_DROPPED, $byPair['dropped2->']['diff_type']);
        $t->same(TableDeltaMatcher::DIFF_ADDED, $byPair['->added']['diff_type']);
        $t->same(TableDeltaMatcher::DIFF_ADDED, $byPair['->added2']['diff_type']);
    },
    'table delta matcher does not call changed column names an overlapping schema' => static function (TestRunner $t): void {
        $from = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 7, 'type' => 'int', 'primaryKey' => true],
        ]);
        $to = TableSchema::fromColumns([
            ['name' => 'renamed_pk', 'tag' => 7, 'type' => 'int', 'primaryKey' => true],
        ]);

        $summaries = (new TableDeltaMatcher())->summaries(
            [['name' => 'old_table', 'schema' => $from, 'rowCount' => 0]],
            [['name' => 'new_table', 'schema' => $to, 'rowCount' => 0]],
        );

        $t->same([TableDeltaMatcher::DIFF_ADDED, TableDeltaMatcher::DIFF_DROPPED], array_column($summaries, 'diff_type'));
    },
    'table delta summaries omit unchanged exact-name tables' => static function (TestRunner $t): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'value', 'tag' => 2, 'type' => 'text'],
        ]);

        $summaries = (new TableDeltaMatcher())->summaries(
            [['name' => 'unchanged', 'schema' => $schema, 'rowHash' => 'same', 'rowCount' => 1]],
            [['name' => 'unchanged', 'schema' => $schema, 'rowHash' => 'same', 'rowCount' => 1]],
        );

        $t->same([], $summaries);
    },
    'row change types map to table diff filter names' => static function (TestRunner $t): void {
        $t->same(TableDeltaMatcher::DIFF_ADDED, TableDeltaMatcher::changeTypeToDiffType('added'));
        $t->same(TableDeltaMatcher::DIFF_DROPPED, TableDeltaMatcher::changeTypeToDiffType('removed'));
        $t->same(TableDeltaMatcher::DIFF_MODIFIED, TableDeltaMatcher::changeTypeToDiffType('modified_old'));
        $t->same(TableDeltaMatcher::DIFF_MODIFIED, TableDeltaMatcher::changeTypeToDiffType('modified_new'));
        $t->same('', TableDeltaMatcher::changeTypeToDiffType('none'));
    },
    'where clauses filter projected to and from primary key columns' => static function (TestRunner $t): void {
        $differ = new TableDiff();
        $rows = $differ->diffTableRows(
            [['pk' => 4, 'c1' => 44]],
            [['pk' => 5, 'c1' => 55]],
            'pk',
            ['pk', 'c1'],
        );

        $fromOnly = $differ->filterDiffTableRows($rows, 'from_pk=4');
        $toOnly = $differ->filterDiffTableRows($rows, 'to_pk=5');
        $both = $differ->filterDiffTableRows($rows, 'from_pk=4 OR to_pk=5');

        $t->same([TableDiff::DIFF_REMOVED], array_column($fromOnly, 'diff_type'));
        $t->same(44, $fromOnly[0]['from_c1']);
        $t->same([TableDiff::DIFF_ADDED], array_column($toOnly, 'diff_type'));
        $t->same(55, $toOnly[0]['to_c1']);
        $t->same([TableDiff::DIFF_REMOVED, TableDiff::DIFF_ADDED], array_column($both, 'diff_type'));
        $t->same([], $differ->filterDiffTableRows($rows, 'from_pk=5'));
        $t->throws(InvalidArgumentException::class, static fn () => $differ->filterDiffTableRows($rows, 'pk=4'));
        $t->throws(InvalidArgumentException::class, static fn () => $differ->filterDiffTableRows($rows, 'some nonsense'));
    },
    'where clauses support null checks and compound primary key predicates' => static function (TestRunner $t): void {
        $differ = new TableDiff();
        $rows = $differ->diffTableRows(
            [
                ['pk' => 1, 'c1' => 2, 'c2' => 3],
                ['pk' => 7, 'c1' => 8, 'c2' => 9],
            ],
            [
                ['pk' => 4, 'c1' => 5, 'c2' => 6],
                ['pk' => 7, 'c1' => 0, 'c2' => 9],
            ],
            'pk',
            ['pk', 'c1', 'c2'],
        );

        $removed = $differ->filterDiffTableRows($rows, 'to_pk IS NULL');
        $added = $differ->filterDiffTableRows($rows, 'from_pk IS NULL');
        $notAdded = $differ->filterDiffTableRows($rows, 'from_pk IS NOT NULL');
        $greaterThan = $differ->filterDiffTableRows($rows, 'to_pk > 1');

        $t->same([TableDiff::DIFF_REMOVED], array_column($removed, 'diff_type'));
        $t->same(1, $removed[0]['from_pk']);
        $t->same([TableDiff::DIFF_ADDED], array_column($added, 'diff_type'));
        $t->same(4, $added[0]['to_pk']);
        $t->same([TableDiff::DIFF_REMOVED, TableDiff::DIFF_MODIFIED], array_column($notAdded, 'diff_type'));
        $t->same([TableDiff::DIFF_ADDED, TableDiff::DIFF_MODIFIED], array_column($greaterThan, 'diff_type'));

        $compound = $differ->diffTableRows(
            [],
            [
                ['pk1' => 1, 'pk2' => 2, 'c1' => 3],
                ['pk1' => 4, 'pk2' => 5, 'c1' => 6],
                ['pk1' => 7, 'pk2' => 8, 'c1' => 9],
            ],
            ['pk1', 'pk2'],
            ['pk1', 'pk2', 'c1'],
        );
        $exact = $differ->filterDiffTableRows($compound, 'to_pk1 = 1 and to_pk2 = 2');
        $range = $differ->filterDiffTableRows($compound, 'to_pk1 > 1 and to_pk2 < 10');

        $t->same(1, count($exact));
        $t->same(2, $exact[0]['to_pk2']);
        $t->same([4, 7], array_column($range, 'to_pk1'));
    },
    'diff row limit is applied after where filtering' => static function (TestRunner $t): void {
        $differ = new TableDiff();
        $rows = $differ->diffTableRows(
            [],
            [
                ['pk' => 0, 'c1' => 0],
                ['pk' => 1, 'c1' => 1],
                ['pk' => 2, 'c1' => 2],
            ],
            'pk',
            ['pk', 'c1'],
        );

        $limited = $differ->filterDiffTableRows($rows, 'to_pk > 0', 1);

        $t->same(1, count($limited));
        $t->same(1, $limited[0]['to_pk']);
        $t->same([], $differ->filterDiffTableRows($rows, null, 0));
        $t->throws(InvalidArgumentException::class, static fn () => $differ->filterDiffTableRows($rows, null, -1));
    },
    'wordpress table delta fixture detects renamed content table' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-table-deltas.php';
        $summaries = (new TableDeltaMatcher())->summaries($fixture['fromTables'], $fixture['toTables']);

        $byPair = [];
        foreach ($summaries as $summary) {
            $byPair[($summary['from_table_name'] ?? '') . '->' . ($summary['to_table_name'] ?? '')] = $summary;
        }

        $t->same(TableDeltaMatcher::DIFF_RENAMED, $byPair['wp_posts->wp_content_posts']['diff_type']);
        $t->true($byPair['wp_posts->wp_content_posts']['schema_change']);
        $t->true($byPair['wp_posts->wp_content_posts']['data_change']);
        $t->true(!$byPair['wp_posts->wp_content_posts']['primary_key_set_changed']);
        $t->same(TableDeltaMatcher::DIFF_DROPPED, $byPair['wp_legacy_links->']['diff_type']);
        $t->same(TableDeltaMatcher::DIFF_ADDED, $byPair['->wp_import_audit']['diff_type']);
    },
    'schema-aware diff rows project dropped columns through a target schema' => static function (TestRunner $t): void {
        $fromSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
            ['name' => 'c2', 'tag' => 3, 'type' => 'int'],
        ]);
        $toSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c2', 'tag' => 3, 'type' => 'int'],
        ]);
        $targetSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c2', 'tag' => 3, 'type' => 'int'],
        ]);

        $warnings = [];
        $rows = (new TableDiff())->diffTableRowsForSchemas(
            [['pk' => 1, 'c1' => 2, 'c2' => 3], ['pk' => 4, 'c1' => 5, 'c2' => 6]],
            [['pk' => 1, 'c2' => 3], ['pk' => 4, 'c2' => 6]],
            'pk',
            $fromSchema,
            $toSchema,
            $targetSchema,
            $targetSchema,
            'commit-1',
            null,
            'commit-2',
            null,
            $warnings,
        );

        $t->same(2, count($rows));
        $t->same([1, 3, 'commit-2', null, 1, 3, 'commit-1', null, 'modified'], array_values($rows[0]));
        $t->same([], $warnings);
    },
    'schema-aware diff rows map non-primary columns by name and coerce target types' => static function (TestRunner $t): void {
        $fromSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c', 'tag' => 2, 'type' => 'int'],
        ]);
        $toSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ]);
        $targetSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c', 'tag' => 9, 'type' => 'varchar(20)'],
        ]);

        $warnings = [];
        $rows = (new TableDiff())->diffTableRowsForSchemas(
            [['pk' => 1, 'c' => 2], ['pk' => 3, 'c' => 4]],
            [['pk' => 1], ['pk' => 3]],
            'pk',
            $fromSchema,
            $toSchema,
            $targetSchema,
            $targetSchema,
            'commit-1',
            null,
            'commit-2',
            null,
            $warnings,
        );

        $t->same(['modified', 'modified'], array_column($rows, 'diff_type'));
        $t->same('2', $rows[0]['from_c']);
        $t->same(null, $rows[0]['to_c']);
        $t->same([], $warnings);
    },
    'schema-aware diff rows warn when target type coercion fails' => static function (TestRunner $t): void {
        $fromSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c', 'tag' => 2, 'type' => 'varchar(20)'],
        ]);
        $toSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ]);
        $targetSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c', 'tag' => 9, 'type' => 'int'],
        ]);

        $warnings = [];
        $rows = (new TableDiff())->diffTableRowsForSchemas(
            [['pk' => 1, 'c' => 'two'], ['pk' => 3, 'c' => 'four']],
            [['pk' => 1], ['pk' => 3]],
            'pk',
            $fromSchema,
            $toSchema,
            $targetSchema,
            $targetSchema,
            'commit-1',
            null,
            'commit-2',
            null,
            $warnings,
        );

        $t->same(2, count($rows));
        $t->same(null, $rows[0]['from_c']);
        $t->same(2, count($warnings));
        $t->same(TableSchema::WARNING_UNKNOWN, $warnings[0]['code']);
        $t->contains('unable to coerce value from field', $warnings[0]['message']);
    },
    'schema-aware diff rows do not map renamed non-primary columns by tag alone' => static function (TestRunner $t): void {
        $fromSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
            ['name' => 'c2', 'tag' => 3, 'type' => 'int'],
        ]);
        $toSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c2', 'tag' => 3, 'type' => 'int'],
        ]);
        $targetSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c1', 'tag' => 3, 'type' => 'int'],
        ]);

        $warnings = [];
        $rows = (new TableDiff())->diffTableRowsForSchemas(
            [['pk' => 1, 'c1' => 2, 'c2' => 3]],
            [['pk' => 1, 'c2' => 3]],
            'pk',
            $fromSchema,
            $toSchema,
            $targetSchema,
            $targetSchema,
            'commit-1',
            null,
            'commit-2',
            null,
            $warnings,
        );

        $t->same(1, count($rows));
        $t->same(2, $rows[0]['from_c1']);
        $t->same(null, $rows[0]['to_c1']);
        $t->same([], $warnings);
    },
    'skinny schema-aware diff rows keep primary keys and changed columns' => static function (TestRunner $t) use ($dataColumns): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 0, 'type' => 'bigint', 'primaryKey' => true],
            ['name' => 'c1', 'tag' => 1, 'type' => 'bigint'],
            ['name' => 'c2', 'tag' => 2, 'type' => 'bigint'],
            ['name' => 'c3', 'tag' => 3, 'type' => 'bigint'],
            ['name' => 'c4', 'tag' => 4, 'type' => 'bigint'],
            ['name' => 'c5', 'tag' => 5, 'type' => 'bigint'],
        ]);
        $warnings = [];
        $rows = (new TableDiff())->diffTableRowsForSchemas(
            [
                ['pk' => 0, 'c1' => 1, 'c2' => 2, 'c3' => 3, 'c4' => 4, 'c5' => 5],
                ['pk' => 1, 'c1' => 1, 'c2' => 2, 'c3' => 3, 'c4' => 4, 'c5' => 5],
            ],
            [
                ['pk' => 0, 'c1' => 100, 'c2' => 2, 'c3' => 300, 'c4' => 4, 'c5' => 5],
                ['pk' => 1, 'c1' => 1, 'c2' => 200, 'c3' => 3, 'c4' => 4, 'c5' => 5],
            ],
            'pk',
            $schema,
            $schema,
            null,
            null,
            'C1',
            null,
            'C2',
            null,
            $warnings,
            true,
        );

        $t->same(['modified', 'modified'], array_column($rows, 'diff_type'));
        $t->same(['pk', 'c1', 'c2', 'c3'], $dataColumns($rows[0]));
        $t->same(100, $rows[0]['to_c1']);
        $t->true(!array_key_exists('to_c4', $rows[0]));
        $t->same([], $warnings);
    },
    'skinny include-cols keeps requested unchanged columns and added columns' => static function (TestRunner $t) use ($dataColumns): void {
        $fromSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 0, 'type' => 'bigint', 'primaryKey' => true],
            ['name' => 'c1', 'tag' => 1, 'type' => 'bigint'],
            ['name' => 'c2', 'tag' => 2, 'type' => 'bigint'],
            ['name' => 'c3', 'tag' => 3, 'type' => 'bigint'],
            ['name' => 'c4', 'tag' => 4, 'type' => 'bigint'],
            ['name' => 'c5', 'tag' => 5, 'type' => 'bigint'],
        ]);
        $toSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 0, 'type' => 'bigint', 'primaryKey' => true],
            ['name' => 'c1', 'tag' => 1, 'type' => 'bigint'],
            ['name' => 'c2', 'tag' => 2, 'type' => 'bigint'],
            ['name' => 'c3', 'tag' => 3, 'type' => 'bigint'],
            ['name' => 'c4', 'tag' => 4, 'type' => 'bigint'],
            ['name' => 'c5', 'tag' => 5, 'type' => 'bigint'],
            ['name' => 'c6', 'tag' => 6, 'type' => 'bigint'],
        ]);

        $warnings = [];
        $rows = (new TableDiff())->diffTableRowsForSchemas(
            [['pk' => 0, 'c1' => 100, 'c2' => 2, 'c3' => 300, 'c4' => 4, 'c5' => 5]],
            [['pk' => 0, 'c1' => 100, 'c2' => 2, 'c3' => 300, 'c4' => 4, 'c5' => 5, 'c6' => 600]],
            'pk',
            $fromSchema,
            $toSchema,
            null,
            null,
            'C2',
            null,
            'C3',
            null,
            $warnings,
            true,
            ['c1', 'c2'],
        );

        $t->same(['pk', 'c1', 'c2', 'c6'], $dataColumns($rows[0]));
        $t->same(600, $rows[0]['to_c6']);
        $t->same(100, $rows[0]['from_c1']);
        $t->true(!array_key_exists('from_c6', $rows[0]));
        $t->true(!array_key_exists('to_c3', $rows[0]));
        $t->same([], $warnings);
    },
    'skinny schema-aware diff rows keep all columns when rows are deleted' => static function (TestRunner $t) use ($dataColumns): void {
        $schema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 0, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'val1', 'tag' => 1, 'type' => 'int'],
            ['name' => 'val2', 'tag' => 2, 'type' => 'int'],
        ]);

        $warnings = [];
        $rows = (new TableDiff())->diffTableRowsForSchemas(
            [
                ['pk' => 1, 'val1' => 1, 'val2' => 1],
                ['pk' => 2, 'val1' => 2, 'val2' => 2],
            ],
            [
                ['pk' => 2, 'val1' => 2, 'val2' => 2],
            ],
            'pk',
            $schema,
            $schema,
            null,
            null,
            'C3',
            null,
            'C4',
            null,
            $warnings,
            true,
        );

        $t->same(1, count($rows));
        $t->same(TableDiff::DIFF_REMOVED, $rows[0]['diff_type']);
        $t->same(['pk', 'val1', 'val2'], $dataColumns($rows[0]));
        $t->same(1, $rows[0]['from_val2']);
        $t->same([], $warnings);
    },
    'wordpress skinny diff fixture keeps reviewer-requested status column' => static function (TestRunner $t) use ($dataColumns): void {
        $fixture = require __DIR__ . '/../fixtures/wp-skinny-diff.php';
        $warnings = [];
        $rows = (new TableDiff())->diffTableRowsForSchemas(
            $fixture['fromRows'],
            $fixture['toRows'],
            'ID',
            $fixture['fromSchema'],
            $fixture['toSchema'],
            null,
            null,
            $fixture['fromCommit'],
            null,
            $fixture['toCommit'],
            null,
            $warnings,
            true,
            $fixture['includeColumns'],
        );

        $t->same(1, count($rows));
        $t->same($fixture['expectedDataColumns'], $dataColumns($rows[0]));
        $t->same('publish', $rows[0]['to_post_status']);
        $t->same('Liberated launch page', $rows[0]['to_post_title']);
        $t->true(!array_key_exists('to_guid', $rows[0]));
        $t->same([], $warnings);
    },
    'wordpress filtered diff fixture limits publish-impacting review rows' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-filtered-review-diff.php';
        $differ = new TableDiff();
        $rows = $differ->diffTableRows(
            $fixture['fromRows'],
            $fixture['toRows'],
            'ID',
            $fixture['columns'],
            $fixture['fromCommit'],
            null,
            $fixture['toCommit'],
            null,
        );

        $filtered = $differ->filterDiffTableRows($rows, $fixture['where'], $fixture['limit']);

        $t->same($fixture['expectedChangedIds'], array_map(
            static fn (array $row): int => (int) ($row['to_ID'] ?? $row['from_ID']),
            $filtered,
        ));
        $t->same('Published resource refresh', $filtered[0]['to_post_title']);
        $t->same(TableDiff::DIFF_MODIFIED, $filtered[0]['diff_type']);
    },
    'primary key set changes report dolt warning and stop non-fuzzy diffs' => static function (TestRunner $t): void {
        $fromSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
            ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
        ]);
        $toSchema = TableSchema::fromColumns([
            ['name' => 'pk', 'tag' => 1, 'type' => 'int'],
            ['name' => 'c1', 'tag' => 2, 'type' => 'int', 'primaryKey' => true],
        ]);

        $warnings = [];
        $rows = (new TableDiff())->diffTableRowsForSchemas(
            [['pk' => 1, 'c1' => 2]],
            [['pk' => 1, 'c1' => 2], ['pk' => 7, 'c1' => 8]],
            'pk',
            $fromSchema,
            $toSchema,
            null,
            null,
            'commit-1',
            null,
            'commit-4',
            null,
            $warnings,
        );

        $t->same([], $rows);
        $t->same(1, count($warnings));
        $t->same(TableSchema::WARNING_UNKNOWN, $warnings[0]['code']);
        $t->contains('cannot render full diff between commits commit-1 and commit-4', $warnings[0]['message']);
    },
    'wordpress plugin schema drift fixture projects through latest diff schema' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-plugin-schema-drift.php';
        $warnings = [];
        $rows = (new TableDiff())->diffTableRowsForSchemas(
            $fixture['fromRows'],
            $fixture['toRows'],
            'event_id',
            $fixture['fromSchema'],
            $fixture['toSchema'],
            $fixture['targetSchema'],
            $fixture['targetSchema'],
            $fixture['fromCommit'],
            null,
            $fixture['toCommit'],
            null,
            $warnings,
        );

        $t->same(1, count($rows));
        $t->same('3', $rows[0]['from_event_count']);
        $t->same(null, $rows[0]['to_event_count']);
        $t->same(TableDiff::DIFF_MODIFIED, $rows[0]['diff_type']);
        $t->same([], $warnings);
    },
];
