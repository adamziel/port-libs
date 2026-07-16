<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeAggregateOrderByCursor;

$rows = [
    ['rowid' => 1, 'autoload' => 'yes', 'bucket' => 'core', 'option_name' => 'siteurl', 'priority' => 20, 'seq' => 2],
    ['rowid' => 2, 'autoload' => 'yes', 'bucket' => 'core', 'option_name' => 'home', 'priority' => 30, 'seq' => 1],
    ['rowid' => 3, 'autoload' => 'yes', 'bucket' => 'plugin', 'option_name' => 'plugin_cache', 'priority' => 10, 'seq' => 3],
    ['rowid' => 4, 'autoload' => 'yes', 'bucket' => 'plugin', 'option_name' => 'Plugin_Cache', 'priority' => 10, 'seq' => 2],
    ['rowid' => 5, 'autoload' => 'no', 'bucket' => 'transient', 'option_name' => 'transient_feed', 'priority' => null, 'seq' => 1],
    ['rowid' => 6, 'autoload' => 'no', 'bucket' => 'transient', 'option_name' => 'transient_timeout_feed', 'priority' => 5, 'seq' => 2],
    ['rowid' => 7, 'autoload' => null, 'bucket' => 'network', 'option_name' => 'network_settings', 'priority' => 40, 'seq' => 1],
];

$cursor = static fn (array $orderBy = [['column' => 'priority', 'direction' => 'DESC'], ['column' => 'seq', 'direction' => 'ASC']]): SQLiteVdbeAggregateOrderByCursor => new SQLiteVdbeAggregateOrderByCursor($rows, ['autoload'], $orderBy);

$tests = [];

$tests['vdbe aggregate orderby starts at null group'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([null], $cursor()->currentGroupKey());
};

$tests['vdbe aggregate orderby exposes original current rows'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([7], array_column($cursor()->currentRows(), 'rowid'));
};

$tests['vdbe aggregate orderby exposes ordered current rows'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([7], array_column($cursor()->currentOrderedRows(), 'rowid'));
};

$tests['vdbe aggregate orderby current values preserve order'] = static function (TestRunner $t) use ($cursor): void {
    $t->same(['network_settings'], $cursor()->currentValues('option_name'));
};

$tests['vdbe aggregate orderby current concat uses separator'] = static function (TestRunner $t) use ($cursor): void {
    $t->same('network_settings', $cursor()->currentGroupConcat('option_name', '|'));
};

$tests['vdbe aggregate orderby next reaches no-autoload group'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $c->next();
    $t->same(['no'], $c->currentGroupKey());
};

$tests['vdbe aggregate orderby nulls sort last within group'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor([['column' => 'priority', 'direction' => 'ASC', 'nulls' => 'LAST']]);
    $c->next();
    $t->same([6, 5], array_column($c->currentOrderedRows(), 'rowid'));
};

$tests['vdbe aggregate orderby default nulls sort first within group'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor([['column' => 'priority', 'direction' => 'ASC']]);
    $c->next();
    $t->same([5, 6], array_column($c->currentOrderedRows(), 'rowid'));
};

$tests['vdbe aggregate orderby desc reverses null placement comparison'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor([['column' => 'priority', 'direction' => 'DESC', 'nulls' => 'LAST']]);
    $c->next();
    $t->same([5, 6], array_column($c->currentOrderedRows(), 'rowid'));
};

$tests['vdbe aggregate orderby reaches yes group after two next calls'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $c->next();
    $c->next();
    $t->same(['yes'], $c->currentGroupKey());
};

$tests['vdbe aggregate orderby multi term orders yes group'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $c->next();
    $c->next();
    $t->same([2, 1, 4, 3], array_column($c->currentOrderedRows(), 'rowid'));
};

$tests['vdbe aggregate orderby current values include all yes rows'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $c->next();
    $c->next();
    $t->same(['home', 'siteurl', 'Plugin_Cache', 'plugin_cache'], $c->currentValues('option_name'));
};

$tests['vdbe aggregate orderby concat follows aggregate order'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $c->next();
    $c->next();
    $t->same('home|siteurl|Plugin_Cache|plugin_cache', $c->currentGroupConcat('option_name', '|'));
};

