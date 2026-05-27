<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeAggregateDistinctCursor;

$optionRows = [
    ['rowid' => 1, 'site' => 'main', 'bucket' => 'Core', 'option_name' => 'SiteUrl', 'option_value' => 'https://example.test', 'bytes' => '24', 'enabled' => 1],
    ['rowid' => 2, 'site' => 'main', 'bucket' => 'core', 'option_name' => 'siteurl', 'option_value' => 'https://example.test/dupe', 'bytes' => 24, 'enabled' => 1],
    ['rowid' => 3, 'site' => 'main', 'bucket' => 'plugin', 'option_name' => 'plugin_cache ', 'option_value' => 'warm', 'bytes' => '12', 'enabled' => 1],
    ['rowid' => 4, 'site' => 'main', 'bucket' => 'plugin', 'option_name' => 'plugin_cache', 'option_value' => 'warm-again', 'bytes' => 12.0, 'enabled' => 1],
    ['rowid' => 5, 'site' => 'network', 'bucket' => 'Plugin', 'option_name' => 'Plugin_Cache', 'option_value' => 'network', 'bytes' => '12.00', 'enabled' => 1],
    ['rowid' => 6, 'site' => 'network', 'bucket' => 'transient', 'option_name' => '_transient_feed', 'option_value' => 'feed', 'bytes' => 30, 'enabled' => 0],
    ['rowid' => 7, 'site' => 'network', 'bucket' => 'transient', 'option_name' => '_transient_feed_timeout', 'option_value' => 'timeout', 'bytes' => 30, 'enabled' => '1'],
    ['rowid' => 8, 'site' => 'main', 'bucket' => null, 'option_name' => null, 'option_value' => null, 'bytes' => null, 'enabled' => 1],
];

$tests = [];

$cursor = static fn (array|string $keys, string $value = 'option_name', ?string $filter = 'enabled', array|string $affinity = 'G', array $collations = []): SQLiteVdbeAggregateDistinctCursor => new SQLiteVdbeAggregateDistinctCursor($optionRows, $keys, $value, $filter, $affinity, $collations);

$tests['vdbe sorter distinct collation current starts on null key'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([null], $cursor('option_name')->currentKey());
};

$tests['vdbe sorter distinct collation current value exposes null option'] = static function (TestRunner $t) use ($cursor): void {
    $t->same(null, $cursor('option_name')->currentValue());
};

$tests['vdbe sorter distinct collation current row exposes null source row'] = static function (TestRunner $t) use ($cursor): void {
    $t->same(8, $cursor('option_name')->currentRow()['rowid']);
};

$tests['vdbe sorter distinct collation next moves from null to transient key'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor('option_name');
    $c->next();
    $t->same(['Plugin_Cache'], $c->currentKey());
};

$tests['vdbe sorter distinct collation default binary keeps case variants'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([null, 'Plugin_Cache', 'SiteUrl', '_transient_feed_timeout', 'plugin_cache', 'plugin_cache ', 'siteurl'], $cursor('option_name')->values());
};

$tests['vdbe sorter distinct collation nocase collapses case variants'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([null, '_transient_feed_timeout', 'plugin_cache', 'plugin_cache ', 'SiteUrl'], $cursor('option_name', 'option_name', 'enabled', 'G', ['NOCASE'])->values());
};

$tests['vdbe sorter distinct collation rtrim collapses padded plugin names'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([null, 'Plugin_Cache', 'SiteUrl', '_transient_feed_timeout', 'plugin_cache ', 'siteurl'], $cursor('option_name', 'option_name', 'enabled', 'G', ['RTRIM'])->values());
};

$tests['vdbe sorter distinct collation nocase rtrim composite collapses plugin variants'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([null, '_transient_feed_timeout', 'plugin_cache', 'plugin_cache ', 'SiteUrl'], $cursor('option_name', 'option_name', 'enabled', 'G', ['NOCASE'])->values());
};

