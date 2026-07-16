<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_suite_map32_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_suite_map32_root(string $label): string
{
    return sys_get_temp_dir() . '/libsqlite-suite-map32-' . $label . '-' . bin2hex(random_bytes(4));
}

function libsqlite_suite_map32_mkdir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function libsqlite_suite_map32_write(string $path, string $contents = '# fixture'): void
{
    libsqlite_suite_map32_mkdir(dirname($path));
    file_put_contents($path, $contents);
}

function libsqlite_suite_map32_cleanup(string $root): void
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

function libsqlite_suite_map32_fixture(string $label, array $parts): string
{
    $root = libsqlite_suite_map32_root($label);
    $source = $root . '/.upstream-cache/libsqlite';
    $test = $source . '/test';
    $build = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';

    if (in_array('source', $parts, true)) {
        libsqlite_suite_map32_mkdir($source);
    }
    if (in_array('testdir', $parts, true)) {
        libsqlite_suite_map32_mkdir($test);
    }
    if (in_array('testrunner', $parts, true)) {
        libsqlite_suite_map32_write($test . '/testrunner.tcl');
    }
    if (in_array('permutations', $parts, true)) {
        $text = '';
        for ($i = 1; $i <= 58; $i++) {
            $text .= sprintf("test_suite \"suite%02d\" -description {fixture}\n", $i);
        }
        libsqlite_suite_map32_write($test . '/permutations.test', $text);
    }
    if (in_array('selected-scripts', $parts, true)) {
        $audit = libsqlite_suite_map32_evidence()->runnerCoverageAudit();
        foreach ($audit['selected_scripts'] as $script) {
            libsqlite_suite_map32_write($test . '/' . $script);
        }
        foreach ($audit['pattern_scripts'] as $pattern) {
            libsqlite_suite_map32_write($test . '/' . str_replace('*', '01', $pattern));
        }
    }
    if (in_array('mptest', $parts, true)) {
        libsqlite_suite_map32_mkdir($source . '/mptest');
    }
    if (in_array('build', $parts, true)) {
        libsqlite_suite_map32_mkdir($build);
    }
    if (in_array('testfixture', $parts, true)) {
        libsqlite_suite_map32_write($build . '/testfixture', "#!/bin/sh\nexit 0\n");
        chmod($build . '/testfixture', in_array('executable', $parts, true) ? 0755 : 0644);
    }
    if (in_array('makefile', $parts, true)) {
        libsqlite_suite_map32_write($build . '/Makefile', "test:\n\t@true\nmptest:\n\t@true\n");
    }

    return $root;
}

function libsqlite_suite_map32_parts(): array
{
    return ['source', 'testdir', 'testrunner', 'permutations', 'selected-scripts', 'mptest', 'build', 'testfixture', 'executable', 'makefile'];
}

function libsqlite_suite_map32_artifact(string $directory, string $head, string $label): void
{
    libsqlite_suite_map32_mkdir($directory);
    $log = $directory . '/' . $label . '.log';
    libsqlite_suite_map32_write($directory . '/' . $label . '.md', <<<MD
# SQLite Tcl Bounded Runner Evidence - {$label}

- Repository HEAD: `{$head}`
- Scratch: `/tmp/{$label}`
- Log: `{$log}`
- SQLite git commit: `8f70ec615f4cd247d36f92a22c99f65ebbcc22a7`
- SQLite VERSION: `3.54.0`
- SQLite manifest UUID: `9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353`
- Testset: `release`
- Jobs: `2`
- Timeout seconds: `7200`
- Patterns: none
- Exit: `0`
- Elapsed seconds: `5`
- Parsed summary: `0 errors out of 18 tests`
- Parsed errors: `0`
- Parsed tests: `18`
- Runner time: `00:00:05`
MD);
    libsqlite_suite_map32_write($log, "0 errors out of 18 tests in 00:00:05\n");
}

$currentHead = '28488284c6b42b08db024e7e34c788f71b24a201';
$nextHead = 'f1811cc4298a2c293ae718fb1f1dae1e656bc706';

