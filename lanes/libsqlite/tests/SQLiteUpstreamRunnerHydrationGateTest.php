<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

function libsqlite_upstream_hydration_evidence(): SQLiteUpstreamSuiteEvidence
{
    return SQLiteUpstreamSuiteEvidence::fromManifestPath(__DIR__ . '/../UPSTREAM_TEST_MANIFEST.json');
}

function libsqlite_upstream_hydration_root(string $label): string
{
    return sys_get_temp_dir() . '/libsqlite-upstream-hydration-' . $label . '-' . bin2hex(random_bytes(4));
}

function libsqlite_upstream_hydration_mkdir(string $path): void
{
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
}

function libsqlite_upstream_hydration_write(string $path, string $contents = '# fixture'): void
{
    libsqlite_upstream_hydration_mkdir(dirname($path));
    file_put_contents($path, $contents);
}

function libsqlite_upstream_hydration_cleanup(string $root): void
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

function libsqlite_upstream_hydration_fixture(string $label, array $parts): string
{
    $root = libsqlite_upstream_hydration_root($label);
    $source = $root . '/.upstream-cache/libsqlite';
    $test = $source . '/test';
    $build = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';

    if (in_array('source', $parts, true)) {
        libsqlite_upstream_hydration_mkdir($source);
    }
    if (in_array('testdir', $parts, true)) {
        libsqlite_upstream_hydration_mkdir($test);
    }
    if (in_array('testrunner', $parts, true)) {
        libsqlite_upstream_hydration_write($test . '/testrunner.tcl');
    }
    if (in_array('permutations', $parts, true)) {
        libsqlite_upstream_hydration_write($test . '/permutations.test', "test_suite full\n");
    }
    if (in_array('mptest', $parts, true)) {
        libsqlite_upstream_hydration_mkdir($source . '/mptest');
    }
    if (in_array('build', $parts, true)) {
        libsqlite_upstream_hydration_mkdir($build);
    }
    if (in_array('testfixture', $parts, true)) {
        libsqlite_upstream_hydration_write($build . '/testfixture', "#!/bin/sh\nexit 0\n");
        chmod($build . '/testfixture', in_array('executable', $parts, true) ? 0755 : 0644);
    }
    if (in_array('makefile', $parts, true)) {
        libsqlite_upstream_hydration_write($build . '/Makefile', "test:\n\t@true\nmptest:\n\t@true\n");
    }

    return $root;
}

