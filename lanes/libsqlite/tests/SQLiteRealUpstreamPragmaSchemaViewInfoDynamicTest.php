<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAttachedSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

/*
 * Real upstream source:
 * - SQLite test/view.test view-1.11 through view-1.14: PRAGMA table_info()
 *   reports inferred columns for simple views, SELECT * views, and views with
 *   an explicit column-name list.
 * - SQLite test/pragma4.test 4.1.* through 4.2.*: schema PRAGMA rowsets are
 *   invalidated by external schema changes, while table-valued PRAGMA calls
 *   observe the updated schema after reparse.
 */
$record = static fn (
    string $type,
    string $name,
    string $table,
    ?int $rootPage,
    ?string $sql,
    int $rowId,
): SQLiteSchemaRecord => new SQLiteSchemaRecord($type, $name, $table, $rootPage, $sql, $rowId);

$catalogFor = static function (int $variant) use ($record): SQLiteAttachedSchemaCatalog {
    $base = sprintf('view_source_%04d', $variant);
    $directView = sprintf('direct_view_%04d', $variant);
    $starView = sprintf('star_view_%04d', $variant);
    $renamedView = sprintf('renamed_view_%04d', $variant);
    $attachedBase = sprintf('attached_view_source_%04d', $variant);
    $attachedView = sprintf('attached_star_view_%04d', $variant);

    $catalog = new SQLiteAttachedSchemaCatalog([
        $record('table', $base, $base, 1000 + $variant, "CREATE TABLE {$base}(setting_id INTEGER PRIMARY KEY, key_name TEXT NOT NULL, key_value TEXT DEFAULT 'v{$variant}')", 1),
        $record('view', $directView, $directView, null, "CREATE VIEW {$directView} AS SELECT key_name FROM {$base}", 2),
        $record('view', $starView, $starView, null, "CREATE VIEW {$starView} AS SELECT * FROM {$base}", 3),
        $record('view', $renamedView, $renamedView, null, "CREATE VIEW {$renamedView}(display_key) AS SELECT key_name FROM {$base}", 4),
    ]);

    $catalog->attach('archive', "/tmp/pragma-view-info-{$variant}.sqlite", [
        $record('table', $attachedBase, $attachedBase, 2000 + $variant, "CREATE TABLE {$attachedBase}(archive_id INTEGER PRIMARY KEY, archive_key TEXT NOT NULL, archived_value TEXT DEFAULT 'a{$variant}')", 5),
        $record('view', $attachedView, $attachedView, null, "CREATE VIEW {$attachedView} AS SELECT * FROM {$attachedBase}", 6),
    ]);

    return $catalog;
};

foreach (range(1, 1000) as $variant) {
    $base = sprintf('view_source_%04d', $variant);
    $directView = sprintf('direct_view_%04d', $variant);
    $starView = sprintf('star_view_%04d', $variant);
    $renamedView = sprintf('renamed_view_%04d', $variant);
    $attachedBase = sprintf('attached_view_source_%04d', $variant);
    $attachedView = sprintf('attached_star_view_%04d', $variant);

    $tests[sprintf('real upstream pragma schema view info direct select variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $directView): void {
        $rows = $catalogFor($variant)->executeSchemaPragma("PRAGMA table_info({$directView})")['rows'];

        $t->same(['key_name'], array_column($rows, 'name'));
        $t->same(['TEXT'], array_column($rows, 'type'));
        $t->same([1], array_column($rows, 'notnull'));
        $t->same([0], array_column($rows, 'pk'));
        $t->same([null], array_column($rows, 'dflt_value'));
    };

    $tests[sprintf('real upstream pragma schema view info star expansion variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $variant, $starView, $attachedView): void {
        $catalog = $catalogFor($variant);
        $mainRows = $catalog->executeTableValuedPragma("pragma_table_info('{$starView}', 'main')")['rows'];
        $archiveRows = $catalog->executeTableValuedPragma("pragma_table_info('{$attachedView}', 'archive')")['rows'];

        $t->same(['setting_id', 'key_name', 'key_value'], array_column($mainRows, 'name'));
        $t->same(['INTEGER', 'TEXT', 'TEXT'], array_column($mainRows, 'type'));
        $t->same([1, 0, 0], array_column($mainRows, 'pk'));
        $t->same(['archive_id', 'archive_key', 'archived_value'], array_column($archiveRows, 'name'));
        $t->same(['INTEGER', 'TEXT', 'TEXT'], array_column($archiveRows, 'type'));
        $t->same([1, 0, 0], array_column($archiveRows, 'pk'));
    };

    $tests[sprintf('real upstream pragma schema view info explicit alias and reparse variant %04d', $variant)] = static function (TestRunner $t) use ($catalogFor, $record, $variant, $base, $directView, $renamedView): void {
        $catalog = $catalogFor($variant);
        $renamedRows = $catalog->executeSchemaPragma("PRAGMA table_info({$renamedView})")['rows'];
        $snapshot = $catalog->schemaCacheResolutionSnapshot([$directView], []);
        $cursor = $catalog->executeTableValuedPragmaCursor("pragma_table_info('{$directView}', 'main')");

        $catalog->replaceSchemaRecords('main', [
            $record('table', $base, $base, 3000 + $variant, "CREATE TABLE {$base}(setting_id INTEGER PRIMARY KEY, key_name TEXT, key_value TEXT)", 10),
        ]);
        $invalidation = $catalog->schemaCacheResolutionInvalidation($snapshot);
        $afterDrop = $catalog->executeTableValuedPragma("pragma_table_info('{$directView}', 'main')")['rows'];

        $t->same(['display_key'], array_column($renamedRows, 'name'));
        $t->same(['TEXT'], array_column($renamedRows, 'type'));
        $t->same('key_name', $cursor->current()['name']);
        $t->same(false, $invalidation['current']);
        $t->same([$directView], $invalidation['changed_tables']);
        $t->same([], $afterDrop);
    };
}

$tests['real upstream pragma schema view info cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'view.test view-1.11 PRAGMA table_info(v9a) returns a column selected directly from the base table',
        'view.test view-1.12 PRAGMA table_info(v9b) expands SELECT * view columns from the base table',
        'view.test view-1.13 and view-1.14 keep explicit view column-list names while preserving source type affinity',
        'pragma4.test 4.1.* and 4.2.* require schema PRAGMAs to observe dropped objects after schema reparse',
    ];

    $t->same(4, count($sections));
    $t->contains('view-1.11', $sections[0]);
    $t->contains('SELECT *', $sections[1]);
    $t->contains('view-1.14', $sections[2]);
    $t->contains('pragma4.test', $sections[3]);
};

return $tests;
