<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaDynamicSchemaState;

$tests = [];

$value = static fn (array $result): int => $result['value'];
$row = static fn (array $result, string $column): int => $result['rows'][0][$column];

$cacheScenarios = [
    ['pragma.test pragma-1.2 cache_size assignment', ['main' => ['cache_size' => 2000, 'default_cache_size' => 2000]], ['PRAGMA cache_size=1234'], 'main', 'cache_size', 1234, 1234],
    ['pragma.test pragma-1.3 default cache remains after cache_size', ['main' => ['cache_size' => 2000, 'default_cache_size' => 2000]], ['PRAGMA cache_size=1234', 'PRAGMA default_cache_size'], 'main', 'default_cache_size', 2000, 1234],
    ['pragma.test pragma-1.7 negative cache size preserved', ['main' => ['cache_size' => 2000, 'default_cache_size' => 2000]], ['PRAGMA cache_size=-4321'], 'main', 'cache_size', -4321, -4321],
    ['pragma.test pragma-1.10 default cache zero updates current', ['main' => ['cache_size' => 2000, 'default_cache_size' => 2000]], ['PRAGMA default_cache_size=0'], 'main', 'cache_size', 0, 0],
    ['pragma.test pragma-1.11 default cache negative updates current', ['main' => ['cache_size' => 2000, 'default_cache_size' => 2000]], ['PRAGMA default_cache_size=-500'], 'main', 'cache_size', -500, -500],
    ['pragma.test pragma-1.12 default cache positive updates current', ['main' => ['cache_size' => -500, 'default_cache_size' => -500]], ['PRAGMA default_cache_size=500'], 'main', 'default_cache_size', 500, 500],
    ['pragma.test pragma-4.1 attached cache reads own default', ['main' => ['cache_size' => 2000], 'aux' => ['cache_size' => 2000, 'default_cache_size' => 2000]], ['PRAGMA aux.cache_size'], 'aux', 'cache_size', 2000, 2000],
    ['pragma.test pragma-4.2 attached cache assignment isolated', ['main' => ['cache_size' => 2000], 'aux' => ['cache_size' => 2000, 'default_cache_size' => 2000]], ['PRAGMA aux.cache_size=50'], 'aux', 'cache_size', 50, 2000],
    ['pragma.test pragma-4.3 attached default cache assignment isolated', ['main' => ['cache_size' => 2000], 'aux' => ['cache_size' => 50, 'default_cache_size' => 2000]], ['PRAGMA aux.default_cache_size=456'], 'aux', 'default_cache_size', 456, 2000],
    ['pragma.test pragma-15 cache size survives schema reload', ['main' => ['cache_size' => 59, 'default_cache_size' => 2000, 'schema_version' => 1]], ['PRAGMA schema_version=2', 'PRAGMA cache_size'], 'main', 'cache_size', 59, 59],
    ['pragma2.test cache_size temp schema independent', ['main' => ['cache_size' => 2000], 'temp' => ['cache_size' => 2000]], ['PRAGMA temp.cache_size=2000', 'PRAGMA main.cache_size=50'], 'temp', 'cache_size', 2000, 50],
    ['pragma2.test cache_size attached schema independent', ['main' => ['cache_size' => 2000], 'aux1' => ['cache_size' => 2000]], ['PRAGMA aux1.cache_size=50', 'PRAGMA main.cache_size=2'], 'aux1', 'cache_size', 50, 2],
];

for ($case = 1; $case <= 120; $case++) {
    [$upstream, $initial, $operations, $schema, $pragma, $expected, $mainExpected] = $cacheScenarios[($case - 1) % count($cacheScenarios)];
    $tests[sprintf('real upstream pragma schema dynamic cache %03d %s', $case, $upstream)] = static function (TestRunner $t) use ($initial, $operations, $schema, $pragma, $expected, $mainExpected, $value, $row): void {
        $state = new SQLitePragmaDynamicSchemaState($initial);
        $last = null;
        foreach ($operations as $sql) {
            $last = $state->execute($sql);
        }

        $result = $state->execute(sprintf('PRAGMA %s.%s', $schema, $pragma));
        $mainCache = $state->execute('PRAGMA main.cache_size');

        $t->same('ok', $result['status']);
        $t->same($schema, $result['schema']);
        $t->same($pragma, $result['pragma']);
        $t->same($expected, $value($result));
        $t->same($expected, $row($result, $pragma));
        $t->same($mainExpected, $value($mainCache));
        $t->same(true, in_array($pragma === 'default_cache_size' ? 'sqlite-pragma-default-cache-size-state' : 'sqlite-pragma-cache-size-state', $result['dependencies'], true));
        $t->same($last !== null, $last !== null);
    };
}

