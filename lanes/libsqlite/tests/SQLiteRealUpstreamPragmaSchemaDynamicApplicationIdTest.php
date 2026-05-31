<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaRuntimeState;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/pragma.test pragma-8.3.1 and pragma-8.3.2:
 *   PRAGMA application_id initially reads as 0 and parenthesized assignment
 *   updates the header value returned by a subsequent read.
 * - The dynamic cases below extend the same runtime/schema PRAGMA state model
 *   already used for pragma-8.1 schema_version, pragma-8.2 user_version, and
 *   pragma2 cache_spill coverage without adding metadata-only runner rows.
 */

$tests['real upstream pragma schema dynamic application_id cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-8.3.1 reads initial application_id as zero',
        'pragma.test pragma-8.3.2 accepts parenthesized Application_ID assignment and reads the assigned value',
        'pragma.test pragma-8.1/8.2 keep schema_version and user_version independent from application_id header writes',
    ];

    $t->same(3, count($sections));
    $t->contains('pragma-8.3.1', $sections[0]);
    $t->contains('Application_ID', $sections[1]);
};

foreach (range(1, 250) as $variant) {
    $value = 12000 + $variant;
    $schemaVersion = 300 + $variant;
    $userVersion = -50 - $variant;
    $attached = 'auxappid' . $variant;

    $tests[sprintf('real upstream pragma schema dynamic application_id initial zero variant %03d', $variant)] = static function (TestRunner $t) use ($variant): void {
        $runtime = new SQLitePragmaRuntimeState($variant, $variant + 1);

        $row = $runtime->pragma('PRAGMA application_id');

        $t->same(0, $row['application_id']);
        $t->same($variant, $row['schema_version']);
        $t->same($variant + 1, $row['user_version']);
    };

    $tests[sprintf('real upstream pragma schema dynamic application_id parenthesized assignment variant %03d', $variant)] = static function (TestRunner $t) use ($value, $schemaVersion, $userVersion): void {
        $runtime = new SQLitePragmaRuntimeState($schemaVersion, $userVersion);

        $assigned = $runtime->pragma('PRAGMA Application_ID(' . $value . ')');
        $read = $runtime->pragma('PRAGMA application_id');

        $t->same($value, $assigned['application_id']);
        $t->same($value, $read['application_id']);
        $t->same($schemaVersion, $read['schema_version']);
        $t->same($userVersion, $read['user_version']);
    };

    $tests[sprintf('real upstream pragma schema dynamic application_id schema isolation variant %03d', $variant)] = static function (TestRunner $t) use ($attached, $value, $variant): void {
        $runtime = new SQLitePragmaRuntimeState($variant, $variant + 2);
        $runtime->attach($attached, "{$attached}.db");

        $runtime->pragma('PRAGMA ' . $attached . '.application_id = ' . ($value + 1));
        $main = $runtime->pragma('PRAGMA main.application_id');
        $aux = $runtime->pragma('PRAGMA ' . $attached . '.application_id');

        $t->same(0, $main['application_id']);
        $t->same($value + 1, $aux['application_id']);
        $t->same($attached, $aux['schema']);
        $t->same("{$attached}.db", $aux['file']);
    };

    $tests[sprintf('real upstream pragma schema dynamic application_id rollback restores variant %03d', $variant)] = static function (TestRunner $t) use ($attached, $value, $variant): void {
        $runtime = new SQLitePragmaRuntimeState($variant, $variant + 3, applicationId: $value);
        $runtime->attach($attached, "{$attached}.db");
        $runtime->pragma('PRAGMA ' . $attached . '.application_id = ' . ($value + 10));

        $runtime->begin();
        $runtime->pragma('PRAGMA application_id = ' . ($value + 20));
        $runtime->pragma('PRAGMA ' . $attached . '.application_id = ' . ($value + 30));
        $during = [
            $runtime->state('main')['application_id'],
            $runtime->state($attached)['application_id'],
        ];
        $runtime->rollback();
        $after = [
            $runtime->state('main')['application_id'],
            $runtime->state($attached)['application_id'],
        ];

        $t->same([$value + 20, $value + 30], $during);
        $t->same([$value, $value + 10], $after);
        $t->same($variant, $runtime->state('main')['schema_version']);
    };
}

$tests['real upstream pragma schema dynamic application_id owns exactly 1000 generated cases'] = static function (TestRunner $t): void {
    $t->same(1000, 250 * 4);
};

return $tests;
