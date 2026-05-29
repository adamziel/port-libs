<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record189 = static fn (string $type, string $name, string $table, int $root, string $sql, int $rowid): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $root, $sql, $rowid);

$currentRecords189 = [
    $record189('table', 'wp_terms', 'wp_terms', 4, 'CREATE TABLE wp_terms(term_id INTEGER PRIMARY KEY, slug TEXT COLLATE NOCASE, active INTEGER)', 1),
    $record189('table', 'wp_termmeta', 'wp_termmeta', 5, 'CREATE TABLE wp_termmeta(meta_id INTEGER PRIMARY KEY, term_slug TEXT COLLATE NOCASE, meta_key TEXT, FOREIGN KEY(term_slug) REFERENCES wp_terms(slug))', 2),
    $record189('index', 'wp_terms_slug_active_unique', 'wp_terms', 6, 'CREATE UNIQUE INDEX wp_terms_slug_active_unique ON wp_terms(slug COLLATE NOCASE) WHERE active = 1', 3),
    $record189('index', 'wp_terms_slug_lower_unique', 'wp_terms', 7, 'CREATE UNIQUE INDEX wp_terms_slug_lower_unique ON wp_terms(lower(slug))', 4),
    $record189('index', 'wp_termmeta_slug_lookup', 'wp_termmeta', 8, 'CREATE INDEX wp_termmeta_slug_lookup ON wp_termmeta(term_slug COLLATE NOCASE)', 5),
];
$nextRecords189 = [
    $currentRecords189[0],
    $currentRecords189[1],
    $record189('index', 'wp_terms_slug_unique', 'wp_terms', 9, 'CREATE UNIQUE INDEX wp_terms_slug_unique ON wp_terms(slug COLLATE NOCASE)', 6),
    $currentRecords189[4],
];
$currentTables189 = [
    'wp_terms' => [
        ['rowid' => 1, 'term_id' => 1, 'slug' => 'news', 'active' => 1],
        ['rowid' => 2, 'term_id' => 2, 'slug' => 'drafts', 'active' => 0],
    ],
    'wp_termmeta' => [
        ['rowid' => 1, 'meta_id' => 1, 'term_slug' => 'news', 'meta_key' => '_wp_attachment_metadata'],
    ],
];
$nextTables189 = $currentTables189;

$page189 = static fn (
    int $offset = 0,
    int $limit = 189,
    ?array $cursor = null,
    ?array $nextRecords = null,
    string $indexSql = 'PRAGMA index_xinfo(wp_terms_slug_unique)',
    bool $tableValued = false,
): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::currentNextPageFromCatalog189(
    $currentRecords189,
    $currentTables189,
    $nextRecords ?? $nextRecords189,
    $nextTables189,
    $indexSql,
    $offset,
    $limit,
    $cursor,
    $tableValued,
);

$valueAt189 = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && array_key_exists($part, $value)) {
            $value = $value[$part];
            continue;
        }
        $value = $value[(int) $part];
    }

    return $value;
};

$default189 = static fn (): array => $page189();
$blocked189 = static fn (): array => $page189(nextRecords: $currentRecords189);
$rejected189 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rejectedParentUniqueIndexRows189($currentRecords189);
$nextRejected189 = static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rejectedParentUniqueIndexRows189($nextRecords189, 'next');
$tableValued189 = static fn (): array => $page189(indexSql: "pragma_index_xinfo('wp_terms_slug_unique')", tableValued: true);

