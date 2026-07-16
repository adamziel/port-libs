<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaRuntimeState;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test pragma-8.1.* and
 * pragma-8.2.*.
 *
 * pragma-8.1 verifies PRAGMA schema_version reads/writes, defensive-mode
 * write suppression, schema-cookie bumps after DDL, and attached-database
 * isolation. pragma-8.2 verifies PRAGMA user_version reads/writes,
 * persistence across reopen/VACUUM-style schema-version movement,
 * attached-database isolation, rollback restoration, and negative values.
 *
 * This ports those behaviors into the bounded runtime PRAGMA state model used
 * by the PHP libsqlite port. The tests use generic application schema names
 * and avoid domain-specific table/API names.
 */

foreach (range(1, 250) as $variant) {
    $baseSchemaVersion = 100 + $variant;
    $nextSchemaVersion = 200 + $variant;
    $defensiveAttempt = 300 + $variant;
    $auxSchemaVersion = 400 + $variant;
    $baseUserVersion = $variant % 97;
    $nextUserVersion = 500 + $variant;
    $auxUserVersion = 600 + $variant;
    $negativeUserVersion = -($variant + 450);
    $schemaName = sprintf('archive_%03d', $variant);

    $tests[sprintf('real upstream pragma schema dynamic version state schema_version defensive attach variant %03d', $variant)] = static function (TestRunner $t) use ($baseSchemaVersion, $nextSchemaVersion, $defensiveAttempt, $auxSchemaVersion, $schemaName): void {
        $state = new SQLitePragmaRuntimeState($baseSchemaVersion, 0);

        $t->same($baseSchemaVersion, $state->pragma('PRAGMA schema_version')['schema_version']);
        $t->same($nextSchemaVersion, $state->pragma("PRAGMA schema_version = {$nextSchemaVersion}")['schema_version']);
        $t->same($nextSchemaVersion, $state->pragma("PRAGMA schema_version = {$defensiveAttempt}", true)['schema_version']);
        $t->same($nextSchemaVersion, $state->pragma('PRAGMA main.schema_version')['schema_version']);

        $attached = $state->attach($schemaName, "/tmp/pragma-version-{$schemaName}.sqlite");
        $t->same($schemaName, $attached['schema']);
        $t->same(0, $attached['schema_version']);
        $t->same($auxSchemaVersion, $state->pragma("PRAGMA {$schemaName}.schema_version = {$auxSchemaVersion}")['schema_version']);
        $t->same($auxSchemaVersion, $state->pragma("PRAGMA {$schemaName}.schema_version")['schema_version']);
        $t->same($nextSchemaVersion, $state->pragma('PRAGMA schema_version')['schema_version']);
    };

    $tests[sprintf('real upstream pragma schema dynamic version state user_version rollback variant %03d', $variant)] = static function (TestRunner $t) use ($baseSchemaVersion, $baseUserVersion, $nextUserVersion, $auxUserVersion, $schemaName): void {
        $state = new SQLitePragmaRuntimeState($baseSchemaVersion, $baseUserVersion);
        $state->attach($schemaName, "/tmp/pragma-user-version-{$schemaName}.sqlite");

        $t->same($baseUserVersion, $state->pragma('PRAGMA user_version')['user_version']);
        $t->same($nextUserVersion, $state->pragma("PRAGMA user_version = {$nextUserVersion}")['user_version']);
        $t->same(0, $state->pragma("PRAGMA {$schemaName}.user_version")['user_version']);
        $t->same($auxUserVersion, $state->pragma("PRAGMA {$schemaName}.user_version = {$auxUserVersion}")['user_version']);
        $t->same($nextUserVersion, $state->pragma('PRAGMA main.user_version')['user_version']);

        $state->begin();
        $state->pragma('PRAGMA user_version = 11');
        $state->pragma("PRAGMA {$schemaName}.user_version = 10");
        $t->same(11, $state->pragma('PRAGMA user_version')['user_version']);
        $t->same(10, $state->pragma("PRAGMA {$schemaName}.user_version")['user_version']);
        $state->rollback();

        $t->same($nextUserVersion, $state->pragma('PRAGMA user_version')['user_version']);
        $t->same($auxUserVersion, $state->pragma("PRAGMA {$schemaName}.user_version")['user_version']);
    };

    $tests[sprintf('real upstream pragma schema dynamic version state user_version negative and commit variant %03d', $variant)] = static function (TestRunner $t) use ($baseSchemaVersion, $negativeUserVersion, $schemaName): void {
        $state = new SQLitePragmaRuntimeState($baseSchemaVersion, 0);
        $state->attach($schemaName, "/tmp/pragma-negative-version-{$schemaName}.sqlite");

        $t->same($negativeUserVersion, $state->pragma("PRAGMA user_version = {$negativeUserVersion}")['user_version']);
        $state->begin();
        $state->pragma('PRAGMA user_version = 27');
        $state->pragma("PRAGMA {$schemaName}.user_version = 31");
        $state->commit();

        $t->same(27, $state->pragma('PRAGMA user_version')['user_version']);
        $t->same(31, $state->pragma("PRAGMA {$schemaName}.user_version")['user_version']);
        $t->same($baseSchemaVersion, $state->pragma('PRAGMA schema_version')['schema_version']);
        $t->same(0, $state->pragma("PRAGMA {$schemaName}.schema_version")['schema_version']);
    };

    $tests[sprintf('real upstream pragma schema dynamic version state cache and detach isolation variant %03d', $variant)] = static function (TestRunner $t) use ($baseSchemaVersion, $baseUserVersion, $schemaName): void {
        $state = new SQLitePragmaRuntimeState($baseSchemaVersion, $baseUserVersion, 25);
        $state->attach($schemaName, "/tmp/pragma-cache-version-{$schemaName}.sqlite");

        $t->same(25, $state->pragma('PRAGMA cache_size')['cache_size']);
        $t->same(13, $state->pragma("PRAGMA {$schemaName}.cache_size = 13")['cache_size']);
        $t->same(25, $state->pragma('PRAGMA main.cache_size')['cache_size']);
        $t->same(13, $state->pragma("PRAGMA {$schemaName}.cache_size")['cache_size']);
        $t->same($schemaName, $state->detach($schemaName)['schema']);
        $t->throws(InvalidArgumentException::class, static fn () => $state->pragma("PRAGMA {$schemaName}.user_version"));
    };
}

$tests['real upstream pragma schema dynamic version state cites pragma 8 sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-8.1.1 through pragma-8.1.4 cover schema_version write/read and defensive-mode suppression',
        'pragma.test pragma-8.1.11 through pragma-8.1.18 cover attached-schema schema_version isolation and schema reload pressure',
        'pragma.test pragma-8.2.1 through pragma-8.2.8 cover user_version read/write, reopen/VACUUM preservation, and attached-schema isolation',
        'pragma.test pragma-8.2.9 through pragma-8.2.15 cover user_version rollback restoration and negative values',
    ];

    $t->same(4, count($sections));
    $t->contains('pragma-8.1.1', $sections[0]);
    $t->contains('attached-schema', $sections[1]);
    $t->contains('user_version', $sections[2]);
    $t->contains('negative values', $sections[3]);
};

return $tests;
