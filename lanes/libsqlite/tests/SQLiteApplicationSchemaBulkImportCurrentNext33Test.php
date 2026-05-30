<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaBulkImportPlan;

$dump = <<<'SQL'
-- Application schema copied from an import dump. Semicolons inside strings must not split.
CREATE TABLE wp_options (
  option_id INTEGER PRIMARY KEY AUTOINCREMENT,
  option_name TEXT NOT NULL UNIQUE COLLATE NOCASE,
  option_value TEXT NOT NULL DEFAULT '',
  autoload TEXT NOT NULL DEFAULT 'yes',
  CHECK (autoload IN ('yes','no'))
);
CREATE TABLE IF NOT EXISTS wp_posts (
  ID INTEGER PRIMARY KEY,
  post_title TEXT NOT NULL DEFAULT '',
  post_content TEXT NOT NULL DEFAULT ''
);
CREATE UNIQUE INDEX wp_options_option_name ON wp_options(option_name COLLATE NOCASE);
CREATE INDEX wp_options_autoload_name ON wp_options(autoload, option_name);
CREATE VIEW wp_autoloaded_options AS SELECT option_name, option_value FROM wp_options WHERE autoload = 'yes';
CREATE TRIGGER wp_options_ai AFTER INSERT ON wp_options BEGIN
  INSERT INTO wp_import_log(message) VALUES('inserted; option');
END;
INSERT INTO wp_options(option_name, option_value) VALUES('siteurl','https://example.test');
SQL;

$plan = static fn (array $existing = [], array $options = []): array => SQLiteSchemaBulkImportPlan::plan(
    $dump,
    $existing,
    array_replace(['schema_version' => 12, 'data_version' => 4, 'next_rootpage' => 8], $options)
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        if (ctype_digit($part)) {
            $part = (int) $part;
        }
        $value = $value[$part];
    }

    return $value;
};

$byName = static function (array $plan, string $name): array {
    foreach ($plan['objects'] as $object) {
        if ($object['name'] === $name) {
            return $object;
        }
    }

    throw new RuntimeException("Missing object {$name}");
};