$tests['vdbe sorter distinct collation numeric affinity collapses textual byte variants'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([null, 'plugin_cache ', 'SiteUrl', '_transient_feed_timeout'], $cursor('bytes', 'option_name', 'enabled', 'C')->values());
};

$tests['vdbe sorter distinct collation none affinity preserves byte storage classes'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([null, 'plugin_cache', 'siteurl', '_transient_feed_timeout', 'plugin_cache ', 'Plugin_Cache', 'SiteUrl'], $cursor('bytes', 'option_name', 'enabled', 'A')->values());
};

$tests['vdbe sorter distinct collation integer affinity uses first sorted duplicate'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([null, 'plugin_cache ', 'SiteUrl', '_transient_feed_timeout'], $cursor('bytes', 'option_name', 'enabled', 'D')->values());
};

$tests['vdbe sorter distinct collation current key follows numeric affinity'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor('bytes', 'option_name', 'enabled', 'C');
    $c->next();
    $t->same(['12'], $c->currentKey());
};

$tests['vdbe sorter distinct collation current row is first input among equal numeric keys'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor('bytes', 'option_name', 'enabled', 'C');
    $c->next();
    $t->same(3, $c->currentRow()['rowid']);
};

$tests['vdbe sorter distinct collation filter removes disabled duplicate before distinct'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([null, '12', '24', 30], $cursor('bytes', 'bytes', 'enabled', 'C')->values());
};

$tests['vdbe sorter distinct collation no filter includes disabled transient peer'] = static function (TestRunner $t) use ($optionRows): void {
    $c = new SQLiteVdbeAggregateDistinctCursor($optionRows, 'option_name', 'option_name', null, 'G');
    $t->same('_transient_feed', $c->values()[3]);
};

$tests['vdbe sorter distinct collation current remains stable before next'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor('option_name', 'rowid');
    $t->same($c->currentValue(), $c->currentValue());
};

$tests['vdbe sorter distinct collation remaining drains from current position'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor('option_name', 'rowid', 'enabled', 'G', ['NOCASE']);
    $c->next();
    $t->same([7, 4, 3, 1], array_map(static fn (array $entry): int => $entry['value'], $c->remaining()));
};

$tests['vdbe sorter distinct collation remaining leaves cursor at eof'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor('option_name');
    $c->remaining();
    $t->true($c->eof());
};

$tests['vdbe sorter distinct collation rewind after remaining restores first row'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor('option_name', 'rowid');
    $c->remaining();
    $c->rewind();
    $t->same(8, $c->currentValue());
};

$tests['vdbe sorter distinct collation next at final row reaches eof'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor('option_name', 'rowid', 'enabled', 'G', ['NOCASE']);
    $c->next();
    $c->next();
    $c->next();
    $c->next();
    $c->next();
    $t->true($c->eof());
};

$tests['vdbe sorter distinct collation next at eof stays eof'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor('option_name', 'rowid', 'enabled', 'G', ['NOCASE']);
    $c->remaining();
    $c->next();
    $t->true($c->eof());
};

$tests['vdbe sorter distinct collation summary reports input and distinct counts'] = static function (TestRunner $t) use ($cursor, $optionRows): void {
    $t->same(['inputRows' => 8, 'distinctRows' => 5, 'filtered' => true, 'eof' => false], $cursor('option_name', 'rowid', 'enabled', 'G', ['NOCASE'])->summary(count($optionRows)));
};

$tests['vdbe sorter distinct collation summary eof follows drain'] = static function (TestRunner $t) use ($cursor, $optionRows): void {
    $c = $cursor('option_name', 'rowid', 'enabled', 'G', ['NOCASE']);
    $c->remaining();
    $t->same(['inputRows' => 8, 'distinctRows' => 5, 'filtered' => true, 'eof' => true], $c->summary(count($optionRows)));
};

$tests['vdbe sorter distinct collation count ignores null current argument'] = static function (TestRunner $t) use ($cursor): void {
    $t->same(4, $cursor('option_name', 'option_value', 'enabled', 'G', ['NOCASE'])->countValue());
};

