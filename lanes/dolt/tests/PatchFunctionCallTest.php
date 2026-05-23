<?php

declare(strict_types=1);

use PortLibs\Dolt\PatchFunctionArgument;
use PortLibs\Dolt\PatchFunctionCall;
use PortLibs\Dolt\TableSchema;

$patchTables = static function (): array {
    $mainSchema = TableSchema::fromColumns([
        ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ['name' => 'c1', 'tag' => 2, 'type' => 'varchar(20)'],
        ['name' => 'c2', 'tag' => 3, 'type' => 'varchar(20)'],
    ]);
    $branchSchema = TableSchema::fromColumns([
        ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ['name' => 'c1', 'tag' => 2, 'type' => 'varchar(20)'],
    ]);
    $newTableSchema = TableSchema::fromColumns([
        ['name' => 'pk', 'tag' => 10, 'type' => 'int', 'primaryKey' => true],
    ]);

    return [
        [
            'tableName' => 't',
            'fromSchema' => $mainSchema,
            'toSchema' => $branchSchema,
            'primaryKey' => 'pk',
            'fromRows' => [
                ['pk' => 1, 'c1' => 'one', 'c2' => 'two'],
                ['pk' => 2, 'c1' => 'two', 'c2' => 'three'],
            ],
            'toRows' => [
                ['pk' => 2, 'c1' => 'two'],
            ],
        ],
        [
            'tableName' => 'newtable',
            'fromSchema' => $newTableSchema,
            'toSchema' => null,
            'primaryKey' => 'pk',
            'fromRows' => [
                ['pk' => 1],
            ],
            'toRows' => [],
        ],
    ];
};

