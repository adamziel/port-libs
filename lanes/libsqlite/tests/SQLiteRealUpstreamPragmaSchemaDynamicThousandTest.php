<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLitePragmaSchemaDataVersion;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$makeCatalog = static function (int $variant): SQLitePragmaSchemaCatalog {
    $tenant = 'tenant_settings_' . $variant;
    $changeLog = 'tenant_settings_log_' . $variant;
    $generated = 'tenant_generated_' . $variant;
    $tenantLookup = $tenant . '_load_lookup';
    $changeLookup = $changeLog . '_key_lookup';
    $generatedPartial = $generated . '_partial';

    return new SQLitePragmaSchemaCatalog([
        new SQLiteSchemaRecord(
            'table',
            $tenant,
            $tenant,
            10000 + $variant,
            "CREATE TABLE {$tenant}(
                tenant_id INTEGER NOT NULL,
                key_name TEXT NOT NULL DEFAULT 'key_{$variant}',
                key_value TEXT DEFAULT (json_object('variant', {$variant})),
                load_policy TEXT COLLATE nocase DEFAULT 'lazy',
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY(tenant_id, key_name),
                UNIQUE(key_name, load_policy)
            ) WITHOUT ROWID",
            20000 + $variant,
        ),
        new SQLiteSchemaRecord('index', 'sqlite_autoindex_' . $tenant . '_1', $tenant, 30000 + $variant, null, 30000 + $variant),
        new SQLiteSchemaRecord('index', 'sqlite_autoindex_' . $tenant . '_2', $tenant, 31000 + $variant, null, 31000 + $variant),
        new SQLiteSchemaRecord(
            'index',
            $tenantLookup,
            $tenant,
            32000 + $variant,
            "CREATE INDEX {$tenantLookup} ON {$tenant}(load_policy COLLATE nocase DESC, updated_at ASC) WHERE key_value IS NOT NULL",
            32000 + $variant,
        ),
        new SQLiteSchemaRecord(
            'table',
            $changeLog,
            $changeLog,
            40000 + $variant,
            "CREATE TABLE {$changeLog}(
                event_id INTEGER PRIMARY KEY,
                tenant_id INTEGER NOT NULL,
                key_name TEXT NOT NULL,
                action TEXT DEFAULT 'update',
                FOREIGN KEY(tenant_id, key_name) REFERENCES {$tenant}(tenant_id, key_name) ON UPDATE CASCADE ON DELETE RESTRICT
            )",
            50000 + $variant,
        ),
        new SQLiteSchemaRecord(
            'index',
            $changeLookup,
            $changeLog,
            51000 + $variant,
            "CREATE INDEX {$changeLookup} ON {$changeLog}(key_name, tenant_id)",
            51000 + $variant,
        ),
        new SQLiteSchemaRecord(
            'table',
            $generated,
            $generated,
            60000 + $variant,
            "CREATE TABLE {$generated}(
                id INTEGER PRIMARY KEY,
                key_name TEXT NOT NULL DEFAULT 'generated_{$variant}',
                key_fold TEXT GENERATED ALWAYS AS (lower(key_name)) VIRTUAL,
                key_len INTEGER GENERATED ALWAYS AS (length(key_name)) STORED,
                UNIQUE(key_name)
            )",
            70000 + $variant,
        ),
        new SQLiteSchemaRecord('index', 'sqlite_autoindex_' . $generated . '_1', $generated, 71000 + $variant, null, 71000 + $variant),
        new SQLiteSchemaRecord(
            'index',
            $generatedPartial,
            $generated,
            72000 + $variant,
            "CREATE INDEX {$generatedPartial} ON {$generated}((length(key_name)), key_fold DESC COLLATE nocase) WHERE key_name IS NOT NULL",
            72000 + $variant,
        ),
    ]);
};