return [
    'blocks upstream runner hydration when source cache is absent' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_root('missing-source');
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same('blocked-missing-hydration', $gate['status']);
            $t->true(in_array('.upstream-cache/libsqlite', $gate['missing'], true), 'Expected missing source cache');
            $t->same(0, $gate['runnable_command_count']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'reports every required hydration input on an empty root' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_root('empty-root');
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(1, $root);
            $t->same(8, $gate['input_count']);
            $t->same(8, $gate['missing_count']);
            $t->same(0, $gate['ready_input_count']);
            $t->true(in_array('.upstream-cache/libsqlite-build-port-libsqlite/testfixture', $gate['missing'], true), 'Expected missing testfixture');
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'keeps focused subset blocked until testrunner is hydrated' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('missing-testrunner', ['source', 'testdir', 'build', 'testfixture', 'executable']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(3, $root);
            $t->same('blocked-missing-hydration', $gate['status']);
            $t->same(false, $gate['commands'][0]['runnable']);
            $t->true(in_array('.upstream-cache/libsqlite/test/testrunner.tcl', $gate['commands'][0]['missing'], true), 'Expected focused command to need testrunner');
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'keeps focused subset blocked when testfixture is not executable' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('nonexec-testfixture', ['source', 'testdir', 'testrunner', 'build', 'testfixture']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(false, $gate['inputs']['testfixture']['executable']);
            $t->true(in_array('.upstream-cache/libsqlite-build-port-libsqlite/testfixture executable bit', $gate['missing'], true), 'Expected executable bit blocker');
            $t->same(false, $gate['commands'][0]['runnable']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'marks focused and release commands runnable with executable testfixture' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('focused-ready', ['source', 'testdir', 'testrunner', 'build', 'testfixture', 'executable']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(4, $root);
            $t->same('partially-hydrated', $gate['status']);
            $t->same(true, $gate['commands'][0]['runnable']);
            $t->same(true, $gate['commands'][1]['runnable']);
            $t->contains('--jobs 4 --stop-on-error veryquick <patterns>', $gate['commands'][0]['command']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'keeps permutation commands blocked until permutations source exists' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('no-permutations', ['source', 'testdir', 'testrunner', 'build', 'testfixture', 'executable']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(false, $gate['commands'][2]['runnable']);
            $t->same(['.upstream-cache/libsqlite/test/permutations.test'], $gate['commands'][2]['missing']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'marks permutation commands runnable when permutations source is hydrated' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('permutations-ready', ['source', 'testdir', 'testrunner', 'permutations', 'build', 'testfixture', 'executable']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(5, $root);
            $t->same(true, $gate['commands'][2]['runnable']);
            $t->same([], $gate['commands'][2]['missing']);
            $t->contains('--jobs 5', (string) $gate['commands'][2]['command']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'keeps make test blocked until Makefile exists' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('no-makefile', ['source', 'build']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(false, $gate['commands'][3]['runnable']);
            $t->same(['.upstream-cache/libsqlite-build-port-libsqlite/Makefile'], $gate['commands'][3]['missing']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'marks make test runnable with hydrated build Makefile' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('make-ready', ['source', 'build', 'makefile']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(true, $gate['commands'][3]['runnable']);
            $t->same('make -C .upstream-cache/libsqlite-build-port-libsqlite test', $gate['commands'][3]['command']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'keeps mptest blocked until mptest directory exists' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('no-mptest', ['source', 'build', 'makefile']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(false, $gate['commands'][4]['runnable']);
            $t->same(['.upstream-cache/libsqlite/mptest'], $gate['commands'][4]['missing']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'marks mptest runnable with hydrated mptest directory' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('mptest-ready', ['source', 'build', 'makefile', 'mptest']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(true, $gate['commands'][4]['runnable']);
            $t->same('make -C .upstream-cache/libsqlite-build-port-libsqlite mptest', $gate['commands'][4]['command']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'marks a fully hydrated upstream runner tree as hydrated' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('full-ready', ['source', 'testdir', 'testrunner', 'permutations', 'mptest', 'build', 'testfixture', 'executable', 'makefile']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(6, $root);
            $t->same('hydrated', $gate['status']);
            $t->same(0, $gate['missing_count']);
            $t->same(5, $gate['runnable_command_count']);
            $t->contains('bounded runner commands are hydrated', $gate['next_gate']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'preserves root and build directory metadata in hydration gate' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('metadata', ['source']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same($root, $gate['root']);
            $t->same('.upstream-cache/libsqlite-build-port-libsqlite', $gate['build_directory']);
            $t->same(2, $gate['jobs']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'reports stable command ids for integrator launch gating' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('command-ids', ['source']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same([
                'focused-veryquick-subset',
                'release-all',
                'permutation-suites',
                'make-test',
                'mptest',
            ], array_column($gate['commands'], 'id'));
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'keeps source cache readiness scoped to upstream cache path' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('source-only', ['source']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(true, $gate['inputs']['source_cache']['ready']);
            $t->same(false, $gate['inputs']['test_directory']['ready']);
            $t->same(false, $gate['inputs']['build_directory']['ready']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'reports testrunner readiness independently from permutations source' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('testrunner-only', ['source', 'testdir', 'testrunner']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(true, $gate['inputs']['testrunner']['ready']);
            $t->same(false, $gate['inputs']['permutation_source']['ready']);
            $t->true(in_array('.upstream-cache/libsqlite/test/permutations.test', $gate['missing'], true), 'Expected permutation source blocker');
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'reports testfixture readiness independently from executable readiness' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('testfixture-nonexec', ['source', 'build', 'testfixture']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(true, $gate['inputs']['testfixture']['ready']);
            $t->same(false, $gate['inputs']['testfixture']['executable']);
            $t->true($gate['missing_count'] >= 1, 'Expected non-executable testfixture to count as missing hydration');
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'reports executable testfixture readiness when chmod allows launch' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('testfixture-exec', ['source', 'build', 'testfixture', 'executable']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(true, $gate['inputs']['testfixture']['ready']);
            $t->same(true, $gate['inputs']['testfixture']['executable']);
            $t->true(!in_array('.upstream-cache/libsqlite-build-port-libsqlite/testfixture executable bit', $gate['missing'], true), 'Expected executable bit blocker to be absent');
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'keeps release-all command blocked by the same focused runner inputs' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('release-blocked', ['source', 'testdir', 'testrunner']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(false, $gate['commands'][1]['runnable']);
            $t->true(in_array('.upstream-cache/libsqlite-build-port-libsqlite', $gate['commands'][1]['missing'], true), 'Expected release-all build dir blocker');
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'uses selected job count in release-all command' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('release-jobs', ['source', 'testdir', 'testrunner', 'build', 'testfixture', 'executable']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(7, $root);
            $t->contains('--jobs 7 --stop-on-error all', $gate['commands'][1]['command']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'counts runnable and blocked commands separately in partial hydration' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('partial-counts', ['source', 'testdir', 'testrunner', 'build', 'testfixture', 'executable', 'makefile']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(5, $gate['command_count']);
            $t->same(3, $gate['runnable_command_count']);
            $t->same(2, $gate['blocked_command_count']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'keeps missing paths unique when several commands share a blocker' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('unique-missing', ['source']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(count($gate['missing']), count(array_unique($gate['missing'])));
            $t->true(in_array('.upstream-cache/libsqlite-build-port-libsqlite/testfixture', $gate['missing'], true), 'Expected shared missing testfixture once');
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'records required consumers for source cache input' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('required-consumers', ['source']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(['focused', 'release-all', 'permutation-suites', 'make-test', 'mptest'], $gate['inputs']['source_cache']['required_for']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'records required consumers for permutation source input' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('permutation-consumer', ['source', 'testdir']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(['permutation-suites'], $gate['inputs']['permutation_source']['required_for']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'rejects invalid upstream runner hydration job counts' => static function (TestRunner $t): void {
        try {
            libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(0, sys_get_temp_dir());
            $t->fail('Expected invalid hydration job count to throw');
        } catch (InvalidArgumentException $exception) {
            $t->contains('jobs must be at least 1', $exception->getMessage());
        }
    },
    'keeps hydrated gate dependency closure lane local' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('dependency-note', ['source']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->contains('no new support component needed', $gate['dependency_closure']);
            $t->contains('SQLite checkout, build tree, testfixture, and harness files', $gate['dependency_closure']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'keeps blocked hydration gate from suggesting a runner launch' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_root('blocked-next-gate');
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->contains('hydrate the missing upstream source/build inputs', $gate['next_gate']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'requires provenance artifact gates after full hydration' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('full-next-gate', ['source', 'testdir', 'testrunner', 'permutations', 'mptest', 'build', 'testfixture', 'executable', 'makefile']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->contains('supervisor-approved duplicate-runner gates', $gate['next_gate']);
            $t->contains('count resulting artifacts by provenance', $gate['next_gate']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'keeps focused command pattern placeholder explicit' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('focused-placeholder', ['source', 'testdir', 'testrunner', 'build', 'testfixture', 'executable']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->contains('<patterns>', $gate['commands'][0]['command']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'keeps release command broad all explicit for launch review' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('release-command', ['source', 'testdir', 'testrunner', 'build', 'testfixture', 'executable']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->contains('--stop-on-error all', $gate['commands'][1]['command']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'keeps make and mptest commands separate for tier accounting' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('make-mptest-commands', ['source', 'build', 'makefile', 'mptest']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->contains(' test', $gate['commands'][3]['command']);
            $t->contains(' mptest', $gate['commands'][4]['command']);
            $t->same(true, $gate['commands'][3]['runnable']);
            $t->same(true, $gate['commands'][4]['runnable']);
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
    'does not count hydrated Makefile as focused testfixture readiness' => static function (TestRunner $t): void {
        $root = libsqlite_upstream_hydration_fixture('makefile-only', ['source', 'build', 'makefile']);
        try {
            $gate = libsqlite_upstream_hydration_evidence()->upstreamRunnerHydrationGate(2, $root);
            $t->same(true, $gate['commands'][3]['runnable']);
            $t->same(false, $gate['commands'][0]['runnable']);
            $t->true(in_array('.upstream-cache/libsqlite-build-port-libsqlite/testfixture', $gate['commands'][0]['missing'], true), 'Expected missing testfixture for focused runner');
        } finally {
            libsqlite_upstream_hydration_cleanup($root);
        }
    },
];
