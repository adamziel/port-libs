<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaSchemaCatalog;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$tests = [];

$catalog = new SQLitePragmaSchemaCatalog([
    new SQLiteSchemaRecord(
        'table',
        'wp_options',
        'wp_options',
        2,
        "CREATE TABLE wp_options(
            option_id INTEGER PRIMARY KEY,
            option_name TEXT NOT NULL DEFAULT '',
            option_value TEXT DEFAULT 'contains NOT NULL and CHECK text',
            autoload TEXT DEFAULT (coalesce('yes','no')) NOT NULL,
            protected INTEGER DEFAULT 0 CHECK(protected IN (0,1)),
            cache_group TEXT DEFAULT 'default primary key unique references generated',
            escaped_quote TEXT DEFAULT 'can''t use NOT NULL here',
            quoted_keyword TEXT DEFAULT \"CHECK and REFERENCES stay literal\",
            bracket_name TEXT DEFAULT [not a constraint token],
            hex_payload BLOB DEFAULT X'4E4F54204E554C4C',
            null_default TEXT DEFAULT NULL,
            timestamp_default TEXT DEFAULT CURRENT_TIMESTAMP,
            signed_default INTEGER DEFAULT -1 NOT NULL,
            generated_name TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL,
            CONSTRAINT wp_options_name_not_blank CHECK(length(option_name) > 0),
            CHECK(option_value IS NULL OR length(option_value) >= 0),
            UNIQUE(option_name)
        )",
        1,
    ),
    new SQLiteSchemaRecord(
        'table',
        'wp_site_options',
        'wp_site_options',
        3,
        "CREATE TABLE wp_site_options(
            blog_id INTEGER NOT NULL,
            option_name TEXT DEFAULT 'network CHECK token' NOT NULL,
            option_value TEXT DEFAULT (json_object('not null','check')),
            option_order INTEGER DEFAULT +10 CHECK(option_order >= 0),
            PRIMARY KEY(blog_id, option_name)
        ) WITHOUT ROWID",
        2,
    ),
]);

$rowByName = static function (string $table, bool $xinfo = false) use ($catalog): array {
    $pragma = $xinfo ? 'table_xinfo' : 'table_info';
    $rows = $catalog->execute("PRAGMA {$pragma}({$table})")['rows'];
    $byName = [];
    foreach ($rows as $row) {
        $byName[$row['name']] = $row;
    }

    return $byName;
};

$optionRows = $rowByName('wp_options');
$optionXInfoRows = $rowByName('wp_options', true);
$siteRows = $rowByName('wp_site_options');

