<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteIndexPredicate;
use PortLibs\LibSqlite\SQLitePragmaIntegrityPartialIndexCurrentSourceNext;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'load_policy' => 'yes', 'tenant_id' => 1, 'priority' => 10],
    ['setting_id' => 2, 'key_name' => 'home', 'load_policy' => 'yes', 'tenant_id' => 1, 'priority' => 20],
    ['setting_id' => 3, 'key_name' => 'cache_feed', 'load_policy' => 'no', 'tenant_id' => 1, 'priority' => 30],
    ['setting_id' => 4, 'key_name' => 'cron', 'load_policy' => 'yes', 'tenant_id' => 2, 'priority' => 40],
    ['setting_id' => 5, 'key_name' => 'module_cache', 'load_policy' => null, 'tenant_id' => 2, 'priority' => 50],
];
$predicate = new SQLiteIndexPredicate('load_policy', SQLiteIndexPredicate::EQUALS, 'yes');
$andPredicate = new SQLiteIndexPredicate('', SQLiteIndexPredicate::AND, [
    new SQLiteIndexPredicate('load_policy', SQLiteIndexPredicate::EQUALS, 'yes'),
    new SQLiteIndexPredicate('tenant_id', SQLiteIndexPredicate::IN_LIST, [1, 2]),
    new SQLiteIndexPredicate('priority', SQLiteIndexPredicate::BETWEEN, ['lower' => 10, 'upper' => 40]),
]);
$orPredicate = new SQLiteIndexPredicate('', SQLiteIndexPredicate::OR, [
    new SQLiteIndexPredicate('key_name', SQLiteIndexPredicate::EQUALS, 'base_url'),
    new SQLiteIndexPredicate('load_policy', SQLiteIndexPredicate::IS_NOT_NULL),
]);
$indexColumns = ['load_policy', 'key_name'];
$validEntries = [
    ['rowid' => 1, 'load_policy' => 'yes', 'key_name' => 'base_url'],
    ['rowid' => 2, 'load_policy' => 'yes', 'key_name' => 'home'],
    ['rowid' => 4, 'load_policy' => 'yes', 'key_name' => 'cron'],
];
$missingEntries = [
    ['rowid' => 1, 'load_policy' => 'yes', 'key_name' => 'base_url'],
    ['rowid' => 4, 'load_policy' => 'yes', 'key_name' => 'cron'],
];
$staleEntries = [
    ...$validEntries,
    ['rowid' => 3, 'load_policy' => 'no', 'key_name' => 'cache_feed'],
];
$orphanEntries = [
    ...$validEntries,
    ['rowid' => 99, 'load_policy' => 'yes', 'key_name' => 'ghost'],
];