return [
    'dolt patch function call parses two-dot revisions and filters requested tables' => static function (TestRunner $t) use ($patchTables): void {
        $rows = (new PatchFunctionCall())->rows($patchTables(), ['main..branch1', 'T'], [
            'knownTables' => ['t', 'newtable', 'unchanged'],
        ]);

        $t->same(['main', 'branch1'], [$rows[0]['from_commit_hash'], $rows[0]['to_commit_hash']]);
        $t->same(['t', 't'], array_column($rows, 'table_name'));
        $t->same(['schema', 'data'], array_column($rows, 'diff_type'));
        $t->same('ALTER TABLE `t` DROP `c2`;', $rows[0]['statement']);
        $t->same('DELETE FROM `t` WHERE `pk`=1;', $rows[1]['statement']);

        $allRows = (new PatchFunctionCall())->rows($patchTables(), ['main', 'branch1'], [
            'knownTables' => ['t', 'newtable', 'unchanged'],
        ]);

        $t->same(['newtable', 't', 't'], array_column($allRows, 'table_name'));
        $t->same('DROP TABLE `newtable`;', $allRows[0]['statement']);
    },
    'dolt patch function call parses three-dot revisions from supplied merge-base context' => static function (TestRunner $t) use ($patchTables): void {
        $call = new PatchFunctionCall();
        $rows = $call->rows($patchTables(), ['main...branch1', 't'], [
            'knownTables' => ['t', 'newtable'],
            'mergeBases' => ['main...branch1' => 'main~2'],
        ]);

        $t->same(['main~2', 'branch1'], [$rows[0]['from_commit_hash'], $rows[0]['to_commit_hash']]);
        $t->same('ALTER TABLE `t` DROP `c2`;', $rows[0]['statement']);
        $t->throws(InvalidArgumentException::class, static fn () => $call->rows($patchTables(), ['main...branch1', 't']));
    },
    'dolt patch function call reports no-diff known tables and missing tables' => static function (TestRunner $t) use ($patchTables): void {
        $call = new PatchFunctionCall();

        $t->same([], $call->rows($patchTables(), ['main', 'branch1', 'unchanged'], [
            'knownTables' => ['t', 'newtable', 'unchanged'],
        ]));
        $t->throws(RuntimeException::class, static fn () => $call->rows($patchTables(), ['main', 'branch1', 'doesnotexist'], [
            'knownTables' => ['t', 'newtable', 'unchanged'],
        ]));
    },
    'dolt patch function call returns no rows for same working or staged refs' => static function (TestRunner $t) use ($patchTables): void {
        $call = new PatchFunctionCall();

        $t->same([], $call->rows($patchTables(), ['WORKING', 'WORKING', 't'], [
            'knownTables' => ['t', 'newtable'],
        ]));
        $t->same([], $call->rows($patchTables(), ['STAGED..STAGED', 't'], [
            'knownTables' => ['t', 'newtable'],
        ]));
    },
    'dolt patch function call rejects upstream invalid argument boundaries' => static function (TestRunner $t) use ($patchTables): void {
        $call = new PatchFunctionCall();

        $t->throws(InvalidArgumentException::class, static fn () => $call->rows($patchTables(), []));
        $t->throws(InvalidArgumentException::class, static fn () => $call->rows($patchTables(), ['HEAD']));
        $t->throws(InvalidArgumentException::class, static fn () => $call->rows($patchTables(), ['HEAD', 'WORKING', 't', 'extra']));
        $t->throws(InvalidArgumentException::class, static fn () => $call->rows($patchTables(), [123, 'WORKING']));

        try {
            $call->rows($patchTables(), [
                'HEAD',
                'WORKING',
                PatchFunctionArgument::expression("LOWER('T')"),
            ]);
            $t->true(false, 'Expected non-literal patch argument to throw.');
        } catch (InvalidArgumentException $exception) {
            $t->contains('only literal values supported', $exception->getMessage());
        }
    },
    'dolt patch function call enforces upstream select privilege boundaries' => static function (TestRunner $t) use ($patchTables): void {
        $call = new PatchFunctionCall();
        $options = [
            'databaseName' => 'mydb/b1',
            'knownTables' => ['t', 'newtable'],
            'databaseTables' => ['t', 'newtable'],
        ];

        try {
            $call->rows($patchTables(), ['main', 'branch1', 't'], $options + [
                'selectPrivileges' => [],
            ]);
            $t->true(false, 'Expected patch call without database privileges to throw.');
        } catch (RuntimeException $exception) {
            $t->contains('database access denied for user on database mydb', $exception->getMessage());
        }

        $authorizedRows = $call->rows($patchTables(), ['main', 'branch1', 't'], $options + [
            'selectPrivileges' => ['mydb.t'],
        ]);
        $t->same(['t', 't'], array_column($authorizedRows, 'table_name'));

        try {
            $call->rows($patchTables(), ['main', 'branch1', 'newtable'], $options + [
                'selectPrivileges' => ['mydb.t'],
            ]);
            $t->true(false, 'Expected patch call without table privileges to throw.');
        } catch (RuntimeException $exception) {
            $t->contains('privilege check failed: SELECT on mydb.newtable', $exception->getMessage());
        }

        try {
            $call->rows($patchTables(), ['main', 'branch1'], $options + [
                'selectPrivileges' => ['mydb.t'],
            ]);
            $t->true(false, 'Expected unscoped patch call without all-table privileges to throw.');
        } catch (RuntimeException $exception) {
            $t->contains('privilege check failed: SELECT on mydb.newtable', $exception->getMessage());
        }

        $allRows = $call->rows($patchTables(), ['main', 'branch1'], $options + [
            'selectPrivileges' => ['mydb.*'],
        ]);
        $t->same(['newtable', 't', 't'], array_column($allRows, 'table_name'));

        try {
            $call->rows($patchTables(), ['WORKING', 'WORKING', 't'], $options + [
                'selectPrivileges' => [],
            ]);
            $t->true(false, 'Expected same-ref no-op patch call to check privileges before returning empty rows.');
        } catch (RuntimeException $exception) {
            $t->contains('database access denied for user on database mydb', $exception->getMessage());
        }
    },
    'wordpress patch call boundary example maps table-function review behavior' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-patch-call-boundary.php';

        $t->true(count($output['postDataPatch']) >= 2);
        $t->same(['data', 'data'], array_slice(array_column($output['postDataPatch'], 'diff_type'), 0, 2));
        $t->same('review-base', $output['allPatchFromThreeDot'][0]['from_commit_hash']);
        $t->same([], $output['unchangedKnownTable']);
        $t->contains('table not found: wp_missing_queue', $output['missingTableError']);
        $t->contains('only literal values supported', $output['nonLiteralError']);
    },
    'wordpress patch privilege review example maps limited reviewer access' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-patch-privilege-review.php';

        $t->same(['wp_posts', 'wp_posts'], array_column($output['limitedReviewerRows'], 'table_name'));
        $t->same(['data', 'data'], array_column($output['limitedReviewerRows'], 'diff_type'));
        $t->contains('database access denied for user on database wp_review', $output['noPrivilegeError']);
        $t->contains('privilege check failed: SELECT on wp_review.wp_import_log', $output['allTablesDenied']);
        $t->true(count($output['databaseWideRows']) >= 5);
        $t->same('review-base', $output['databaseWideRows'][0]['from_commit_hash']);
    },
];
