<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_upstream_gap_proof_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_upstream_gap_proof_root(string $label): string
{
    return sys_get_temp_dir() . '/libsqlite-release-upstream-gap-proof-' . $label . '-' . bin2hex(random_bytes(4));
}

function libsqlite_release_upstream_gap_proof_cleanup(string $root): void
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
            continue;
        }

        @chmod($path->getPathname(), 0666);
        @unlink($path->getPathname());
    }
    @rmdir($root);
}

function libsqlite_release_upstream_gap_proof_hydrate(string $root, array $scripts): void
{
    $testDir = $root . '/.upstream-cache/libsqlite/test';
    $buildDir = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';
    mkdir($testDir, 0777, true);
    mkdir($buildDir, 0777, true);
    file_put_contents($testDir . '/testrunner.tcl', '# testrunner');
    foreach ($scripts as $script) {
        file_put_contents($testDir . '/' . $script, "# {$script}");
    }
    file_put_contents($buildDir . '/testfixture', "#!/bin/sh\nexit 0\n");
    chmod($buildDir . '/testfixture', 0777);
}

function libsqlite_release_upstream_gap_proof_artifact(string $head, string $label = 'current-release', int $tests = 22000, string $testset = 'release', string $patterns = 'none', int $exit = 0, int $errors = 0): string
{
    return "# SQLite Tcl Bounded Runner Evidence - {$label}\n\n"
        . "- Repository HEAD: `{$head}`\n"
        . "- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`\n"
        . "- SQLite VERSION: `3.54.0`\n"
        . "- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`\n"
        . "- Scratch: `/tmp/libsqlite-release-gap37`\n"
        . "- Log: `{$label}.log`\n"
        . "- Testset: `{$testset}`\n"
        . "- Patterns: `{$patterns}`\n"
        . "- Exit: `{$exit}`\n"
        . "- Parsed errors: `{$errors}`\n"
        . "- Parsed tests: `{$tests}`\n";
}

function libsqlite_release_upstream_gap_proof_write_artifact(string $dir, string $head, string $label = 'current-release', int $tests = 22000, string $testset = 'release', string $patterns = 'none', int $exit = 0, int $errors = 0): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir . '/' . $label . '.md', libsqlite_release_upstream_gap_proof_artifact($head, $label, $tests, $testset, $patterns, $exit, $errors));
    file_put_contents($dir . '/' . $label . '.log', "{$errors} errors out of {$tests} tests\n");
}

function libsqlite_release_upstream_gap_proof_focused_output(int $assertions = 57, int $failures = 0, int $files = 1): string
{
    return "Focused test run: {$files} selected test files (root lock skipped)\n"
        . "{$files} test files, {$assertions} assertions, {$failures} failures";
}

$acceptedHead = '28488284c6b42b08db024e7e34c788f71b24a201';
$nextHead = 'current-next37-proof-head';
$focusedPath = 'lanes/libsqlite/tests/SQLiteReleaseRunnerUpstreamGapProofTest.php';
$nonOverlap = 'Avoids accepted release runner parity ledger, current/next count, audit extension, hydration cluster, JSON/VFS/WAL/B-tree/SQL behavior clusters; proves only current-next37 upstream gap readiness.';