$page = static function (
    ?array $entryRows = null,
    ?SQLiteIndexPredicate $partial = null,
    string $pragma = 'PRAGMA integrity_check',
    int $offset = 0,
    int $limit = 126,
    ?array $cursor = null,
) use ($rows, $validEntries, $predicate, $indexColumns): array {
    return SQLitePragmaIntegrityPartialIndexCurrentSourceNext::page(
        $rows,
        $entryRows ?? $validEntries,
        $partial ?? $predicate,
        $indexColumns,
        $offset,
        $limit,
        'app_settings',
        'app_settings_load_policy_yes',
        $pragma,
        $cursor,
    );
};

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$cases = [
    'valid status ok' => [static fn (): array => $page(), 'status', 'ok'],
    'valid total rows' => [static fn (): array => $page(), 'total', 5],
    'valid count rows' => [static fn (): array => $page(), 'count', 5],
    'valid complete' => [static fn (): array => $page(), 'complete', true],
    'valid next null' => [static fn (): array => $page(), 'next', null],
    'source id length' => [static fn (): array => ['length' => strlen($page()['source_id'])], 'length', 64],
    'current source table' => [static fn (): array => $page(), 'current_source.table', 'app_settings'],
    'current source index' => [static fn (): array => $page(), 'current_source.index', 'app_settings_load_policy_yes'],
    'current source pragma' => [static fn (): array => $page(), 'current_source.pragma', 'integrity_check'],
    'current source row count' => [static fn (): array => $page(), 'current_source.row_count', 5],
    'current source index count' => [static fn (): array => $page(), 'current_source.index_entry_count', 3],
    'current source columns' => [static fn (): array => $page(), 'current_source.index_columns', ['load_policy', 'key_name']],
    'current rows count' => [static fn (): array => $page(), 'current.rows', 5],
    'current index entry diagnostics count' => [static fn (): array => $page(), 'current.index_entries', 0],
    'current predicate matches' => [static fn (): array => $page(), 'current.predicate_matches', 3],
    'current errors zero' => [static fn (): array => $page(), 'current.errors', 0],
    'current missing zero' => [static fn (): array => $page(), 'current.missing', 0],
    'current stale zero' => [static fn (): array => $page(), 'current.stale', 0],
    'current orphan zero' => [static fn (): array => $page(), 'current.orphan', 0],
    'row0 kind' => [static fn (): array => $page(), 'rows.0.kind', 'partial_index_row'],
    'row0 source' => [static fn (): array => $page(), 'rows.0.source', 'pragma_integrity_check'],
    'row0 rowid' => [static fn (): array => $page(), 'rows.0.rowid', 1],
    'row0 key' => [static fn (): array => $page(), 'rows.0.key', 'text:yes|text:base_url|rowid:1'],
    'row0 predicate matches' => [static fn (): array => $page(), 'rows.0.predicate_matches', true],
    'row0 index present' => [static fn (): array => $page(), 'rows.0.index_present', true],
    'row0 status ok' => [static fn (): array => $page(), 'rows.0.status', 'ok'],
    'row2 predicate false' => [static fn (): array => $page(), 'rows.2.predicate_matches', false],
    'row2 index absent' => [static fn (): array => $page(), 'rows.2.index_present', false],
    'row4 null key' => [static fn (): array => $page(), 'rows.4.key', 'null:|text:module_cache|rowid:5'],
    'missing status blocked' => [static fn (): array => $page($missingEntries), 'status', 'blocked'],
    'missing errors count' => [static fn (): array => $page($missingEntries), 'current.errors', 1],
    'missing count' => [static fn (): array => $page($missingEntries), 'current.missing', 1],
    'missing row status' => [static fn (): array => $page($missingEntries), 'rows.1.status', 'missing_index_entry'],
    'missing row message' => [static fn (): array => $page($missingEntries), 'rows.1.message', 'partial index app_settings_load_policy_yes is missing rowid 2 for table app_settings'],
    'stale status blocked' => [static fn (): array => $page($staleEntries), 'status', 'blocked'],
    'stale errors count' => [static fn (): array => $page($staleEntries), 'current.errors', 1],
    'stale count' => [static fn (): array => $page($staleEntries), 'current.stale', 1],
    'stale row status' => [static fn (): array => $page($staleEntries), 'rows.2.status', 'stale_index_entry'],
    'stale row message' => [static fn (): array => $page($staleEntries), 'rows.2.message', 'partial index app_settings_load_policy_yes contains rowid 3 that does not satisfy the WHERE clause'],
    'quick skips stale entry' => [static fn (): array => $page($staleEntries, $predicate, 'PRAGMA quick_check'), 'status', 'ok'],
    'quick source pragma' => [static fn (): array => $page($staleEntries, $predicate, 'PRAGMA quick_check'), 'current_source.pragma', 'quick_check'],
    'quick stale zero' => [static fn (): array => $page($staleEntries, $predicate, 'PRAGMA quick_check'), 'current.stale', 0],
    'orphan status blocked' => [static fn (): array => $page($orphanEntries), 'status', 'blocked'],
    'orphan total includes entry row' => [static fn (): array => $page($orphanEntries), 'total', 6],
    'orphan index entry diagnostics count' => [static fn (): array => $page($orphanEntries), 'current.index_entries', 1],
    'orphan count' => [static fn (): array => $page($orphanEntries), 'current.orphan', 1],
    'orphan row kind' => [static fn (): array => $page($orphanEntries), 'rows.5.kind', 'partial_index_entry'],
    'orphan row status' => [static fn (): array => $page($orphanEntries), 'rows.5.status', 'orphan_index_entry'],
    'and predicate status ok' => [static fn (): array => $page($validEntries, $andPredicate), 'status', 'ok'],
    'and predicate matches' => [static fn (): array => $page($validEntries, $andPredicate), 'current.predicate_matches', 3],
    'or predicate status blocked' => [static fn (): array => $page($validEntries, $orPredicate), 'status', 'blocked'],
    'or predicate missing rows' => [static fn (): array => $page($validEntries, $orPredicate), 'current.missing', 1],
    'or predicate row2 missing' => [static fn (): array => $page($validEntries, $orPredicate), 'rows.2.status', 'missing_index_entry'],
    'pagination count' => [static fn (): array => $page($orphanEntries, $predicate, 'integrity_check', 0, 2), 'count', 2],
    'pagination total' => [static fn (): array => $page($orphanEntries, $predicate, 'integrity_check', 0, 2), 'total', 6],
    'pagination incomplete' => [static fn (): array => $page($orphanEntries, $predicate, 'integrity_check', 0, 2), 'complete', false],
    'pagination next offset' => [static fn (): array => $page($orphanEntries, $predicate, 'integrity_check', 0, 2), 'next.offset', 2],
    'pagination next row' => [static fn (): array => $page($orphanEntries, $predicate, 'integrity_check', 0, 2), 'next_row.rowid', 2],
    'resume count' => [static fn (): array => $page($orphanEntries, $predicate, 'integrity_check', 2, 2, $page($orphanEntries, $predicate, 'integrity_check', 0, 2)['next']), 'count', 2],
    'resume offset' => [static fn (): array => $page($orphanEntries, $predicate, 'integrity_check', 2, 2, $page($orphanEntries, $predicate, 'integrity_check', 0, 2)['next']), 'offset', 2],
    'resume first rowid' => [static fn (): array => $page($orphanEntries, $predicate, 'integrity_check', 2, 2, $page($orphanEntries, $predicate, 'integrity_check', 0, 2)['next']), 'rows.0.rowid', 3],
    'last page complete' => [static fn (): array => $page($orphanEntries, $predicate, 'integrity_check', 4, 4), 'complete', true],
    'last page next null' => [static fn (): array => $page($orphanEntries, $predicate, 'integrity_check', 4, 4), 'next', null],
];

