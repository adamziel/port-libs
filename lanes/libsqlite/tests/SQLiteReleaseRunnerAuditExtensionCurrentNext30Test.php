<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_audit_ext_next30_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_audit_ext_next30_root(string $label): string
{
    return sys_get_temp_dir() . '/libsqlite-release-audit-ext-next30-' . $label . '-' . bin2hex(random_bytes(4));
}

function libsqlite_release_audit_ext_next30_cleanup(string $root): void
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

function libsqlite_release_audit_ext_next30_audit(
    string $label,
    string $head = 'accepted-head',
    string $testset = 'release',
    string $patterns = 'none',
    int $exit = 0,
    int $tests = 22000,
    int $errors = 0,
    string $log = ''
): string {
    $logLine = $log === '' ? '' : "- Log: `{$log}`\n";

    return "# SQLite Tcl Bounded Runner Evidence - {$label}\n\n"
        . "- Repository HEAD: `{$head}`\n"
        . "- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`\n"
        . "- SQLite VERSION: `3.54.0`\n"
        . "- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`\n"
        . $logLine
        . "- Testset: `{$testset}`\n"
        . "- Patterns: `{$patterns}`\n"
        . "- Exit: `{$exit}`\n"
        . "- Parsed errors: `{$errors}`\n"
        . "- Parsed tests: `{$tests}`\n"
        . "- Jobs: `2`\n"
        . "- Timeout seconds: `7200`\n";
}

/**
 * @return array{0:string,1:string,2:string}
 */
function libsqlite_release_audit_ext_next30_write(
    string $label,
    string $extension = 'audit',
    string $head = 'accepted-head',
    string $testset = 'release',
    string $patterns = 'none',
    int $exit = 0,
    int $tests = 22000,
    int $errors = 0,
    bool $withLogField = false,
    bool $writeLog = true
): array {
    $root = libsqlite_release_audit_ext_next30_root($label);
    mkdir($root, 0777, true);
    $auditPath = $root . '/' . $label . '.' . $extension;
    $logPath = $root . '/' . $label . '.log';
    $logField = $withLogField ? '/tmp/not-shared/' . $label . '.log' : '';
    file_put_contents($auditPath, libsqlite_release_audit_ext_next30_audit($label, $head, $testset, $patterns, $exit, $tests, $errors, $logField));
    if ($writeLog) {
        file_put_contents($logPath, $errors . ' errors out of ' . $tests . " tests\n");
    }

    return [$root, $auditPath, $logPath];
}

$tests = [
    'current next30 directory scanner counts inferred audit log pairs' => static function (TestRunner $t): void {
        [$root, , $logPath] = libsqlite_release_audit_ext_next30_write('audit-inferred-log');
        try {
            $record = libsqlite_release_audit_ext_next30_evidence()->boundedRunnerArtifactDirectoryRecord($root, 'accepted-head');

            $t->same('countable', $record['status']);
            $t->same(1, $record['artifact_count']);
            $t->same(1, $record['countable_count']);
            $t->same(0, $record['missing_log_count']);
            $t->same($logPath, $record['entries'][0]['gate']['artifact']['stdout_path']);
        } finally {
            libsqlite_release_audit_ext_next30_cleanup($root);
        }
    },
    'current next30 provenance scanner counts inferred audit log pairs' => static function (TestRunner $t): void {
        [$root, , $logPath] = libsqlite_release_audit_ext_next30_write('provenance-audit');
        try {
            $record = libsqlite_release_audit_ext_next30_evidence()->acceptedHeadArtifactProvenanceDirectoryRecord($root, 'accepted-head');

            $t->same('all-current-accepted-head', $record['status']);
            $t->same(1, $record['artifact_count']);
            $t->same(1, $record['current_accepted_count']);
            $t->same(0, $record['missing_log_count']);
            $t->same(22000, $record['entries'][0]['acceptance']['tests']);
            $t->same(0, $record['entries'][0]['acceptance']['errors']);
        } finally {
            libsqlite_release_audit_ext_next30_cleanup($root);
        }
    },
    'current next30 directory scanner still counts markdown artifacts' => static function (TestRunner $t): void {
        [$root] = libsqlite_release_audit_ext_next30_write('markdown-still-counted', 'md');
        try {
            $record = libsqlite_release_audit_ext_next30_evidence()->boundedRunnerArtifactDirectoryRecord($root, 'accepted-head');

            $t->same('countable', $record['status']);
            $t->same(['markdown-still-counted'], $record['countable_labels']);
        } finally {
            libsqlite_release_audit_ext_next30_cleanup($root);
        }
    },
    'current next30 mixed audit and markdown artifacts sort by path and accumulate totals' => static function (TestRunner $t): void {
        $root = libsqlite_release_audit_ext_next30_root('mixed');
        mkdir($root, 0777, true);
        file_put_contents($root . '/a.audit', libsqlite_release_audit_ext_next30_audit('a', tests: 7));
        file_put_contents($root . '/a.log', "0 errors out of 7 tests\n");
        file_put_contents($root . '/b.md', libsqlite_release_audit_ext_next30_audit('b', tests: 11));
        file_put_contents($root . '/b.log', "0 errors out of 11 tests\n");
        try {
            $record = libsqlite_release_audit_ext_next30_evidence()->boundedRunnerArtifactDirectoryRecord($root, 'accepted-head');

            $t->same('countable', $record['status']);
            $t->same(2, $record['artifact_count']);
            $t->same(18, $record['tests_total']);
            $t->same(['a', 'b'], $record['countable_labels']);
        } finally {
            libsqlite_release_audit_ext_next30_cleanup($root);
        }
    },
];

