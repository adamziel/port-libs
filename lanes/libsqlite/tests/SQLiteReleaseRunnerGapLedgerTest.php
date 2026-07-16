<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_release_gap_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_release_gap_root(string $label): string
{
    return sys_get_temp_dir() . '/libsqlite-release-gap-' . $label . '-' . bin2hex(random_bytes(4));
}

function libsqlite_release_gap_mkdir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function libsqlite_release_gap_write(string $path, string $contents = '# fixture'): void
{
    libsqlite_release_gap_mkdir(dirname($path));
    file_put_contents($path, $contents);
}

function libsqlite_release_gap_cleanup(string $root): void
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

function libsqlite_release_gap_hydrate(string $label, bool $completePermutations = true): string
{
    $root = libsqlite_release_gap_root($label);
    $source = $root . '/.upstream-cache/libsqlite';
    $test = $source . '/test';
    $build = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';

    libsqlite_release_gap_write($test . '/testrunner.tcl');
    libsqlite_release_gap_write($build . '/testfixture', "#!/bin/sh\nexit 0\n");
    chmod($build . '/testfixture', 0755);
    libsqlite_release_gap_write($build . '/Makefile', "test:\n\t@true\nmptest:\n\t@true\n");
    libsqlite_release_gap_mkdir($source . '/mptest');

    $permutations = '';
    $count = $completePermutations ? 58 : 2;
    for ($i = 1; $i <= $count; $i++) {
        $permutations .= 'test_suite suite' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . "\n";
    }
    libsqlite_release_gap_write($test . '/permutations.test', $permutations);

    foreach (libsqlite_release_gap_evidence()->runnerCoverageAudit()['pattern_scripts'] as $pattern) {
        libsqlite_release_gap_write($test . '/' . str_replace('*', '01', $pattern));
    }

    return $root;
}

function libsqlite_release_gap_artifact(string $directory, string $head, string $label, int $tests = 31): void
{
    libsqlite_release_gap_mkdir($directory);
    $log = $directory . '/' . $label . '.log';
    libsqlite_release_gap_write($directory . '/' . $label . '.md', <<<MD
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
- Parsed summary: `0 errors out of {$tests} tests`
- Parsed errors: `0`
- Parsed tests: `{$tests}`
- Runner time: `00:00:05`
MD);
    libsqlite_release_gap_write($log, "0 errors out of {$tests} tests in 00:00:05\n");
}

$currentHead = '28488284c6b42b08db024e7e34c788f71b24a201';
$nextHead = '51e928e238d8d5d3e25cf7508399227fadad761f';