$tests['vdbe sorter distinct collation group concat follows sorter order'] = static function (TestRunner $t) use ($cursor): void {
    $t->same('_transient_feed_timeout|plugin_cache|plugin_cache |SiteUrl', $cursor('option_name', 'option_name', 'enabled', 'G', ['NOCASE'])->groupConcat('|'));
};

$tests['vdbe sorter distinct collation group concat null separator returns null'] = static function (TestRunner $t) use ($cursor): void {
    $t->same(null, $cursor('option_name')->groupConcat(null));
};

$tests['vdbe sorter distinct collation group concat blob separator uses bytes'] = static function (TestRunner $t) use ($cursor): void {
    $t->same('_transient_feed_timeout::plugin_cache::plugin_cache ::SiteUrl', $cursor('option_name', 'option_name', 'enabled', 'G', ['NOCASE'])->groupConcat(new SQLiteBlobValue('::')));
};

$tests['vdbe sorter distinct collation sum uses deduplicated numeric byte keys'] = static function (TestRunner $t) use ($cursor): void {
    $t->same(66, $cursor('bytes', 'bytes', 'enabled', 'C')->sum());
};

$tests['vdbe sorter distinct collation avg uses deduplicated numeric byte keys'] = static function (TestRunner $t) use ($cursor): void {
    $t->same(22.0, $cursor('bytes', 'bytes', 'enabled', 'C')->avg());
};

$tests['vdbe sorter distinct collation total returns floating total'] = static function (TestRunner $t) use ($cursor): void {
    $t->same(66.0, $cursor('bytes', 'bytes', 'enabled', 'C')->total());
};

$tests['vdbe sorter distinct collation composite site and name keeps per site duplicates'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([null, 'plugin_cache', 'plugin_cache ', 'SiteUrl', '_transient_feed_timeout', 'Plugin_Cache'], $cursor(['site', 'option_name'], 'option_name', 'enabled', 'GG', ['BINARY', 'NOCASE'])->values());
};

$tests['vdbe sorter distinct collation composite site name current key includes both slots'] = static function (TestRunner $t) use ($cursor): void {
    $t->same(['main', null], $cursor(['site', 'option_name'], 'option_name', 'enabled', 'GG', ['BINARY', 'NOCASE'])->currentKey());
};

$tests['vdbe sorter distinct collation composite site name next reaches plugin key'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor(['site', 'option_name'], 'option_name', 'enabled', 'GG', ['BINARY', 'NOCASE']);
    $c->next();
    $t->same(['main', 'plugin_cache'], $c->currentKey());
};

$tests['vdbe sorter distinct collation composite bucket nocase collapses bucket variants'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([null, 'Core', 'core', 'Plugin', 'plugin', 'plugin', 'transient'], $cursor(['bucket', 'option_name'], 'bucket', 'enabled', 'GG', ['NOCASE', 'BINARY'])->values());
};

$tests['vdbe sorter distinct collation composite bucket rtrim keeps case when binary'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([null, 'Core', 'Plugin', 'core', 'plugin', 'transient'], $cursor(['bucket'], 'bucket', 'enabled', 'G', ['BINARY'])->values());
};

$tests['vdbe sorter distinct collation composite numeric text key collapses row pair'] = static function (TestRunner $t) use ($optionRows): void {
    $c = new SQLiteVdbeAggregateDistinctCursor($optionRows, ['site', 'bytes'], 'rowid', 'enabled', 'GC', ['BINARY', 'BINARY']);
    $t->same([8, 3, 1, 5, 7], $c->values());
};

$tests['vdbe sorter distinct collation composite none affinity preserves byte text pair'] = static function (TestRunner $t) use ($optionRows): void {
    $c = new SQLiteVdbeAggregateDistinctCursor($optionRows, ['site', 'bytes'], 'rowid', 'enabled', 'GA', ['BINARY', 'BINARY']);
    $t->same([8, 4, 2, 3, 1, 7, 5], $c->values());
};