$tests = [
    'current next37 proves current artifact and next runner gap with focused php admission' => static function (TestRunner $t) use ($acceptedHead, $nextHead, $focusedPath, $nonOverlap): void {
        $root = libsqlite_release_upstream_gap_proof_root('ready');
        $artifacts = $root . '/artifacts';
        try {
            libsqlite_release_upstream_gap_proof_hydrate($root, ['json101.test', 'wal.test', 'btree01.test']);
            libsqlite_release_upstream_gap_proof_write_artifact($artifacts, $acceptedHead);

            $record = libsqlite_release_upstream_gap_proof_evidence()->releaseRunnerUpstreamGapProof(
                $acceptedHead,
                $nextHead,
                $artifacts,
                [
                    'json-wal' => ['json101.test', 'wal.test'],
                    'btree' => ['btree01.test'],
                ],
                12903,
                $focusedPath,
                libsqlite_release_upstream_gap_proof_focused_output(57),
                $nonOverlap,
                $root
            );

            $t->same('current-artifact-gap-proof-next-ready', $record['status']);
            $t->same(1, $record['current_accepted_artifact_count']);
            $t->same(0, $record['next_accepted_artifact_count']);
            $t->same(2, $record['focused_runnable_group_count']);
            $t->same(0, $record['focused_blocked_group_count']);
            $t->same(3, $record['focused_script_count']);
            $t->same(['btree01.test', 'json101.test', 'wal.test'], $record['focused_scripts']);
            $t->same('clear', $record['active_runner_status']);
            $t->same('admitted', $record['php_pass_admission']['status']);
            $t->same(57, $record['php_pass_delta']);
            $t->same(12960, $record['next_php_pass']);
            $t->true($record['counts_current_artifact_only']);
            $t->true($record['ready_to_launch_next_guarded_runner']);
            $t->same(false, $record['counts_next_artifact']);
            $t->same(0, $record['blocker_count']);
            $t->contains('current-next37 gap proof', $record['dependency_closure']);
            $t->contains('launch at most one guarded next-source runner', $record['next_gate']);
        } finally {
            libsqlite_release_upstream_gap_proof_cleanup($root);
        }
    },
    'current next37 blocks duplicate next artifact rather than relaunching broad runner' => static function (TestRunner $t) use ($acceptedHead, $nextHead, $focusedPath, $nonOverlap): void {
        $root = libsqlite_release_upstream_gap_proof_root('duplicate-next');
        $artifacts = $root . '/artifacts';
        try {
            libsqlite_release_upstream_gap_proof_hydrate($root, ['json101.test']);
            libsqlite_release_upstream_gap_proof_write_artifact($artifacts, $acceptedHead, 'current-release');
            libsqlite_release_upstream_gap_proof_write_artifact($artifacts, $nextHead, 'next-release');

            $record = libsqlite_release_upstream_gap_proof_evidence()->releaseRunnerUpstreamGapProof(
                $acceptedHead,
                $nextHead,
                $artifacts,
                ['json' => ['json101.test']],
                12903,
                $focusedPath,
                libsqlite_release_upstream_gap_proof_focused_output(43),
                $nonOverlap,
                $root
            );

            $t->same('blocked', $record['status']);
            $t->same(1, $record['current_accepted_artifact_count']);
            $t->same(1, $record['next_accepted_artifact_count']);
            $t->same(['next-source-artifact-already-countable'], array_column($record['blockers'], 'id'));
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
            $t->same(43, $record['php_pass_delta']);
            $t->same(12946, $record['next_php_pass']);
        } finally {
            libsqlite_release_upstream_gap_proof_cleanup($root);
        }
    },
    'current next37 reports focused subset hydration blockers' => static function (TestRunner $t) use ($acceptedHead, $nextHead, $focusedPath, $nonOverlap): void {
        $root = libsqlite_release_upstream_gap_proof_root('missing-script');
        $artifacts = $root . '/artifacts';
        try {
            libsqlite_release_upstream_gap_proof_hydrate($root, ['json101.test']);
            libsqlite_release_upstream_gap_proof_write_artifact($artifacts, $acceptedHead);

            $record = libsqlite_release_upstream_gap_proof_evidence()->releaseRunnerUpstreamGapProof(
                $acceptedHead,
                $nextHead,
                $artifacts,
                [
                    'json' => ['json101.test'],
                    'wal' => ['wal.test'],
                ],
                12903,
                $focusedPath,
                libsqlite_release_upstream_gap_proof_focused_output(41),
                $nonOverlap,
                $root
            );

            $t->same('current-artifact-preserved-next-blocked', $record['status']);
            $t->same(1, $record['focused_runnable_group_count']);
            $t->same(1, $record['focused_blocked_group_count']);
            $t->same(['wal'], $record['focused_blocked_groups']);
            $t->true(in_array('focused-subset-hydration-blocked', array_column($record['blockers'], 'id'), true), 'Expected focused subset hydration blocker');
            $t->same('skipped', $record['focused_subsets']['wal']['status']);
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
        } finally {
            libsqlite_release_upstream_gap_proof_cleanup($root);
        }
    },
    'current next37 preserves current artifact while broad runner is active' => static function (TestRunner $t) use ($acceptedHead, $nextHead, $focusedPath, $nonOverlap): void {
        $root = libsqlite_release_upstream_gap_proof_root('active-runner');
        $artifacts = $root . '/artifacts';
        try {
            libsqlite_release_upstream_gap_proof_hydrate($root, ['json101.test']);
            libsqlite_release_upstream_gap_proof_write_artifact($artifacts, $acceptedHead);

            $record = libsqlite_release_upstream_gap_proof_evidence()->releaseRunnerUpstreamGapProof(
                $acceptedHead,
                $nextHead,
                $artifacts,
                ['json' => ['json101.test']],
                12903,
                $focusedPath,
                libsqlite_release_upstream_gap_proof_focused_output(40),
                $nonOverlap,
                $root,
                '12345 ./testfixture ../libsqlite/test/testrunner.tcl --jobs 2 --stop-on-error all'
            );

            $t->same('current-artifact-preserved-next-blocked', $record['status']);
            $t->same('blocked-active-runner', $record['active_runner_status']);
            $t->same(1, $record['active_runner_count']);
            $t->true(in_array('duplicate-broad-runner-active', array_column($record['blockers'], 'id'), true), 'Expected duplicate runner blocker');
            $t->same(40, $record['php_pass_delta']);
        } finally {
            libsqlite_release_upstream_gap_proof_cleanup($root);
        }
    },
    'current next37 blocks missing php pass admission' => static function (TestRunner $t) use ($acceptedHead, $nextHead, $focusedPath, $nonOverlap): void {
        $root = libsqlite_release_upstream_gap_proof_root('php-blocked');
        $artifacts = $root . '/artifacts';
        try {
            libsqlite_release_upstream_gap_proof_hydrate($root, ['json101.test']);
            libsqlite_release_upstream_gap_proof_write_artifact($artifacts, $acceptedHead);

            $record = libsqlite_release_upstream_gap_proof_evidence()->releaseRunnerUpstreamGapProof(
                $acceptedHead,
                $nextHead,
                $artifacts,
                ['json' => ['json101.test']],
                12903,
                $focusedPath,
                "1 test files, 40 assertions, 0 failures",
                $nonOverlap,
                $root
            );

            $t->same('current-artifact-preserved-next-blocked', $record['status']);
            $t->same('blocked', $record['php_pass_admission']['status']);
            $t->same(0, $record['php_pass_delta']);
            $t->true(in_array('php-pass-admission-blocked', array_column($record['blockers'], 'id'), true), 'Expected PHP pass blocker');
            $t->contains('missing focused TestRunner output', json_encode($record['blockers'], JSON_THROW_ON_ERROR));
        } finally {
            libsqlite_release_upstream_gap_proof_cleanup($root);
        }
    },
];