$cases = [
    'status is ok' => static fn (): mixed => $plan()['status'],
    'statement count keeps unsupported data insert' => static fn (): mixed => $plan()['statement_count'],
    'applied count excludes unsupported insert' => static fn (): mixed => $plan()['applied_count'],
    'skipped count defaults zero' => static fn (): mixed => $plan()['skipped_count'],
    'one unsupported warning is recorded' => static fn (): mixed => $valueAt($plan(), 'warnings.count'),
    'unsupported warning names non schema statement' => static fn (): mixed => $valueAt($plan(), 'warnings.0.reason'),
    'schema version before preserved' => static fn (): mixed => $plan()['schema_version_before'],
    'schema version after increments per applied object' => static fn (): mixed => $plan()['schema_version_after'],
    'data version before preserved' => static fn (): mixed => $plan()['data_version_before'],
    'data version after increments once' => static fn (): mixed => $plan()['data_version_after'],
    'transaction begins immediate' => static fn (): mixed => $valueAt($plan(), 'transaction.begin'),
    'transaction commits' => static fn (): mixed => $valueAt($plan(), 'transaction.commit'),
    'transaction is atomic' => static fn (): mixed => $valueAt($plan(), 'transaction.atomic'),
    'dependency order puts tables first' => static fn (): mixed => array_slice($plan()['ordered_names'], 0, 2),
    'dependency order puts indexes after tables' => static fn (): mixed => array_slice($plan()['ordered_names'], 2, 2),
    'dependency order puts view before trigger' => static fn (): mixed => array_slice($plan()['ordered_names'], 4, 2),
    'wp options table rootpage assigned' => static fn (): mixed => $byName($plan(), 'wp_options')['rootpage'],
    'wp posts table rootpage assigned' => static fn (): mixed => $byName($plan(), 'wp_posts')['rootpage'],
    'option name index rootpage assigned' => static fn (): mixed => $byName($plan(), 'wp_options_option_name')['rootpage'],
    'autoload index rootpage assigned' => static fn (): mixed => $byName($plan(), 'wp_options_autoload_name')['rootpage'],
    'view has no rootpage' => static fn (): mixed => $byName($plan(), 'wp_autoloaded_options')['rootpage'],
    'trigger has no rootpage' => static fn (): mixed => $byName($plan(), 'wp_options_ai')['rootpage'],
    'table object type captured' => static fn (): mixed => $byName($plan(), 'wp_options')['type'],
    'index object type captured' => static fn (): mixed => $byName($plan(), 'wp_options_option_name')['type'],
    'view object type captured' => static fn (): mixed => $byName($plan(), 'wp_autoloaded_options')['type'],
    'trigger object type captured' => static fn (): mixed => $byName($plan(), 'wp_options_ai')['type'],
    'index owning table captured' => static fn (): mixed => $byName($plan(), 'wp_options_option_name')['table'],
    'trigger owning table captured' => static fn (): mixed => $byName($plan(), 'wp_options_ai')['table'],
    'view table remains null' => static fn (): mixed => $byName($plan(), 'wp_autoloaded_options')['table'],
    'wp options autoindex counted' => static fn (): mixed => $byName($plan(), 'wp_options')['autoindex_count'],
    'wp posts rowid primary key has no autoindex' => static fn (): mixed => $byName($plan(), 'wp_posts')['autoindex_count'],
    'table dependency records autoindex helper' => static fn (): mixed => in_array('sqlite-create-table-autoindex', $byName($plan(), 'wp_options')['dependencies'], true),
    'index dependency records create index columns' => static fn (): mixed => in_array('sqlite-create-index-columns', $byName($plan(), 'wp_options_autoload_name')['dependencies'], true),
    'plan dependency names bulk import' => static fn (): mixed => in_array('sqlite-schema-bulk-import', $plan()['dependencies'], true),
    'plan dependency names schema cookie' => static fn (): mixed => in_array('sqlite-schema-cookie-update', $plan()['dependencies'], true),
    'quoted table name is normalized' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan('CREATE TABLE "wp options" (id INTEGER);')['ordered_names'][0],
    'bracket index name is normalized' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan('CREATE TABLE wp_options(id INTEGER); CREATE INDEX [idx options] ON wp_options(id);')['ordered_names'][1],
    'backtick view name is normalized' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan('CREATE VIEW `wp view` AS SELECT 1;')['ordered_names'][0],
    'if not exists duplicate skips existing table' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan('CREATE TABLE IF NOT EXISTS wp_options(id INTEGER);', ['wp_options' => []])['skipped'][0]['reason'],
    'if not exists duplicate skipped count' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan('CREATE TABLE IF NOT EXISTS wp_options(id INTEGER);', ['wp_options' => []])['skipped_count'],
    'if not exists duplicate does not increment schema version' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan('CREATE TABLE IF NOT EXISTS wp_options(id INTEGER);', ['wp_options' => []], ['schema_version' => 9])['schema_version_after'],
    'honor if not exists can be disabled' => static function (): mixed {
        try {
            SQLiteSchemaBulkImportPlan::plan('CREATE TABLE IF NOT EXISTS wp_options(id INTEGER);', ['wp_options' => []], ['honor_if_not_exists' => false]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'duplicate object in dump is rejected' => static function (): mixed {
        try {
            SQLiteSchemaBulkImportPlan::plan('CREATE TABLE wp_options(id INTEGER); CREATE TABLE wp_options(id INTEGER);');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'existing object without if not exists is rejected' => static function (): mixed {
        try {
            SQLiteSchemaBulkImportPlan::plan('CREATE TABLE wp_options(id INTEGER);', ['wp_options' => []]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'unterminated string is rejected' => static function (): mixed {
        try {
            SQLiteSchemaBulkImportPlan::plan("CREATE TABLE wp_options(name TEXT DEFAULT 'bad);");
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'unterminated block comment is rejected' => static function (): mixed {
        try {
            SQLiteSchemaBulkImportPlan::plan('/* bad comment');
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'negative schema version is rejected' => static function (): mixed {
        try {
            SQLiteSchemaBulkImportPlan::plan('CREATE TABLE wp_options(id INTEGER);', [], ['schema_version' => -1]);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'non integer data version is rejected' => static function (): mixed {
        try {
            SQLiteSchemaBulkImportPlan::plan('CREATE TABLE wp_options(id INTEGER);', [], ['data_version' => 'x']);
        } catch (InvalidArgumentException) {
            return 'rejected';
        }
        return 'missed';
    },
    'rootpage lower than two is promoted' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan('CREATE TABLE wp_options(id INTEGER);', [], ['next_rootpage' => 0])['objects'][0]['rootpage'],
    'semicolon inside trigger string is preserved' => static fn (): mixed => str_contains($byName($plan(), 'wp_options_ai')['sql'], 'inserted; option'),
    'line comment is ignored while splitting' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan("-- comment ;\nCREATE TABLE wp_options(id INTEGER);")['statement_count'],
    'block comment is ignored while splitting' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan("/* comment ; */ CREATE TABLE wp_options(id INTEGER);")['statement_count'],
    'table if not exists without duplicate applies' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan('CREATE TABLE IF NOT EXISTS wp_options(id INTEGER);')['applied_count'],
    'index if not exists without duplicate applies' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan('CREATE TABLE wp_options(id INTEGER); CREATE INDEX IF NOT EXISTS idx ON wp_options(id);')['applied_count'],
    'trigger if not exists without duplicate applies' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan('CREATE TRIGGER IF NOT EXISTS trg AFTER INSERT ON wp_options BEGIN SELECT 1; END;')['applied_count'],
    'view if not exists without duplicate applies' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan('CREATE VIEW IF NOT EXISTS v AS SELECT 1;')['applied_count'],
    'unsupported only dump leaves data version stable' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan('INSERT INTO wp_options VALUES (1);', [], ['data_version' => 7])['data_version_after'],
    'unsupported only dump leaves schema version stable' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan('INSERT INTO wp_options VALUES (1);', [], ['schema_version' => 7])['schema_version_after'],
    'unsupported only dump records warning statement' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan('INSERT INTO wp_options VALUES (1);')['warnings'][0]['statement'],
    'empty dump has zero statements' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan(" \n ")['statement_count'],
    'empty dump has zero applied objects' => static fn (): mixed => SQLiteSchemaBulkImportPlan::plan(" \n ")['applied_count'],
];

$expected = [
    'status is ok' => 'ok',
    'statement count keeps unsupported data insert' => 7,
    'applied count excludes unsupported insert' => 6,
    'skipped count defaults zero' => 0,
    'one unsupported warning is recorded' => 1,
    'unsupported warning names non schema statement' => 'unsupported_or_non_schema_statement',
    'schema version before preserved' => 12,
    'schema version after increments per applied object' => 18,
    'data version before preserved' => 4,
    'data version after increments once' => 5,
    'transaction begins immediate' => 'BEGIN IMMEDIATE',
    'transaction commits' => 'COMMIT',
    'transaction is atomic' => true,
    'dependency order puts tables first' => ['wp_options', 'wp_posts'],
    'dependency order puts indexes after tables' => ['wp_options_autoload_name', 'wp_options_option_name'],
    'dependency order puts view before trigger' => ['wp_autoloaded_options', 'wp_options_ai'],
    'wp options table rootpage assigned' => 8,
    'wp posts table rootpage assigned' => 9,
    'option name index rootpage assigned' => 10,
    'autoload index rootpage assigned' => 11,
    'view has no rootpage' => 0,
    'trigger has no rootpage' => 0,
    'table object type captured' => 'table',
    'index object type captured' => 'index',
    'view object type captured' => 'view',
    'trigger object type captured' => 'trigger',
    'index owning table captured' => 'wp_options',
    'trigger owning table captured' => 'wp_options',
    'view table remains null' => null,
    'wp options autoindex counted' => 1,
    'wp posts rowid primary key has no autoindex' => 0,
    'table dependency records autoindex helper' => true,
    'index dependency records create index columns' => true,
    'plan dependency names bulk import' => true,
    'plan dependency names schema cookie' => true,
    'quoted table name is normalized' => 'wp options',
    'bracket index name is normalized' => 'idx options',
    'backtick view name is normalized' => 'wp view',
    'if not exists duplicate skips existing table' => 'already_exists_if_not_exists',
    'if not exists duplicate skipped count' => 1,
    'if not exists duplicate does not increment schema version' => 9,
    'honor if not exists can be disabled' => 'rejected',
    'duplicate object in dump is rejected' => 'rejected',
    'existing object without if not exists is rejected' => 'rejected',
    'unterminated string is rejected' => 'rejected',
    'unterminated block comment is rejected' => 'rejected',
    'negative schema version is rejected' => 'rejected',
    'non integer data version is rejected' => 'rejected',
    'rootpage lower than two is promoted' => 2,
    'semicolon inside trigger string is preserved' => true,
    'line comment is ignored while splitting' => 1,
    'block comment is ignored while splitting' => 1,
    'table if not exists without duplicate applies' => 1,
    'index if not exists without duplicate applies' => 2,
    'trigger if not exists without duplicate applies' => 1,
    'view if not exists without duplicate applies' => 1,
    'unsupported only dump leaves data version stable' => 7,
    'unsupported only dump leaves schema version stable' => 7,
    'unsupported only dump records warning statement' => 'INSERT INTO wp_options VALUES (1)',
    'empty dump has zero statements' => 0,
    'empty dump has zero applied objects' => 0,
];

$tests = [];
foreach ($cases as $name => $callback) {
    $tests['sqlite application schema bulk import current next33 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