$tests['vdbe sorter distinct collation null option sorts before underscore'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor('option_name');
    $c->next();
    $t->same('Plugin_Cache', $c->currentValue());
};

$tests['vdbe sorter distinct collation nocase first duplicate wins by input sequence'] = static function (TestRunner $t) use ($optionRows): void {
    $c = new SQLiteVdbeAggregateDistinctCursor($optionRows, 'option_name', 'rowid', 'enabled', 'G', ['NOCASE']);
    $t->same([8, 7, 4, 3, 1], $c->values());
};

$tests['vdbe sorter distinct collation rtrim first padded duplicate wins'] = static function (TestRunner $t) use ($optionRows): void {
    $c = new SQLiteVdbeAggregateDistinctCursor($optionRows, 'option_name', 'rowid', 'enabled', 'G', ['RTRIM']);
    $t->same([8, 5, 1, 7, 3, 2], $c->values());
};

$tests['vdbe sorter distinct collation no case rtrim first padded duplicate wins'] = static function (TestRunner $t) use ($optionRows): void {
    $c = new SQLiteVdbeAggregateDistinctCursor($optionRows, 'option_name', 'rowid', 'enabled', 'G', ['NOCASE']);
    $t->same([8, 7, 4, 3, 1], $c->values());
};

$tests['vdbe sorter distinct collation all disabled rows produce eof'] = static function (TestRunner $t): void {
    $c = new SQLiteVdbeAggregateDistinctCursor([['v' => 'a', 'ok' => 0], ['v' => 'b', 'ok' => null]], 'v', 'v', 'ok');
    $t->true($c->eof());
};

$tests['vdbe sorter distinct collation all disabled current is null'] = static function (TestRunner $t): void {
    $c = new SQLiteVdbeAggregateDistinctCursor([['v' => 'a', 'ok' => 0], ['v' => 'b', 'ok' => null]], 'v', 'v', 'ok');
    $t->same(null, $c->current());
};

$tests['vdbe sorter distinct collation numeric text filter true includes row'] = static function (TestRunner $t): void {
    $c = new SQLiteVdbeAggregateDistinctCursor([['v' => 'a', 'ok' => '2']], 'v', 'v', 'ok');
    $t->same(['a'], $c->values());
};

$tests['vdbe sorter distinct collation float zero text filter false excludes row'] = static function (TestRunner $t): void {
    $c = new SQLiteVdbeAggregateDistinctCursor([['v' => 'a', 'ok' => '0.0'], ['v' => 'b', 'ok' => '0.1']], 'v', 'v', 'ok');
    $t->same(['b'], $c->values());
};

$tests['vdbe sorter distinct collation boolean false filter excludes row'] = static function (TestRunner $t): void {
    $c = new SQLiteVdbeAggregateDistinctCursor([['v' => 'a', 'ok' => false], ['v' => 'b', 'ok' => true]], 'v', 'v', 'ok');
    $t->same(['b'], $c->values());
};

$tests['vdbe sorter distinct collation eof key throws after drain'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor('option_name');
    $c->remaining();
    $t->throws(OutOfBoundsException::class, static fn () => $c->currentKey());
};

$tests['vdbe sorter distinct collation eof value throws after drain'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor('option_name');
    $c->remaining();
    $t->throws(OutOfBoundsException::class, static fn () => $c->currentValue());
};

$tests['vdbe sorter distinct collation eof row throws after drain'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor('option_name');
    $c->remaining();
    $t->throws(OutOfBoundsException::class, static fn () => $c->currentRow());
};

$tests['vdbe sorter distinct collation rejects unsupported collation name'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateDistinctCursor([['v' => 'a'], ['v' => 'b']], 'v', 'v', null, 'G', ['UNICODE']));
};

$tests['vdbe sorter distinct collation rejects missing composite key column'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateDistinctCursor([['v' => 1]], ['v', 'missing'], 'v'));
};

$tests['vdbe sorter distinct collation rejects associative input rows'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateDistinctCursor(['first' => ['v' => 1]], 'v', 'v'));
};

return $tests;
