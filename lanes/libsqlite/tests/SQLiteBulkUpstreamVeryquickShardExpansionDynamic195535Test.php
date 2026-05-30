<?php

declare(strict_types=1);

const LIBSQLITE_BULK_VQ_195535_AUDIT = __DIR__ . '/../fixtures/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T195535Z-0.audit.md';
const LIBSQLITE_BULK_VQ_195535_LOG = __DIR__ . '/../fixtures/bulk-upstream-veryquick-shard-expansion-dynamic-20260530T195535Z-0.runner.log';
const LIBSQLITE_BULK_VQ_195535_UPSTREAM = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

/**
 * @return list<string>
 */
function libsqlite_bulk_vq_195535_patterns(): array
{
    return [
        'date*.test',
        'func*.test',
        'json*.test',
        'jsonb*.test',
        'window*.test',
        'e_expr.test',
        'expr*.test',
        'select4.test',
        'select5.test',
        'select6.test',
        'select7.test',
        'select8.test',
        'select9.test',
        'selectA.test',
        'selectB.test',
        'selectC.test',
    ];
}

/**
 * @return array{exit:int, errors:int, tests:int, elapsed:int, uuid:string, version:string}
 */
function libsqlite_bulk_vq_195535_audit_record(): array
{
    $audit = file_get_contents(LIBSQLITE_BULK_VQ_195535_AUDIT);
    if (!is_string($audit)) {
        throw new RuntimeException('bulk upstream veryquick 195535 audit fixture is missing');
    }

    preg_match('/- Exit: `(\d+)`/', $audit, $exit);
    preg_match('/- Parsed errors: `(\d+)`/', $audit, $errors);
    preg_match('/- Parsed tests: `(\d+)`/', $audit, $tests);
    preg_match('/- Elapsed seconds: `(\d+)`/', $audit, $elapsed);
    preg_match('/- SQLite manifest UUID: `([^`]+)`/', $audit, $uuid);
    preg_match('/- SQLite VERSION: `([^`]+)`/', $audit, $version);

    return [
        'exit' => (int) ($exit[1] ?? -1),
        'errors' => (int) ($errors[1] ?? -1),
        'tests' => (int) ($tests[1] ?? 0),
        'elapsed' => (int) ($elapsed[1] ?? 0),
        'uuid' => (string) ($uuid[1] ?? ''),
        'version' => (string) ($version[1] ?? ''),
    ];
}

function libsqlite_bulk_vq_195535_audit_text(): string
{
    $audit = file_get_contents(LIBSQLITE_BULK_VQ_195535_AUDIT);
    if (!is_string($audit)) {
        throw new RuntimeException('bulk upstream veryquick 195535 audit fixture is missing');
    }

    return $audit;
}

$tests = [];

$tests['bulk upstream veryquick 195535 records zero-error high-volume runner summary'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_vq_195535_audit_record();

    $t->same(0, $record['exit']);
    $t->same(0, $record['errors']);
    $t->same(113596, $record['tests']);
    $t->true($record['tests'] >= 10000, 'bulk-upstream upstream-subtest floor is satisfied');
    $t->same(21, $record['elapsed']);
    $t->same('3.54.0', $record['version']);
    $t->same('9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353', $record['uuid']);
};

$tests['bulk upstream veryquick 195535 cites real hydrated upstream patterns'] = static function (TestRunner $t): void {
    $audit = libsqlite_bulk_vq_195535_audit_text();

    foreach (libsqlite_bulk_vq_195535_patterns() as $pattern) {
        $t->contains('`' . $pattern . '`', $audit);
        if (!str_contains($pattern, '*')) {
            $t->true(is_file(LIBSQLITE_BULK_VQ_195535_UPSTREAM . '/' . $pattern), $pattern . ' exists in hydrated upstream SQLite test cache');
        }
    }

    $t->contains('- Testset: `veryquick`', $audit);
    $t->contains('- Jobs: `1`', $audit);
    $t->contains('- Timeout seconds: `600`', $audit);
    $t->contains('0 errors out of 113596 tests', $audit);
};

$tests['bulk upstream veryquick 195535 preserves guarded runner gates'] = static function (TestRunner $t): void {
    $audit = libsqlite_bulk_vq_195535_audit_text();

    $t->contains('- active SQLite testfixture runners: `0`', $audit);
    $t->contains('- Source copy exit: `0`', $audit);
    $t->contains('- Build copy exit: `0`', $audit);
    $t->contains('- Stderr bytes: `0`', $audit);
    $t->contains('- `git diff --check`: exit `0`, output bytes `0`', $audit);
};

$tests['bulk upstream veryquick 195535 runner log records command completion'] = static function (TestRunner $t): void {
    $log = file_get_contents(LIBSQLITE_BULK_VQ_195535_LOG);
    if (!is_string($log)) {
        throw new RuntimeException('bulk upstream veryquick 195535 runner log fixture is missing');
    }

    $t->contains('bulk-upstream-veryquick-shard-expansion-dynamic-20260530T195535Z-0 start', $log);
    $t->contains('run SQLite testrunner testset=veryquick jobs=1 timeout=600', $log);
    $t->contains('exit=0 summary=0 errors out of 113596 tests', $log);
};

return $tests;