$cases = [
    'release-countable' => ['release', 'none', 'accepted-head', 0, 101, 0, true, true, 'countable', 'release-like'],
    'all-countable' => ['all', 'none', 'accepted-head', 0, 102, 0, true, true, 'countable', 'release-like'],
    'focused-veryquick' => ['veryquick', 'json101.test', 'accepted-head', 0, 103, 0, true, true, 'countable', 'focused'],
    'focused-release' => ['release', 'wal.test', 'accepted-head', 0, 104, 0, true, true, 'countable', 'focused'],
    'focused-all' => ['all', 'btree01.test', 'accepted-head', 0, 105, 0, true, true, 'countable', 'focused'],
    'stale-head' => ['release', 'none', 'stale-head', 0, 106, 0, true, true, 'blocked', 'release-like'],
    'failed-exit' => ['release', 'none', 'accepted-head', 1, 107, 1, true, true, 'blocked', 'release-like'],
    'timeout-exit' => ['release', 'none', 'accepted-head', 124, 108, 0, true, true, 'blocked', 'release-like'],
    'missing-log' => ['release', 'none', 'accepted-head', 0, 109, 0, true, false, 'countable', 'release-like'],
    'no-log-field-inferred' => ['release', 'none', 'accepted-head', 0, 110, 0, false, true, 'countable', 'release-like'],
    'relative-log-field' => ['release', 'none', 'accepted-head', 0, 111, 0, true, true, 'countable', 'release-like'],
    'json-pattern-list' => ['veryquick', 'json101.test, json102.test', 'accepted-head', 0, 112, 0, true, true, 'countable', 'focused'],
];

for ($i = 1; $i <= 40; $i++) {
    $case = 'audit-batch-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
    $cases[$case] = [
        $i % 5 === 0 ? 'all' : ($i % 3 === 0 ? 'veryquick' : 'release'),
        $i % 4 === 0 ? 'json' . (100 + $i) . '.test' : 'none',
        $i % 11 === 0 ? 'stale-head' : 'accepted-head',
        $i % 13 === 0 ? 1 : 0,
        200 + $i,
        $i % 13 === 0 ? 1 : 0,
        $i % 2 === 0,
        $i % 17 !== 0,
        ($i % 11 === 0 || $i % 13 === 0) ? 'blocked' : 'countable',
        $i % 4 === 0 ? 'focused' : (in_array($i % 5 === 0 ? 'all' : ($i % 3 === 0 ? 'veryquick' : 'release'), ['all', 'release'], true) ? 'release-like' : 'unselected'),
    ];
}

foreach ($cases as $label => [$testset, $patterns, $head, $exit, $testCount, $errors, $withLogField, $writeLog, $expectedDirectoryStatus, $expectedKind]) {
    $tests['current next30 admits audit extension artifact case ' . $label] = static function (TestRunner $t) use ($label, $testset, $patterns, $head, $exit, $testCount, $errors, $withLogField, $writeLog, $expectedDirectoryStatus, $expectedKind): void {
        [$root] = libsqlite_release_audit_ext_next30_write(
            $label,
            'audit',
            $head,
            $testset,
            $patterns,
            $exit,
            $testCount,
            $errors,
            $withLogField,
            $writeLog
        );

        try {
            $record = libsqlite_release_audit_ext_next30_evidence()->boundedRunnerArtifactDirectoryRecord($root, 'accepted-head');
            $provenance = libsqlite_release_audit_ext_next30_evidence()->acceptedHeadArtifactProvenanceDirectoryRecord($root, 'accepted-head');
            $entry = $provenance['entries'][0];

            $t->same(1, $record['artifact_count']);
            $t->same(1, $provenance['artifact_count']);
            $t->same($expectedDirectoryStatus, $record['status']);
            $t->same($expectedDirectoryStatus === 'countable' ? 1 : 0, $record['countable_count']);
            $t->same($writeLog ? 0 : 1, $record['missing_log_count']);
            $t->same($expectedKind, $entry['kind']);
            $t->same($testset, $entry['testset']);
            $t->same($patterns === 'none' ? 0 : 1, $entry['pattern_count']);
            $t->same($expectedDirectoryStatus === 'countable' ? 'all-current-accepted-head' : 'blocked', $provenance['status']);
            $t->same($expectedDirectoryStatus === 'countable' ? $testCount : 0, $provenance['tests_total']);
        } finally {
            libsqlite_release_audit_ext_next30_cleanup($root);
        }
    };
}

return $tests;