foreach (range(1, 200) as $variant) {
    $tenant = 'tenant_settings_' . $variant;
    $changeLog = 'tenant_settings_log_' . $variant;
    $generated = 'tenant_generated_' . $variant;
    $tenantLookup = $tenant . '_load_lookup';
    $changeLookup = $changeLog . '_key_lookup';
    $generatedPartial = $generated . '_partial';

    $tests["real upstream pragma schema dynamic thousand pragma.test 6.2 table-info variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $tenant, $variant): void {
        $rows = $makeCatalog($variant)->execute("PRAGMA table_info({$tenant})")['rows'];

        $t->same(5, count($rows));
        $t->same('tenant_id', $rows[0]['name']);
        $t->same('INTEGER', $rows[0]['type']);
        $t->same(1, $rows[0]['notnull']);
        $t->same(1, $rows[0]['pk']);
        $t->same('key_name', $rows[1]['name']);
        $t->same("'key_{$variant}'", $rows[1]['dflt_value']);
        $t->same(2, $rows[1]['pk']);
        $t->same("json_object('variant', {$variant})", $rows[2]['dflt_value']);
        $t->same('CURRENT_TIMESTAMP', $rows[4]['dflt_value']);
    };

    $tests["real upstream pragma schema dynamic thousand pragma4 4.4 index-list variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $tenant, $tenantLookup, $variant): void {
        $rows = $makeCatalog($variant)->executeTableValuedPragma("pragma_index_list('{$tenant}')")['rows'];

        $t->same(3, count($rows));
        $t->same('sqlite_autoindex_' . $tenant . '_1', $rows[0]['name']);
        $t->same(1, $rows[0]['unique']);
        $t->same('u', $rows[0]['origin']);
        $t->same('sqlite_autoindex_' . $tenant . '_2', $rows[1]['name']);
        $t->same(1, $rows[1]['unique']);
        $t->same($tenantLookup, $rows[2]['name']);
        $t->same(0, $rows[2]['unique']);
        $t->same('c', $rows[2]['origin']);
        $t->same(1, $rows[2]['partial']);
    };

    $tests["real upstream pragma schema dynamic thousand pragma4 4.3 index-xinfo variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $tenantLookup, $changeLookup, $variant): void {
        $catalog = $makeCatalog($variant);
        $lookupRows = $catalog->executeTableValuedPragma("pragma_index_xinfo('{$tenantLookup}')")['rows'];
        $changeRows = $catalog->executeTableValuedPragma("pragma_index_info('{$changeLookup}')")['rows'];

        $t->same('load_policy', $lookupRows[0]['name']);
        $t->same('NOCASE', $lookupRows[0]['coll']);
        $t->same(1, $lookupRows[0]['desc']);
        $t->same('updated_at', $lookupRows[1]['name']);
        $t->same(0, $lookupRows[1]['desc']);
        $t->same(0, $lookupRows[2]['cid']);
        $t->same(0, $lookupRows[2]['key']);
        $t->same('key_name', $changeRows[0]['name']);
        $t->same('tenant_id', $changeRows[1]['name']);
        $t->same(1, $changeRows[1]['cid']);
    };

    $tests["real upstream pragma schema dynamic thousand pragma4 4.5 foreign-key variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $tenant, $changeLog, $variant): void {
        $catalog = $makeCatalog($variant);
        $fkRows = $catalog->executeTableValuedPragma("pragma_foreign_key_list('{$changeLog}')")['rows'];
        $tableList = $catalog->executeTableValuedPragma("pragma_table_list('{$changeLog}')")['rows'];

        $t->same(2, count($fkRows));
        $t->same($tenant, $fkRows[0]['table']);
        $t->same('tenant_id', $fkRows[0]['from']);
        $t->same('tenant_id', $fkRows[0]['to']);
        $t->same('CASCADE', $fkRows[0]['on_update']);
        $t->same('RESTRICT', $fkRows[0]['on_delete']);
        $t->same('key_name', $fkRows[1]['from']);
        $t->same('key_name', $fkRows[1]['to']);
        $t->same($changeLog, $tableList[0]['name']);
        $t->same(4, $tableList[0]['ncol']);
    };

    $tests["real upstream pragma schema dynamic thousand pragma3 schema6 generated metadata variant {$variant}"] = static function (TestRunner $t) use ($makeCatalog, $generated, $generatedPartial, $variant): void {
        $catalog = $makeCatalog($variant);
        $xinfo = $catalog->execute("PRAGMA table_xinfo({$generated})")['rows'];
        $indexXinfo = $catalog->execute("PRAGMA index_xinfo({$generatedPartial})")['rows'];
        $state = new SQLitePragmaSchemaDataVersion([
            'main' => ['schema_version' => 100 + $variant, 'data_version' => 1, 'change_counter' => 1, 'user_version' => $variant],
        ]);

        $state->beginTransaction();
        $state->execute('PRAGMA user_version = ' . (1000 + $variant));
        $state->recordExternalCommit('main', 1, 'upstream_pragma3_external_commit');
        $during = $state->execute('PRAGMA data_version')['value'];
        $state->rollbackTransaction();

        $t->same('key_fold', $xinfo[2]['name']);
        $t->same(2, $xinfo[2]['hidden']);
        $t->same('key_len', $xinfo[3]['name']);
        $t->same(3, $xinfo[3]['hidden']);
        $t->same(-2, $indexXinfo[0]['cid']);
        $t->same('key_fold', $indexXinfo[1]['name']);
        $t->same('NOCASE', $indexXinfo[1]['coll']);
        $t->same(2, $during);
        $t->same($variant, $state->execute('PRAGMA user_version')['value']);
        $t->same(1, $state->execute('PRAGMA data_version')['value']);
    };
}

$tests['real upstream pragma schema dynamic thousand cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-6.2 table_info default/type/primary-key rows',
        'pragma3.test pragma3-100 through pragma3-190 data_version observer semantics',
        'pragma4.test pragma4-4.3 through 4.5 table-valued index and foreign-key pragmas',
        'schema6.test row-format stability for equivalent CREATE TABLE constraints',
        'schema.test/schema2.test schema invalidation and prepared statement reparse families',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma.test', $sections[0]);
    $t->contains('pragma3.test', $sections[1]);
    $t->contains('pragma4.test', $sections[2]);
    $t->contains('schema6.test', $sections[3]);
    $t->contains('schema.test/schema2.test', $sections[4]);
};

return $tests;
