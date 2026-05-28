<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeSorterDistinctCurrentSourceCursor;

$initialRows = [
    ['rowid' => 1, 'option_name' => 'SiteUrl', 'bucket' => 'core', 'bytes' => '24', 'enabled' => 1],
    ['rowid' => 2, 'option_name' => 'siteurl', 'bucket' => 'core', 'bytes' => 24, 'enabled' => 1],
    ['rowid' => 3, 'option_name' => 'plugin_cache', 'bucket' => 'plugin', 'bytes' => '12', 'enabled' => 1],
    ['rowid' => 4, 'option_name' => 'Plugin_Cache', 'bucket' => 'plugin', 'bytes' => 12.0, 'enabled' => 1],
    ['rowid' => 5, 'option_name' => 'plugin_cache ', 'bucket' => 'plugin', 'bytes' => '12.00', 'enabled' => 1],
    ['rowid' => 6, 'option_name' => '_transient_feed', 'bucket' => 'transient', 'bytes' => 30, 'enabled' => 0],
    ['rowid' => 7, 'option_name' => '_transient_feed_timeout', 'bucket' => 'transient', 'bytes' => '30', 'enabled' => 1],
    ['rowid' => 8, 'option_name' => null, 'bucket' => null, 'bytes' => null, 'enabled' => 1],
];

$updatedRows = [
    ['rowid' => 1, 'option_name' => 'SiteUrl', 'bucket' => 'core', 'bytes' => '24', 'enabled' => 1],
    ['rowid' => 2, 'option_name' => 'siteurl', 'bucket' => 'core', 'bytes' => 24, 'enabled' => 1],
    ['rowid' => 3, 'option_name' => 'plugin_cache', 'bucket' => 'plugin', 'bytes' => '12', 'enabled' => 0],
    ['rowid' => 4, 'option_name' => 'Plugin_Cache', 'bucket' => 'plugin', 'bytes' => 12.0, 'enabled' => 1],
    ['rowid' => 5, 'option_name' => 'plugin_cache ', 'bucket' => 'plugin', 'bytes' => '12.00', 'enabled' => 1],
    ['rowid' => 7, 'option_name' => '_transient_feed_timeout', 'bucket' => 'transient', 'bytes' => '30', 'enabled' => 1],
    ['rowid' => 8, 'option_name' => null, 'bucket' => null, 'bytes' => null, 'enabled' => 1],
    ['rowid' => 9, 'option_name' => 'plugin_cache_new', 'bucket' => 'plugin', 'bytes' => '13', 'enabled' => 1],
    ['rowid' => 10, 'option_name' => 'Zoo_Option', 'bucket' => 'late', 'bytes' => '99', 'enabled' => 1],
];

$tests = [];

$make = static fn (array $rows = null): SQLiteVdbeSorterDistinctCurrentSourceCursor => new SQLiteVdbeSorterDistinctCurrentSourceCursor(
    $rows ?? $initialRows,
    'option_name',
    'rowid',
    'enabled',
    'G',
    ['NOCASE'],
    'schema-cookie-1'
);

$tests['vdbe sorter distinct current source starts at null key'] = static function (TestRunner $t) use ($make): void {
    $cursor = $make();
    $t->same([null], $cursor->currentKey());
    $t->same(8, $cursor->currentValue());
};

$tests['vdbe sorter distinct current source initial values honor nocase collation'] = static function (TestRunner $t) use ($make): void {
    $t->same([8, 7, 3, 5, 1], $make()->values());
};

$tests['vdbe sorter distinct current source refresh same token is ignored'] = static function (TestRunner $t) use ($make, $updatedRows): void {
    $cursor = $make();
    $t->same(false, $cursor->refresh($updatedRows, 'schema-cookie-1'));
    $t->same('schema-cookie-1', $cursor->sourceToken());
    $t->same([8, 7, 3, 5, 1], $cursor->values());
};

$tests['vdbe sorter distinct current source refresh updates token and rows'] = static function (TestRunner $t) use ($make, $updatedRows): void {
    $cursor = $make();
    $t->true($cursor->refresh($updatedRows, 'schema-cookie-2'));
    $t->same('schema-cookie-2', $cursor->sourceToken());
    $t->same([8, 7, 4, 5, 9, 1, 10], $cursor->values());
};

