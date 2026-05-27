<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpstreamSuiteEvidence;

$tests = [];

$manifestPath = __DIR__ . '/../UPSTREAM_TEST_MANIFEST.json';

$withHydratedFixture = static function (callable $callback): mixed {
    $root = sys_get_temp_dir() . '/libsqlite-focused-subset-next11-' . bin2hex(random_bytes(4));
    $build = $root . '/.upstream-cache/libsqlite-build-port-libsqlite';
    $test = $root . '/.upstream-cache/libsqlite/test';
    mkdir($build, 0777, true);
    mkdir($test, 0777, true);
    file_put_contents($build . '/testfixture', '#!/bin/sh');
    file_put_contents($test . '/testrunner.tcl', '# testrunner fixture');

    try {
        return $callback($root);
    } finally {
        @unlink($build . '/testfixture');
        @unlink($test . '/testrunner.tcl');
        @rmdir($test);
        @rmdir($root . '/.upstream-cache/libsqlite');
        @rmdir($build);
        @rmdir($root . '/.upstream-cache');
        @rmdir($root);
    }
};

$readyCases = [
    'single pager test' => ['pager1.test', 1],
    'single wal test' => ['wal.test', 2],
    'single json test' => ['json101.test', 3],
    'single btree test' => ['btree01.test', 4],
    'single select test' => ['select1.test', 5],
    'single expr test' => ['expr.test', 6],
    'single trigger test' => ['trigger1.test', 7],
    'single fk test' => ['fkey1.test', 8],
    'single pragma test' => ['pragma.test', 9],
    'single collate test' => ['collate1.test', 10],
    'single like test' => ['like.test', 11],
    'single enc test' => ['enc.test', 12],
    'single e_fkey path test' => ['../libsqlite/test/e_fkey.test', 13],
    'single ext fts path test' => ['../libsqlite/ext/fts5/test/fts5aux.test', 14],
    'single mptest path test' => ['../libsqlite/mptest/multiwrite01.test', 15],
];

foreach ($readyCases as $name => [$script, $jobs]) {
    $tests['focused subset next11 ready ' . $name] = static function (TestRunner $t) use ($manifestPath, $withHydratedFixture, $script, $jobs): void {
        $record = $withHydratedFixture(static function (string $root) use ($manifestPath, $script, $jobs): array {
            return SQLiteUpstreamSuiteEvidence::fromManifestPath($manifestPath)
                ->focusedSubsetRunRecord('subset-' . basename($script, '.test'), [$script], $jobs, $root);
        });

        $t->same('ready', $record['status']);
        $t->same(true, $record['runnable']);
        $t->same([$script], $record['scripts']);
        $t->same($jobs, $record['jobs']);
        $t->contains('--jobs ' . $jobs, $record['command']);
        $t->contains($script, $record['command']);
    };
}

$passedCases = [
    'pager zero error' => [['pager1.test'], 1, 1, 24],
    'wal zero error' => [['wal.test'], 2, 1, 31],
    'json pair zero error' => [['json101.test', 'json102.test'], 3, 2, 812],
    'btree pair zero error' => [['btree01.test', 'btree02.test'], 4, 2, 143],
    'select pair zero error' => [['select1.test', 'select2.test'], 5, 2, 401],
    'constraint trio zero error' => [['fkey1.test', 'trigger1.test', 'check.test'], 6, 3, 250],
    'encoding trio zero error' => [['enc.test', 'collate1.test', 'like.test'], 7, 3, 182],
    'pragma pair zero error' => [['pragma.test', 'pragma2.test'], 8, 2, 116],
    'fts sanitizer focused zero error' => [['../libsqlite/ext/fts5/test/fts5aux.test'], 9, 1, 1],
    'mptest focused zero error' => [['../libsqlite/mptest/multiwrite01.test'], 10, 1, 9],
];

foreach ($passedCases as $name => [$scripts, $jobs, $scriptCount, $testCount]) {
    $tests['focused subset next11 passed ' . $name] = static function (TestRunner $t) use ($manifestPath, $withHydratedFixture, $scripts, $jobs, $scriptCount, $testCount): void {
        $record = $withHydratedFixture(static function (string $root) use ($manifestPath, $scripts, $jobs, $scriptCount, $testCount): array {
            return SQLiteUpstreamSuiteEvidence::fromManifestPath($manifestPath)
                ->focusedSubsetRunRecord(
                    'passed-subset',
                    $scripts,
                    $jobs,
                    $root,
                    sprintf('Passed %d Tcl scripts with 0 errors out of %d tests', $scriptCount, $testCount)
                );
        });

        $t->same('passed', $record['status']);
        $t->same($scriptCount, $record['result_scripts']);
        $t->same($testCount, $record['result_tests']);
        $t->same(0, $record['result_errors']);
        $t->same($scripts, $record['scripts']);
    };
}