return [
    'current next preserves current artifact while next runner gaps remain open' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $root = libsqlite_release_gap_hydrate('current-open', false);
        $artifacts = $root . '/artifacts';
        libsqlite_release_gap_artifact($artifacts, $currentHead, 'current', 31);

        try {
            $record = libsqlite_release_gap_evidence()->releaseRunnerGapLedger(
                $currentHead,
                $nextHead,
                $root,
                $artifacts,
                '',
                3
            );

            $t->same('current-artifact-preserved-with-open-gaps', $record['status']);
            $t->same($currentHead, $record['current_accepted_head']);
            $t->same($nextHead, $record['next_accepted_head']);
            $t->same($artifacts, $record['artifact_directory']);
            $t->same(3, $record['jobs']);
            $t->same(7, $record['ledger_count']);
            $t->same(4, $record['satisfied_gap_count']);
            $t->same(3, $record['open_gap_count']);
            $t->same(['next-accepted-artifact', 'command-manifest', 'permutation-suite-map'], $record['open_gap_ids']);
            $t->same(1, $record['current_artifact_count']);
            $t->same(0, $record['next_artifact_count']);
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
            $t->same(false, $record['counts_as_release_parity']);
            $t->same(true, $record['counts_current_artifact_only']);
            $t->contains('preserve current artifact evidence', $record['next_gate']);

            $ledger = [];
            foreach ($record['ledger'] as $entry) {
                $ledger[$entry['id']] = $entry;
            }
            $t->same('satisfied', $ledger['current-accepted-artifact']['status']);
            $t->same('open', $ledger['next-accepted-artifact']['status']);
            $t->same('satisfied', $ledger['runner-hydration']['status']);
            $t->same('open', $ledger['command-manifest']['status']);
            $t->same('satisfied', $ledger['duplicate-runner']['status']);
            $t->same('satisfied', $ledger['wildcard-expansion']['status']);
            $t->same('open', $ledger['permutation-suite-map']['status']);
            $t->same(1, $ledger['current-accepted-artifact']['evidence']['countable_current_artifacts']);
            $t->same(0, $ledger['next-accepted-artifact']['evidence']['countable_next_artifacts']);
            $t->same(0, $ledger['runner-hydration']['evidence']['missing_count']);
            $t->same(7, $ledger['command-manifest']['evidence']['command_count']);
            $t->same(4, $ledger['command-manifest']['evidence']['runnable_command_count']);
            $t->same(0, $ledger['duplicate-runner']['evidence']['active_count']);
            $t->same(58, $ledger['permutation-suite-map']['evidence']['declared_suite_count']);
            $t->same(2, $ledger['permutation-suite-map']['evidence']['mapped_suite_count']);
            $t->contains('no new support component needed', $record['dependency_closure']);
        } finally {
            libsqlite_release_gap_cleanup($root);
        }
    },
    'current next marks next artifact countable and suppresses duplicate launches' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $root = libsqlite_release_gap_hydrate('next-countable');
        $artifacts = $root . '/artifacts';
        libsqlite_release_gap_artifact($artifacts, $currentHead, 'current', 31);
        libsqlite_release_gap_artifact($artifacts, $nextHead, 'next', 32);

        try {
            $record = libsqlite_release_gap_evidence()->releaseRunnerGapLedger($currentHead, $nextHead, $root, $artifacts);

            $t->same('next-artifact-countable', $record['status']);
            $t->same(1, $record['current_artifact_count']);
            $t->same(1, $record['next_artifact_count']);
            $t->same(true, $record['counts_as_release_parity']);
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
            $t->same(0, $record['open_gap_count']);
            $t->same(7, $record['satisfied_gap_count']);
            $t->same([], $record['open_gap_ids']);
            $t->contains('do not launch another broad runner', $record['next_gate']);
        } finally {
            libsqlite_release_gap_cleanup($root);
        }
    },
    'current next reports ready launch only when current evidence and all gates are clear' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $root = libsqlite_release_gap_hydrate('ready-launch');
        $artifacts = $root . '/artifacts';
        libsqlite_release_gap_artifact($artifacts, $currentHead, 'current', 31);

        try {
            $record = libsqlite_release_gap_evidence()->releaseRunnerGapLedger($currentHead, $nextHead, $root, $artifacts, '', 4);

            $t->same('ready-for-next-guarded-runner', $record['status']);
            $t->same(1, $record['current_artifact_count']);
            $t->same(0, $record['next_artifact_count']);
            $t->same(true, $record['ready_to_launch_next_guarded_runner']);
            $t->same(false, $record['counts_as_release_parity']);
            $t->same(1, $record['open_gap_count']);
            $t->same(['next-accepted-artifact'], $record['open_gap_ids']);
            $t->contains('launch at most one supervisor-approved guarded runner', $record['next_gate']);
        } finally {
            libsqlite_release_gap_cleanup($root);
        }
    },
    'current next blocks duplicate active broad runners before next launch' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $root = libsqlite_release_gap_hydrate('active-runner');
        $artifacts = $root . '/artifacts';
        libsqlite_release_gap_artifact($artifacts, $currentHead, 'current', 31);
        $snapshot = "101 100 S 02:14 0.1 ./testfixture ../src/test/testrunner.tcl --jobs 2 --stop-on-error all\n";

        try {
            $record = libsqlite_release_gap_evidence()->releaseRunnerGapLedger($currentHead, $nextHead, $root, $artifacts, $snapshot);
            $ledger = [];
            foreach ($record['ledger'] as $entry) {
                $ledger[$entry['id']] = $entry;
            }

            $t->same('current-artifact-preserved-with-open-gaps', $record['status']);
            $t->same(false, $record['ready_to_launch_next_guarded_runner']);
            $t->true(in_array('duplicate-runner', $record['open_gap_ids'], true), 'Expected duplicate-runner gap');
            $t->same('open', $ledger['duplicate-runner']['status']);
            $t->same('blocked-active-runner', $ledger['duplicate-runner']['evidence']['status']);
            $t->same(1, $ledger['duplicate-runner']['evidence']['active_count']);
            $t->same(['all'], $ledger['duplicate-runner']['evidence']['active_tiers']);
        } finally {
            libsqlite_release_gap_cleanup($root);
        }
    },
    'current next blocks empty head inputs and invalid job counts' => static function (TestRunner $t) use ($currentHead, $nextHead): void {
        $evidence = libsqlite_release_gap_evidence();

        $t->throws(
            InvalidArgumentException::class,
            static fn () => $evidence->releaseRunnerGapLedger('', $nextHead)
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $evidence->releaseRunnerGapLedger($currentHead, '')
        );
        $t->throws(
            InvalidArgumentException::class,
            static fn () => $evidence->releaseRunnerGapLedger($currentHead, $nextHead, null, null, '', 0)
        );
    },
];
