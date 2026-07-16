<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteFts5SchemaImportPlan;

$plan = static function (string $sql, array $tables = ['wp_posts', 'wp_options', 'wp_sitemeta']): array {
    return SQLiteFts5SchemaImportPlan::fromSql($sql, $tables);
};

$valueAt = static function (mixed $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$wpPosts = "CREATE VIRTUAL TABLE wp_posts_fts USING fts5(post_title, post_content, post_excerpt UNINDEXED, content='wp_posts', content_rowid='ID', tokenize='unicode61 remove_diacritics 2 tokenchars ''-_''', prefix='2 3 4', detail=column, tokendata=1)";
$wpOptions = "CREATE VIRTUAL TABLE temp.wp_options_fts USING fts5(option_name, option_value, autoload UNINDEXED, tokenize='porter unicode61', prefix='3,4')";
$contentlessDelete = "CREATE VIRTUAL TABLE wp_import_fts USING fts5(title, body, source UNINDEXED, content='', contentless_delete=1, columnsize=0, detail=none)";
$quoted = 'CREATE VIRTUAL TABLE "network"."wp site search" USING fts5([meta key], `meta value` UNINDEXED, tokenize="ascii", prefix="1 2")';

$cases = [
    'post schema record count' => [$wpPosts, 'schemaRecords.count', 6],
    'post virtual schema record type' => [$wpPosts, 'schemaRecords.0.type', 'table'],
    'post virtual schema record name' => [$wpPosts, 'schemaRecords.0.name', 'main.wp_posts_fts'],
    'post virtual schema record tbl name' => [$wpPosts, 'schemaRecords.0.tbl_name', 'wp_posts_fts'],
    'post virtual schema root page' => [$wpPosts, 'schemaRecords.0.rootpage', 0],
    'post virtual schema is not shadow' => [$wpPosts, 'schemaRecords.0.shadow', false],
    'post virtual schema SQL preserves options' => [$wpPosts, 'schemaRecords.0.sql', $wpPosts],
    'post data shadow record name' => [$wpPosts, 'schemaRecords.1.name', 'main.wp_posts_fts_data'],
    'post data shadow sql' => [$wpPosts, 'schemaRecords.1.sql', 'CREATE TABLE "main"."wp_posts_fts_data"(id INTEGER PRIMARY KEY, block BLOB)'],
    'post idx shadow record name' => [$wpPosts, 'schemaRecords.2.name', 'main.wp_posts_fts_idx'],
    'post idx shadow sql' => [$wpPosts, 'schemaRecords.2.sql', 'CREATE TABLE "main"."wp_posts_fts_idx"(segid, term, pgno, PRIMARY KEY(segid, term)) WITHOUT ROWID'],
    'post content shadow record name' => [$wpPosts, 'schemaRecords.3.name', 'main.wp_posts_fts_content'],
    'post content shadow sql' => [$wpPosts, 'schemaRecords.3.sql', 'CREATE TABLE "main"."wp_posts_fts_content"(id INTEGER PRIMARY KEY, "post_title", "post_content", "post_excerpt")'],
    'post docsize shadow record name' => [$wpPosts, 'schemaRecords.4.name', 'main.wp_posts_fts_docsize'],
    'post docsize shadow sql' => [$wpPosts, 'schemaRecords.4.sql', 'CREATE TABLE "main"."wp_posts_fts_docsize"(id INTEGER PRIMARY KEY, sz BLOB)'],
    'post config shadow record name' => [$wpPosts, 'schemaRecords.5.name', 'main.wp_posts_fts_config'],
    'post config shadow sql' => [$wpPosts, 'schemaRecords.5.sql', 'CREATE TABLE "main"."wp_posts_fts_config"(k PRIMARY KEY, v) WITHOUT ROWID'],
    'post rebuild sql' => [$wpPosts, 'externalContentSql.rebuild', 'INSERT INTO "main"."wp_posts_fts"("main"."wp_posts_fts") VALUES(\'rebuild\')'],
    'post delete all sql' => [$wpPosts, 'externalContentSql.deleteAll', 'INSERT INTO "main"."wp_posts_fts"("main"."wp_posts_fts") VALUES(\'delete-all\')'],
    'post insert select sql' => [$wpPosts, 'externalContentSql.insertSelect', 'INSERT INTO "main"."wp_posts_fts"("rowid", "post_title", "post_content", "post_excerpt") SELECT "ID", "post_title", "post_content", "post_excerpt" FROM "wp_posts"'],
    'post external blocked reason empty' => [$wpPosts, 'externalContentSql.blockedReason', null],
    'post json kind' => [$wpPosts, 'jsonSchema.kind', 'sqlite-fts5-import-schema'],
    'post json schema' => [$wpPosts, 'jsonSchema.schema', 'main'],
    'post json table' => [$wpPosts, 'jsonSchema.table', 'wp_posts_fts'],
    'post json first column name' => [$wpPosts, 'jsonSchema.columns.0.name', 'post_title'],
    'post json first column indexed' => [$wpPosts, 'jsonSchema.columns.0.indexed', true],
    'post json unindexed column name' => [$wpPosts, 'jsonSchema.columns.2.name', 'post_excerpt'],
    'post json unindexed column indexed flag' => [$wpPosts, 'jsonSchema.columns.2.indexed', false],
    'post json tokenizer name' => [$wpPosts, 'jsonSchema.tokenizer.name', 'unicode61'],
    'post json tokenizer tokenchars' => [$wpPosts, 'jsonSchema.tokenizer.tokenchars', '-_'],
    'post json prefix' => [$wpPosts, 'jsonSchema.prefix', [2, 3, 4]],
    'post json content' => [$wpPosts, 'jsonSchema.content', 'wp_posts'],
    'post json external content' => [$wpPosts, 'jsonSchema.externalContent', true],
    'post json content table present' => [$wpPosts, 'jsonSchema.contentTablePresent', true],
    'post json contentless false' => [$wpPosts, 'jsonSchema.contentless', false],
    'post json contentless delete false' => [$wpPosts, 'jsonSchema.contentlessDelete', false],
    'post json tokendata true' => [$wpPosts, 'jsonSchema.tokendata', true],
    'post json detail' => [$wpPosts, 'jsonSchema.detail', 'column'],
    'post json columnsize' => [$wpPosts, 'jsonSchema.columnsize', 1],
    'option schema record count' => [$wpOptions, 'schemaRecords.count', 6],
    'option temp content sql' => [$wpOptions, 'schemaRecords.3.sql', 'CREATE TABLE "temp"."wp_options_fts_content"(id INTEGER PRIMARY KEY, "option_name", "option_value", "autoload")'],
    'option no external rebuild' => [$wpOptions, 'externalContentSql.rebuild', null],
    'option inline blocked reason empty' => [$wpOptions, 'externalContentSql.blockedReason', null],
    'option json tokenizer porter' => [$wpOptions, 'jsonSchema.tokenizer.name', 'porter'],
    'option json tokenizer args' => [$wpOptions, 'jsonSchema.tokenizer.args', ['unicode61']],
    'option json prefix' => [$wpOptions, 'jsonSchema.prefix', [3, 4]],
    'option json external content false' => [$wpOptions, 'jsonSchema.externalContent', false],
    'contentless schema record count' => [$contentlessDelete, 'schemaRecords.count', 5],
    'contentless skips content shadow record' => [$contentlessDelete, 'shadowTables', ['main.wp_import_fts_data', 'main.wp_import_fts_idx', 'main.wp_import_fts_docsize', 'main.wp_import_fts_config']],
    'contentless docsize record index' => [$contentlessDelete, 'schemaRecords.3.name', 'main.wp_import_fts_docsize'],
    'contentless no rebuild' => [$contentlessDelete, 'externalContentSql.rebuild', null],
    'contentless json contentless true' => [$contentlessDelete, 'jsonSchema.contentless', true],
    'contentless json contentless delete true' => [$contentlessDelete, 'jsonSchema.contentlessDelete', true],
    'contentless json detail none' => [$contentlessDelete, 'jsonSchema.detail', 'none'],
    'contentless json columnsize zero' => [$contentlessDelete, 'jsonSchema.columnsize', 0],
    'quoted schema record name' => [$quoted, 'schemaRecords.0.name', 'network.wp site search'],
    'quoted content sql' => [$quoted, 'schemaRecords.3.sql', 'CREATE TABLE "network"."wp site search_content"(id INTEGER PRIMARY KEY, "meta key", "meta value")'],
    'quoted json table' => [$quoted, 'jsonSchema.table', 'wp site search'],
    'quoted json first column' => [$quoted, 'jsonSchema.columns.0.name', 'meta key'],
    'quoted json second column unindexed' => [$quoted, 'jsonSchema.columns.1.indexed', false],
    'quoted data shadow quote' => [$quoted, 'schemaRecords.1.sql', 'CREATE TABLE "network"."wp site search_data"(id INTEGER PRIMARY KEY, block BLOB)'],
];

foreach ($cases as $name => [$sql, $path, $expected]) {
    $tests['fts5 json schema current next36 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $sql, $path, $expected): void {
        $t->same($expected, $valueAt($plan($sql), $path));
    };
}

$tests['fts5 json schema current next36 reports missing external content in sql plan'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan("CREATE VIRTUAL TABLE wp_missing_fts USING fts5(title, body, content='wp_missing_posts')", ['wp_posts']);
    $t->same(null, $result['externalContentSql']['rebuild']);
    $t->same(null, $result['externalContentSql']['deleteAll']);
    $t->same(null, $result['externalContentSql']['insertSelect']);
    $t->same('missing external content table', $result['externalContentSql']['blockedReason']);
    $t->same(false, $result['jsonSchema']['contentTablePresent']);
};

$tests['fts5 json schema current next36 quotes embedded identifier quotes in rebuild SQL'] = static function (TestRunner $t) use ($plan): void {
    $sql = 'CREATE VIRTUAL TABLE "wp""fts" USING fts5("post""title", body, content="wp_posts", content_rowid="ID")';
    $result = $plan($sql, ['wp_posts']);
    $t->same('INSERT INTO "main"."wp""fts"("rowid", "post""title", "body") SELECT "ID", "post""title", "body" FROM "wp_posts"', $result['externalContentSql']['insertSelect']);
    $t->same('CREATE TABLE "main"."wp""fts_content"(id INTEGER PRIMARY KEY, "post""title", "body")', $result['schemaRecords'][3]['sql']);
};

$tests['fts5 json schema current next36 rejects contentless delete on external content'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql("CREATE VIRTUAL TABLE wp_fts USING fts5(title, content='wp_posts', contentless_delete=1)", ['wp_posts']));
};

