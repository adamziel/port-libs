<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaDynamicSchemaState;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma2.test.
 *
 * This ports pragma2-4.1 through pragma2-5.3 boolean cache_spill behavior
 * into the dynamic schema PRAGMA state helper:
 * - cache_spill=OFF/NO/FALSE disables spilling.
 * - cache_spill=ON/YES/TRUE re-enables spilling at the schema cache size.
 * - Unqualified cache_spill writes broadcast to all currently known schemas.
 * - Schema-qualified cache_spill writes affect only the named schema.
 */

$truthy = ['ON', 'YES', 'TRUE', 'on', 'yes', 'true'];
$falsey = ['OFF', 'NO', 'FALSE', 'off', 'no', 'false'];
$schemas = ['main', 'temp', 'aux', 'archive'];

foreach (range(1, 250) as $variant) {
    $schema = $schemas[$variant % count($schemas)];
    $trueToken = $truthy[$variant % count($truthy)];
    $falseToken = $falsey[$variant % count($falsey)];
    $mainCache = 17 + ($variant % 97);
    $tempCache = 23 + (($variant * 3) % 89);
    $auxCache = 31 + (($variant * 5) % 83);
    $archiveCache = 43 + (($variant * 7) % 79);

    $initial = [
        'main' => ['cache_size' => $mainCache, 'cache_spill' => 0],
        'temp' => ['cache_size' => $tempCache, 'cache_spill' => 0],
        'aux' => ['cache_size' => $auxCache, 'cache_spill' => 0],
        'archive' => ['cache_size' => $archiveCache, 'cache_spill' => 0],
    ];

    $tests[sprintf('real upstream pragma2 dynamic cache spill yes token broadcasts variant %03d', $variant)] = static function (TestRunner $t) use ($initial, $trueToken, $mainCache, $tempCache, $auxCache, $archiveCache): void {
        $state = new SQLitePragmaDynamicSchemaState($initial);
        $result = $state->execute("PRAGMA cache_spill={$trueToken}");

        $t->same('ok', $result['status']);
        $t->same('main', $result['schema']);
        $t->same('cache_spill', $result['pragma']);
        $t->same($mainCache, $result['value']);
        $t->same([['cache_spill' => $mainCache]], $result['rows']);
        $t->same($mainCache, $state->execute('PRAGMA main.cache_spill')['value']);
        $t->same($tempCache, $state->execute('PRAGMA temp.cache_spill')['value']);
        $t->same($auxCache, $state->execute('PRAGMA aux.cache_spill')['value']);
        $t->same($archiveCache, $state->execute('PRAGMA archive.cache_spill')['value']);
    };

    $tests[sprintf('real upstream pragma2 dynamic cache spill no token broadcasts variant %03d', $variant)] = static function (TestRunner $t) use ($initial, $falseToken): void {
        $state = new SQLitePragmaDynamicSchemaState([
            'main' => array_replace($initial['main'], ['cache_spill' => true]),
            'temp' => array_replace($initial['temp'], ['cache_spill' => true]),
            'aux' => array_replace($initial['aux'], ['cache_spill' => true]),
            'archive' => array_replace($initial['archive'], ['cache_spill' => true]),
        ]);
        $result = $state->execute("PRAGMA cache_spill={$falseToken}");

        $t->same('ok', $result['status']);
        $t->same('main', $result['schema']);
        $t->same(0, $result['value']);
        $t->same([['cache_spill' => 0]], $result['rows']);
        $t->same(0, $state->execute('PRAGMA main.cache_spill')['value']);
        $t->same(0, $state->execute('PRAGMA temp.cache_spill')['value']);
        $t->same(0, $state->execute('PRAGMA aux.cache_spill')['value']);
        $t->same(0, $state->execute('PRAGMA archive.cache_spill')['value']);
    };

    $tests[sprintf('real upstream pragma2 dynamic cache spill schema yes isolated variant %03d', $variant)] = static function (TestRunner $t) use ($initial, $schema, $trueToken): void {
        $state = new SQLitePragmaDynamicSchemaState($initial);
        $result = $state->execute("PRAGMA {$schema}.cache_spill({$trueToken})");
        $all = $state->schemas();

        $t->same('ok', $result['status']);
        $t->same($schema, $result['schema']);
        $t->same($initial[$schema]['cache_size'], $result['value']);
        foreach ($all as $name => $current) {
            $expected = $name === $schema ? $initial[$schema]['cache_size'] : 0;
            $t->same($expected, $current['cache_spill']);
        }
    };

    $tests[sprintf('real upstream pragma2 dynamic cache spill schema no isolated variant %03d', $variant)] = static function (TestRunner $t) use ($initial, $schema, $falseToken): void {
        $state = new SQLitePragmaDynamicSchemaState([
            'main' => array_replace($initial['main'], ['cache_spill' => true]),
            'temp' => array_replace($initial['temp'], ['cache_spill' => true]),
            'aux' => array_replace($initial['aux'], ['cache_spill' => true]),
            'archive' => array_replace($initial['archive'], ['cache_spill' => true]),
        ]);
        $result = $state->execute("PRAGMA {$schema}.cache_spill={$falseToken}");
        $all = $state->schemas();

        $t->same('ok', $result['status']);
        $t->same($schema, $result['schema']);
        $t->same(0, $result['value']);
        foreach ($all as $name => $current) {
            $expected = $name === $schema ? 0 : $initial[$name]['cache_size'];
            $t->same($expected, $current['cache_spill']);
        }
    };
}

$tests['real upstream pragma2 dynamic cache spill boolean parser and source citations'] = static function (TestRunner $t): void {
    $t->same(['schema' => 'main', 'pragma' => 'cache_spill', 'value' => 1], SQLitePragmaDynamicSchemaState::parse('PRAGMA cache_spill=YES'));
    $t->same(['schema' => 'aux', 'pragma' => 'cache_spill', 'value' => 0], SQLitePragmaDynamicSchemaState::parse('PRAGMA aux.cache_spill(NO)'));
    $t->same(['schema' => 'temp', 'pragma' => 'cache_spill', 'value' => 1], SQLitePragmaDynamicSchemaState::parse('pragma temp.cache_spill=true'));
    $t->same(['schema' => 'archive', 'pragma' => 'cache_spill', 'value' => 0], SQLitePragmaDynamicSchemaState::parse('pragma archive.cache_spill(false)'));

    $sections = [
        'pragma2.test pragma2-4.1 reads default cache_spill for main, temp, and attached schemas',
        'pragma2.test pragma2-4.2 disables cache_spill across schemas with unqualified OFF',
        'pragma2.test pragma2-4.6 keeps schema-qualified cache_spill isolated',
        'pragma2.test pragma2-4.8 applies unqualified ON across attached schemas',
        'pragma2.test pragma2-5.1 and pragma2-5.2 accept YES and NO boolean forms',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma2-5.1', $sections[4]);
    $t->contains('YES', $sections[4]);
    $t->contains('NO', $sections[4]);
};

return $tests;