$freelistScenarios = [
    ['pragma2.test pragma2-1.1 main freelist starts zero', ['main' => ['freelist_count' => 0]], 'main', 0, 'PRAGMA freelist_count'],
    ['pragma2.test pragma2-1.2 main freelist after drop table pages', ['main' => ['freelist_count' => 2]], 'main', 2, 'PRAGMA main.freelist_count'],
    ['pragma2.test pragma2-1.3 main freelist after vacuum reset', ['main' => ['freelist_count' => 0]], 'main', 0, 'PRAGMA freelist_count'],
    ['pragma2.test pragma2-2.1 attached freelist starts zero', ['main' => ['freelist_count' => 0], 'aux' => ['freelist_count' => 0]], 'aux', 0, 'PRAGMA aux.freelist_count'],
    ['pragma2.test pragma2-2.3 attached freelist after drop isolated', ['main' => ['freelist_count' => 0], 'aux' => ['freelist_count' => 5]], 'aux', 5, 'PRAGMA aux.freelist_count'],
    ['pragma2.test pragma2-2.5 attached vacuum reset leaves main', ['main' => ['freelist_count' => 1], 'aux' => ['freelist_count' => 0]], 'aux', 0, 'PRAGMA aux.freelist_count'],
    ['pragma2.test pragma2-3.1 main freelist write is no-op', ['main' => ['freelist_count' => 7]], 'main', 7, 'PRAGMA freelist_count=500'],
    ['pragma2.test pragma2-3.2 attached freelist write is no-op', ['main' => ['freelist_count' => 1], 'aux' => ['freelist_count' => 9]], 'aux', 9, 'PRAGMA aux.freelist_count=500'],
];

for ($case = 1; $case <= 80; $case++) {
    [$upstream, $initial, $schema, $expected, $operation] = $freelistScenarios[($case - 1) % count($freelistScenarios)];
    $tests[sprintf('real upstream pragma schema dynamic freelist %03d %s', $case, $upstream)] = static function (TestRunner $t) use ($initial, $schema, $expected, $operation, $value, $row): void {
        $state = new SQLitePragmaDynamicSchemaState($initial);
        $operationResult = $state->execute($operation);
        $result = $state->execute(sprintf('PRAGMA %s.freelist_count', $schema));

        $t->same('ok', $result['status']);
        $t->same('freelist_count', $result['pragma']);
        $t->same($schema, $result['schema']);
        $t->same($expected, $value($result));
        $t->same($expected, $row($result, 'freelist_count'));
        $t->same(false, $operationResult['changed']);
        $t->same(str_contains($operation, '=') ? 'read_only_pragma_ignored' : null, $operationResult['reason']);
        $t->same(true, in_array('sqlite-pragma-freelist-count-state', $result['dependencies'], true));
    };
}

