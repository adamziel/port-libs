<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_hydration_evidence_next25(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_hydration_root_next25(string $label): string
{
    return sys_get_temp_dir() . '/libsqlite-release-hydration-next25-' . $label . '-' . bin2hex(random_bytes(4));
}

function libsqlite_release_hydration_cleanup_next25(string $root): void
{
    if (!is_dir($root)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $path) {
        if ($path->isDir()) {
            @rmdir($path->getPathname());
        } else {
            @chmod($path->getPathname(), 0666);
            @unlink($path->getPathname());
        }
    }
    @rmdir($root);
}

function libsqlite_release_hydration_audit_next25(
    string $label,
    string $head = 'accepted-head',
    string $testset = 'all',
    string $patterns = 'none',
    int $exit = 0,
    int $tests = 1250,
    int $errors = 0,
    string $log = 'runner.log',
    string $manifestUuid = '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353'
): string {
    return "# SQLite Tcl Bounded Runner Evidence - {$label}\n\n"
        . "- Repository HEAD: `{$head}`\n"
        . "- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`\n"
        . "- SQLite VERSION: `3.54.0`\n"
        . "- SQLite manifest UUID: `{$manifestUuid}`\n"
        . "- Scratch: `/tmp/libsqlite-next25`\n"
        . "- Log: `{$log}`\n"
        . "- Testset: `{$testset}`\n"
        . "- Patterns: `{$patterns}`\n"
        . "- Exit: `{$exit}`\n"
        . "- Elapsed seconds: `91`\n"
        . "- Parsed errors: `{$errors}`\n"
        . "- Parsed tests: `{$tests}`\n"
        . "- Runner time: `1m31s`\n"
        . "- Jobs: `2`\n"
        . "- Timeout seconds: `7200`\n";
}

/**
 * @return array{0:string,1:string,2:string}
 */
function libsqlite_release_hydration_write_next25(string $label, string $audit, string $stdout = ''): array
{
    $root = libsqlite_release_hydration_root_next25($label);
    mkdir($root, 0777, true);
    $auditPath = $root . '/' . $label . '.md';
    $logPath = $root . '/runner.log';
    file_put_contents($auditPath, $audit);
    file_put_contents($logPath, $stdout === '' ? "0 errors out of 1250 tests\n" : $stdout);

    return [$root, $auditPath, $logPath];
}

$statusCases = [
    'countable',
    'stale',
    'wrong-manifest',
    'failed',
    'timeout',
    'missing-log',
    'release-like',
    'focused',
    'mixed',
    'active',
    'audit-extension',
    'ignored-file',
    'relative-log',
    'basename-log',
    'heading-label',
    'fallback-label',
    'accepted-head',
    'sqlite-version',
    'sqlite-commit',
    'test-totals',
    'error-totals',
    'blocked-labels',
    'countable-labels',
    'missing-directory',
    'empty-directory',
    'directory-metadata',
    'stdout-count',
    'audit-count',
    'next-gate-countable',
    'next-gate-blocked',
    'dependency-closure',
    'release-patterns',
    'focused-patterns',
    'stale-blocker-id',
    'failed-blocker-id',
    'manifest-blocker-id',
    'active-blocker-id',
    'sort-order',
    'artifact-rows',
    'set-present',
    'set-counts',
    'stdout-null',
    'non-md-ignore',
    'current-head-provenance',
    'parity-not-counted',
];

$tests = [
    'current next25 blocks missing artifact directories before countability' => static function (TestRunner $t): void {
        $root = libsqlite_release_hydration_root_next25('missing');
        $record = libsqlite_release_hydration_evidence_next25()->boundedRunnerArtifactDirectoryHydration($root, 'accepted-head');

        $t->same('blocked-missing-artifact-directory', $record['status']);
        $t->same(0, $record['artifact_count']);
        $t->same([$root], $record['missing']);
    },
    'current next25 blocks empty artifact directories explicitly' => static function (TestRunner $t): void {
        $root = libsqlite_release_hydration_root_next25('empty');
        mkdir($root, 0777, true);
        try {
            $record = libsqlite_release_hydration_evidence_next25()->boundedRunnerArtifactDirectoryHydration($root, 'accepted-head');

            $t->same('blocked-empty-artifact-directory', $record['status']);
            $t->same(['bounded-runner audit artifacts (*.md or *.audit)'], $record['missing']);
        } finally {
            libsqlite_release_hydration_cleanup_next25($root);
        }
    },
];

foreach ($statusCases as $case) {
    $tests['current next25 hydrates release runner artifact directory case ' . $case] = static function (TestRunner $t) use ($case): void {
        $head = in_array($case, ['stale', 'stale-blocker-id'], true) ? 'stale-head' : 'accepted-head';
        $manifest = in_array($case, ['wrong-manifest', 'manifest-blocker-id'], true) ? 'wrong-manifest' : '9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353';
        $exit = in_array($case, ['failed', 'failed-blocker-id'], true) ? 1 : ($case === 'timeout' ? 124 : 0);
        $errors = in_array($case, ['failed', 'failed-blocker-id', 'error-totals'], true) ? 1 : 0;
        $testsTotal = $case === 'test-totals' ? 333 : ($case === 'timeout' ? 0 : 1250);
        $testset = in_array($case, ['focused', 'focused-patterns'], true) ? 'veryquick' : ($case === 'release-patterns' ? 'release' : 'all');
        $patterns = in_array($case, ['focused', 'focused-patterns', 'release-patterns'], true) ? 'json101.test, wal.test' : 'none';
        [$root] = libsqlite_release_hydration_write_next25(
            $case,
            libsqlite_release_hydration_audit_next25($case, $head, $testset, $patterns, $exit, $testsTotal, $errors, 'runner.log', $manifest),
            $errors === 0 ? "0 errors out of {$testsTotal} tests\n" : "1 errors out of {$testsTotal} tests\n"
        );

        if ($case === 'audit-extension') {
            rename($root . '/' . $case . '.md', $root . '/' . $case . '.audit');
        }
        if ($case === 'fallback-label') {
            $auditPath = $root . '/' . $case . '.md';
            file_put_contents($auditPath, preg_replace('/^# SQLite Tcl Bounded Runner Evidence - .+\n\n/', "# Runner Artifact\n\n", (string) file_get_contents($auditPath)));
        }
        if ($case === 'relative-log') {
            rename($root . '/runner.log', $root . '/relative-runner.log');
            $auditPath = $root . '/' . $case . '.md';
            file_put_contents($auditPath, str_replace('`runner.log`', '`relative-runner.log`', (string) file_get_contents($auditPath)));
        }
        if ($case === 'basename-log') {
            rename($root . '/runner.log', $root . '/basename-runner.log');
            $auditPath = $root . '/' . $case . '.md';
            file_put_contents($auditPath, str_replace('`runner.log`', '`/tmp/not-shared/basename-runner.log`', (string) file_get_contents($auditPath)));
        }
        if ($case === 'ignored-file' || $case === 'non-md-ignore') {
            file_put_contents($root . '/ignored.txt', libsqlite_release_hydration_audit_next25('ignored'));
        }
        if ($case === 'missing-log' || $case === 'stdout-null') {
            @unlink($root . '/runner.log');
        }

        try {
            $snapshot = in_array($case, ['active', 'active-blocker-id'], true)
                ? "577297 577296 S 02:14 9.1 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release\n"
                : '';
            $record = libsqlite_release_hydration_evidence_next25()->boundedRunnerArtifactDirectoryHydration($root, 'accepted-head', $snapshot);
            $set = $record['set'];
            $entry = $set['entries'][0];
            $blockers = $entry['blocker_ids'];

            $t->same($root, $record['artifact_directory']);
            $t->same('accepted-head', $record['accepted_repository_head']);
            $t->same(1, $record['artifact_count']);
            $t->same(1, $record['audit_file_count']);
            $t->same(in_array($case, ['missing-log', 'stdout-null'], true) ? 0 : 1, $record['stdout_file_count']);
            $t->same($case, $record['artifacts'][0]['label']);
            $t->same(!in_array($case, ['missing-log', 'stdout-null'], true), $record['artifacts'][0]['stdout_ready']);
            $t->true(is_array($set), 'Expected hydrated artifact set');
            $t->same(false, $set['entries'][0]['gate']['artifact']['results']['errors'] !== 0 && $errors === 0);

            if (in_array($case, ['stale', 'stale-blocker-id'], true)) {
                $t->true(in_array('repository-head-mismatch', $blockers, true), 'Expected stale head blocker');
                $t->same('blocked', $record['status']);
                return;
            }
            if (in_array($case, ['wrong-manifest', 'manifest-blocker-id'], true)) {
                $t->true(in_array('sqlite-manifest-uuid-mismatch', $blockers, true), 'Expected manifest blocker');
                $t->same('blocked', $record['status']);
                return;
            }
            if (in_array($case, ['failed', 'failed-blocker-id'], true)) {
                $t->true(in_array('artifact-not-passed', $blockers, true), 'Expected failed artifact blocker');
                $t->same(1, $record['blocked_count']);
                return;
            }
            if ($case === 'error-totals' || $case === 'timeout') {
                $t->true(in_array('artifact-not-passed', $blockers, true), 'Expected non-zero error or timeout artifact blocker');
                $t->same('blocked', $record['status']);
                return;
            }
            if (in_array($case, ['active', 'active-blocker-id'], true)) {
                $t->true(in_array('active-runner-still-running', $blockers, true), 'Expected active runner blocker');
                $t->same(1, $record['blocked_count']);
                return;
            }

            $t->same('countable', $record['status']);
            $t->same(1, $record['countable_count']);
            $t->same(0, $record['blocked_count']);
            $t->same($testsTotal, $set['tests_total']);
            $t->same(0, $set['errors_total']);
            $t->contains('no new support component needed', $record['dependency_closure']);
            $t->contains('publish only countable hydrated bounded-runner artifacts', $record['next_gate']);
        } finally {
            libsqlite_release_hydration_cleanup_next25($root);
        }
    };
}

$tests['current next25 hydrates mixed countable and blocked artifact directories'] = static function (TestRunner $t): void {
    $root = libsqlite_release_hydration_root_next25('mixed-set');
    mkdir($root, 0777, true);
    file_put_contents($root . '/a.md', libsqlite_release_hydration_audit_next25('a', 'accepted-head', 'all', 'none', 0, 10, 0, 'a.log'));
    file_put_contents($root . '/a.log', "0 errors out of 10 tests\n");
    file_put_contents($root . '/b.md', libsqlite_release_hydration_audit_next25('b', 'stale-head', 'all', 'none', 0, 20, 0, 'b.log'));
    file_put_contents($root . '/b.log', "0 errors out of 20 tests\n");

    try {
        $record = libsqlite_release_hydration_evidence_next25()->boundedRunnerArtifactDirectoryHydration($root, 'accepted-head');

        $t->same('partially-countable', $record['status']);
        $t->same(2, $record['artifact_count']);
        $t->same(1, $record['countable_count']);
        $t->same(1, $record['blocked_count']);
        $t->same(['a'], $record['set']['countable_labels']);
        $t->same(['b'], $record['set']['blocked_labels']);
    } finally {
        libsqlite_release_hydration_cleanup_next25($root);
    }
};

return $tests;
