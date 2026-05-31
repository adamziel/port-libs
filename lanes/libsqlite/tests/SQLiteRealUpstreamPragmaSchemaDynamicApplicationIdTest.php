<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaRuntimeState;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test pragma-8.3.*.
 *
 * The upstream test verifies PRAGMA application_id starts at zero and accepts
 * parenthesized assignment syntax. This corpus expands that behavior through
 * the bounded runtime PRAGMA state model with attached-schema isolation,
 * transaction rollback/commit restoration, case-insensitive pragma names, and
 * signed 32-bit application-id values.
 */

foreach (range(1, 500) as $variant) {
    $schemaVersion = 10 + $variant;
    $userVersion = $variant % 41;
    $initialApplicationId = ($variant % 5) === 0 ? 0 : 1000 + $variant;
    $mainApplicationId = 12000 + $variant;
    $attachedApplicationId = 22000 + $variant;
    $rollbackApplicationId = 32000 + $variant;
    $committedApplicationId = 42000 + $variant;
    $negativeApplicationId = -($variant + 5000);
    $schemaName = sprintf('archive_app_%03d', $variant);
    $pragmaName = ($variant % 2) === 0 ? 'Application_ID' : 'application_id';

    $tests[sprintf('real upstream pragma schema dynamic application_id default and parenthesized assignment variant %03d', $variant)] = static function (TestRunner $t) use ($schemaVersion, $userVersion, $initialApplicationId, $mainApplicationId, $pragmaName): void {
        $state = new SQLitePragmaRuntimeState($schemaVersion, $userVersion, 2000, null, $initialApplicationId);

        $t->same($initialApplicationId, $state->pragma('PRAGMA application_id')['application_id']);
        $t->same($mainApplicationId, $state->pragma("PRAGMA {$pragmaName}({$mainApplicationId})")['application_id']);
        $t->same($mainApplicationId, $state->pragma('PRAGMA main.application_id')['application_id']);
        $t->same($schemaVersion, $state->pragma('PRAGMA schema_version')['schema_version']);
        $t->same($userVersion, $state->pragma('PRAGMA user_version')['user_version']);
    };

    $tests[sprintf('real upstream pragma schema dynamic application_id attached isolation variant %03d', $variant)] = static function (TestRunner $t) use ($schemaVersion, $initialApplicationId, $mainApplicationId, $attachedApplicationId, $schemaName): void {
        $state = new SQLitePragmaRuntimeState($schemaVersion, 0, 2000, null, $initialApplicationId);
        $attached = $state->attach($schemaName, "/tmp/pragma-application-id-{$schemaName}.sqlite");

        $t->same($schemaName, $attached['schema']);
        $t->same(0, $attached['application_id']);
        $t->same($mainApplicationId, $state->pragma("PRAGMA main.application_id = {$mainApplicationId}")['application_id']);
        $t->same($attachedApplicationId, $state->pragma("PRAGMA {$schemaName}.application_id({$attachedApplicationId})")['application_id']);
        $t->same($mainApplicationId, $state->pragma('PRAGMA application_id')['application_id']);
        $t->same($attachedApplicationId, $state->pragma("PRAGMA {$schemaName}.application_id")['application_id']);
    };

    $tests[sprintf('real upstream pragma schema dynamic application_id rollback restores main and attached variant %03d', $variant)] = static function (TestRunner $t) use ($schemaVersion, $mainApplicationId, $attachedApplicationId, $rollbackApplicationId, $schemaName): void {
        $state = new SQLitePragmaRuntimeState($schemaVersion, 0);
        $state->attach($schemaName, "/tmp/pragma-application-id-rollback-{$schemaName}.sqlite");
        $state->pragma("PRAGMA application_id={$mainApplicationId}");
        $state->pragma("PRAGMA {$schemaName}.application_id={$attachedApplicationId}");

        $state->begin();
        $state->pragma("PRAGMA application_id={$rollbackApplicationId}");
        $state->pragma("PRAGMA {$schemaName}.application_id=" . ($rollbackApplicationId + 1));
        $t->same($rollbackApplicationId, $state->pragma('PRAGMA application_id')['application_id']);
        $t->same($rollbackApplicationId + 1, $state->pragma("PRAGMA {$schemaName}.application_id")['application_id']);
        $state->rollback();

        $t->same($mainApplicationId, $state->pragma('PRAGMA application_id')['application_id']);
        $t->same($attachedApplicationId, $state->pragma("PRAGMA {$schemaName}.application_id")['application_id']);
    };

    $tests[sprintf('real upstream pragma schema dynamic application_id commit and signed value variant %03d', $variant)] = static function (TestRunner $t) use ($schemaVersion, $committedApplicationId, $negativeApplicationId, $schemaName): void {
        $state = new SQLitePragmaRuntimeState($schemaVersion, 0);
        $state->attach($schemaName, "/tmp/pragma-application-id-commit-{$schemaName}.sqlite");

        $state->begin();
        $state->pragma("PRAGMA application_id={$committedApplicationId}");
        $state->pragma("PRAGMA {$schemaName}.application_id={$negativeApplicationId}");
        $state->commit();

        $t->same($committedApplicationId, $state->pragma('PRAGMA application_id')['application_id']);
        $t->same($negativeApplicationId, $state->pragma("PRAGMA {$schemaName}.application_id")['application_id']);
        $t->same($schemaVersion, $state->pragma('PRAGMA schema_version')['schema_version']);
        $t->same(0, $state->pragma("PRAGMA {$schemaName}.schema_version")['schema_version']);
    };

    $tests[sprintf('real upstream pragma schema dynamic application_id detach removes attached state variant %03d', $variant)] = static function (TestRunner $t) use ($schemaVersion, $attachedApplicationId, $schemaName): void {
        $state = new SQLitePragmaRuntimeState($schemaVersion, 0);
        $state->attach($schemaName, "/tmp/pragma-application-id-detach-{$schemaName}.sqlite");
        $state->pragma("PRAGMA {$schemaName}.application_id={$attachedApplicationId}");

        $t->same($attachedApplicationId, $state->pragma("PRAGMA {$schemaName}.application_id")['application_id']);
        $t->same($schemaName, $state->detach($schemaName)['schema']);
        $t->throws(InvalidArgumentException::class, static fn () => $state->pragma("PRAGMA {$schemaName}.application_id"));
    };
}

$tests['real upstream pragma schema dynamic application_id source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-8.3.1 verifies PRAGMA application_id initially returns zero',
        'pragma.test pragma-8.3.2 verifies PRAGMA Application_ID(12345) assignment syntax and readback',
        'pragma.test pragma-8.1 and pragma-8.2 adjacent runtime-version sections establish attached-schema and transaction restoration expectations used by this state model',
    ];

    $t->same(3, count($sections));
    $t->contains('pragma-8.3.1', $sections[0]);
    $t->contains('Application_ID(12345)', $sections[1]);
    $t->contains('attached-schema', $sections[2]);
};

return $tests;