$matrix = [
    'json-single' => [['json101.test'], 31, 1, 1],
    'json-pair' => [['json101.test', 'json102.test'], 32, 1, 2],
    'wal-btree' => [['wal.test', 'btree01.test'], 33, 1, 2],
    'pager-corrupt' => [['pager1.test', 'corrupt.test'], 34, 1, 2],
    'select-index' => [['select1.test', 'index1.test'], 35, 1, 2],
    'pragma-trigger-fk' => [['pragma.test', 'trigger1.test', 'fkey1.test'], 36, 1, 3],
    'json-wal-btree' => [['json101.test', 'wal.test', 'btree01.test'], 37, 1, 3],
    'two-groups' => [['json101.test'], 38, 2, 2],
    'three-groups' => [['json101.test'], 39, 3, 3],
    'four-groups' => [['json101.test'], 40, 4, 4],
];

foreach ($matrix as $label => [$scripts, $assertions, $groupCount, $scriptCount]) {
    $tests['current next37 matrix gap proof ' . $label] = static function (TestRunner $t) use ($label, $scripts, $assertions, $groupCount, $scriptCount, $acceptedHead, $nextHead, $focusedPath, $nonOverlap): void {
        $root = libsqlite_release_upstream_gap_proof_root($label);
        $artifacts = $root . '/artifacts';
        try {
            $hydratedScripts = $scripts;
            if ($groupCount > 1) {
                $hydratedScripts = [];
                for ($i = 1; $i <= $groupCount; $i++) {
                    $hydratedScripts[] = 'subset' . $i . '.test';
                }
            }
            libsqlite_release_upstream_gap_proof_hydrate($root, $hydratedScripts);
            libsqlite_release_upstream_gap_proof_write_artifact($artifacts, $acceptedHead);

            $groups = [];
            if ($groupCount > 1) {
                for ($i = 1; $i <= $groupCount; $i++) {
                    $groups['subset-' . $i] = ['subset' . $i . '.test'];
                }
            } else {
                $groups['subset'] = $scripts;
            }

            $record = libsqlite_release_upstream_gap_proof_evidence()->releaseRunnerUpstreamGapProof(
                $acceptedHead,
                $nextHead,
                $artifacts,
                $groups,
                12903,
                $focusedPath,
                libsqlite_release_upstream_gap_proof_focused_output($assertions),
                $nonOverlap,
                $root,
                '',
                3
            );

            $t->same('current-artifact-gap-proof-next-ready', $record['status']);
            $t->same($groupCount, $record['focused_group_count']);
            $t->same($groupCount, $record['focused_runnable_group_count']);
            $t->same($scriptCount, $record['focused_script_count']);
            $t->same(3, $record['jobs']);
            $t->same($assertions, $record['php_pass_delta']);
            $t->same(12903 + $assertions, $record['next_php_pass']);
        } finally {
            libsqlite_release_upstream_gap_proof_cleanup($root);
        }
    };
}