$tests['vdbe sorter distinct current source refresh preserves matching current key'] = static function (TestRunner $t) use ($make, $updatedRows): void {
    $cursor = $make();
    $cursor->next();
    $cursor->next();
    $t->same(['plugin_cache'], $cursor->currentKey());
    $t->true($cursor->refresh($updatedRows, 'schema-cookie-2'));
    $t->same(['Plugin_Cache'], $cursor->currentKey());
    $t->same(4, $cursor->currentValue());
};

$tests['vdbe sorter distinct current source refresh seeks to next greater key when current key disappears'] = static function (TestRunner $t) use ($make, $updatedRows): void {
    $cursor = $make();
    $cursor->next();
    $t->same(['_transient_feed_timeout'], $cursor->currentKey());
    $withoutTransient = array_values(array_filter($updatedRows, static fn (array $row): bool => $row['rowid'] !== 7));
    $t->true($cursor->refresh($withoutTransient, 'schema-cookie-3'));
    $t->same(['Plugin_Cache'], $cursor->currentKey());
    $t->same(4, $cursor->currentValue());
};

$tests['vdbe sorter distinct current source refresh reaches eof when no greater key remains'] = static function (TestRunner $t) use ($make): void {
    $cursor = $make();
    while (!$cursor->eof()) {
        $last = $cursor->currentKey();
        $cursor->next();
    }
    $cursor = $make();
    while ($cursor->currentKey() !== ['SiteUrl']) {
        $cursor->next();
    }
    $t->same(['SiteUrl'], $last);
    $t->true($cursor->refresh([['rowid' => 8, 'option_name' => null, 'enabled' => 1]], 'schema-cookie-4'));
    $t->true($cursor->eof());
};

$tests['vdbe sorter distinct current source snapshot exposes current source state'] = static function (TestRunner $t) use ($make): void {
    $cursor = $make();
    $cursor->next();
    $snapshot = $cursor->snapshot();
    $t->same('schema-cookie-1', $snapshot['sourceToken']);
    $t->same(false, $snapshot['eof']);
    $t->same(['_transient_feed_timeout'], $snapshot['currentKey']);
    $t->same(7, $snapshot['currentValue']);
    $t->same(5, $snapshot['distinctRows']);
    $t->same([8, 7, 3, 5, 1], $snapshot['values']);
};

$tests['vdbe sorter distinct current source rtrim refresh collapses trailing spaces'] = static function (TestRunner $t) use ($initialRows, $updatedRows): void {
    $cursor = new SQLiteVdbeSorterDistinctCurrentSourceCursor($initialRows, 'option_name', 'rowid', 'enabled', 'G', ['RTRIM'], 'rtrim-1');
    $t->same([8, 4, 1, 7, 3, 2], $cursor->values());
    $t->true($cursor->refresh($updatedRows, 'rtrim-2'));
    $t->same([8, 4, 1, 10, 7, 5, 9, 2], $cursor->values());
};

$tests['vdbe sorter distinct current source binary refresh keeps case variants'] = static function (TestRunner $t) use ($initialRows, $updatedRows): void {
    $cursor = new SQLiteVdbeSorterDistinctCurrentSourceCursor($initialRows, 'option_name', 'rowid', 'enabled', 'G', ['BINARY'], 'binary-1');
    $t->same([8, 4, 1, 7, 3, 5, 2], $cursor->values());
    $t->true($cursor->refresh($updatedRows, 'binary-2'));
    $t->same([8, 4, 1, 10, 7, 5, 9, 2], $cursor->values());
};

$tests['vdbe sorter distinct current source numeric affinity refresh reseeks duplicate class'] = static function (TestRunner $t) use ($initialRows, $updatedRows): void {
    $cursor = new SQLiteVdbeSorterDistinctCurrentSourceCursor($initialRows, 'bytes', 'rowid', 'enabled', 'C', ['BINARY'], 'bytes-1');
    $cursor->next();
    $t->same(['12'], $cursor->currentKey());
    $t->same(3, $cursor->currentValue());
    $t->true($cursor->refresh($updatedRows, 'bytes-2'));
    $t->same([12.0], $cursor->currentKey());
    $t->same(4, $cursor->currentValue());
    $t->same([8, 4, 9, 1, 7, 10], $cursor->values());
};

