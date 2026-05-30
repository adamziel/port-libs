<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;

$tests = [];

$value = static fn (SQLitePragmaSchemaDataVersion $state, string $schema = 'main'): int => (int) $state->execute("PRAGMA {$schema}.data_version")['value'];
$counter = static fn (SQLitePragmaSchemaDataVersion $state, string $schema = 'main'): int => (int) $state->headerUpdate($schema)['file_change_counter'];
$state = static fn (int $dataVersion = 1, int $schemaVersion = 0): SQLitePragmaSchemaDataVersion => new SQLitePragmaSchemaDataVersion([
    'main' => [
        'schema_version' => $schemaVersion,
        'data_version' => $dataVersion,
        'change_counter' => $dataVersion,
    ],
]);

$tests['real upstream pragma3 shared-cache data_version local writer commit stays local through pragma3-300'] = static function (TestRunner $t) use ($state, $value, $counter): void {
    $writer = $state();
    $before = $value($writer);
    $writer->beginTransaction();
    $duringCreateT3 = $writer->recordLocalCommit('main', 1, 'pragma3_300_create_t3');
    $duringCreateT4 = $writer->recordLocalCommit('main', 1, 'pragma3_300_create_t4');
    $writer->commitTransaction();

    $t->same(1, $before);
    $t->same(1, $duringCreateT3['value']);
    $t->same(1, $duringCreateT4['value']);
    $t->same(1, $value($writer));
    $t->same(3, $counter($writer));
    $t->same('pragma3_300_create_t4', $duringCreateT4['reason']);
};

$tests['real upstream pragma3 shared-cache reader sees external transaction only after commit through pragma3-310-to-340'] = static function (TestRunner $t) use ($state, $value, $counter): void {
    $reader = $state();
    $writer = $state(2);

    $writer->beginTransaction();
    $writerDuring = $writer->recordLocalCommit('main', 1, 'pragma3_310_insert_t3');
    $readerBeforeCommit = $value($reader);
    $writer->commitTransaction();
    $readerObserved = $reader->recordExternalCommit('main', 1, 'pragma3_340_shared_cache_observed');

    $t->same(2, $value($writer));
    $t->same(2, $writerDuring['value']);
    $t->same(1, $readerBeforeCommit);
    $t->same(2, $readerObserved['value']);
    $t->same(2, $value($reader));
    $t->same(2, $counter($reader));
};

$tests['real upstream pragma3 wal data_version starts stable for both connections through pragma3-400-and-410'] = static function (TestRunner $t) use ($state, $value): void {
    $connection = $state(2);
    $other = $state(2);

    $t->same(2, $value($connection));
    $t->same(2, $value($other));
    $t->same(2, $connection->execute('PRAGMA data_version')['rows'][0]['data_version']);
    $t->same(2, $other->execute('PRAGMA data_version')['rows'][0]['data_version']);
    $t->same('current', $connection->execute('PRAGMA data_version')['reason']);
    $t->same('current', $other->execute('PRAGMA data_version')['reason']);
};

$tests['real upstream pragma3 wal writer update leaves writer version stable and advances observer through pragma3-420-and-430'] = static function (TestRunner $t) use ($state, $value, $counter): void {
    $writer = $state(2);
    $reader = $state(2);
    $writerResult = $writer->recordLocalCommit('main', 1, 'pragma3_420_wal_update');
    $readerResult = $reader->recordExternalCommit('main', 1, 'pragma3_430_wal_observed');

    $t->same(2, $writerResult['value']);
    $t->same(2, $value($writer));
    $t->same(3, $counter($writer));
    $t->same(3, $readerResult['value']);
    $t->same(3, $value($reader));
    $t->same(3, $counter($reader));
};