$tests = [
    'current next32 maps hydrated current artifact to next suite runner' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $root = libsqlite_suite_map32_fixture('ready', libsqlite_suite_map32_parts());
        $artifacts = $root . '/artifacts';
        libsqlite_suite_map32_artifact($artifacts, $currentHead, 'current');

        try {
            $record = libsqlite_suite_map32_evidence()->releaseRunnerSuiteMap($currentHead, $nextHead, $root, $artifacts, '', 3);

            $t->same('ready-current-to-next-suite-map', $record['status']);
            $t->same('ready-map-current-to-next-runner', $record['upstream_map_status']);
            $t->same($currentHead, $record['current_accepted_head']);
            $t->same($nextHead, $record['next_accepted_head']);
            $t->same(1, $record['current_artifact_count']);
            $t->same(0, $record['next_artifact_count']);
            $t->same('ready', $record['command_manifest_status']);
            $t->same(7, $record['command_count']);
            $t->same(7, $record['runnable_command_count']);
            $t->same(0, $record['blocked_command_count']);
            $t->same([], $record['suite_map_blockers']);
            $t->same('ready', $record['selected_script_status']);
            $t->true($record['selected_resolved_count'] >= 40, 'Expected selected script inventory to resolve');
            $t->same('ready', $record['wildcard_status']);
            $t->true($record['wildcard_expanded_script_count'] >= $record['wildcard_pattern_count'], 'Expected wildcard scripts to expand');
            $t->same('ready', $record['permutation_status']);
            $t->same(58, $record['permutation_mapped_count']);
            $t->same(0, $record['permutation_unmapped_count']);
            $t->same(true, $record['counts_current_artifact_only']);
            $t->same(true, $record['ready_to_launch_next_guarded_runner']);
            $t->contains('concrete selected, wildcard-expanded, permutation, and release-tier command map', $record['next_gate']);
            $t->contains('no new support component needed', $record['dependency_closure']);
        } finally {
            libsqlite_suite_map32_cleanup($root);
        }
    },
    'current next32 suppresses duplicate launch when next artifact is already countable' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $root = libsqlite_suite_map32_fixture('next-ready', libsqlite_suite_map32_parts());
        $artifacts = $root . '/artifacts';
        libsqlite_suite_map32_artifact($artifacts, $currentHead, 'current');
        libsqlite_suite_map32_artifact($artifacts, $nextHead, 'next');

        try {
            $record = libsqlite_suite_map32_evidence()->releaseRunnerSuiteMap($currentHead, $nextHead, $root, $artifacts);

            $t->same('next-artifact-already-countable', $record['status']);
            $t->same(1, $record['current_artifact_count']);
            $t->same(1, $record['next_artifact_count']);
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
            $t->contains('suppress duplicate release/all runner launch', $record['next_gate']);
        } finally {
            libsqlite_suite_map32_cleanup($root);
        }
    },
    'current next32 preserves current artifact when selected scripts are missing' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $root = libsqlite_suite_map32_fixture('missing-selected', ['source', 'testdir', 'testrunner', 'permutations', 'mptest', 'build', 'testfixture', 'executable', 'makefile']);
        $artifacts = $root . '/artifacts';
        libsqlite_suite_map32_artifact($artifacts, $currentHead, 'current');

        try {
            $record = libsqlite_suite_map32_evidence()->releaseRunnerSuiteMap($currentHead, $nextHead, $root, $artifacts);

            $t->same('current-artifact-preserved-suite-map-blocked', $record['status']);
            $t->same('blocked', $record['selected_script_status']);
            $t->true(in_array('selected-script-inventory', $record['suite_map_blockers'], true), 'Expected selected inventory blocker');
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
        } finally {
            libsqlite_suite_map32_cleanup($root);
        }
    },
    'current next32 blocks when current accepted artifact is absent' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $root = libsqlite_suite_map32_fixture('no-current', libsqlite_suite_map32_parts());

        try {
            $record = libsqlite_suite_map32_evidence()->releaseRunnerSuiteMap($currentHead, $nextHead, $root, $root . '/artifacts');

            $t->same('blocked', $record['status']);
            $t->same(0, $record['current_artifact_count']);
            $t->same(0, $record['next_artifact_count']);
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
            $t->contains('do not count release/all movement', $record['next_gate']);
        } finally {
            libsqlite_suite_map32_cleanup($root);
        }
    },
    'current next32 blocks duplicate active broad runner' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $root = libsqlite_suite_map32_fixture('active-runner', libsqlite_suite_map32_parts());
        $artifacts = $root . '/artifacts';
        libsqlite_suite_map32_artifact($artifacts, $currentHead, 'current');
        $snapshot = "1666104 1666103 S+ 28:39 0.2 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error release\n";

        try {
            $record = libsqlite_suite_map32_evidence()->releaseRunnerSuiteMap($currentHead, $nextHead, $root, $artifacts, $snapshot);

            $t->same('current-artifact-preserved-suite-map-blocked', $record['status']);
            $t->same('blocked-active-runner', $record['active_runner_status']);
            $t->same(1, $record['active_runner_count']);
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
        } finally {
            libsqlite_suite_map32_cleanup($root);
        }
    },
    'current next32 rejects missing current head' => static function (TestRunner $t) use ($nextHead): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_suite_map32_evidence()->releaseRunnerSuiteMap('', $nextHead)
        );
    },
    'current next32 rejects missing next head' => static function (TestRunner $t) use ($currentHead): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_suite_map32_evidence()->releaseRunnerSuiteMap($currentHead, '')
        );
    },
    'current next32 rejects zero jobs' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $t->throws(
            InvalidArgumentException::class,
            static fn () => libsqlite_suite_map32_evidence()->releaseRunnerSuiteMap($currentHead, $nextHead, null, null, '', 0)
        );
    },
];