$cases189 = [
    'status ok after parent unique repair' => [$default189, 'status', 'ok'],
    'default limit' => [$default189, 'limit', 189],
    'complete true' => [$default189, 'complete', true],
    'next null' => [$default189, 'next', null],
    'next ready true' => [$default189, 'next_state.ready', true],
    'next blocking empty' => [$default189, 'next_state.blocking', []],
    'source id length' => [static fn (): array => ['len' => strlen($page189()['source_id'])], 'len', 64],
    'current source has rejected source label' => [$default189, 'current_source.foreign_key_rejected_parent_unique_source', 'pragma_index_list_partial_and_pragma_index_xinfo_expression_keys'],
    'next source has rejected source label' => [$default189, 'next_source.foreign_key_rejected_parent_unique_source', 'pragma_index_list_partial_and_pragma_index_xinfo_expression_keys'],
    'current rejected summary partial' => [$default189, 'current_source.foreign_key_rejected_parent_unique.0', 'current:wp_termmeta#0->wp_terms:wp_terms_slug_active_unique:partial_unique_index'],
    'current rejected summary expression' => [$default189, 'current_source.foreign_key_rejected_parent_unique.1', 'current:wp_termmeta#0->wp_terms:wp_terms_slug_lower_unique:expression_unique_index'],
    'next rejected summary empty' => [$default189, 'next_source.foreign_key_rejected_parent_unique', []],
    'current rejected rows' => [$default189, 'current.foreign_key_rejected_parent_unique_indexes.rows', 2],
    'current partial rejected' => [$default189, 'current.foreign_key_rejected_parent_unique_indexes.partial_unique', 1],
    'current expression rejected' => [$default189, 'current.foreign_key_rejected_parent_unique_indexes.expression_unique', 1],
    'next rejected rows clear' => [$default189, 'next_counts.foreign_key_rejected_parent_unique_indexes.rows', 0],
    'next partial rejected clear' => [$default189, 'next_counts.foreign_key_rejected_parent_unique_indexes.partial_unique', 0],
    'next expression rejected clear' => [$default189, 'next_counts.foreign_key_rejected_parent_unique_indexes.expression_unique', 0],
    'delta rejected rows' => [$default189, 'delta.foreign_key_rejected_parent_unique_rows', -2],
    'delta partial unique rows' => [$default189, 'delta.foreign_key_rejected_partial_unique_rows', -1],
    'delta expression unique rows' => [$default189, 'delta.foreign_key_rejected_expression_unique_rows', -1],
    'delta rejected cleared' => [$default189, 'delta.foreign_key_rejected_parent_unique_cleared', true],
    'delta rejected changed' => [$default189, 'delta.foreign_key_rejected_parent_unique_changed', true],
    'current admission blocked by parent index' => [$default189, 'current.index_blockers', 1],
    'next admission repaired' => [$default189, 'next_counts.index_blockers', 0],
    'next parent index admitted' => [$default189, 'next_counts.parent_indexes', ['wp_terms_slug_unique']],
    'decorates missing parent key with rejected index' => [$default189, 'rows.6.rejected_parent_unique_index', 'wp_terms_slug_active_unique'],
    'decorates missing parent key with rejected reason' => [$default189, 'rows.6.rejected_parent_unique_reason', 'partial_unique_index'],
    'first rejected row kind' => [$default189, 'rows.12.kind', 'foreign_key_rejected_parent_unique'],
    'first rejected row index' => [$default189, 'rows.12.index', 'wp_terms_slug_active_unique'],
    'first rejected row reason' => [$default189, 'rows.12.reason', 'partial_unique_index'],
    'first rejected row partial flag' => [$default189, 'rows.12.index_partial', 1],
    'first rejected row expression count' => [$default189, 'rows.12.index_expression_keys', 0],
    'first rejected row parent column' => [$default189, 'rows.12.parent_columns.0', 'slug'],
    'first rejected row key column' => [$default189, 'rows.12.index_key_columns.0', 'slug'],
    'second rejected row index' => [$default189, 'rows.13.index', 'wp_terms_slug_lower_unique'],
    'second rejected row reason' => [$default189, 'rows.13.reason', 'expression_unique_index'],
    'second rejected row partial flag' => [$default189, 'rows.13.index_partial', 0],
    'second rejected row expression count' => [$default189, 'rows.13.index_expression_keys', 1],
    'blocked remains blocked' => [$blocked189, 'status', 'blocked'],
    'blocked next rejected rows remain' => [$blocked189, 'next_counts.foreign_key_rejected_parent_unique_indexes.rows', 2],
    'blocked next includes parent blocker' => [$blocked189, 'next_state.blocking.0', 'foreign_key_parent_unique_index'],
    'blocked next includes rejected blocker' => [$blocked189, 'next_state.blocking.1', 'foreign_key_rejected_parent_unique_index'],
    'helper rejected first side' => [$rejected189, '0.side', 'current'],
    'helper rejected first message' => [$rejected189, '0.message', 'foreign key wp_termmeta->wp_terms cannot use partial UNIQUE index wp_terms_slug_active_unique as a parent key'],
    'helper rejected expression key column null' => [$rejected189, '1.index_key_columns.0', null],
    'helper next rejected empty' => [$nextRejected189, '', []],
    'table valued flag preserved' => [$tableValued189, 'current_source.table_valued_index_xinfo', true],
];

$tests = [];
foreach ($cases189 as $name => [$factory, $path, $expected]) {
    $tests['pragma index xinfo foreignkey rejected parent unique current source next189 ' . $name] = static function (TestRunner $t) use ($factory, $path, $expected, $valueAt189): void {
        $value = $factory();
        if ($path !== '') {
            $value = $valueAt189($value, $path);
        }
        $t->same($expected, $value);
    };
}

$tests['pragma index xinfo foreignkey rejected parent unique current source next189 paginates rejected rows'] = static function (TestRunner $t) use ($page189): void {
    $first = $page189(0, 13);
    $second = $page189(13, 2, $first['next']);

    $t->same(13, $first['count']);
    $t->same(['source_id' => $first['source_id'], 'offset' => 13], $first['next']);
    $t->same('foreign_key_rejected_parent_unique', $second['rows'][0]['kind']);
    $t->same('wp_terms_slug_lower_unique', $second['rows'][0]['index']);
    $t->same(null, $second['next']);
};

$tests['pragma index xinfo foreignkey rejected parent unique current source next189 source changes with full unique repair'] = static function (TestRunner $t) use ($page189, $currentRecords189): void {
    $changed = $page189();
    $same = $page189(nextRecords: $currentRecords189);

    $t->same(true, $changed['source_id'] !== $same['source_id']);
    $t->same(true, $changed['delta']['foreign_key_rejected_parent_unique_changed']);
    $t->same(false, $same['delta']['foreign_key_rejected_parent_unique_changed']);
};

$tests['pragma index xinfo foreignkey rejected parent unique current source next189 rejects stale cursor'] = static function (TestRunner $t) use ($page189, $currentRecords189): void {
    $first = $page189(0, 13);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page189(13, 2, $first['next'], nextRecords: $currentRecords189));
};

$tests['pragma index xinfo foreignkey rejected parent unique current source next189 rejects stale offset cursor'] = static function (TestRunner $t) use ($page189): void {
    $first = $page189(0, 13);

    $t->throws(InvalidArgumentException::class, static fn (): array => $page189(14, 2, $first['next']));
};

$tests['pragma index xinfo foreignkey rejected parent unique current source next189 rejects malformed records'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaIndexXinfoForeignKeyCurrentSourceNext::rejectedParentUniqueIndexRows189([['bad' => 'record']]));
};

$tests['pragma index xinfo foreignkey rejected parent unique current source next189 rejects negative offset'] = static function (TestRunner $t) use ($page189): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page189(offset: -1));
};

$tests['pragma index xinfo foreignkey rejected parent unique current source next189 rejects zero limit'] = static function (TestRunner $t) use ($page189): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => $page189(limit: 0));
};

return $tests;