$tests['vdbe sorter distinct current source none affinity refresh preserves storage classes'] = static function (TestRunner $t) use ($initialRows, $updatedRows): void {
    $cursor = new SQLiteVdbeSorterDistinctCurrentSourceCursor($initialRows, 'bytes', 'rowid', 'enabled', 'A', ['BINARY'], 'bytes-none-1');
    $t->same([8, 4, 2, 3, 5, 1, 7], $cursor->values());
    $t->true($cursor->refresh($updatedRows, 'bytes-none-2'));
    $t->same([8, 4, 2, 5, 9, 1, 7, 10], $cursor->values());
};

$tests['vdbe sorter distinct current source composite collation refresh preserves current composite class'] = static function (TestRunner $t) use ($initialRows, $updatedRows): void {
    $cursor = new SQLiteVdbeSorterDistinctCurrentSourceCursor($initialRows, ['bucket', 'option_name'], 'rowid', 'enabled', 'GG', ['NOCASE', 'NOCASE'], 'composite-1');
    $cursor->next();
    $cursor->next();
    $t->same(['plugin', 'plugin_cache'], $cursor->currentKey());
    $t->true($cursor->refresh($updatedRows, 'composite-2'));
    $t->same(['plugin', 'Plugin_Cache'], $cursor->currentKey());
    $t->same(4, $cursor->currentValue());
};

$tests['vdbe sorter distinct current source filter change can remove current row'] = static function (TestRunner $t) use ($make, $initialRows): void {
    $cursor = $make();
    $cursor->next();
    $cursor->next();
    $rows = $initialRows;
    $rows[2]['enabled'] = 0;
    $rows[4]['enabled'] = 0;
    $t->true($cursor->refresh($rows, 'schema-cookie-filtered'));
    $t->same(['Plugin_Cache'], $cursor->currentKey());
    $t->same(4, $cursor->currentValue());
};

$tests['vdbe sorter distinct current source inserted lower key does not rewind past current'] = static function (TestRunner $t) use ($make, $updatedRows): void {
    $cursor = $make();
    $cursor->next();
    $cursor->next();
    $rows = $updatedRows;
    $rows[] = ['rowid' => 11, 'option_name' => 'aaa_option', 'enabled' => 1];
    $t->true($cursor->refresh($rows, 'schema-cookie-with-lower-insert'));
    $t->same(['Plugin_Cache'], $cursor->currentKey());
    $t->same(4, $cursor->currentValue());
};

$tests['vdbe sorter distinct current source inserted greater key appears in values'] = static function (TestRunner $t) use ($make, $updatedRows): void {
    $cursor = $make();
    $t->true($cursor->refresh($updatedRows, 'schema-cookie-2'));
    $t->same([8, 7, 4, 5, 9, 1, 10], $cursor->values());
    $t->same([null], $cursor->currentKey());
    $t->same(8, $cursor->currentValue());
};

$tests['vdbe sorter distinct current source eof snapshot after source removal'] = static function (TestRunner $t) use ($make): void {
    $cursor = $make();
    while (!$cursor->eof() && $cursor->currentValue() !== 1) {
        $cursor->next();
    }
    $t->true($cursor->refresh([['rowid' => 8, 'option_name' => null, 'enabled' => 1]], 'schema-cookie-eof'));
    $snapshot = $cursor->snapshot();
    $t->true($snapshot['eof']);
    $t->same(null, $snapshot['currentKey']);
    $t->same(null, $snapshot['currentValue']);
    $t->same(1, $snapshot['distinctRows']);
    $t->same([8], $snapshot['values']);
};

$tests['vdbe sorter distinct current source next after refresh continues from reseeked row'] = static function (TestRunner $t) use ($make, $updatedRows): void {
    $cursor = $make();
    $cursor->next();
    $cursor->next();
    $t->true($cursor->refresh($updatedRows, 'schema-cookie-next'));
    $cursor->next();
    $t->same(['plugin_cache '], $cursor->currentKey());
    $t->same(5, $cursor->currentValue());
};

$tests['vdbe sorter distinct current source rejects empty refresh token'] = static function (TestRunner $t) use ($make, $updatedRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $make()->refresh($updatedRows, ''));
};

$tests['vdbe sorter distinct current source rejects missing refreshed key column'] = static function (TestRunner $t) use ($make): void {
    $t->throws(InvalidArgumentException::class, static fn () => $make()->refresh([['rowid' => 1, 'enabled' => 1]], 'bad-source'));
};

return $tests;
