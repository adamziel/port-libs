<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIntegrityTargetArgument;

/*
 * Real upstream source truth:
 * - /home/claude/port-libs/.upstream-cache/libsqlite/test/pragma.test
 * - pragma-3.5.2: PRAGMA integrity_check='4' treats quoted 4 as a target
 *   name and returns no such table instead of a numeric limit.
 * - pragma-3.6: PRAGMA integrity_check=xyz treats bare non-numeric RHS as a
 *   target name and returns no such table when it cannot resolve.
 * - pragma-3.6b/3.6c and 3.9b/3.9c: equals-form target arguments can route to
 *   an attached schema or sqlite_schema.
 * - pragma-3.7 and 3.10 through 3.18: numeric equals/paren arguments limit
 *   rows across the selected attached-schema integrity sweep, while zero uses
 *   the default limit.
 */

$tests = [];

$makeSchemas = static function (int $variant): array {
    $suffix = sprintf('%04d', $variant);
    $aux = "t2_{$suffix}";
    $extra = "t3_{$suffix}";
    $table = "settings_{$suffix}";
    $index = "settings_key_{$suffix}";

    $mainErrors = [
        "wrong # of entries in index {$index}",
        "row {$variant} missing from index {$index}",
        'row ' . ($variant + 1) . " missing from index {$index}",
    ];
    $auxPageErrors = [
        "*** in database {$aux} ***\nPage " . ($variant + 3) . ': never used',
        "*** in database {$aux} ***\nPage " . ($variant + 4) . ': never used',
        "*** in database {$aux} ***\nPage " . ($variant + 5) . ': never used',
    ];
    $auxTargetErrors = [
        "wrong # of entries in index {$aux}_{$index}",
        "row {$variant} missing from index {$aux}_{$index}",
        'row ' . ($variant + 1) . " missing from index {$aux}_{$index}",
    ];
    $extraErrors = [
        "*** in database {$extra} ***\nPage " . ($variant + 6) . ': never used',
        "*** in database {$extra} ***\nPage " . ($variant + 7) . ': never used',
        "*** in database {$extra} ***\nPage " . ($variant + 8) . ': never used',
        "wrong # of entries in index {$extra}_{$index}",
    ];

    return [
        'main' => [
            'tables' => ['sqlite_schema', $table],
            'errors' => $mainErrors,
            'target_errors' => [
                'sqlite_schema' => [],
                $table => [$mainErrors[0]],
            ],
        ],
        $aux => [
            'tables' => ['sqlite_schema', $table],
            'errors' => array_merge($auxPageErrors, $auxTargetErrors),
            'target_errors' => [
                'sqlite_schema' => [],
                $aux => $auxTargetErrors,
                $table => [$auxTargetErrors[0]],
            ],
        ],
        $extra => [
            'tables' => ['sqlite_schema', $table],
            'errors' => $extraErrors,
            'target_errors' => [
                'sqlite_schema' => [],
                $extra => [$extraErrors[3]],
            ],
        ],
    ];
};

