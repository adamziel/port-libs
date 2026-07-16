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

$applicationPostSearch = "CREATE VIRTUAL TABLE wp_posts_fts USING fts5(post_title, post_content, post_excerpt UNINDEXED, content='wp_posts', content_rowid='ID', tokenize='unicode61 remove_diacritics 2 tokenchars ''-_''', prefix='2 3 4', detail=column)";
$optionRowSearch = "CREATE VIRTUAL TABLE temp.wp_option_search USING fts5(option_name, option_value, autoload UNINDEXED, tokenize='porter unicode61', prefix='3,4')";
$contentless = "CREATE VIRTUAL TABLE wp_import_fts USING fts5(title, body, source UNINDEXED, content='', columnsize=0, detail=none)";
$quoted = 'CREATE VIRTUAL TABLE "network"."wp site search" USING fts5([meta key], `meta value` UNINDEXED, tokenize="ascii", prefix="1 2")';

$cases = [
    'post status' => [$applicationPostSearch, 'status', 'ok'],
    'post module' => [$applicationPostSearch, 'module', 'fts5'],
    'post default schema' => [$applicationPostSearch, 'schema', 'main'],
    'post table' => [$applicationPostSearch, 'table', 'wp_posts_fts'],
    'post qualified name' => [$applicationPostSearch, 'qualifiedName', 'main.wp_posts_fts'],
    'post column count' => [$applicationPostSearch, 'columns.count', 3],
    'post first column' => [$applicationPostSearch, 'columns.0.name', 'post_title'],
    'post second column' => [$applicationPostSearch, 'columns.1.name', 'post_content'],
    'post unindexed column name' => [$applicationPostSearch, 'columns.2.name', 'post_excerpt'],
    'post unindexed flag' => [$applicationPostSearch, 'columns.2.unindexed', true],
    'post indexed columns only' => [$applicationPostSearch, 'indexedColumns', ['post_title', 'post_content']],
    'post unindexed columns only' => [$applicationPostSearch, 'unindexedColumns', ['post_excerpt']],
    'post tokenizer name' => [$applicationPostSearch, 'options.tokenize.name', 'unicode61'],
    'post tokenizer remove diacritics' => [$applicationPostSearch, 'options.tokenize.removeDiacritics', 2],
    'post tokenizer tokenchars' => [$applicationPostSearch, 'options.tokenize.tokenchars', '-_'],
    'post prefix lengths' => [$applicationPostSearch, 'options.prefix', [2, 3, 4]],
    'post content table' => [$applicationPostSearch, 'options.content', 'wp_posts'],
    'post external content' => [$applicationPostSearch, 'options.externalContent', true],
    'post content table present' => [$applicationPostSearch, 'options.contentTablePresent', true],
    'post content rowid' => [$applicationPostSearch, 'options.contentRowid', 'ID'],
    'post detail mode' => [$applicationPostSearch, 'options.detail', 'column'],
    'post columnsize default' => [$applicationPostSearch, 'options.columnsize', 1],
    'post shadow data table' => [$applicationPostSearch, 'shadowTables.0', 'main.wp_posts_fts_data'],
    'post shadow content table' => [$applicationPostSearch, 'shadowTables.2', 'main.wp_posts_fts_content'],
    'post shadow config table' => [$applicationPostSearch, 'shadowTables.4', 'main.wp_posts_fts_config'],
    'post import action rebuild' => [$applicationPostSearch, 'importActions.3', 'scheduleExternalContentRebuild'],
    'option schema' => [$optionRowSearch, 'schema', 'temp'],
    'option table' => [$optionRowSearch, 'table', 'wp_option_search'],
    'option qualified name' => [$optionRowSearch, 'qualifiedName', 'temp.wp_option_search'],
    'option tokenizer porter' => [$optionRowSearch, 'options.tokenize.name', 'porter'],
    'option tokenizer args' => [$optionRowSearch, 'options.tokenize.args', ['unicode61']],
    'option comma prefix lengths' => [$optionRowSearch, 'options.prefix', [3, 4]],
    'option inline content external false' => [$optionRowSearch, 'options.externalContent', false],
    'option inline content action' => [$optionRowSearch, 'importActions.3', 'copyInlineContentRows'],
    'contentless flag' => [$contentless, 'options.contentless', true],
    'contentless content value' => [$contentless, 'options.content', ''],
    'contentless columnsize' => [$contentless, 'options.columnsize', 0],
    'contentless detail' => [$contentless, 'options.detail', 'none'],
    'contentless omits content shadow' => [$contentless, 'shadowTables', ['main.wp_import_fts_data', 'main.wp_import_fts_idx', 'main.wp_import_fts_docsize', 'main.wp_import_fts_config']],
    'contentless action' => [$contentless, 'importActions.3', 'skipContentShadowRows'],
    'quoted schema' => [$quoted, 'schema', 'network'],
    'quoted table' => [$quoted, 'table', 'wp site search'],
    'quoted first column' => [$quoted, 'columns.0.name', 'meta key'],
    'quoted unindexed column' => [$quoted, 'unindexedColumns', ['meta value']],
    'quoted tokenizer' => [$quoted, 'options.tokenize.name', 'ascii'],
    'quoted prefix' => [$quoted, 'options.prefix', [1, 2]],
];