$cases = [
    'option_id keeps integer type' => [$optionRows, 'option_id', 'type', 'INTEGER'],
    'option_id primary key ordinal' => [$optionRows, 'option_id', 'pk', 1],
    'option_id primary key remains not null' => [$optionRows, 'option_id', 'notnull', 1],
    'option_id has no default' => [$optionRows, 'option_id', 'dflt_value', null],
    'option_name keeps text type' => [$optionRows, 'option_name', 'type', 'TEXT'],
    'option_name reports not null' => [$optionRows, 'option_name', 'notnull', 1],
    'option_name preserves empty text default' => [$optionRows, 'option_name', 'dflt_value', "''"],
    'option_value remains nullable' => [$optionRows, 'option_value', 'notnull', 0],
    'option_value keeps string default containing not null' => [$optionRows, 'option_value', 'dflt_value', "'contains NOT NULL and CHECK text'"],
    'autoload reports not null after default expression' => [$optionRows, 'autoload', 'notnull', 1],
    'autoload preserves parenthesized default expression' => [$optionRows, 'autoload', 'dflt_value', "(coalesce('yes','no'))"],
    'protected keeps integer type' => [$optionRows, 'protected', 'type', 'INTEGER'],
    'protected remains nullable with check constraint' => [$optionRows, 'protected', 'notnull', 0],
    'protected stops numeric default before check' => [$optionRows, 'protected', 'dflt_value', '0'],
    'cache group keeps long text default with constraint words' => [$optionRows, 'cache_group', 'dflt_value', "'default primary key unique references generated'"],
    'escaped quote keeps doubled quote string default' => [$optionRows, 'escaped_quote', 'dflt_value', "'can''t use NOT NULL here'"],
    'quoted keyword keeps double-quoted default' => [$optionRows, 'quoted_keyword', 'dflt_value', '"CHECK and REFERENCES stay literal"'],
    'bracket default keeps bracket quoted token' => [$optionRows, 'bracket_name', 'dflt_value', '[not a constraint token]'],
    'hex payload keeps blob literal default' => [$optionRows, 'hex_payload', 'dflt_value', "X'4E4F54204E554C4C'"],
    'hex payload keeps blob type' => [$optionRows, 'hex_payload', 'type', 'BLOB'],
    'null default is not mistaken for notnull constraint' => [$optionRows, 'null_default', 'notnull', 0],
    'null default is preserved as literal null token' => [$optionRows, 'null_default', 'dflt_value', 'NULL'],
    'timestamp default keeps bare keyword expression' => [$optionRows, 'timestamp_default', 'dflt_value', 'CURRENT_TIMESTAMP'],
    'signed default stops before not null' => [$optionRows, 'signed_default', 'dflt_value', '-1'],
    'signed default reports not null' => [$optionRows, 'signed_default', 'notnull', 1],
    'generated column omitted from table_info' => [$optionRows, 'generated_name', 'exists', false],
    'generated column present in table_xinfo' => [$optionXInfoRows, 'generated_name', 'exists', true],
    'generated column hidden code is virtual' => [$optionXInfoRows, 'generated_name', 'hidden', 2],
    'generated column has no default' => [$optionXInfoRows, 'generated_name', 'dflt_value', null],
    'table check constraint does not add a column row' => [$optionRows, 'wp_options_name_not_blank', 'exists', false],
    'blog id reports not null' => [$siteRows, 'blog_id', 'notnull', 1],
    'blog id table primary key ordinal' => [$siteRows, 'blog_id', 'pk', 1],
    'site option name table primary key ordinal' => [$siteRows, 'option_name', 'pk', 2],
    'site option name table primary key not null' => [$siteRows, 'option_name', 'notnull', 1],
    'site option name keeps string default before not null' => [$siteRows, 'option_name', 'dflt_value', "'network CHECK token'"],
    'site option value keeps json default expression' => [$siteRows, 'option_value', 'dflt_value', "(json_object('not null','check'))"],
    'site option value remains nullable' => [$siteRows, 'option_value', 'notnull', 0],
    'site option order keeps signed plus default' => [$siteRows, 'option_order', 'dflt_value', '+10'],
    'site option order remains nullable despite check' => [$siteRows, 'option_order', 'notnull', 0],
    'site table has four visible columns' => [$siteRows, 'option_order', 'table_count', 4],
];

foreach ($cases as $name => [$rows, $column, $field, $expected]) {
    $tests['check not-null default constraint corpus ' . $name] = static function (TestRunner $t) use ($rows, $column, $field, $expected): void {
        if ($field === 'exists') {
            $t->same($expected, array_key_exists($column, $rows));
            return;
        }
        if ($field === 'table_count') {
            $t->same($expected, count($rows));
            return;
        }

        $t->same($expected, $rows[$column][$field]);
    };
}

$pragmaCases = [
    'table-info row count excludes generated and table constraints' => ['PRAGMA table_info(wp_options)', 'rows.count', 13],
    'table-xinfo row count includes generated column only' => ['PRAGMA table_xinfo(wp_options)', 'rows.count', 14],
    'schema-qualified table-info keeps schema' => ['PRAGMA main.table_info(wp_options)', 'schema', 'main'],
    'table-info target survives equals syntax' => ['PRAGMA table_info = wp_options', 'target', 'wp_options'],
    'table-info quoted target survives apostrophe syntax' => ["PRAGMA table_info('wp_site_options')", 'target', 'wp_site_options'],
    'table-info option-value default keeps embedded constraint words' => ['PRAGMA table_info(wp_options)', 'rows.2.dflt_value', "'contains NOT NULL and CHECK text'"],
    'table-info autoload default keeps full expression' => ['PRAGMA table_info(wp_options)', 'rows.3.dflt_value', "(coalesce('yes','no'))"],
    'table-info numeric default trims trailing check' => ['PRAGMA table_info(wp_options)', 'rows.4.dflt_value', '0'],
    'table-info signed default trims trailing not-null' => ['PRAGMA table_info(wp_options)', 'rows.12.dflt_value', '-1'],
    'table-xinfo generated hidden value remains virtual' => ['PRAGMA table_xinfo(wp_options)', 'rows.13.hidden', 2],
];

foreach ($pragmaCases as $name => [$sql, $path, $expected]) {
    $tests['check not-null default constraint corpus ' . $name] = static function (TestRunner $t) use ($catalog, $sql, $path, $expected): void {
        $value = $catalog->execute($sql);
        foreach (explode('.', $path) as $part) {
            if ($part === 'count') {
                $value = count($value);
                continue;
            }
            $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
        }
        $t->same($expected, $value);
    };
}

return $tests;