$failedCases = [
    'pager one error' => [['pager1.test'], 1, 1, 24],
    'wal two errors' => [['wal.test'], 2, 1, 31],
    'json pair one error' => [['json101.test', 'json102.test'], 3, 2, 812],
    'btree pair three errors' => [['btree01.test', 'btree02.test'], 4, 2, 143],
    'select pair one error' => [['select1.test', 'select2.test'], 5, 2, 401],
    'constraint trio two errors' => [['fkey1.test', 'trigger1.test', 'check.test'], 6, 3, 250],
    'encoding trio one error' => [['enc.test', 'collate1.test', 'like.test'], 7, 3, 182],
    'pragma pair one error' => [['pragma.test', 'pragma2.test'], 8, 2, 116],
    'fts sanitizer focused error' => [['../libsqlite/ext/fts5/test/fts5aux.test'], 9, 1, 1],
    'mptest focused error' => [['../libsqlite/mptest/multiwrite01.test'], 10, 1, 9],
];

$failedIndex = 0;
foreach ($failedCases as $name => [$scripts, $jobs, $scriptCount, $testCount]) {
    $failedIndex++;
    $errors = (($failedIndex - 1) % 3) + 1;
    $tests['focused subset next11 failed ' . $name] = static function (TestRunner $t) use ($manifestPath, $withHydratedFixture, $scripts, $jobs, $scriptCount, $testCount, $errors): void {
        $record = $withHydratedFixture(static function (string $root) use ($manifestPath, $scripts, $jobs, $scriptCount, $testCount, $errors): array {
            return SQLiteUpstreamSuiteEvidence::fromManifestPath($manifestPath)
                ->focusedSubsetRunRecord(
                    'failed-subset',
                    $scripts,
                    $jobs,
                    $root,
                    sprintf('Passed %d Tcl scripts with %d errors out of %d tests', $scriptCount, $errors, $testCount)
                );
        });

        $t->same('failed', $record['status']);
        $t->same($scriptCount, $record['result_scripts']);
        $t->same($testCount, $record['result_tests']);
        $t->same($errors, $record['result_errors']);
    };
}

$skipCases = [
    'missing cache single' => ['pager1.test', 1],
    'missing cache pair' => ['json101.test', 2],
    'missing cache wildcard' => ['wal*.test', 3],
    'missing cache path' => ['../libsqlite/test/e_fkey.test', 4],
    'missing cache ext path' => ['../libsqlite/ext/fts5/test/fts5aux.test', 5],
    'missing cache mptest path' => ['../libsqlite/mptest/multiwrite01.test', 6],
    'missing cache pragma' => ['pragma.test', 7],
    'missing cache collate' => ['collate1.test', 8],
    'missing cache like' => ['like.test', 9],
    'missing cache enc' => ['enc.test', 10],
];

foreach ($skipCases as $name => [$script, $jobs]) {
    $tests['focused subset next11 skipped ' . $name] = static function (TestRunner $t) use ($manifestPath, $script, $jobs): void {
        $root = sys_get_temp_dir() . '/missing-libsqlite-focused-subset-' . bin2hex(random_bytes(4));
        $record = SQLiteUpstreamSuiteEvidence::fromManifestPath($manifestPath)
            ->focusedSubsetRunRecord('skipped-subset', [$script], $jobs, $root);

        $t->same('skipped', $record['status']);
        $t->same(false, $record['runnable']);
        $t->contains('upstream cache/testfixture not hydrated', (string) $record['skip_reason']);
        $t->same([$script], $record['scripts']);
    };
}

$invalidCases = [
    'empty script' => '',
    'not a test file' => 'pager1.tcl',
    'absolute path' => '/tmp/pager1.test',
    'shell separator' => 'pager1.test;rm.test',
    'space in name' => 'pager one.test',
];

foreach ($invalidCases as $name => $script) {
    $tests['focused subset next11 rejects invalid script ' . $name] = static function (TestRunner $t) use ($manifestPath, $script): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteUpstreamSuiteEvidence::fromManifestPath($manifestPath)
            ->focusedSubsetRunRecord('invalid-subset', [$script]));
    };
}

return $tests;