foreach ([
    'source cache' => ['parts' => [], 'blockers' => ['selected-script-inventory', 'wildcard-expansion', 'permutation-suite-map', 'command-manifest']],
    'test directory' => ['parts' => ['source', 'build', 'testfixture', 'executable', 'makefile'], 'blockers' => ['selected-script-inventory', 'wildcard-expansion', 'permutation-suite-map', 'command-manifest']],
    'permutation source' => ['parts' => ['source', 'testdir', 'testrunner', 'selected-scripts', 'mptest', 'build', 'testfixture', 'executable', 'makefile'], 'blockers' => ['permutation-suite-map', 'command-manifest']],
    'makefile' => ['parts' => ['source', 'testdir', 'testrunner', 'permutations', 'selected-scripts', 'mptest', 'build', 'testfixture', 'executable'], 'blockers' => ['command-manifest']],
    'mptest directory' => ['parts' => ['source', 'testdir', 'testrunner', 'permutations', 'selected-scripts', 'build', 'testfixture', 'executable', 'makefile'], 'blockers' => ['command-manifest']],
] as $label => $case) {
    $tests['current next32 suite map blockers when ' . $label . ' is missing'] = static function (TestRunner $t) use ($currentHead, $nextHead, $label, $case): void {
        $root = libsqlite_suite_map32_fixture(str_replace(' ', '-', $label), $case['parts']);
        $artifacts = $root . '/artifacts';
        libsqlite_suite_map32_artifact($artifacts, $currentHead, 'current');

        try {
            $record = libsqlite_suite_map32_evidence()->releaseRunnerSuiteMap($currentHead, $nextHead, $root, $artifacts);

            $t->same('current-artifact-preserved-suite-map-blocked', $record['status']);
            foreach ($case['blockers'] as $blocker) {
                $t->true(in_array($blocker, $record['suite_map_blockers'], true), 'Expected blocker ' . $blocker);
            }
            $t->same(count($record['suite_map_blockers']), $record['suite_map_blocker_count']);
        } finally {
            libsqlite_suite_map32_cleanup($root);
        }
    };
}

for ($i = 1; $i <= 42; $i++) {
    $tests['current next32 ready suite map sample ' . $i] = static function (TestRunner $t) use ($currentHead, $nextHead, $i): void {
        $root = libsqlite_suite_map32_fixture('sample-' . $i, libsqlite_suite_map32_parts());
        $artifacts = $root . '/artifacts';
        libsqlite_suite_map32_artifact($artifacts, $currentHead, 'current-' . $i);

        try {
            $record = libsqlite_suite_map32_evidence()->releaseRunnerSuiteMap($currentHead, $nextHead, $root, $artifacts, '', 2);

            $t->same('ready-current-to-next-suite-map', $record['status']);
            $t->same(1, $record['current_artifact_count']);
            $t->same(0, $record['next_artifact_count']);
            $t->same(7, $record['runnable_command_count']);
            $t->same(58, $record['permutation_mapped_count']);
            $t->same(true, $record['ready_to_launch_next_guarded_runner']);
        } finally {
            libsqlite_suite_map32_cleanup($root);
        }
    };
}

return $tests;