$tests['real upstream pragma3 empty exclusive transaction does not decrement data_version through pragma3-510A-and-520A'] = static function (TestRunner $t) use ($state, $value, $counter): void {
    $connection = $state();
    $before = $value($connection);
    $connection->beginTransaction();
    $commit = $connection->commitTransaction();

    $t->same(1, $before);
    $t->same('commit', $commit['operation']);
    $t->same(true, $commit['committed']);
    $t->same(1, $value($connection));
    $t->same(1, $counter($connection));
    $t->same(false, $connection->state()['main']['data_dirty']);
};

$tests['real upstream pragma3 empty persistent exclusive transaction does not decrement data_version through pragma3-510B-and-520B'] = static function (TestRunner $t) use ($state, $value, $counter): void {
    $connection = $state();
    $connection->beginTransaction();
    $commit = $connection->commitTransaction();

    $t->same(1, $value($connection));
    $t->same(1, $counter($connection));
    $t->same('commit', $commit['operation']);
    $t->same(1, $commit['schema_count']);
    $t->same(false, $connection->state()['main']['schema_dirty']);
    $t->same(false, $connection->state()['main']['data_dirty']);
};

foreach (range(1, 45) as $case) {
    $tests['real upstream pragma3 shared-cache dynamic observer case ' . $case] = static function (TestRunner $t) use ($state, $value, $counter, $case): void {
        $base = 1 + ($case % 4);
        $reader = $state($base);
        $writer = $state($base);
        $writer->beginTransaction();
        $local = $writer->recordLocalCommit('main', $case, 'pragma3_310_shared_cache_local_' . $case);
        $readerBefore = $value($reader);
        $writer->commitTransaction();
        $observed = $reader->recordExternalCommit('main', $case, 'pragma3_340_shared_cache_observed_' . $case);

        $t->same($base, $readerBefore);
        $t->same($base, $local['value']);
        $t->same($base, $value($writer));
        $t->same($base + $case, $counter($writer));
        $t->same($base + $case, $observed['value']);
        $t->same($base + $case, $value($reader));
        $t->same($base + $case, $counter($reader));
        $t->same('pragma3_340_shared_cache_observed_' . $case, $observed['reason']);
    };
}

foreach (range(1, 45) as $case) {
    $tests['real upstream pragma3 wal dynamic observer case ' . $case] = static function (TestRunner $t) use ($state, $value, $counter, $case): void {
        $base = 2 + ($case % 5);
        $writer = $state($base);
        $reader = $state($base);
        $writerLocal = $writer->recordLocalCommit('main', $case + 1, 'pragma3_420_wal_local_' . $case);
        $readerObserved = $reader->recordExternalCommit('main', $case + 1, 'pragma3_430_wal_observed_' . $case);

        $t->same($base, $writerLocal['value']);
        $t->same($base, $value($writer));
        $t->same($base + $case + 1, $counter($writer));
        $t->same($base + $case + 1, $readerObserved['value']);
        $t->same($base + $case + 1, $value($reader));
        $t->same($base + $case + 1, $counter($reader));
        $t->same(true, $readerObserved['changed']);
        $t->same('pragma3_430_wal_observed_' . $case, $readerObserved['reason']);
    };
}

foreach (range(1, 45) as $case) {
    $tests['real upstream pragma3 empty transaction dynamic case ' . $case] = static function (TestRunner $t) use ($state, $value, $counter, $case): void {
        $base = 1 + ($case % 3);
        $connection = $state($base, $case);
        $before = $value($connection);
        $connection->beginTransaction();
        $commit = $connection->commitTransaction();
        $after = $connection->execute('PRAGMA data_version');

        $t->same($base, $before);
        $t->same($base, $after['value']);
        $t->same($base, $counter($connection));
        $t->same($case, $connection->execute('PRAGMA schema_version')['value']);
        $t->same('commit', $commit['operation']);
        $t->same(true, $commit['committed']);
        $t->same(false, $after['changed']);
        $t->same(false, $connection->state()['main']['data_dirty']);
    };
}

return $tests;