$tests['current next37 validates required inputs'] = static function (TestRunner $t) use ($acceptedHead, $nextHead, $focusedPath, $nonOverlap): void {
    $evidence = libsqlite_release_upstream_gap_proof_evidence();

    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerUpstreamGapProof('', $nextHead, '/tmp/artifacts', ['json' => ['json101.test']], 1, $focusedPath, libsqlite_release_upstream_gap_proof_focused_output(), $nonOverlap));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerUpstreamGapProof($acceptedHead, '', '/tmp/artifacts', ['json' => ['json101.test']], 1, $focusedPath, libsqlite_release_upstream_gap_proof_focused_output(), $nonOverlap));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerUpstreamGapProof($acceptedHead, $nextHead, '', ['json' => ['json101.test']], 1, $focusedPath, libsqlite_release_upstream_gap_proof_focused_output(), $nonOverlap));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerUpstreamGapProof($acceptedHead, $nextHead, '/tmp/artifacts', [], 1, $focusedPath, libsqlite_release_upstream_gap_proof_focused_output(), $nonOverlap));
    $t->throws(InvalidArgumentException::class, static fn () => $evidence->releaseRunnerUpstreamGapProof($acceptedHead, $nextHead, '/tmp/artifacts', ['json' => ['json101.test']], 1, $focusedPath, libsqlite_release_upstream_gap_proof_focused_output(), $nonOverlap, null, '', 0));
};

return $tests;
