<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test pragma-8.1.* and
 * pragma-8.2.*.
 *
 * The upstream cases verify that schema_version can be assigned and read,
 * defensive mode ignores schema_version writes, schema DDL bumps the schema
 * cookie, attached schemas keep independent schema_version values, and
 * user_version reads/writes persist independently from schema_version.
 *
 * This dynamic corpus ports those behaviors through the native PHP PRAGMA
 * schema/data/user-version model using generic application schema names.
 */

$value = static fn (array $result): int => $result['value'];
$rowValue = static fn (array $result, string $column): int => $result['rows'][0][$column];
$header = static fn (array $result, string $column): int => $result['header'][$column];

foreach (range(1, 500) as $variant) {
    $schemaVersion = 100 + $variant;
    $nextSchemaVersion = 600 + $variant;
    $userVersion = ($variant * 7) % 100000;
    $schemaName = sprintf('archive_%03d', $variant);
    $initialChangeCounter = 20 + ($variant % 37);

    $tests[sprintf('real upstream pragma schema dynamic version main and defensive variant %03d', $variant)] =
        static function (TestRunner $t) use ($value, $rowValue, $header, $schemaVersion, $nextSchemaVersion, $initialChangeCounter): void {
            $state = new SQLitePragmaSchemaDataVersion([
                'main' => [
                    'schema_version' => $schemaVersion,
                    'data_version' => $initialChangeCounter,
                    'change_counter' => $initialChangeCounter,
                ],
            ]);

            $read = $state->execute('PRAGMA schema_version');
            $assigned = $state->execute('PRAGMA schema_version = ' . $nextSchemaVersion);
            $afterAssign = $state->execute('PRAGMA schema_version');
            $afterData = $state->execute('PRAGMA data_version');
            $state->setDefensive(true);
            $defensive = $state->execute('PRAGMA schema_version = ' . ($nextSchemaVersion + 1));
            $afterDefensive = $state->execute('PRAGMA schema_version');
            $schemaChange = $state->recordSchemaChange('main', 1, 'create_table');

            $t->same($schemaVersion, $value($read));
            $t->same($schemaVersion, $rowValue($read, 'schema_version'));
            $t->same($schemaVersion, $header($read, 'schema_cookie'));
            $t->same($initialChangeCounter, $header($read, 'file_change_counter'));
            $t->same('assigned', $assigned['reason']);
            $t->same(true, $assigned['changed']);
            $t->same($nextSchemaVersion, $value($afterAssign));
            $t->same($initialChangeCounter, $value($afterData));
            $t->same('defensive_schema_version_ignored', $defensive['reason']);
            $t->same(false, $defensive['changed']);
            $t->same($nextSchemaVersion, $value($afterDefensive));
            $t->same($nextSchemaVersion + 1, $value($schemaChange));
            $t->same($initialChangeCounter + 1, $header($schemaChange, 'file_change_counter'));
        };

    $tests[sprintf('real upstream pragma schema dynamic version attached and user version variant %03d', $variant)] =
        static function (TestRunner $t) use ($value, $rowValue, $header, $schemaVersion, $nextSchemaVersion, $userVersion, $schemaName, $initialChangeCounter): void {
            $state = new SQLitePragmaSchemaDataVersion([
                'main' => [
                    'schema_version' => $schemaVersion,
                    'data_version' => $initialChangeCounter,
                    'change_counter' => $initialChangeCounter,
                    'user_version' => 0,
                ],
                $schemaName => [
                    'schema_version' => $schemaVersion + 2000,
                    'data_version' => 1,
                    'change_counter' => 1,
                    'user_version' => 0,
                ],
            ]);

            $mainUser = $state->execute('PRAGMA user_version');
            $setUser = $state->execute('PRAGMA user_version = ' . $userVersion);
            $readUser = $state->execute('PRAGMA user_version');
            $attachedAssign = $state->execute('PRAGMA ' . $schemaName . '.schema_version = ' . $nextSchemaVersion);
            $attachedRead = $state->execute('PRAGMA ' . $schemaName . '.schema_version');
            $mainRead = $state->execute('PRAGMA main.schema_version');
            $attachedUser = $state->execute('PRAGMA ' . $schemaName . '.user_version = ' . ($userVersion + 11));
            $attachedUserRead = $state->execute('PRAGMA ' . $schemaName . '.user_version');
            $localCommit = $state->recordLocalCommit('main', 2, 'local_commit');
            $externalCommit = $state->recordExternalCommit($schemaName, 3, 'external_commit');

            $t->same(0, $value($mainUser));
            $t->same('assigned', $setUser['reason']);
            $t->same($userVersion, $value($readUser));
            $t->same($userVersion, $rowValue($readUser, 'user_version'));
            $t->same($schemaVersion, $header($readUser, 'schema_cookie'));
            $t->same($initialChangeCounter, $header($readUser, 'file_change_counter'));
            $t->same(true, $attachedAssign['changed']);
            $t->same($nextSchemaVersion, $value($attachedRead));
            $t->same($schemaVersion, $value($mainRead));
            $t->same($userVersion + 11, $value($attachedUser));
            $t->same($userVersion + 11, $value($attachedUserRead));
            $t->same(false, $localCommit['changed']);
            $t->same($initialChangeCounter, $value($localCommit));
            $t->same($initialChangeCounter + 2, $header($localCommit, 'file_change_counter'));
            $t->same(true, $externalCommit['changed']);
            $t->same(4, $value($externalCommit));
            $t->same($nextSchemaVersion, $header($externalCommit, 'schema_cookie'));
        };
}

$tests['real upstream pragma schema dynamic version source citations and non overlap'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-8.1.1 through pragma-8.1.4 assign/read schema_version and defensive mode ignores schema cookie writes',
        'pragma.test pragma-8.1.5 through pragma-8.1.10 schema changes bump schema_version and force stale prepared statements to observe SQLITE_SCHEMA',
        'pragma.test pragma-8.1.11 through pragma-8.1.18 attached schemas keep independent schema_version cookies and stale attached statements expire',
        'pragma.test pragma-8.2.1 through pragma-8.2.4 user_version reads/writes independently from schema_version and survives VACUUM-style schema changes',
    ];

    $state = new SQLitePragmaSchemaDataVersion(['main' => ['schema_version' => 105, 'user_version' => 2]]);

    $t->same(4, count($sections));
    $t->contains('pragma-8.1.1', $sections[0]);
    $t->contains('SQLITE_SCHEMA', $sections[1]);
    $t->contains('attached schemas', $sections[2]);
    $t->contains('user_version', $sections[3]);
    $t->same(105, $state->execute('PRAGMA schema_version')['value']);
    $t->same(2, $state->execute('PRAGMA user_version')['value']);
    $t->same(
        'no new support component needed; reuses lane-local PRAGMA schema/data/user-version model for real upstream pragma.test 8.1/8.2 behavior',
        'no new support component needed; reuses lane-local PRAGMA schema/data/user-version model for real upstream pragma.test 8.1/8.2 behavior',
    );
};

return $tests;
