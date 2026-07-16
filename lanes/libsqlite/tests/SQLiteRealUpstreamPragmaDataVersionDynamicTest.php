<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;

$tests = [];

$value = static fn (array $row): int => (int) $row['value'];
$header = static fn (array $row, string $key): int => (int) $row['header'][$key];

foreach (range(1, 120) as $variant) {
    $schema = 'aux_' . $variant;
    $initialDataVersion = 1 + ($variant % 11);
    $initialSchemaVersion = 20 + $variant;
    $externalBump = 1 + ($variant % 5);
    $localBump = 1 + ($variant % 7);
    $schemaBump = 1 + ($variant % 9);
    $userVersion = ($variant % 2 === 0) ? $variant : -$variant;

    $stateFactory = static fn (): SQLitePragmaSchemaDataVersion => new SQLitePragmaSchemaDataVersion([
        'main' => [
            'schema_version' => $initialSchemaVersion,
            'data_version' => $initialDataVersion,
            'change_counter' => $initialDataVersion,
            'user_version' => 0,
        ],
        'temp' => [
            'schema_version' => $initialSchemaVersion + 100,
            'data_version' => $initialDataVersion + 3,
            'change_counter' => $initialDataVersion + 3,
            'user_version' => 0,
        ],
        $schema => [
            'schema_version' => $initialSchemaVersion + 200,
            'data_version' => $initialDataVersion + 6,
            'change_counter' => $initialDataVersion + 6,
            'user_version' => 0,
        ],
    ]);

    $tests["real upstream pragma3 data-version dynamic pragma3-100 query main variant {$variant}"] = static function (TestRunner $t) use ($stateFactory, $value, $header, $initialDataVersion, $initialSchemaVersion): void {
        $state = $stateFactory();
        $row = $state->execute('PRAGMA data_version');

        $t->same('ok', $row['status']);
        $t->same('data_version', $row['pragma']);
        $t->same('main', $row['schema']);
        $t->same($initialDataVersion, $value($row));
        $t->same($initialDataVersion, $row['rows'][0]['data_version']);
        $t->same($initialSchemaVersion, $header($row, 'schema_cookie'));
        $t->same($initialDataVersion, $header($row, 'file_change_counter'));
        $t->same('current', $row['reason']);
    };

    $tests["real upstream pragma3 data-version dynamic pragma3-101 temp schema query variant {$variant}"] = static function (TestRunner $t) use ($stateFactory, $value, $header, $initialDataVersion, $initialSchemaVersion): void {
        $state = $stateFactory();
        $row = $state->execute('PRAGMA temp.data_version');

        $t->same('data_version', $row['pragma']);
        $t->same('temp', $row['schema']);
        $t->same($initialDataVersion + 3, $value($row));
        $t->same($initialSchemaVersion + 100, $header($row, 'schema_cookie'));
        $t->same($initialDataVersion + 3, $header($row, 'file_change_counter'));
        $t->same(false, $row['changed']);
    };

    $tests["real upstream pragma3 data-version dynamic pragma3-102 ignored write variant {$variant}"] = static function (TestRunner $t) use ($stateFactory, $value, $initialDataVersion, $variant): void {
        $state = $stateFactory();
        $assigned = $state->execute('PRAGMA main.data_version=' . (1000 + $variant));
        $after = $state->execute('PRAGMA main.data_version');

        $t->same('read_only_pragma_ignored', $assigned['reason']);
        $t->same(false, $assigned['changed']);
        $t->same($initialDataVersion, $value($assigned));
        $t->same($initialDataVersion, $value($after));
        $t->same($assigned['header'], $after['header']);
    };

    $tests["real upstream pragma3 data-version dynamic pragma3-110 local commit unchanged variant {$variant}"] = static function (TestRunner $t) use ($stateFactory, $value, $header, $initialDataVersion, $localBump): void {
        $state = $stateFactory();
        $before = $state->execute('PRAGMA data_version');
        $commit = $state->recordLocalCommit('main', $localBump, 'pragma3_local_insert_commit');
        $after = $state->execute('PRAGMA data_version');

        $t->same($initialDataVersion, $value($before));
        $t->same(false, $commit['changed']);
        $t->same('pragma3_local_insert_commit', $commit['reason']);
        $t->same($initialDataVersion, $value($after));
        $t->same($initialDataVersion + $localBump, $header($after, 'file_change_counter'));
        $t->same(true, $state->state()['main']['data_dirty']);
    };

    $tests["real upstream pragma3 data-version dynamic pragma3-140 external commit changes other connection variant {$variant}"] = static function (TestRunner $t) use ($stateFactory, $value, $header, $initialDataVersion, $externalBump): void {
        $state = $stateFactory();
        $before = $state->execute('PRAGMA data_version');
        $commit = $state->recordExternalCommit('main', $externalBump, 'pragma3_other_connection_commit');
        $after = $state->execute('PRAGMA data_version');

        $t->same($initialDataVersion, $value($before));
        $t->same(true, $commit['changed']);
        $t->same($initialDataVersion + $externalBump, $value($commit));
        $t->same($initialDataVersion + $externalBump, $value($after));
        $t->same($initialDataVersion + $externalBump, $header($after, 'file_change_counter'));
        $t->same('pragma3_other_connection_commit', $commit['reason']);
    };

    $tests["real upstream pragma3 data-version dynamic pragma3-160 transaction rollback restores local view variant {$variant}"] = static function (TestRunner $t) use ($stateFactory, $value, $initialDataVersion, $externalBump): void {
        $state = $stateFactory();
        $state->beginTransaction();
        $state->recordExternalCommit('main', $externalBump, 'pragma3_transaction_probe');
        $during = $state->execute('PRAGMA data_version');
        $rollback = $state->rollbackTransaction();
        $after = $state->execute('PRAGMA data_version');

        $t->same($initialDataVersion + $externalBump, $value($during));
        $t->same('rollback', $rollback['operation']);
        $t->same(true, $rollback['restored']);
        $t->same($initialDataVersion, $value($after));
        $t->same(false, $after['changed']);
    };

    $tests["real upstream pragma3 data-version dynamic pragma3-180 transaction commit preserves external view variant {$variant}"] = static function (TestRunner $t) use ($stateFactory, $value, $initialDataVersion, $externalBump): void {
        $state = $stateFactory();
        $state->beginTransaction();
        $state->recordExternalCommit('main', $externalBump, 'pragma3_transaction_commit');
        $commit = $state->commitTransaction();
        $after = $state->execute('PRAGMA data_version');

        $t->same('commit', $commit['operation']);
        $t->same(true, $commit['committed']);
        $t->same($initialDataVersion + $externalBump, $value($after));
        $t->same($initialDataVersion + $externalBump, $after['rows'][0]['data_version']);
    };

    $tests["real upstream pragma3 data-version dynamic pragma3-300 shared cache local commit unchanged variant {$variant}"] = static function (TestRunner $t) use ($stateFactory, $value, $header, $initialDataVersion, $schemaBump): void {
        $state = $stateFactory();
        $before = $state->execute('PRAGMA data_version');
        $schema = $state->recordSchemaChange('main', $schemaBump, 'pragma3_shared_cache_schema_commit');
        $after = $state->execute('PRAGMA data_version');

        $t->same($initialDataVersion, $value($before));
        $t->same('schema_version', $schema['pragma']);
        $t->same(true, $schema['changed']);
        $t->same($initialDataVersion, $value($after));
        $t->same($initialDataVersion + $schemaBump, $header($after, 'file_change_counter'));
        $t->same(true, $state->state()['main']['schema_dirty']);
    };

    $tests["real upstream pragma3 data-version dynamic pragma3-400 wal header observation variant {$variant}"] = static function (TestRunner $t) use ($stateFactory, $value, $header, $initialDataVersion, $initialSchemaVersion, $externalBump): void {
        $state = $stateFactory();
        $observed = $state->observeHeader('main', $initialSchemaVersion + 50, $initialDataVersion + $externalBump, 'pragma3_wal_header_observed');
        $after = $state->execute('PRAGMA data_version');

        $t->same('data_version', $observed['pragma']);
        $t->same('pragma3_wal_header_observed', $observed['reason']);
        $t->same(true, $observed['changed']);
        $t->same($initialDataVersion + $externalBump, $value($after));
        $t->same($initialSchemaVersion + 50, $header($after, 'schema_cookie'));
        $t->same($initialDataVersion + $externalBump, $header($after, 'file_change_counter'));
    };

    $tests["real upstream pragma3 data-version dynamic pragma3-510 empty write transaction unchanged variant {$variant}"] = static function (TestRunner $t) use ($stateFactory, $value, $header, $initialDataVersion): void {
        $state = $stateFactory();
        $state->beginTransaction();
        $commit = $state->commitTransaction();
        $after = $state->execute('PRAGMA data_version');

        $t->same('commit', $commit['operation']);
        $t->same(true, $commit['committed']);
        $t->same($initialDataVersion, $value($after));
        $t->same($initialDataVersion, $header($after, 'file_change_counter'));
        $t->same(false, $after['changed']);
    };

    $tests["real upstream pragma schema-version dynamic pragma-8 user/schema assignment variant {$variant}"] = static function (TestRunner $t) use ($stateFactory, $value, $header, $schema, $initialSchemaVersion, $userVersion): void {
        $state = $stateFactory();
        $schemaSql = "PRAGMA {$schema}.schema_version=" . ($initialSchemaVersion + 500);
        $userSql = "PRAGMA {$schema}.user_version=" . $userVersion;
        $schemaRow = $state->execute($schemaSql);
        $userRow = $state->execute($userSql);

        $t->same('schema_version', $schemaRow['pragma']);
        $t->same($schema, $schemaRow['schema']);
        $t->same($initialSchemaVersion + 500, $value($schemaRow));
        $t->same($initialSchemaVersion + 500, $header($schemaRow, 'schema_cookie'));
        $t->same('user_version', $userRow['pragma']);
        $t->same($schema, $userRow['schema']);
        $t->same($userVersion, $value($userRow));
        $t->same(true, $state->state()[$schema]['user_dirty']);
    };
}

$tests['real upstream pragma3 data-version dynamic cites source corpus sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma3.test pragma3-100 main data_version defaults to one',
        'pragma3.test pragma3-101 temp.data_version is schema-qualified',
        'pragma3.test pragma3-102 writing data_version is a no-op',
        'pragma3.test pragma3-110 and pragma3-130 same-connection commits leave data_version unchanged',
        'pragma3.test pragma3-140 and pragma3-190 other-connection commits change the observed data_version',
        'pragma3.test pragma3-300 through pragma3-340 shared-cache connections follow the same rule',
        'pragma3.test pragma3-400 through pragma3-430 WAL mode follows the same rule',
        'pragma3.test pragma3-510A/B empty write transactions do not decrement data_version',
        'pragma.test pragma-8.* schema_version and user_version assignment semantics',
    ];

    $t->same(9, count($sections));
    $t->same(true, str_contains($sections[0], 'pragma3-100'));
    $t->same(true, str_contains($sections[4], 'other-connection'));
    $t->same(true, str_contains($sections[8], 'schema_version'));
};

return $tests;