foreach ($cases as $name => [$factory, $path, $expected]) {
    $tests['pragma integrity partial index current source next126 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt): void {
        $t->same($expected, $valueAt($factory(), $path));
    };
}

$tests['pragma integrity partial index current source next126 rejects stale cursor source'] = static function (TestRunner $t) use ($page, $orphanEntries, $predicate): void {
    $cursor = $page($orphanEntries, $predicate, 'integrity_check', 0, 2)['next'];
    $cursor['source_id'] = str_repeat('0', 64);

    $t->throws(InvalidArgumentException::class, static fn () => $page($orphanEntries, $predicate, 'integrity_check', 2, 2, $cursor));
};

$tests['pragma integrity partial index current source next126 rejects cursor offset mismatch'] = static function (TestRunner $t) use ($page, $orphanEntries, $predicate): void {
    $cursor = $page($orphanEntries, $predicate, 'integrity_check', 0, 2)['next'];

    $t->throws(InvalidArgumentException::class, static fn () => $page($orphanEntries, $predicate, 'integrity_check', 3, 2, $cursor));
};

$tests['pragma integrity partial index current source next126 rejects empty index columns'] = static function (TestRunner $t) use ($rows, $validEntries, $predicate): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityPartialIndexCurrentSourceNext::page($rows, $validEntries, $predicate, []));
};

$tests['pragma integrity partial index current source next126 rejects duplicate index entry keys'] = static function (TestRunner $t) use ($rows, $validEntries, $predicate, $indexColumns): void {
    $entries = [$validEntries[0], $validEntries[0]];

    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityPartialIndexCurrentSourceNext::page($rows, $entries, $predicate, $indexColumns));
};

$tests['pragma integrity partial index current source next126 rejects unsupported pragma'] = static function (TestRunner $t) use ($rows, $validEntries, $predicate, $indexColumns): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLitePragmaIntegrityPartialIndexCurrentSourceNext::page($rows, $validEntries, $predicate, $indexColumns, 0, 126, 'app_settings', 'app_settings_load_policy_yes', 'PRAGMA table_info(app_settings)'));
};

return $tests;