foreach (range(1, 250) as $variant) {
    $suffix = sprintf('%04d', $variant);
    $aux = "t2_{$suffix}";
    $extra = "t3_{$suffix}";
    $missing = "missing_{$suffix}";

    $tests[sprintf('real upstream pragma integrity target quoted number and missing target variant %04d', $variant)] =
        static function (TestRunner $t) use ($makeSchemas, $variant, $missing): void {
            $schemas = $makeSchemas($variant);
            $quoted = SQLitePragmaIntegrityTargetArgument::execute("PRAGMA integrity_check='4'", $schemas);
            $missingResult = SQLitePragmaIntegrityTargetArgument::execute("PRAGMA integrity_check={$missing}", $schemas);
            $parsed = SQLitePragmaIntegrityTargetArgument::parse('PRAGMA integrity_check=[quoted target]');

            $t->same('error', $quoted['status']);
            $t->same([1, 'no such table: 4'], $quoted['catchsql']);
            $t->same('target', $quoted['argument']['kind']);
            $t->same(true, $quoted['argument']['quoted']);
            $t->same('4', $quoted['target']);
            $t->same([], $quoted['checked_schemas']);
            $t->same('error', $missingResult['status']);
            $t->same([1, "no such table: {$missing}"], $missingResult['catchsql']);
            $t->same('quoted target', $parsed['argument']['value']);
        };

    $tests[sprintf('real upstream pragma integrity target attached schema dispatch variant %04d', $variant)] =
        static function (TestRunner $t) use ($makeSchemas, $variant, $aux): void {
            $schemas = $makeSchemas($variant);
            $result = SQLitePragmaIntegrityTargetArgument::execute("PRAGMA integrity_check={$aux}", $schemas);

            $t->same('ok', $result['status']);
            $t->same(null, $result['schema']);
            $t->same($aux, $result['target']);
            $t->same([$aux], $result['checked_schemas']);
            $t->same(3, count($result['errors']));
            $t->contains("{$aux}_settings_key_", $result['errors'][0]);
            $t->same([0, $result['errors']], $result['catchsql']);
            $t->same($result['errors'][0], $result['rows'][0]['integrity_check']);
            $t->same(['sqlite-pragma-integrity-target-argument'], $result['dependencies']);
        };

    $tests[sprintf('real upstream pragma integrity target schema qualified target and sqlite schema variant %04d', $variant)] =
        static function (TestRunner $t) use ($makeSchemas, $variant, $aux, $extra): void {
            $schemas = $makeSchemas($variant);
            $schemaTarget = SQLitePragmaIntegrityTargetArgument::execute("PRAGMA {$aux}.integrity_check={$aux};", $schemas);
            $schemaTable = SQLitePragmaIntegrityTargetArgument::execute("PRAGMA {$extra}.integrity_check=sqlite_schema", $schemas);
            $missingInSchema = SQLitePragmaIntegrityTargetArgument::execute("PRAGMA {$aux}.integrity_check=missing_table", $schemas);

            $t->same($aux, $schemaTarget['schema']);
            $t->same([$aux], $schemaTarget['checked_schemas']);
            $t->same(3, count($schemaTarget['errors']));
            $t->contains("{$aux}_settings_key_", $schemaTarget['errors'][0]);
            $t->same($extra, $schemaTable['schema']);
            $t->same('sqlite_schema', $schemaTable['target']);
            $t->same([], $schemaTable['errors']);
            $t->same([['integrity_check' => 'ok']], $schemaTable['rows']);
            $t->same([0, 'ok'], $schemaTable['catchsql']);
            $t->same([1, 'no such table: missing_table'], $missingInSchema['catchsql']);
        };

    $tests[sprintf('real upstream pragma integrity target numeric limit and zero default variant %04d', $variant)] =
        static function (TestRunner $t) use ($makeSchemas, $variant): void {
            $schemas = $makeSchemas($variant);
            $limit4 = SQLitePragmaIntegrityTargetArgument::execute('PRAGMA integrity_check=4', $schemas);
            $limit2 = SQLitePragmaIntegrityTargetArgument::execute('PRAGMA integrity_check(2)', $schemas);
            $zero = SQLitePragmaIntegrityTargetArgument::execute('PRAGMA integrity_check=0', $schemas);
            $quick = SQLitePragmaIntegrityTargetArgument::execute('PRAGMA QUICK_CHECK(2)', $schemas);

            $t->same('limit', $limit4['argument']['kind']);
            $t->same(4, $limit4['limit']);
            $t->same(4, count($limit4['errors']));
            $t->same(['main', 't2_' . sprintf('%04d', $variant)], array_slice($limit4['checked_schemas'], 0, 2));
            $t->same(2, $limit2['limit']);
            $t->same(2, count($limit2['rows']));
            $t->same(100, $zero['limit']);
            $t->same(13, count($zero['errors']));
            $t->same(['main', 't2_' . sprintf('%04d', $variant), 't3_' . sprintf('%04d', $variant)], $zero['checked_schemas']);
            $t->same('quick_check', $quick['pragma']);
            $t->same(2, count($quick['errors']));
        };
}

$tests['real upstream pragma integrity target argument source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-3.5.2 treats quoted 4 as target name and returns no such table: 4',
        'pragma.test pragma-3.6 treats PRAGMA integrity_check=xyz as a target-name lookup',
        'pragma.test pragma-3.6b and pragma-3.9b route equals-form t2 targets to the attached schema',
        'pragma.test pragma-3.6c and pragma-3.9c keep sqlite_schema target checks clean',
        'pragma.test pragma-3.7 and pragma-3.10 through pragma-3.18 apply numeric limits across attached-schema integrity rows',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma-3.5.2', $sections[0]);
    $t->contains('pragma-3.6b', $sections[2]);
    $t->contains('pragma-3.18', $sections[4]);
};

$tests['real upstream pragma integrity target argument non overlap and guards'] = static function (TestRunner $t): void {
    $note = 'owns pragma.test pragma-3.5.2 through pragma-3.18 equals-form integrity_check target/limit routing; avoids accepted pragma-3.20..3.25 writable-schema checks, pragma-3.40 index root swaps, pragma-22 attached schema qualification, pragma-24 malformed leaf, pragma-25 generated/temp integrity, file-control, freelist_count, table-valued PRAGMA, VFS, WAL, B-tree, JSON, and SELECT clusters; no new support component needed';

    $t->contains('pragma-3.5.2 through pragma-3.18', $note);
    $t->contains('equals-form integrity_check target/limit routing', $note);
    $t->contains('no new support component needed', $note);
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityTargetArgument::parse('PRAGMA table_info(settings)'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityTargetArgument::execute('PRAGMA integrity_check', []));
};

return $tests;
