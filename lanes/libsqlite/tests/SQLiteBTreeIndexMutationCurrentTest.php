<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeIndexMutationCurrent;
use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexLeafPage;
use PortLibs\LibSqlite\SQLiteOverflowPage;
use PortLibs\LibSqlite\SQLiteRecord;

$indexCell = static fn (array $values): string => SQLiteIndexCell::encode(SQLiteRecord::encode($values));

$basePage = static function () use ($indexCell): string {
    return SQLiteIndexLeafPage::assemble([
        $indexCell(['_site_transient_update_plugins', 7]),
        $indexCell(['active_plugins', 2]),
        $indexCell(['blog_public', 3]),
        $indexCell(['stylesheet', 4]),
    ]);
};

$replace = static fn (): array => SQLiteBTreeIndexMutationCurrent::replaceRecordValuesReusingFreedCell(
    $basePage(),
    ['active_plugins', 2],
    ['autoload', 2],
    secureDelete: true,
);

$batch = static fn (): array => SQLiteBTreeIndexMutationCurrent::applyReplacementBatch($basePage(), [
    ['delete' => ['active_plugins', 2], 'insert' => ['autoload', 2]],
    ['delete' => ['blog_public', 3], 'insert' => ['blog_public', 9]],
], secureDelete: true);

$records = static function (string $page): array {
    $header = SQLiteBTreePageHeader::parsePage($page, 512);
    return array_map(
        static fn (SQLiteIndexCell $cell): array => $cell->record()->values,
        SQLiteIndexCell::parsePageCells($page, $header),
    );
};

$overflowFixture = static function (): array {
    $payload = SQLiteRecord::encode([str_repeat('plugin-', 80), 99]);
    $encoded = SQLiteIndexCell::encodeWithOverflowPages($payload, 7);
    $overflowPages = [];
    foreach ($encoded['overflowPages'] as $offset => $overflowPage) {
        $overflowPages[7 + $offset] = $overflowPage;
    }
    $reader = static function (int $firstPage, int $byteCount) use ($overflowPages): string {
        $payload = '';
        foreach (SQLiteOverflowPage::pageNumbersFromChain($firstPage, $byteCount, static fn (int $pageNumber): string => $overflowPages[$pageNumber]) as $pageNumber) {
            $payload .= substr($overflowPages[$pageNumber], 4);
        }

        return substr($payload, 0, $byteCount);
    };

    $page = SQLiteIndexLeafPage::assemble([
        SQLiteIndexCell::encode(SQLiteRecord::encode(['alpha', 1])),
        $encoded['cell'],
        SQLiteIndexCell::encode(SQLiteRecord::encode(['omega', 2])),
    ]);

    return [$page, $reader, $overflowPages];
};

$overflowReplace = static function () use ($overflowFixture): array {
    [$page, $reader, $overflowPages] = $overflowFixture();
    return SQLiteBTreeIndexMutationCurrent::replaceRecordValuesReusingFreedCell(
        $page,
        [str_repeat('plugin-', 80), 99],
        ['plugin-short', 99],
        static fn (int $firstPage, int $byteCount): array => SQLiteOverflowPage::pageNumbersFromChain($firstPage, $byteCount, static fn (int $pageNumber): string => $overflowPages[$pageNumber]),
        overflowReader: $reader,
        secureDelete: true,
    );
};

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $segment) {
        $value = $value[$segment];
    }

    return $value;
};

