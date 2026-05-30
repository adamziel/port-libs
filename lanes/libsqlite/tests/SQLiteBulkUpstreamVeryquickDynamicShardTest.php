<?php

declare(strict_types=1);

const LIBSQLITE_BULK_VQ_DYNAMIC_AUDIT = __DIR__ . '/../fixtures/bulk-upstream-veryquick-dynamic-0.audit.md';
const LIBSQLITE_BULK_VQ_DYNAMIC_LOG = __DIR__ . '/../fixtures/bulk-upstream-veryquick-dynamic-0.runner.log';
const LIBSQLITE_BULK_VQ_DYNAMIC_UPSTREAM = '/home/claude/port-libs/.upstream-cache/libsqlite/test';

/**
 * @return list<string>
 */
function libsqlite_bulk_vq_dynamic_scripts(): array
{
    return [
        'quick.test',
        'select1.test',
        'select2.test',
        'select3.test',
        'expr.test',
        'where.test',
        'join.test',
        'insert.test',
        'update.test',
        'delete.test',
    ];
}

/**
 * @return array{exit:int, errors:int, tests:int, elapsed:int, source:string, version:string}
 */
function libsqlite_bulk_vq_dynamic_audit_record(): array
{
    $audit = file_get_contents(LIBSQLITE_BULK_VQ_DYNAMIC_AUDIT);
    if (!is_string($audit)) {
        throw new RuntimeException('bulk upstream veryquick audit fixture is missing');
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

function libsqlite_bulk_vq_dynamic_audit_text(): string
{
    $audit = file_get_contents(LIBSQLITE_BULK_VQ_DYNAMIC_AUDIT);
    if (!is_string($audit)) {
        throw new RuntimeException('bulk upstream veryquick audit fixture is missing');
    }

    return $audit;
}

$tests = [];

$tests['bulk upstream veryquick dynamic run records zero-error real runner summary'] = static function (TestRunner $t): void {
    $record = libsqlite_bulk_vq_dynamic_audit_record();

    $t->same(0, $record['exit']);
    $t->same(0, $record['errors']);
    $t->same(19541, $record['tests']);
    $t->same(5, $record['elapsed']);
    $t->same('3.54.0', $record['version']);
    $t->same('9ac4a33a2932d353c4871fd8e09c10addf827f1fc3fc9380037d738cf2cd0353', $record['source']);
};

$tests['bulk upstream veryquick dynamic run is bounded to real upstream scripts'] = static function (TestRunner $t): void {
    $audit = libsqlite_bulk_vq_dynamic_audit_text();

    foreach (libsqlite_bulk_vq_dynamic_scripts() as $script) {
        $t->contains('`' . $script . '`', $audit);
        $t->true(is_file(LIBSQLITE_BULK_VQ_DYNAMIC_UPSTREAM . '/' . $script), $script . ' exists in hydrated upstream SQLite test cache');
    }

    $t->contains('- Testset: `veryquick`', $audit);
    $t->contains('- Jobs: `1`', $audit);
    $t->contains('- Timeout seconds: `600`', $audit);
};

$tests['bulk upstream veryquick dynamic run preserves guarded runner gate data'] = static function (TestRunner $t): void {
    $audit = libsqlite_bulk_vq_dynamic_audit_text();

    $t->contains('- active SQLite testfixture runners: `0`', $audit);
    $t->contains('- loadavg: `', $audit);
    $t->contains('- MemAvailable: `', $audit);
    $t->contains('- root free: `', $audit);
    $t->contains('- Source copy exit: `0`', $audit);
    $t->contains('- Build copy exit: `0`', $audit);
    $t->contains('- Stderr bytes: `0`', $audit);
    $t->contains('- `git diff --check`: exit `0`, output bytes `0`', $audit);
};

$tests['bulk upstream veryquick dynamic run cites exact command output log'] = static function (TestRunner $t): void {
    $log = file_get_contents(LIBSQLITE_BULK_VQ_DYNAMIC_LOG);
    if (!is_string($log)) {
        throw new RuntimeException('bulk upstream veryquick runner log fixture is missing');
    }

    $t->contains('bulk-upstream-veryquick-dynamic-0 start', $log);
    $t->contains('run SQLite testrunner testset=veryquick jobs=1 timeout=600', $log);
    $t->contains('complete audit=/home/claude/port-libs/lanes/libsqlite/fixtures/bulk-upstream-veryquick-dynamic-0.audit.md exit=0 summary=0 errors out of 19541 tests', $log);
};

return $tests;
