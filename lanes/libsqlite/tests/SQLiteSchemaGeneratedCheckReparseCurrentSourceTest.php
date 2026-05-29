<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaGeneratedCheckReparseCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$record116 = static fn (string $sql, int $version = 1): SQLiteSchemaRecord => new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, $sql, $version);

$currentSql116 = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value TEXT NOT NULL DEFAULT '',
  autoload TEXT NOT NULL DEFAULT 'yes',
  option_slug TEXT GENERATED ALWAYS AS (lower(option_name)) VIRTUAL CHECK(option_slug <> ''),
  option_value_len INTEGER AS (length(option_value || ' CHECK(option_value) ')) STORED,
  CHECK(length(option_name) > 0)
)
SQL;

$nextSql116 = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT NOT NULL,
  option_value TEXT NOT NULL DEFAULT '',
  autoload TEXT NOT NULL DEFAULT 'yes',
  option_slug TEXT GENERATED ALWAYS AS (lower(option_name)) STORED CHECK(option_slug <> '' AND option_slug NOT LIKE 'tmp_%'),
  option_value_len INTEGER AS (length(option_value || ' CHECK(option_value) ')) STORED CHECK(option_value_len BETWEEN 0 AND 1048576),
  option_bucket TEXT AS (CASE WHEN autoload = 'yes' THEN 'autoloaded' ELSE 'manual' END) VIRTUAL CHECK(option_bucket IN ('autoloaded','manual')),
  CHECK(length(option_name) > 0),
  CHECK(option_name <> 'deprecated')
)
SQL;

$stableSql116 = <<<'SQL'
CREATE TABLE wp_options(
  option_id INTEGER PRIMARY KEY,
  option_name TEXT,
  option_label TEXT AS (option_name || ' CHECK UNIQUE PRIMARY KEY ') VIRTUAL,
  UNIQUE(option_name),
  CHECK(option_name <> 'UNIQUE(option_name)')
)
SQL;

$quotedSql116 = <<<'SQL'
CREATE TABLE "wp options"(
  "option name" TEXT,
  [option-key] TEXT AS (lower("option name")) VIRTUAL CHECK([option-key] <> ''),
  `option-len` INTEGER GENERATED ALWAYS AS (length("option name")) STORED CHECK(`option-len` >= 0)
)
SQL;

$plan116 = static fn (?string $current = null, ?string $next = null, array $options = []): array => SQLiteSchemaGeneratedCheckReparseCurrentSourceNextPlan::currentNext(
    [$record116($current ?? $currentSql116)],
    [$record116($next ?? $nextSql116, 2)],
    'wp_options',
    $options + ['schema_version_before' => 116, 'schema_version_after' => 117],
);

$stable116 = static fn (): array => SQLiteSchemaGeneratedCheckReparseCurrentSourceNextPlan::currentNext(
    [$record116($stableSql116)],
    [$record116($stableSql116)],
    'wp_options',
    ['schema_version_before' => 117, 'schema_version_after' => 117],
);

$quoted116 = static fn (): array => SQLiteSchemaGeneratedCheckReparseCurrentSourceNextPlan::currentNext(
    [new SQLiteSchemaRecord('table', 'wp options', 'wp options', 2, $quotedSql116, 1)],
    [new SQLiteSchemaRecord('table', 'wp options', 'wp options', 2, $quotedSql116, 2)],
    'wp options',
    ['schema_version_before' => 1, 'schema_version_after' => 2],
);

$value116 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if ($part === 'count') {
            $value = count($value);
            continue;
        }
        $value = is_numeric($part) ? $value[(int) $part] : $value[$part];
    }

    return $value;
};

$tests = [];

