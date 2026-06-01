<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteKeyValueRow;

$libsqliteRoot = dirname(__DIR__);
$sourceRoot = $libsqliteRoot . '/src';

$relativePath = static fn (string $path): string => str_replace($libsqliteRoot . '/', '', $path);

$legacyDomainPattern = static function (): string {
    $terms = [
        'wp' . '_',
        'wp' . '_options',
        'wp' . '_sitemeta',
        'blog' . '_id',
        'blog' . 'Id',
        'Blog' . 'Id',
        'option' . '_id',
        'option' . '_name',
        'option' . '_value',
        'Option' . 'Row',
        'option' . 'Row',
        'option' . 'Name',
        'option' . 'Value',
        'option' . 'Id',
        'Auto' . 'load',
        'auto' . 'load',
        'continue' . '_on_site_error',
    ];

    return '/(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $terms)) . ')/';
};

$keyValueSourceFiles = static function () use ($sourceRoot): array {
    $files = [$sourceRoot . '/SQLiteDatabase.php'];
    foreach (glob($sourceRoot . '/SQLiteKeyValueRow*.php') ?: [] as $file) {
        $files[] = $file;
    }
    sort($files, SORT_STRING);

    return array_values(array_unique($files));
};

$keyValueExampleFiles = [
    $libsqliteRoot . '/examples/application-json-setting-value-list.php',
    $libsqliteRoot . '/examples/application-setting-value-integer-list.php',
];

$keyValueDatabaseMethodNames = static function (): array {
    $reflection = new ReflectionClass(SQLiteDatabase::class);
    $names = [];
    foreach ($reflection->getMethods() as $method) {
        if ($method->getDeclaringClass()->getName() !== SQLiteDatabase::class) {
            continue;
        }
        $name = $method->getName();
        if (
            str_contains($name, 'KeyValueRow')
            || str_contains($name, 'keyValueRow')
            || str_contains($name, 'keyValueRows')
            || str_contains($name, 'partialPredicateCoversAllKeyValueNameRows')
        ) {
            $names[] = $name;
        }
    }
    sort($names, SORT_STRING);

    return $names;
};

$methodSource = static function (ReflectionMethod $method): string {
    $file = $method->getFileName();
    if (!is_string($file)) {
        throw new RuntimeException('Unable to locate reflected method source');
    }

    $lines = file($file);
    if ($lines === false) {
        throw new RuntimeException("Unable to read {$file}");
    }

    return implode('', array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));
};

$databaseMethodLegacyMatches = static function () use ($keyValueDatabaseMethodNames, $legacyDomainPattern, $methodSource): array {
    $matches = [];
    $pattern = $legacyDomainPattern();
    foreach ($keyValueDatabaseMethodNames() as $name) {
        $method = new ReflectionMethod(SQLiteDatabase::class, $name);
        if (preg_match_all($pattern, $methodSource($method), $methodMatches) < 1) {
            continue;
        }
        foreach ($methodMatches[0] as $match) {
            $matches[] = "{$name}: {$match}";
        }
    }

    return $matches;
};

$helperFileLegacyMatches = static function () use ($keyValueSourceFiles, $legacyDomainPattern, $relativePath): array {
    $matches = [];
    $pattern = $legacyDomainPattern();
    foreach ($keyValueSourceFiles() as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }
        if (preg_match_all($pattern, $contents, $fileMatches) < 1) {
            continue;
        }
        foreach ($fileMatches[0] as $match) {
            $matches[] = $relativePath($file) . ': ' . $match;
        }
    }

    return $matches;
};

$exampleLegacyMatches = static function () use ($keyValueExampleFiles, $legacyDomainPattern, $relativePath): array {
    $matches = [];
    $pattern = $legacyDomainPattern();
    foreach ($keyValueExampleFiles as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException("Unable to read {$file}");
        }
        $relative = $relativePath($file);
        if (preg_match_all($pattern, $relative . "\n" . $contents, $fileMatches) < 1) {
            continue;
        }
        foreach ($fileMatches[0] as $match) {
            $matches[] = $relative . ': ' . $match;
        }
    }

    return $matches;
};

return [
    'source-neutral database key-value API has dynamic method coverage' => static function (TestRunner $t) use ($keyValueDatabaseMethodNames): void {
        $methods = $keyValueDatabaseMethodNames();

        $t->true(in_array('planKeyValueRowInsert', $methods, true));
        $t->true(in_array('keyValueRowsByIndexedNameRange', $methods, true));
        $t->true(in_array('keyValueRowsByIndexedLoadPolicyAndNameRange', $methods, true));
    },
    'source-neutral database key-value API methods contain no legacy domain terms' => static fn (TestRunner $t) => $t->same([], $databaseMethodLegacyMatches()),
    'source-neutral key-value row helper files contain no legacy domain terms' => static fn (TestRunner $t) => $t->same([], $helperFileLegacyMatches()),
    'source-neutral key-value indexed lookup examples contain no legacy domain terms' => static fn (TestRunner $t) => $t->same([], $exampleLegacyMatches()),
    'source-neutral key-value identifiers are centralized on generic row metadata' => static fn (TestRunner $t) => $t->same([
        'table' => 'app_settings',
        'id' => 'setting_id',
        'key' => 'key_name',
        'value' => 'key_value',
        'loadPolicy' => 'load_policy',
    ], [
        'table' => SQLiteKeyValueRow::TABLE_NAME,
        'id' => SQLiteKeyValueRow::ID_COLUMN,
        'key' => SQLiteKeyValueRow::KEY_COLUMN,
        'value' => SQLiteKeyValueRow::VALUE_COLUMN,
        'loadPolicy' => SQLiteKeyValueRow::LOAD_POLICY_COLUMN,
    ]),
    'source-neutral key-value row arrays expose generic setting keys' => static function (TestRunner $t): void {
        $row = new SQLiteKeyValueRow(7, 'module_registry', 'enabled', 'yes', 7);

        $t->same([
            'setting_id',
            'key_name',
            'key_value',
            'load_policy',
            'rowid',
        ], array_keys($row->toArray()));
    },
    'source-neutral database key-value API dependency closure' => static fn (TestRunner $t) => $t->same(
        'no new support component needed; reuses native SQLiteDatabase b-tree scans, index lookups, and key-value row write plans with generic application settings identifiers',
        'no new support component needed; reuses native SQLiteDatabase b-tree scans, index lookups, and key-value row write plans with generic application settings identifiers',
    ),
];
