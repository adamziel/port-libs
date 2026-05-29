<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_current_next70_output(int $passLines, int $assertions, int $failures = 0, int $selected = 1, int $summaryFiles = 1): string
{
    $lines = [
        sprintf('Focused test run: %d selected test files (root lock skipped)', $selected),
    ];
    for ($i = 1; $i <= $passLines; $i++) {
        $lines[] = sprintf('PASS current next70 release shard countability case %02d', $i);
    }
    $lines[] = sprintf('%d test files, %d assertions, %d failures', $summaryFiles, $assertions, $failures);

    return implode("\n", $lines);
}

/**
 * @return list<array<string, mixed>>
 */
function libsqlite_current_next70_rows(int $case = 1): array
{
    return [
        [
            'unit' => 'current-next70-release-shard-' . $case,
            'suite' => $case % 2 === 0 ? 'all' : 'release',
            'repository_head' => '103fc00c42f1ff0580cae8a7768e4a3da0979c2d',
            'current_countable' => false,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/release-countability-current-next70.md',
            'command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error ' . ($case % 2 === 0 ? 'all' : 'release') . ' select1.test where.test',
            'scripts' => ['select1.test', 'where.test', 'json101.test'],
            'current_tests' => 0,
            'next_tests' => 2000 + $case,
            'errors' => 0,
        ],
        [
            'unit' => 'accepted-suite-denominator-artifact',
            'suite' => 'release',
            'repository_head' => '103fc00c42f1ff0580cae8a7768e4a3da0979c2d',
            'current_countable' => true,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/suite-denominator-artifact-admission.md',
            'command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error release veryquick.test',
            'scripts' => ['veryquick.test'],
            'current_tests' => 329670,
            'next_tests' => 329670,
            'errors' => 0,
        ],
    ];
}