$cases = [
    'single mutation page type remains index leaf' => [$replace, 'after_insert.page_type', 'index-leaf'],
    'single mutation preserves cell count' => [$replace, 'after_insert.cell_count', 4],
    'single mutation before had four cell pointers' => [static fn (): mixed => count($replace()['before']['cell_pointers']), null, 4],
    'single mutation after insert has four cell pointers' => [static fn (): mixed => count($replace()['after_insert']['cell_pointers']), null, 4],
    'single mutation removes stale active plugins record' => [static fn (): mixed => in_array(['active_plugins', 2], $records($replace()['page']), true), null, false],
    'single mutation inserts replacement autoload record' => [static fn (): mixed => in_array(['autoload', 2], $records($replace()['page']), true), null, true],
    'single mutation keeps lower key before replacement' => [static fn (): mixed => $records($replace()['page'])[0], null, ['_site_transient_update_plugins', 7]],
    'single mutation sorts replacement before blog public' => [static fn (): mixed => $records($replace()['page'])[1], null, ['autoload', 2]],
    'single mutation reports applied' => [$replace, 'mutation_applied', true],
    'single mutation reuses freed offset' => [static fn (): mixed => $replace()['inserted_cell_offset'] === $replace()['reused_freeblock_offset'], null, true],
    'single mutation keeps freeblock integrity ok' => [$replace, 'after_insert.integrity_status', 'ok'],
    'single mutation keeps one freeblock after smaller insert' => [$replace, 'after_insert.freeblock_count', 1],
    'single mutation freeblock offset is retained after insert' => [static fn (): mixed => $replace()['after_insert']['first_freeblock_offset'] > $replace()['reused_freeblock_offset'], null, true],
    'single mutation increases free space after shorter replacement' => [static fn (): mixed => $replace()['after_insert']['free_space_bytes'] > $replace()['before']['free_space_bytes'], null, true],
    'single mutation records no overflow pages for local cell' => [$replace, 'obsolete_overflow_page_numbers', []],
    'single mutation carries dependency closure' => [static fn (): mixed => str_contains($replace()['dependency_closure'], 'SQLiteIndexLeafPage'), null, true],
    'single mutation carries non overlap note' => [static fn (): mixed => str_contains($replace()['non_overlap'], 'does not repeat page relocation'), null, true],
    'batch mutation count' => [$batch, 'mutation_count', 2],
    'batch all mutations applied' => [$batch, 'all_mutations_applied', true],
    'batch records keep site transient first' => [static fn (): mixed => $records($batch()['page'])[0], null, ['_site_transient_update_plugins', 7]],
    'batch records keep stylesheet last' => [static fn (): mixed => $records($batch()['page'])[3], null, ['stylesheet', 4]],
    'batch inserted first replacement' => [static fn (): mixed => in_array(['autoload', 2], $records($batch()['page']), true), null, true],
    'batch inserted second replacement' => [static fn (): mixed => in_array(['blog_public', 9], $records($batch()['page']), true), null, true],
    'batch removed first stale record' => [static fn (): mixed => in_array(['active_plugins', 2], $records($batch()['page']), true), null, false],
    'batch removed second stale record' => [static fn (): mixed => in_array(['blog_public', 3], $records($batch()['page']), true), null, false],
    'batch first mutation reused offset' => [static fn (): mixed => $batch()['mutations'][0]['inserted_cell_offset'] === $batch()['mutations'][0]['reused_freeblock_offset'], null, true],
    'batch second mutation reused offset' => [static fn (): mixed => $batch()['mutations'][1]['inserted_cell_offset'] === $batch()['mutations'][1]['reused_freeblock_offset'], null, true],
    'batch obsolete overflow pages empty' => [$batch, 'obsolete_overflow_page_numbers', []],
    'overflow mutation reports obsolete page seven' => [$overflowReplace, 'obsolete_overflow_page_numbers', [7]],
    'overflow mutation after delete free space grew' => [static fn (): mixed => $overflowReplace()['after_delete']['free_space_bytes'] > $overflowReplace()['before']['free_space_bytes'], null, true],
    'overflow mutation inserts local replacement' => [static fn (): mixed => in_array(['plugin-short', 99], $records($overflowReplace()['page']), true), null, true],
    'overflow mutation removes long record' => [static fn (): mixed => in_array([str_repeat('plugin-', 80), 99], $records($overflowReplace()['page']), true), null, false],
    'overflow mutation reports applied' => [$overflowReplace, 'mutation_applied', true],
    'overflow mutation keeps cell count' => [$overflowReplace, 'after_insert.cell_count', 3],
    'overflow mutation keeps integrity ok' => [$overflowReplace, 'after_insert.integrity_status', 'ok'],
    'overflow mutation reuses freed local offset' => [static fn (): mixed => $overflowReplace()['inserted_cell_offset'] === $overflowReplace()['reused_freeblock_offset'], null, true],
    'overflow mutation increases free space' => [static fn (): mixed => $overflowReplace()['after_insert']['free_space_bytes'] > $overflowReplace()['before']['free_space_bytes'], null, true],
    'overflow mutation delete summary has one freeblock before insertion' => [$overflowReplace, 'after_delete.freeblock_count', 1],
    'overflow mutation stores short key after omega' => [static fn (): mixed => $records($overflowReplace()['page'])[2], null, ['plugin-short', 99]],
];

$tests = [];

foreach ($cases as $name => [$factory, $path, $expected]) {
    $tests['btree index mutation current ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt): void {
        $actual = $path === null ? $factory() : $valueAt($factory(), $path);
        $t->same($expected, $actual);
    };
}

$tests['btree index mutation current rejects duplicate replacement'] = static function (TestRunner $t) use ($basePage): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexMutationCurrent::replaceRecordValuesReusingFreedCell(
        $basePage(),
        ['active_plugins', 2],
        ['stylesheet', 4],
    ));
};

$tests['btree index mutation current rejects missing delete key'] = static function (TestRunner $t) use ($basePage): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexMutationCurrent::replaceRecordValuesReusingFreedCell(
        $basePage(),
        ['missing', 1],
        ['autoload', 2],
    ));
};

$tests['btree index mutation current rejects empty batch'] = static function (TestRunner $t) use ($basePage): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexMutationCurrent::applyReplacementBatch($basePage(), []));
};

$tests['btree index mutation current rejects malformed batch entry'] = static function (TestRunner $t) use ($basePage): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteBTreeIndexMutationCurrent::applyReplacementBatch($basePage(), [['delete' => ['active_plugins', 2]]]));
};

return $tests;