$tests['fts5 json schema current next36 rejects contentless delete on inline content'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql('CREATE VIRTUAL TABLE wp_fts USING fts5(title, contentless_delete=1)'));
};

$tests['fts5 json schema current next36 rejects invalid contentless delete boolean'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql("CREATE VIRTUAL TABLE wp_fts USING fts5(title, content='', contentless_delete=maybe)"));
};

$tests['fts5 json schema current next36 accepts textual tokendata boolean'] = static function (TestRunner $t): void {
    $result = SQLiteFts5SchemaImportPlan::fromSql("CREATE VIRTUAL TABLE wp_fts USING fts5(title, tokendata='on')");
    $t->same(true, $result['options']['tokendata']);
    $t->same(true, $result['jsonSchema']['tokendata']);
};

$tests['fts5 json schema current next36 rejects invalid tokendata boolean'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql('CREATE VIRTUAL TABLE wp_fts USING fts5(title, tokendata=2)'));
};

$tests['fts5 json schema current next36 rejects non integer columnsize'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql('CREATE VIRTUAL TABLE wp_fts USING fts5(title, columnsize=small)'));
};

$tests['fts5 json schema current next36 rejects negative columnsize'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql('CREATE VIRTUAL TABLE wp_fts USING fts5(title, columnsize=-1)'));
};

$tests['fts5 json schema current next36 rejects columnsize above one'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql('CREATE VIRTUAL TABLE wp_fts USING fts5(title, columnsize=2)'));
};

return $tests;