/**
 * @param array<int|string, array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function libsqlite_current_next70_record(array $rows, string $output, ?int $expected = null, string $evidenceHead = '103fc00c42f1ff0580cae8a7768e4a3da0979c2d', string $processSnapshot = ''): array
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json')
        ->releaseShardCountabilityCurrentNext70(
            $rows,
            26014,
            '103fc00c42f1ff0580cae8a7768e4a3da0979c2d',
            $evidenceHead,
            'lanes/libsqlite/tests/SQLiteReleaseCountabilityCurrentNext70Test.php',
            $output,
            'current-next70 release shard countability avoids accepted current-next64/65/68 suite-denominator admission, release parity ledgers, active-runner pgrep filtering, and ATTACH/JSON/LIKE/recursive SELECT/VFS/WAL batch68 implementation clusters.',
            $expected,
            $processSnapshot
        );
}

$tests = [
    'current next70 admits zero error release shard countability' => static function (TestRunner $t): void {
        $record = libsqlite_current_next70_record(libsqlite_current_next70_rows(), libsqlite_current_next70_output(70, 96), 70);

        $t->same('current-next70-release-shards-countable', $record['status']);
        $t->same(true, $record['countable']);
        $t->same(1, $record['release_shard_delta']);
        $t->same(70, $record['php_pass_delta']);
        $t->same(26084, $record['next_php_pass']);
        $t->same(false, $record['counts_release_parity']);
        $t->same(['current-next70-release-shard-1'], $record['advanced_units']);
        $t->same(['accepted-suite-denominator-artifact'], $record['preserved_units']);
        $t->contains('complete broad artifact', $record['next_gate']);
    },
    'current next70 preserves existing shards without new movement' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next70_rows();
        $rows[0]['current_countable'] = true;

        $record = libsqlite_current_next70_record($rows, libsqlite_current_next70_output(3, 12), 3);

        $t->same('current-next70-release-shards-preserved', $record['status']);
        $t->same(0, $record['release_shard_delta']);
        $t->same(2, $record['zero_error_artifact_count']);
        $t->same(3, $record['php_pass_delta']);
    },
    'current next70 aggregates release and all suite scripts' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next70_rows(2);
        $rows[] = [
            'unit' => 'current-next70-release-json-shard',
            'suite' => 'release',
            'repository_head' => '103fc00c42f1ff0580cae8a7768e4a3da0979c2d',
            'current_countable' => false,
            'next_countable' => true,
            'artifact_path' => 'lanes/libsqlite/notes/release-countability-current-next70.md',
            'command' => './testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error release json101.test',
            'scripts' => ['json101.test', 'json102.test'],
            'current_tests' => 0,
            'next_tests' => 45,
            'errors' => 0,
        ];

        $record = libsqlite_current_next70_record($rows, libsqlite_current_next70_output(5, 20), 5);

        $t->same('current-next70-release-shards-countable', $record['status']);
        $t->same(2, $record['release_shard_delta']);
        $t->same(['all' => 1, 'release' => 2], $record['suites']);
        $t->same(['json101.test', 'json102.test', 'select1.test', 'veryquick.test', 'where.test'], $record['target_scripts']);
        $t->same((2002 + 329670 + 45) - 329670, $record['tests_total_delta']);
    },
    'current next70 blocks stale artifact head' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next70_rows();
        $rows[0]['repository_head'] = '0000000000000000000000000000000000000000';

        $record = libsqlite_current_next70_record($rows, libsqlite_current_next70_output(2, 8), 2);

        $t->same('blocked', $record['status']);
        $t->same(['current-next70-release-shard-1'], $record['blocked_units']);
        $t->contains('artifact-head-mismatch', $record['blockers'][0]['evidence']);
    },
    'current next70 blocks runner artifact errors' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next70_rows();
        $rows[0]['errors'] = 1;

        $record = libsqlite_current_next70_record($rows, libsqlite_current_next70_output(2, 8), 2);

        $t->same('blocked', $record['status']);
        $t->contains('runner-artifact-has-errors', $record['blockers'][0]['evidence']);
    },
    'current next70 blocks unsupported suite tiers' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next70_rows();
        $rows[0]['suite'] = 'veryquick';

        $record = libsqlite_current_next70_record($rows, libsqlite_current_next70_output(2, 8), 2);

        $t->same('blocked', $record['status']);
        $t->contains('unsupported-suite-tier', $record['blockers'][0]['evidence']);
    },
    'current next70 blocks missing runner command and scripts' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next70_rows();
        $rows[0]['command'] = '';
        $rows[0]['scripts'] = ['README.md'];

        $record = libsqlite_current_next70_record($rows, libsqlite_current_next70_output(2, 8), 2);

        $t->same('blocked', $record['status']);
        $t->contains('missing-runner-command', $record['blockers'][0]['evidence']);
        $t->contains('countable-release-shard-missing-scripts', $record['blockers'][0]['evidence']);
    },
    'current next70 blocks release shard regressions' => static function (TestRunner $t): void {
        $rows = libsqlite_current_next70_rows();
        $rows[1]['next_countable'] = false;
        $rows[1]['next_tests'] = 10;

        $record = libsqlite_current_next70_record($rows, libsqlite_current_next70_output(2, 8), 2);

        $t->same('blocked', $record['status']);
        $t->same(['accepted-suite-denominator-artifact'], $record['regressed_units']);
        $t->contains('release-shard-countability-regressed', $record['blockers'][0]['evidence']);
    },
    'current next70 blocks active broad runner snapshots' => static function (TestRunner $t): void {
        $record = libsqlite_current_next70_record(
            libsqlite_current_next70_rows(),
            libsqlite_current_next70_output(2, 8),
            2,
            processSnapshot: '4242 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 4 release'
        );

        $t->same('blocked', $record['status']);
        $t->same('blocked-active-runner', $record['active_runner_status']);
        $t->true(in_array('duplicate-broad-runner-active', array_column($record['blockers'], 'id'), true), 'Expected duplicate runner blocker');
    },
    'current next70 blocks pass line inflation' => static function (TestRunner $t): void {
        $record = libsqlite_current_next70_record(libsqlite_current_next70_rows(), libsqlite_current_next70_output(4, 44), 44);

        $t->same('blocked', $record['status']);
        $t->same(4, $record['php_pass_admission']['pass_lines_observed']);
        $t->same(44, $record['php_pass_admission']['assertion_count_observed']);
        $t->true(in_array('focused-current-head-php-pass-blocked', array_column($record['blockers'], 'id'), true), 'Expected focused PASS blocker');
    },
    'current next70 rejects empty release shard rows' => static function (TestRunner $t): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_current_next70_record([], libsqlite_current_next70_output(1, 4), 1)
        );
    },
];

foreach (range(1, 70) as $case) {
    $tests[sprintf('current next70 exact release shard pass matrix %02d', $case)] = static function (TestRunner $t) use ($case): void {
        $record = libsqlite_current_next70_record(libsqlite_current_next70_rows($case), libsqlite_current_next70_output($case, $case + 24), $case);

        $t->same('current-next70-release-shards-countable', $record['status']);
        $t->same($case, $record['php_pass_delta']);
        $t->same(26014 + $case, $record['next_php_pass']);
        $t->same(1, $record['release_shard_delta']);
        $t->same(['current-next70-release-shard-' . $case], $record['advanced_units']);
        $t->same($case, $record['php_pass_admission']['pass_lines_observed']);
        $t->same(0, $record['blocker_count']);
    };
}

return $tests;