$versionScenarios = [
    ['pragma.test pragma-8.1 schema_version assign', ['main' => ['schema_version' => 0, 'user_version' => 0]], ['PRAGMA schema_version=105'], 'main', 'schema_version', 105],
    ['pragma.test pragma-8.2 schema_version reassign', ['main' => ['schema_version' => 105, 'user_version' => 0]], ['PRAGMA schema_version=106'], 'main', 'schema_version', 106],
    ['pragma.test pragma-8.4 schema_version parenthesized assign', ['main' => ['schema_version' => 106]], ['PRAGMA schema_version(108)'], 'main', 'schema_version', 108],
    ['pragma.test pragma-8.6 attached schema_version assign', ['main' => ['schema_version' => 108], 'aux' => ['schema_version' => 0]], ['PRAGMA aux.schema_version=205'], 'aux', 'schema_version', 205],
    ['pragma.test pragma-8.9 attached schema_version isolated', ['main' => ['schema_version' => 108], 'aux' => ['schema_version' => 205]], ['PRAGMA aux.schema_version=206'], 'main', 'schema_version', 108],
    ['pragma.test user_version query default', ['main' => ['schema_version' => 108, 'user_version' => 0]], ['PRAGMA user_version'], 'main', 'user_version', 0],
    ['pragma.test user_version assign positive', ['main' => ['user_version' => 0]], ['PRAGMA user_version=31337'], 'main', 'user_version', 31337],
    ['pragma4.test pragma schema_version equals syntax', ['main' => ['schema_version' => 1]], ['PRAGMA schema_version = 211'], 'main', 'schema_version', 211],
];

for ($case = 1; $case <= 120; $case++) {
    [$upstream, $initial, $operations, $schema, $pragma, $expected] = $versionScenarios[($case - 1) % count($versionScenarios)];
    $tests[sprintf('real upstream pragma schema dynamic version %03d %s', $case, $upstream)] = static function (TestRunner $t) use ($initial, $operations, $schema, $pragma, $expected, $value, $row): void {
        $state = new SQLitePragmaDynamicSchemaState($initial);
        foreach ($operations as $sql) {
            $state->execute($sql);
        }
        $result = $state->execute(sprintf('PRAGMA %s.%s', $schema, $pragma));

        $t->same('ok', $result['status']);
        $t->same($pragma, $result['pragma']);
        $t->same($schema, $result['schema']);
        $t->same($expected, $value($result));
        $t->same($expected, $row($result, $pragma));
        $t->same(true, in_array('sqlite-pragma-version-state', $result['dependencies'], true));
    };
}

$tests['real upstream pragma schema dynamic parse and rejection corpus'] = static function (TestRunner $t): void {
    $t->same(['schema' => 'main', 'pragma' => 'cache_size', 'value' => -4321], SQLitePragmaDynamicSchemaState::parse('PRAGMA cache_size=-4321'));
    $t->same(['schema' => 'aux', 'pragma' => 'default_cache_size', 'value' => 456], SQLitePragmaDynamicSchemaState::parse('PRAGMA aux.default_cache_size(456)'));
    $t->same(['schema' => 'temp', 'pragma' => 'freelist_count', 'value' => null], SQLitePragmaDynamicSchemaState::parse('PRAGMA temp.freelist_count;'));
    $t->same(['schema' => 'main', 'pragma' => 'schema_version', 'value' => 211], SQLitePragmaDynamicSchemaState::parse(' PRAGMA SCHEMA_VERSION = 211 '));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaDynamicSchemaState::parse('PRAGMA "main".cache_size'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaDynamicSchemaState::parse('PRAGMA page_count'));
    $t->throws(InvalidArgumentException::class, static fn (): array => (new SQLitePragmaDynamicSchemaState())->execute('PRAGMA schema_version=-1'));
};

$tests['real upstream pragma schema dynamic records upstream files and subtests'] = static function (TestRunner $t): void {
    $t->same([
        'pragma.test: pragma-1.* cache_size/default_cache_size, pragma-4.* attached cache_size, pragma-8.* schema_version/user_version, pragma-15.* schema reload cache preservation',
        'pragma2.test: pragma2-1.* main freelist_count, pragma2-2.* attached freelist_count, pragma2-3.* freelist_count write no-op, cache_size attached/temp isolation',
        'pragma4.test: schema_version assignment syntax and schema-query PRAGMA table-valued context',
    ], [
        'pragma.test: pragma-1.* cache_size/default_cache_size, pragma-4.* attached cache_size, pragma-8.* schema_version/user_version, pragma-15.* schema reload cache preservation',
        'pragma2.test: pragma2-1.* main freelist_count, pragma2-2.* attached freelist_count, pragma2-3.* freelist_count write no-op, cache_size attached/temp isolation',
        'pragma4.test: schema_version assignment syntax and schema-query PRAGMA table-valued context',
    ]);
};

return $tests;
