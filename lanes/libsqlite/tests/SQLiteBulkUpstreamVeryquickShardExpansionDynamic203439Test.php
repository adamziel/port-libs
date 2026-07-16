<?php

declare(strict_types=1);

const LIBSQLITE_BULK_VQ_EXPANSION_203439_AUDIT = __DIR__ . '/../fixtures/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T203439Z-0.audit.md';
const LIBSQLITE_BULK_VQ_EXPANSION_203439_LOG = __DIR__ . '/../fixtures/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T203439Z-0.runner.log';
const LIBSQLITE_BULK_VQ_EXPANSION_203439_UPSTREAM = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

/**
 * @return list<string>
 */
function libsqlite_bulk_vq_expansion_203439_scripts(): array
{
    return [
        'fts-9fd058691.test',
        'fuzz-oss1.test',
        'quota-glob.test',
        'tkt-02a8e81d44.test',
        'tkt-18458b1a.test',
        'tkt-26ff0c2d1e.test',
        'tkt-2a5629202f.test',
        'tkt-2d1a5c67d.test',
        'tkt-2ea2425d34.test',
        'tkt-31338dca7e.test',
        'tkt-313723c356.test',
        'tkt-385a5b56b9.test',
        'tkt-38cb5df375.test',
        'tkt-3998683a16.test',
        'tkt-3a77c9714e.test',
        'tkt-3fe897352e.test',
        'tkt-4a03edc4c8.test',
        'tkt-4c86b126f2.test',
        'tkt-4dd95f6943.test',
        'tkt-4ef7e3cfca.test',
        'tkt-54844eea3f.test',
        'tkt-5d863f876e.test',
        'tkt-5e10420e8d.test',
        'tkt-5ee23731f.test',
        'tkt-6bfb98dfc0.test',
        'tkt-752e1646fc.test',
        'tkt-78e04e52ea.test',
        'tkt-7a31705a7e6.test',
        'tkt-7bbfb7d442.test',
        'tkt-80ba201079.test',
        'tkt-80e031a00f.test',
        'tkt-8454a207b9.test',
        'tkt-868145d012.test',
        'tkt-8c63ff0ec.test',
        'tkt-91e2e8ba6f.test',
        'tkt-99378177930f87bd.test',
        'tkt-9a8b09f8e6.test',
        'tkt-9d68c883.test',
        'tkt-9f2eb3abac.test',
        'tkt-a7b7803e.test',
        'tkt-a7debbe0.test',
        'tkt-a8a0d2996a.test',
        'tkt-b1d3a2e531.test',
        'tkt-b351d95f9.test',
        'tkt-b72787b1.test',
        'tkt-b75a9ca6b0.test',
        'tkt-ba7cbfaedc.test',
        'tkt-bd484a090c.test',
        'tkt-bdc6bbbb38.test',
        'tkt-c48d99d690.test',
        'tkt-c694113d5.test',
        'tkt-cbd054fa6b.test',
        'tkt-d11f09d36e.test',
        'tkt-d635236375.test',
        'tkt-d82e3f3721.test',
        'tkt-f3e5abed55.test',
        'tkt-f67b41381a.test',
        'tkt-f777251dc7a.test',
        'tkt-f7b4edec.test',
        'tkt-f973c7ac31.test',
        'tkt-fa7bf5ec.test',
        'tkt-fc62af4523.test',
        'tkt-fc7bd6358f.test',
        'vacuum-into.test',
    ];
}

/**
 * @return array{exit:int, errors:int, tests:int, elapsed:int, source:string, version:string}
 */
function libsqlite_bulk_vq_expansion_203439_record(): array
{
    $audit = file_get_contents(LIBSQLITE_BULK_VQ_EXPANSION_203439_AUDIT);
    if (!is_string($audit)) {
        throw new RuntimeException('bulk upstream veryquick shard expansion audit fixture is missing');
    }

    preg_match('/- Exit: `(\d+)`/', $audit, $exit);
    preg_match('/- Parsed errors: `(\d+)`/', $audit, $errors);
    preg_match('/- Parsed tests: `(\d+)`/', $audit, $tests);
    preg_match('/- Elapsed seconds: `(\d+)`/', $audit, $elapsed);
    preg_match('/- SQLite manifest UUID: `([^`]+)`/', $audit, $source);
    preg_match('/- SQLite VERSION: `([^`]+)`/', $audit, $version);

    return [
        'exit' => (int) ($exit[1] ?? -1),
        'errors' => (int) ($errors[1] ?? -1),
        'tests' => (int) ($tests[1] ?? 0),
        'elapsed' => (int) ($elapsed[1] ?? 0),
        'source' => (string) ($source[1] ?? ''),
        'version' => (string) ($version[1] ?? ''),
    ];
}

function libsqlite_bulk_vq_expansion_203439_audit_text(): string
{
    $audit = file_get_contents(LIBSQLITE_BULK_VQ_EXPANSION_203439_AUDIT);
    if (!is_string($audit)) {
        throw new RuntimeException('bulk upstream veryquick shard expansion audit fixture is missing');
    }

    return $audit;
}

$tests = [];

$tests['bulk upstream veryquick shard expansion 203439 records zero-error runner evidence'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_vq_expansion_203439_record();

    $t->same(0, $record['exit']);
    $t->same(0, $record['errors']);
    $t->same(1721, $record['tests']);
    $t->true($record['elapsed'] > 0 && $record['elapsed'] <= 600);
    $t->same('3.54.0', $record['version']);
    $t->same('9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353', $record['source']);
};

$tests['bulk upstream veryquick shard expansion 203439 uses only real hydrated upstream scripts'] = static function (TestRunner $t): void {
    $audit = libsqlite_bulk_vq_expansion_203439_audit_text();

    foreach (libsqlite_bulk_vq_expansion_203439_scripts() as $script) {
        $t->contains('`' . $script . '`', $audit);
        $t->true(is_file(LIBSQLITE_BULK_VQ_EXPANSION_203439_UPSTREAM . '/' . $script), $script . ' exists in hydrated upstream SQLite test cache');
    }

    $t->contains('- Testset: `veryquick`', $audit);
    $t->contains('- Jobs: `1`', $audit);
    $t->contains('- Timeout seconds: `600`', $audit);
};

$tests['bulk upstream veryquick shard expansion 203439 preserves guarded runner gates'] = static function (TestRunner $t): void {
    $audit = libsqlite_bulk_vq_expansion_203439_audit_text();

    $t->contains('- active SQLite testfixture runners: `0`', $audit);
    $t->contains('- loadavg: `', $audit);
    $t->contains('- MemAvailable: `', $audit);
    $t->contains('- root free: `', $audit);
    $t->contains('- Source copy exit: `0`', $audit);
    $t->contains('- Build copy exit: `0`', $audit);
    $t->contains('- Stderr bytes: `0`', $audit);
    $t->contains('- `git diff --check`: exit `0`, output bytes `0`', $audit);
};

$tests['bulk upstream veryquick shard expansion 203439 cites exact runner log'] = static function (TestRunner $t): void {
    $log = file_get_contents(LIBSQLITE_BULK_VQ_EXPANSION_203439_LOG);
    if (!is_string($log)) {
        throw new RuntimeException('bulk upstream veryquick shard expansion runner log fixture is missing');
    }

    $t->contains('bulk-upstream-veryquick-shard-expansion-dynamic-20260530T203439Z-0 start', $log);
    $t->contains('run SQLite testrunner testset=veryquick jobs=1 timeout=600', $log);
    $t->contains('complete audit=', $log);
    $t->contains('summary=0 errors out of 1721 tests', $log);
};

return $tests;