foreach ([
    'operation' => ['operation', 'schema-generated-check-reparse-current-source'],
    'table' => ['table', 'wp_options'],
    'schema version before' => ['schema_version_before', 116],
    'schema version after' => ['schema_version_after', 117],
    'schema cookie changed' => ['schema_cookie_changed', true],
    'changed' => ['changed', true],
    'requires reparse' => ['requiresReparse', true],
    'status reparse required' => ['status', 'reparse-required'],
    'current status resolved' => ['current.status', 'resolved'],
    'next status resolved' => ['next.status', 'resolved'],
    'current column count' => ['current.columns.count', 6],
    'next column count' => ['next.columns.count', 7],
    'current generated count' => ['current.generatedColumns.count', 2],
    'next generated count' => ['next.generatedColumns.count', 3],
    'current first generated' => ['current.generatedColumns.0', 'option_slug'],
    'current second generated' => ['current.generatedColumns.1', 'option_value_len'],
    'next added generated' => ['generatedAdded.0', 'option_bucket'],
    'no generated removed' => ['generatedRemoved', []],
    'current generated check count' => ['current.generatedChecks.count', 1],
    'next generated check count' => ['next.generatedChecks.count', 3],
    'current generated check column' => ['current.generatedChecks.0.column', 'option_slug'],
    'current generated check expression' => ['current.generatedChecks.0.expression', 'lower(option_name)'],
    'current generated check text' => ['current.generatedChecks.0.check', "option_slug <> ''"],
    'next slug storage changed' => ['next.generatedChecks.0.storage', 'STORED'],
    'next slug check text' => ['next.generatedChecks.0.check', "option_slug <> '' AND option_slug NOT LIKE 'tmp_%'"],
    'next length check column' => ['next.generatedChecks.1.column', 'option_value_len'],
    'next length check expression keeps literal keyword' => ['next.generatedChecks.1.expression', "length(option_value || ' CHECK(option_value) ')"],
    'next length check text' => ['next.generatedChecks.1.check', 'option_value_len BETWEEN 0 AND 1048576'],
    'next bucket check column' => ['next.generatedChecks.2.column', 'option_bucket'],
    'next bucket expression' => ['next.generatedChecks.2.expression', "CASE WHEN autoload = 'yes' THEN 'autoloaded' ELSE 'manual' END"],
    'next bucket check text' => ['next.generatedChecks.2.check', "option_bucket IN ('autoloaded','manual')"],
    'generated checks added count' => ['generatedChecksAdded.count', 3],
    'generated checks removed count' => ['generatedChecksRemoved.count', 1],
    'removed check column' => ['generatedChecksRemoved.0.column', 'option_slug'],
    'table checks current count' => ['current.tableChecks.count', 1],
    'table checks next count' => ['next.tableChecks.count', 2],
    'table check added' => ['tableChecksAdded.0', "option_name <> 'deprecated'"],
    'table checks removed empty' => ['tableChecksRemoved', []],
    'next generated refs first' => ['next.generatedCheckReferences.0', 'option_bucket'],
    'next generated refs second' => ['next.generatedCheckReferences.1', 'option_slug'],
    'next generated refs third' => ['next.generatedCheckReferences.2', 'option_value_len'],
    'changed fields includes columns' => ['changedFields.0', 'columns'],
    'changed fields includes generated columns' => ['changedFields.1', 'generatedColumns'],
    'changed fields includes generated checks' => ['changedFields.2', 'generatedChecks'],
    'changed fields includes table checks' => ['changedFields.3', 'tableChecks'],
    'changed fields includes references' => ['changedFields.4', 'generatedCheckReferences'],
    'dependency ddl reparse' => ['dependencies.0', 'sqlite-schema-ddl-reparse'],
    'dependency generated catalog' => ['dependencies.1', 'sqlite-generated-column-catalog'],
    'dependency check catalog' => ['dependencies.2', 'sqlite-check-constraint-catalog'],
] as $name => [$path, $expected]) {
    $tests['schema generated check reparse current source ' . $name] = static function (TestRunner $t) use ($plan116, $value116, $path, $expected): void {
        $t->same($expected, $value116($plan116(), $path));
    };
}

foreach ([
    'stable status' => ['status', 'stable'],
    'stable no cookie change' => ['schema_cookie_changed', false],
    'stable no reparse' => ['requiresReparse', false],
    'stable no generated checks' => ['current.status', 'no-generated-checks'],
    'stable generated keyword literal ignored' => ['current.generatedDetails.0.expression', "option_name || ' CHECK UNIQUE PRIMARY KEY '"],
    'stable table check retained only as table check' => ['current.tableChecks.0', "option_name <> 'UNIQUE(option_name)'"],
    'stable generated check added empty' => ['generatedChecksAdded', []],
    'stable generated check removed empty' => ['generatedChecksRemoved', []],
    'stable changed fields empty' => ['changedFields', []],
] as $name => [$path, $expected]) {
    $tests['schema generated check reparse current source ' . $name] = static function (TestRunner $t) use ($stable116, $value116, $path, $expected): void {
        $t->same($expected, $value116($stable116(), $path));
    };
}

foreach ([
    'quoted table name' => ['table', 'wp options'],
    'quoted generated first name' => ['current.generatedColumns.0', 'option-key'],
    'quoted generated second name' => ['current.generatedColumns.1', 'option-len'],
    'quoted bracket check reference' => ['current.generatedChecks.0.references.0', 'option-key'],
    'quoted backtick check reference' => ['current.generatedChecks.1.references.0', 'option-len'],
    'quoted stored code' => ['current.generatedChecks.1.storage', 'STORED'],
] as $name => [$path, $expected]) {
    $tests['schema generated check reparse current source ' . $name] = static function (TestRunner $t) use ($quoted116, $value116, $path, $expected): void {
        $t->same($expected, $value116($quoted116(), $path));
    };
}

$tests['schema generated check reparse current source cookie unchanged keeps stable despite ddl diff'] = static function (TestRunner $t) use ($plan116): void {
    $result = $plan116(null, null, ['schema_version_before' => 9, 'schema_version_after' => 9]);
    $t->same(true, $result['changed']);
    $t->same(false, $result['requiresReparse']);
    $t->same('stable', $result['status']);
};

$tests['schema generated check reparse current source rejects missing table'] = static function (TestRunner $t) use ($record116, $currentSql116): void {
    try {
        SQLiteSchemaGeneratedCheckReparseCurrentSourceNextPlan::currentNext([$record116($currentSql116)], [$record116($currentSql116)], 'missing');
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }
    $t->same('rejected', 'missed');
};

$tests['schema generated check reparse current source rejects missing SQL'] = static function (TestRunner $t): void {
    try {
        SQLiteSchemaGeneratedCheckReparseCurrentSourceNextPlan::currentNext([
            new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, null, 1),
        ], [
            new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, null, 2),
        ], 'wp_options');
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }
    $t->same('rejected', 'missed');
};

$tests['schema generated check reparse current source rejects bad schema version'] = static function (TestRunner $t) use ($plan116): void {
    try {
        $plan116(null, null, ['schema_version_before' => -1]);
    } catch (InvalidArgumentException) {
        $t->same('rejected', 'rejected');
        return;
    }
    $t->same('rejected', 'missed');
};

return $tests;