$tests['vdbe aggregate orderby stable tie keeps input order'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor([['column' => 'priority', 'direction' => 'ASC']]);
    $c->next();
    $c->next();
    $t->same([3, 4, 1, 2], array_column($c->currentOrderedRows(), 'rowid'));
};

$tests['vdbe aggregate orderby secondary term breaks ties'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor([['column' => 'priority', 'direction' => 'ASC'], ['column' => 'seq', 'direction' => 'ASC']]);
    $c->next();
    $c->next();
    $t->same([4, 3, 1, 2], array_column($c->currentOrderedRows(), 'rowid'));
};

$tests['vdbe aggregate orderby nocase collation groups text ordering'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor([['column' => 'option_name', 'direction' => 'ASC', 'collation' => 'NOCASE']]);
    $c->next();
    $c->next();
    $t->same(['home', 'plugin_cache', 'Plugin_Cache', 'siteurl'], $c->currentValues('option_name'));
};

$tests['vdbe aggregate orderby binary collation keeps uppercase before lowercase'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor([['column' => 'option_name', 'direction' => 'ASC', 'collation' => 'BINARY']]);
    $c->next();
    $c->next();
    $t->same(['Plugin_Cache', 'home', 'plugin_cache', 'siteurl'], $c->currentValues('option_name'));
};

$tests['vdbe aggregate orderby summary reports group key'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([null], $cursor()->currentSummary('option_name')['key']);
};

$tests['vdbe aggregate orderby summary reports row count'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $c->next();
    $c->next();
    $t->same(4, $c->currentSummary('option_name')['rowCount']);
};

$tests['vdbe aggregate orderby summary reports ordered rowids'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $c->next();
    $c->next();
    $t->same([2, 1, 4, 3], $c->currentSummary('option_name')['orderedRowids']);
};

$tests['vdbe aggregate orderby summary reports concat'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $c->next();
    $c->next();
    $t->same('home|siteurl|Plugin_Cache|plugin_cache', $c->currentSummary('option_name')['concat']);
};

$tests['vdbe aggregate orderby drain summaries preserves group order'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([[null], ['no'], ['yes']], array_column($cursor()->drainSummaries('option_name'), 'key'));
};

$tests['vdbe aggregate orderby drain summaries preserves row counts'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([1, 2, 4], array_column($cursor()->drainSummaries('option_name'), 'rowCount'));
};

$tests['vdbe aggregate orderby drain summaries preserves ordered rowids'] = static function (TestRunner $t) use ($cursor): void {
    $t->same([[7], [6, 5], [2, 1, 4, 3]], array_column($cursor()->drainSummaries('option_name'), 'orderedRowids'));
};

$tests['vdbe aggregate orderby drain summaries preserves concat output'] = static function (TestRunner $t) use ($cursor): void {
    $t->same(['network_settings', 'transient_timeout_feed|transient_feed', 'home|siteurl|Plugin_Cache|plugin_cache'], array_column($cursor()->drainSummaries('option_name'), 'concat'));
};

$tests['vdbe aggregate orderby drain leaves cursor at eof'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $c->drainSummaries('option_name');
    $t->true($c->eof());
};

$tests['vdbe aggregate orderby next at eof remains eof'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $c->next();
    $c->next();
    $c->next();
    $c->next();
    $t->true($c->eof());
};

$tests['vdbe aggregate orderby empty cursor is eof'] = static function (TestRunner $t): void {
    $t->true((new SQLiteVdbeAggregateOrderByCursor([], ['autoload'], [['column' => 'priority']]))->eof());
};

$tests['vdbe aggregate orderby current group at eof throws'] = static function (TestRunner $t): void {
    $c = new SQLiteVdbeAggregateOrderByCursor([], ['autoload'], [['column' => 'priority']]);
    $t->throws(OutOfBoundsException::class, static fn () => $c->currentGroupKey());
};

$tests['vdbe aggregate orderby current rows at eof throws'] = static function (TestRunner $t): void {
    $c = new SQLiteVdbeAggregateOrderByCursor([], ['autoload'], [['column' => 'priority']]);
    $t->throws(OutOfBoundsException::class, static fn () => $c->currentRows());
};

