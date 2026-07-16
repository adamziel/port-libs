<?php

declare(strict_types=1);

use PortLibs\Dolt\PatchFunctionArgument;
use PortLibs\Dolt\PatchFunctionCall;
use PortLibs\Dolt\PatchRenderer;
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
    'dolt patch function call collects staged worktree primary key warnings' => static function (TestRunner $t): void {
        $headSchema = TableSchema::fromColumns([
            ['name' => 'meta_id', 'tag' => 1, 'type' => 'bigint', 'primaryKey' => true],
            ['name' => 'post_id', 'tag' => 2, 'type' => 'bigint'],
            ['name' => 'meta_key', 'tag' => 3, 'type' => 'varchar(255)'],
            ['name' => 'meta_value', 'tag' => 4, 'type' => 'longtext'],
        ]);
        $workingSchema = TableSchema::fromColumns([
            ['name' => 'meta_id', 'tag' => 1, 'type' => 'bigint'],
            ['name' => 'post_id', 'tag' => 2, 'type' => 'bigint', 'primaryKey' => true],
            ['name' => 'meta_key', 'tag' => 3, 'type' => 'varchar(255)', 'primaryKey' => true],
            ['name' => 'meta_value', 'tag' => 4, 'type' => 'longtext'],
        ]);
        $options = [
            'revisionSnapshots' => [
                'STAGED' => [
                    [
                        'name' => 'wp_postmeta',
                        'schema' => $headSchema,
                        'rows' => [
                            ['meta_id' => 1, 'post_id' => 501, 'meta_key' => '_thumbnail_id', 'meta_value' => '7001'],
                        ],
                    ],
                ],
                'WORKING' => [
                    [
                        'name' => 'wp_postmeta',
                        'schema' => $workingSchema,
                        'rows' => [
                            ['meta_id' => 1, 'post_id' => 501, 'meta_key' => '_thumbnail_id', 'meta_value' => '7002'],
                        ],
                    ],
                ],
            ],
            'knownTables' => ['wp_postmeta'],
        ];
        $warnings = [];

        $rows = (new PatchFunctionCall())->rows([], ['STAGED', 'WORKING', 'wp_postmeta'], $options, $warnings);

        $t->same(['schema', 'schema'], array_column($rows, 'diff_type'));
        $t->contains('DROP PRIMARY KEY', $rows[0]['statement']);
        $t->contains('ADD PRIMARY KEY', $rows[1]['statement']);
        $t->same(1, count($warnings));
        $t->same(PatchRenderer::PRIMARY_KEY_CHANGE_WARNING_CODE, $warnings[0]['code']);
        $t->same("Primary key sets differ between revisions for table 'wp_postmeta', skipping data diff", $warnings[0]['message']);
    },
    'dolt patch function call materializes staged index and foreign key snapshot deltas' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-patch-foreign-key-review.php';
        $warnings = [];

        $rows = (new PatchFunctionCall())->rows([], $fixture['arguments'], $fixture['options'], $warnings);

        $t->same([
            'ALTER TABLE `wp_postmeta` ADD INDEX `fk_post_id`(`post_id`);',
            'ALTER TABLE `wp_postmeta` ADD CONSTRAINT `fk_post_id` FOREIGN KEY (`post_id`) REFERENCES `wp_posts` (`ID`);',
            'ALTER TABLE `wp_posts` DROP PRIMARY KEY;',
            'ALTER TABLE `wp_posts` ADD PRIMARY KEY (ID,import_site_id);',
        ], array_column($rows, 'statement'));
        $t->same(['schema', 'schema', 'schema', 'schema'], array_column($rows, 'diff_type'));
        $t->same(1, count($warnings));
        $t->same(PatchRenderer::PRIMARY_KEY_CHANGE_WARNING_CODE, $warnings[0]['code']);
        $t->contains('wp_posts', $warnings[0]['message']);
    },
    'dolt patch function call materializes modified and dropped foreign key snapshot deltas' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-patch-foreign-key-maintenance.php';
        $warnings = [];

        $rows = (new PatchFunctionCall())->rows([], $fixture['arguments'], $fixture['options'], $warnings);

        $t->same($fixture['expectedStatements'], array_column($rows, 'statement'));
        $t->same(['schema', 'schema', 'schema', 'schema', 'schema', 'schema'], array_column($rows, 'diff_type'));
        $t->same([1, 2, 3, 4, 5, 6], array_column($rows, 'statement_order'));
        $t->same([], $warnings);
    },
    'dolt patch function call materializes create-table check constraints' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-patch-check-constraint-review.php';
        $warnings = [];

        $rows = (new PatchFunctionCall())->rows([], $fixture['arguments'], $fixture['options'], $warnings);

        $t->same([
            "CREATE TABLE `wp_import_audit` (\n"
            . "  `id` bigint NOT NULL,\n"
            . "  `status` varchar(20),\n"
            . "  `note` text,\n"
            . "  PRIMARY KEY (`id`),\n"
            . "  CONSTRAINT `wp_import_audit_chk_status` CHECK ((`status` in ('queued','ready','failed')))\n"
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_bin;',
        ], array_column($rows, 'statement'));
        $t->same(['schema'], array_column($rows, 'diff_type'));
        $t->same([], $warnings);
    },
    'dolt patch function call omits existing-table check constraint maintenance rows' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-patch-check-constraint-maintenance.php';
        $warnings = [];

        $rows = (new PatchFunctionCall())->rows([], $fixture['arguments'], $fixture['options'], $warnings);

        $t->same($fixture['expectedCheckDiffTypes'], array_column(
            TableSchema::diffChecks($fixture['baseSchema'], $fixture['workingSchema']),
            'diff_type'
        ));
        $t->same($fixture['expectedStatements'], array_column($rows, 'statement'));
        $t->same([], $rows);
        $t->same([], $warnings);
    },
    'dolt patch function call materializes table collation snapshot deltas' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-patch-collation-review.php';
        $warnings = [];

        $rows = (new PatchFunctionCall())->rows([], $fixture['arguments'], $fixture['options'], $warnings);

        $t->same([
            "ALTER TABLE `wp_options` COLLATE='utf8mb4_0900_bin';",
            "UPDATE `wp_options` SET `option_value`='https://review.example.test' WHERE `option_id`=1;",
        ], array_column($rows, 'statement'));
        $t->same(['schema', 'data'], array_column($rows, 'diff_type'));
        $t->same([], $warnings);
    },
    'dolt patch function call materializes target row size snapshot deltas' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-patch-target-row-size-review.php';
        $warnings = [];

        $rows = (new PatchFunctionCall())->rows([], $fixture['arguments'], $fixture['options'], $warnings);

        $t->same([
            'ALTER TABLE `wp_postmeta` TARGET_ROW_SIZE=4096;',
            'UPDATE `wp_postmeta` SET `meta_value`=\'{"widgets":["legacy","reviewed"],"layout":"wide"}\' WHERE `meta_id`=7001;',
        ], array_column($rows, 'statement'));
        $t->same(['schema', 'data'], array_column($rows, 'diff_type'));
        $t->same([], $warnings);
    },
    'dolt patch function call materializes default generated and on update column snapshot deltas' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-patch-generated-default-review.php';
        $warnings = [];

        $rows = (new PatchFunctionCall())->rows([], $fixture['arguments'], $fixture['options'], $warnings);

        $t->same($fixture['expectedStatements'], array_column($rows, 'statement'));
        $t->same(['schema', 'schema', 'schema', 'data'], array_column($rows, 'diff_type'));
        $t->same([1, 2, 3, 4], array_column($rows, 'statement_order'));
        $t->same([], $warnings);
    },
    'dolt patch function call materializes auto increment wp_posts creation' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-patch-autoincrement-review.php';
        $warnings = [];

        $rows = (new PatchFunctionCall())->rows([], $fixture['arguments'], $fixture['options'], $warnings);

        $t->same($fixture['expectedStatements'], array_column($rows, 'statement'));
        $t->same($fixture['expectedDiffTypes'], array_column($rows, 'diff_type'));
        $t->same([1, 2, 3], array_column($rows, 'statement_order'));
        $t->same([], $warnings);
    },
    'dolt patch function call omits metadata-only column snapshot deltas' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-patch-metadata-only-column-review.php';
        $warnings = [];

        $rows = (new PatchFunctionCall())->rows([], $fixture['arguments'], $fixture['options'], $warnings);

        $t->same($fixture['expectedStatements'], array_column($rows, 'statement'));
        $t->same([], $rows);
        $t->same([], $warnings);
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
    'wordpress patch primary key warning example skips unsafe postmeta data patch' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-patch-primary-key-warning.php';

        $t->same(['schema', 'schema'], array_column($output['rows'], 'diff_type'));
        $t->contains('DROP PRIMARY KEY', $output['statements'][0]);
        $t->contains('ADD PRIMARY KEY', $output['statements'][1]);
        $t->same(1, count($output['warnings']));
        $t->same(PatchRenderer::PRIMARY_KEY_CHANGE_WARNING_CODE, $output['warnings'][0]['code']);
        $t->contains('wp_postmeta', $output['warnings'][0]['message']);
    },
    'wordpress patch foreign key review example orders staged ddl safely' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-patch-foreign-key-review.php';

        $t->same([
            'ALTER TABLE `wp_postmeta` ADD INDEX `fk_post_id`(`post_id`);',
            'ALTER TABLE `wp_postmeta` ADD CONSTRAINT `fk_post_id` FOREIGN KEY (`post_id`) REFERENCES `wp_posts` (`ID`);',
            'ALTER TABLE `wp_posts` DROP PRIMARY KEY;',
            'ALTER TABLE `wp_posts` ADD PRIMARY KEY (ID,import_site_id);',
        ], $output['statements']);
        $t->same(1, count($output['warnings']));
        $t->contains('wp_posts', $output['warnings'][0]['message']);
    },
    'wordpress patch foreign key maintenance example exposes modified and dropped ddl' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-patch-foreign-key-maintenance.php';
        $output = require __DIR__ . '/../examples/wordpress-patch-foreign-key-maintenance.php';

        $t->same($fixture['expectedStatements'], $output['statements']);
        $t->same(['schema', 'schema', 'schema', 'schema', 'schema', 'schema'], array_column($output['rows'], 'diff_type'));
        $t->same([], $output['warnings']);
        $t->contains('ADD CONSTRAINT `fk_import_post`', $output['statements'][4]);
    },
    'wordpress patch check constraint review example exposes import status guard' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-patch-check-constraint-review.php';

        $t->same(['schema'], array_column($output['rows'], 'diff_type'));
        $t->contains('CONSTRAINT `wp_import_audit_chk_status` CHECK', $output['statements'][0]);
        $t->same([], $output['warnings']);
    },
    'wordpress patch check constraint maintenance example stays empty like upstream' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-patch-check-constraint-maintenance.php';
        $output = require __DIR__ . '/../examples/wordpress-patch-check-constraint-maintenance.php';

        $t->same($fixture['expectedCheckDiffTypes'], $output['checkDiffTypes']);
        $t->same($fixture['expectedStatements'], $output['statements']);
        $t->same([], $output['rows']);
        $t->same([], $output['warnings']);
    },
    'wordpress patch collation review example exposes option comparison drift' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-patch-collation-review.php';

        $t->same(['schema', 'data'], array_column($output['rows'], 'diff_type'));
        $t->same("ALTER TABLE `wp_options` COLLATE='utf8mb4_0900_bin';", $output['statements'][0]);
        $t->contains("UPDATE `wp_options` SET `option_value`='https://review.example.test'", $output['statements'][1]);
        $t->same([], $output['warnings']);
    },
    'wordpress patch target row size review example exposes large meta storage drift' => static function (TestRunner $t): void {
        $output = require __DIR__ . '/../examples/wordpress-patch-target-row-size-review.php';

        $t->same(['schema', 'data'], array_column($output['rows'], 'diff_type'));
        $t->same('ALTER TABLE `wp_postmeta` TARGET_ROW_SIZE=4096;', $output['statements'][0]);
        $t->contains('UPDATE `wp_postmeta` SET `meta_value`=', $output['statements'][1]);
        $t->same([], $output['warnings']);
    },
    'wordpress patch generated default review example exposes import queue ddl' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-patch-generated-default-review.php';
        $output = require __DIR__ . '/../examples/wordpress-patch-generated-default-review.php';

        $t->same($fixture['expectedStatements'], $output['statements']);
        $t->same(['schema', 'schema', 'schema', 'data'], array_column($output['rows'], 'diff_type'));
        $t->contains('GENERATED ALWAYS AS', $output['statements'][1]);
        $t->contains("DEFAULT 'draft'", $output['statements'][2]);
        $t->same([], $output['warnings']);
    },
    'wordpress patch auto increment review example exposes imported post ids' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-patch-autoincrement-review.php';
        $output = require __DIR__ . '/../examples/wordpress-patch-autoincrement-review.php';

        $t->same($fixture['expectedStatements'], $output['statements']);
        $t->same($fixture['expectedDiffTypes'], array_column($output['rows'], 'diff_type'));
        $t->contains('AUTO_INCREMENT', $output['statements'][0]);
        $t->contains('Imported launch', $output['statements'][1]);
        $t->same([], $output['warnings']);
    },
    'wordpress patch metadata-only column review example stays empty like upstream' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-patch-metadata-only-column-review.php';
        $output = require __DIR__ . '/../examples/wordpress-patch-metadata-only-column-review.php';

        $t->same($fixture['expectedStatements'], $output['statements']);
        $t->same([], $output['rows']);
        $t->same([], $output['warnings']);
    },
];