foreach ($cases as $name => [$sql, $path, $expected]) {
    $tests['fts5 schema import current next26 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $sql, $path, $expected): void {
        $t->same($expected, $valueAt($plan($sql), $path));
    };
}

$tests['fts5 schema import current next26 blocks missing external content table'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan("CREATE VIRTUAL TABLE wp_missing_fts USING fts5(title, content='wp_missing_posts')", ['wp_posts']);
    $t->same(false, $result['options']['contentTablePresent']);
    $t->same('blockMissingContentTable', $result['importActions'][3]);
};

$tests['fts5 schema import current next26 accepts if not exists'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('CREATE VIRTUAL TABLE IF NOT EXISTS wp_options_fts USING fts5(option_name, option_value)');
    $t->same('wp_options_fts', $result['table']);
    $t->same(['option_name', 'option_value'], $result['indexedColumns']);
};

$tests['fts5 schema import current next26 deduplicates prefix lengths'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan("CREATE VIRTUAL TABLE wp_prefix_fts USING fts5(body, prefix='2 2 3,3 4')");
    $t->same([2, 3, 4], $result['options']['prefix']);
};

$tests['fts5 schema import current next26 preserves separator tokenizer option'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan("CREATE VIRTUAL TABLE wp_sep_fts USING fts5(body, tokenize='unicode61 separators ''.''')");
    $t->same('.', $result['options']['tokenize']['separators']);
};

$tests['fts5 schema import current next26 supports trigram tokenizer for imported LIKE acceleration'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan("CREATE VIRTUAL TABLE wp_trigram_fts USING fts5(body, tokenize='trigram')");
    $t->same('trigram', $result['options']['tokenize']['name']);
};

$tests['fts5 schema import current next26 rejects non virtual table sql'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql('CREATE TABLE wp_posts_fts(title)'));
};

$tests['fts5 schema import current next26 rejects fts4 module'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql('CREATE VIRTUAL TABLE wp_posts_fts USING fts4(title)'));
};

$tests['fts5 schema import current next26 rejects empty column list'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql('CREATE VIRTUAL TABLE wp_posts_fts USING fts5()'));
};

$tests['fts5 schema import current next26 rejects duplicate columns'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql('CREATE VIRTUAL TABLE wp_posts_fts USING fts5(title, Title)'));
};

$tests['fts5 schema import current next26 rejects unsupported option'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql('CREATE VIRTUAL TABLE wp_posts_fts USING fts5(title, rank=custom)'));
};

$tests['fts5 schema import current next26 rejects unsupported tokenizer'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql("CREATE VIRTUAL TABLE wp_posts_fts USING fts5(title, tokenize='icu')"));
};

$tests['fts5 schema import current next26 rejects invalid prefix'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql("CREATE VIRTUAL TABLE wp_posts_fts USING fts5(title, prefix='2 x')"));
};

$tests['fts5 schema import current next26 rejects invalid detail'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql('CREATE VIRTUAL TABLE wp_posts_fts USING fts5(title, detail=page)'));
};

$tests['fts5 schema import current next26 rejects unsupported column tail'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteFts5SchemaImportPlan::fromSql('CREATE VIRTUAL TABLE wp_posts_fts USING fts5(title BOGUS)'));
};

return $tests;