$tests['vdbe aggregate orderby current ordered rows at eof throws'] = static function (TestRunner $t): void {
    $c = new SQLiteVdbeAggregateOrderByCursor([], ['autoload'], [['column' => 'priority']]);
    $t->throws(OutOfBoundsException::class, static fn () => $c->currentOrderedRows());
};

$tests['vdbe aggregate orderby current values missing column throws'] = static function (TestRunner $t) use ($cursor): void {
    $t->throws(InvalidArgumentException::class, static fn () => $cursor()->currentValues('missing'));
};

$tests['vdbe aggregate orderby missing group column throws'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderByCursor([['x' => 1]], ['autoload'], [['column' => 'x']]));
};

$tests['vdbe aggregate orderby missing order column throws'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderByCursor([['autoload' => 'yes']], ['autoload'], [['column' => 'priority']]));
};

$tests['vdbe aggregate orderby empty group list throws'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderByCursor([], [], [['column' => 'priority']]));
};

$tests['vdbe aggregate orderby empty order list throws'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderByCursor([], ['autoload'], []));
};

$tests['vdbe aggregate orderby invalid direction throws'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderByCursor([], ['autoload'], [['column' => 'priority', 'direction' => 'SIDEWAYS']]));
};

$tests['vdbe aggregate orderby invalid collation throws'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderByCursor([], ['autoload'], [['column' => 'priority', 'collation' => 'RTRIM']]));
};

$tests['vdbe aggregate orderby invalid nulls placement throws'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderByCursor([], ['autoload'], [['column' => 'priority', 'nulls' => 'MIDDLE']]));
};

$tests['vdbe aggregate orderby unsupported value type throws'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => new SQLiteVdbeAggregateOrderByCursor([['autoload' => 'yes', 'priority' => []]], ['autoload'], [['column' => 'priority']]));
};

$tests['vdbe aggregate orderby blob values sort after text'] = static function (TestRunner $t): void {
    $c = new SQLiteVdbeAggregateOrderByCursor([
        ['rowid' => 1, 'g' => 'x', 'v' => 'z'],
        ['rowid' => 2, 'g' => 'x', 'v' => new SQLiteBlobValue('a')],
    ], ['g'], [['column' => 'v']]);
    $t->same([1, 2], array_column($c->currentOrderedRows(), 'rowid'));
};

$tests['vdbe aggregate orderby booleans sort with numeric values'] = static function (TestRunner $t): void {
    $c = new SQLiteVdbeAggregateOrderByCursor([
        ['rowid' => 1, 'g' => 'x', 'v' => true],
        ['rowid' => 2, 'g' => 'x', 'v' => 0],
    ], ['g'], [['column' => 'v']]);
    $t->same([2, 1], array_column($c->currentOrderedRows(), 'rowid'));
};

$tests['vdbe aggregate orderby composite groups sort independently'] = static function (TestRunner $t): void {
    $c = new SQLiteVdbeAggregateOrderByCursor([
        ['rowid' => 1, 'autoload' => 'yes', 'bucket' => 'plugin', 'v' => 2],
        ['rowid' => 2, 'autoload' => 'yes', 'bucket' => 'core', 'v' => 1],
    ], ['autoload', 'bucket'], [['column' => 'v']]);
    $t->same([['yes', 'core'], ['yes', 'plugin']], array_column($c->drainSummaries('bucket'), 'key'));
};

$tests['vdbe aggregate orderby preserves original rows separately from ordered rows'] = static function (TestRunner $t) use ($cursor): void {
    $c = $cursor();
    $c->next();
    $c->next();
    $t->same([1, 2, 3, 4], array_column($c->currentRows(), 'rowid'));
};

$tests['vdbe aggregate orderby supports application autoload option summary'] = static function (TestRunner $t) use ($cursor): void {
    $summary = $cursor()->drainSummaries('option_name')[2];
    $t->same('home|siteurl|Plugin_Cache|plugin_cache', $summary['concat']);
};

$tests['vdbe aggregate orderby supports rowid alias summary selection'] = static function (TestRunner $t) use ($cursor): void {
    $summary = $cursor()->drainSummaries('option_name', 'rowid')[2];
    $t->same([2, 1, 4, 3], $summary['orderedRowids']);
};

return $tests;
