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

$patchRevisionGraph = static function (): array {
    return [
        ['commit_hash' => 'base0000000000000000000000000000', 'parents' => []],
        [
            'commit_hash' => 'main0000000000000000000000000000',
            'parents' => ['base0000000000000000000000000000'],
            'refs' => ['refs/heads/main', 'refs/tags/tag_main'],
        ],
        [
            'commit_hash' => 'branch00000000000000000000000000',
            'parents' => ['main0000000000000000000000000000'],
            'refs' => ['refs/heads/branch1', 'refs/tags/tag_branch1'],
        ],
    ];
};

$patchWorktreeSnapshots = static function (): array {
    $headSchema = TableSchema::fromColumns([
        ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ['name' => 'c1', 'tag' => 2, 'type' => 'int'],
        ['name' => 'c2', 'tag' => 3, 'type' => 'int'],
        ['name' => 'c3', 'tag' => 4, 'type' => 'int'],
        ['name' => 'c4', 'tag' => 5, 'type' => 'int'],
        ['name' => 'c5', 'tag' => 6, 'type' => 'int'],
    ]);
    $workingSchema = TableSchema::fromColumns([
        ['name' => 'pk', 'tag' => 1, 'type' => 'int', 'primaryKey' => true],
        ['name' => 'c0', 'tag' => 2, 'type' => 'int'],
        ['name' => 'c2', 'tag' => 3, 'type' => 'int'],
        ['name' => 'c3', 'tag' => 4, 'type' => 'int'],
        ['name' => 'c5', 'tag' => 6, 'type' => 'int'],
        ['name' => 'c6', 'tag' => 7, 'type' => 'bigint'],
    ]);

    return [
        'revisionGraph' => [
            ['commit_hash' => 'head000000000000000000000000000', 'parents' => [], 'refs' => ['refs/heads/main']],
        ],
        'headHash' => 'head000000000000000000000000000',
        'revisionSnapshots' => [
            'head000000000000000000000000000' => [
                [
                    'name' => 't',
                    'schema' => $headSchema,
                    'rows' => [
                        ['pk' => 0, 'c1' => 1, 'c2' => 2, 'c3' => 3, 'c4' => 4, 'c5' => 5],
                    ],
                ],
            ],
            'STAGED' => [
                [
                    'name' => 't',
                    'schema' => $headSchema,
                    'rows' => [
                        ['pk' => 0, 'c1' => 1, 'c2' => 2, 'c3' => 3, 'c4' => 4, 'c5' => 5],
                        ['pk' => 1, 'c1' => 1, 'c2' => 2, 'c3' => 3, 'c4' => 4, 'c5' => 5],
                    ],
                ],
            ],
            'WORKING' => [
                [
                    'name' => 't',
                    'schema' => $workingSchema,
                    'rows' => [
                        ['pk' => 0, 'c0' => 1, 'c2' => 2, 'c3' => 3, 'c5' => 5, 'c6' => null],
                        ['pk' => 1, 'c0' => 1, 'c2' => 2, 'c3' => 3, 'c5' => 5, 'c6' => null],
                    ],
                ],
            ],
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
    'dolt patch function call resolves branch tag and working refs when commit graph is supplied' => static function (TestRunner $t) use ($patchTables, $patchRevisionGraph): void {
        $call = new PatchFunctionCall();
        $options = [
            'knownTables' => ['t', 'newtable'],
            'revisionGraph' => $patchRevisionGraph(),
            'headHash' => 'main0000000000000000000000000000',
        ];

        $rows = $call->rows($patchTables(), ['tag_main', 'branch1', 't'], $options);
        $t->same(['main0000000000000000000000000000', 'branch00000000000000000000000000'], [
            $rows[0]['from_commit_hash'],
            $rows[0]['to_commit_hash'],
        ]);
        $t->same('ALTER TABLE `t` DROP `c2`;', $rows[0]['statement']);

        $sameResolvedRef = $call->rows($patchTables(), ['tag_main', 'main', 't'], $options);
        $t->same([], $sameResolvedRef);

        $workingRows = $call->rows($patchTables(), ['main', 'WORKING', 't'], $options);
        $t->same(['main0000000000000000000000000000', 'WORKING'], [
            $workingRows[0]['from_commit_hash'],
            $workingRows[0]['to_commit_hash'],
        ]);

        $threeDotRows = $call->rows($patchTables(), ['main...branch1', 't'], $options + [
            'mergeBases' => ['main...branch1' => 'tag_main'],
        ]);
        $t->same(['main0000000000000000000000000000', 'branch00000000000000000000000000'], [
            $threeDotRows[0]['from_commit_hash'],
            $threeDotRows[0]['to_commit_hash'],
        ]);
    },
    'dolt patch function call reports upstream-shaped revision resolution errors' => static function (TestRunner $t) use ($patchTables, $patchRevisionGraph): void {
        $call = new PatchFunctionCall();
        $options = [
            'knownTables' => ['t', 'newtable'],
            'revisionGraph' => $patchRevisionGraph(),
            'headHash' => 'main0000000000000000000000000000',
        ];

        try {
            $call->rows($patchTables(), ['tag_main', 'missing-branch', 't'], $options);
            $t->true(false, 'Expected missing branch to throw.');
        } catch (RuntimeException $exception) {
            $t->contains('branch not found: missing-branch', $exception->getMessage());
        }

        try {
            $call->rows($patchTables(), ['fakefakefakefakefakefakefakefake', 'branch1', 't'], $options);
            $t->true(false, 'Expected missing commit hash to throw.');
        } catch (RuntimeException $exception) {
            $t->contains('target commit not found', $exception->getMessage());
        }
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
    'dolt patch function call materializes distinct head staged and working snapshots' => static function (TestRunner $t) use ($patchWorktreeSnapshots): void {
        $call = new PatchFunctionCall();
        $options = $patchWorktreeSnapshots() + ['knownTables' => ['t']];

        $staged = $call->rows([], ['HEAD', 'STAGED', 't'], $options);
        $t->same(['head000000000000000000000000000', 'STAGED'], [
            $staged[0]['from_commit_hash'],
            $staged[0]['to_commit_hash'],
        ]);
        $t->same(['data'], array_values(array_unique(array_column($staged, 'diff_type'))));
        $t->same("INSERT INTO `t` (`pk`,`c1`,`c2`,`c3`,`c4`,`c5`) VALUES (1,1,2,3,4,5);", $staged[0]['statement']);

        $worktree = $call->rows([], ['STAGED', 'WORKING', 't'], $options);
        $t->same(['STAGED', 'WORKING'], [$worktree[0]['from_commit_hash'], $worktree[0]['to_commit_hash']]);
        $t->same([
            'ALTER TABLE `t` RENAME COLUMN `c1` TO `c0`;',
            'ALTER TABLE `t` DROP `c4`;',
            'ALTER TABLE `t` ADD `c6` bigint;',
            'UPDATE `t` SET `c0`=1 WHERE `pk`=0;',
            'UPDATE `t` SET `c0`=1 WHERE `pk`=1;',
        ], array_column($worktree, 'statement'));

        $reverse = $call->rows([], ['WORKING', 'STAGED', 't'], $options);
        $t->same([
            'ALTER TABLE `t` RENAME COLUMN `c0` TO `c1`;',
            'ALTER TABLE `t` DROP `c6`;',
            'ALTER TABLE `t` ADD `c4` int;',
            'UPDATE `t` SET `c1`=1,`c4`=4 WHERE `pk`=0;',
            'UPDATE `t` SET `c1`=1,`c4`=4 WHERE `pk`=1;',
        ], array_column($reverse, 'statement'));

        $t->same([], $call->rows([], ['WORKING..WORKING', 't'], $options));
        $t->same([], $call->rows([], ['HEAD', 'STAGED', 'unchanged'], array_replace($options, [
            'knownTables' => ['t', 'unchanged'],
        ])));
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
        $t->same('review-head-hash', $output['resolvedBranchPatch'][0]['from_commit_hash']);
        $t->same('review-working-hash', $output['resolvedBranchPatch'][0]['to_commit_hash']);
        $t->same('WORKING', $output['resolvedWorkingPatch'][0]['to_commit_hash']);
        $t->same([], $output['unchangedKnownTable']);
        $t->contains('table not found: wp_missing_queue', $output['missingTableError']);
        $t->contains('branch not found: missing-review-branch', $output['missingBranchError']);
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
    'wordpress patch worktree review example compares head staged and working snapshots' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-patch-worktree-review.php';

        $t->same('STAGED', $output['stagedPostPatch'][0]['to_commit_hash']);
        $t->contains('INSERT INTO `wp_posts`', $output['stagedPostPatch'][1]['statement']);
        $t->same(['schema', 'schema', 'schema', 'data', 'data', 'data'], array_column($output['worktreePostPatch'], 'diff_type'));
        $t->contains('RENAME COLUMN `post_status` TO `post_state`', $output['worktreePostPatch'][0]['statement']);
        $t->contains('DROP `legacy_checksum`', $output['worktreePostPatch'][1]['statement']);
        $t->contains('ADD `import_batch`', $output['worktreePostPatch'][2]['statement']);
        $t->contains('INSERT INTO `wp_posts`', $output['worktreePostPatch'][5]['statement']);
        $t->same([], $output['sameWorkingPatch']);
    },
];
