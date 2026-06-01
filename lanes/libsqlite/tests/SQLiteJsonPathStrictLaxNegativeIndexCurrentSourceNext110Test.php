<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan;

$currentRows = [
    [
        'setting_id' => 1,
        'key_name' => 'module_cache_settings',
        'key_value' => '{"modules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":true},{"slug":"forms","enabled":false}],"meta":{"version":1}}',
    ],
    [
        'setting_id' => 2,
        'key_name' => 'module_theme_settings',
        'key_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'modules' => [
                ['slug' => 'blocks', 'enabled' => true],
                ['slug' => 'patterns', 'enabled' => false],
            ],
            'meta' => ['version' => 2],
        ])),
    ],
    [
        'setting_id' => 3,
        'key_name' => 'module_empty_settings',
        'key_value' => '{"modules":[],"meta":{"version":3}}',
    ],
];

$nextRows = [
    $currentRows[0],
    [
        'setting_id' => 2,
        'key_name' => 'module_theme_settings',
        'key_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'modules' => [
                ['slug' => 'blocks', 'enabled' => true],
                ['slug' => 'patterns', 'enabled' => true],
                ['slug' => 'stylebook', 'enabled' => true],
            ],
            'meta' => ['version' => 4],
        ])),
    ],
    [
        'setting_id' => 3,
        'key_name' => 'module_empty_settings',
        'key_value' => '{"modules":[{"slug":"imported","enabled":true}],"meta":{"version":3}}',
    ],
];

$paths = [
    '$.modules[#-1].slug',
    '$.modules[#-2].enabled',
    '$.meta.version',
    'strict $.modules[#-1].slug',
    'lax $.modules[#-1].slug',
    '$.modules[-1].slug',
    '$.modules[#-].slug',
];

$plan = static fn (): array => SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare($currentRows, $nextRows, $paths);

$tests = [
    'json path strict lax negative index current source next110 records surface' => static fn (TestRunner $t) => $t->same('json-path-strict-lax-negative-index-current-source-next110', $plan()['surface']),
    'json path strict lax negative index current source next110 counts paths' => static fn (TestRunner $t) => $t->same(7, $plan()['pathCount']),
    'json path strict lax negative index current source next110 counts valid paths' => static fn (TestRunner $t) => $t->same(3, $plan()['validPathCount']),
    'json path strict lax negative index current source next110 counts invalid paths' => static fn (TestRunner $t) => $t->same(4, $plan()['invalidPathCount']),
    'json path strict lax negative index current source next110 keeps valid path order' => static fn (TestRunner $t) => $t->same(['$.modules[#-1].slug', '$.modules[#-2].enabled', '$.meta.version'], $plan()['validPaths']),
    'json path strict lax negative index current source next110 keeps invalid path order' => static fn (TestRunner $t) => $t->same(['strict $.modules[#-1].slug', 'lax $.modules[#-1].slug', '$.modules[-1].slug', '$.modules[#-].slug'], $plan()['invalidPaths']),
    'json path strict lax negative index current source next110 classifies strict prefix' => static fn (TestRunner $t) => $t->same('strict-prefix', $plan()['paths']['strict $.modules[#-1].slug']['classification']),
    'json path strict lax negative index current source next110 classifies lax prefix' => static fn (TestRunner $t) => $t->same('lax-prefix', $plan()['paths']['lax $.modules[#-1].slug']['classification']),
    'json path strict lax negative index current source next110 classifies negative index' => static fn (TestRunner $t) => $t->same('negative-array-index', $plan()['paths']['$.modules[-1].slug']['classification']),
    'json path strict lax negative index current source next110 classifies malformed reverse index' => static fn (TestRunner $t) => $t->same('malformed', $plan()['paths']['$.modules[#-].slug']['classification']),
    'json path strict lax negative index current source next110 classifies sqlite reverse index' => static fn (TestRunner $t) => $t->same('sqlite-reverse-index', $plan()['paths']['$.modules[#-1].slug']['classification']),
    'json path strict lax negative index current source next110 accepts reverse index path' => static fn (TestRunner $t) => $t->same(true, $plan()['paths']['$.modules[#-1].slug']['wellFormed']),
    'json path strict lax negative index current source next110 accepts reverse second index path' => static fn (TestRunner $t) => $t->same(true, $plan()['paths']['$.modules[#-2].enabled']['wellFormed']),
    'json path strict lax negative index current source next110 rejects strict path' => static fn (TestRunner $t) => $t->same(false, $plan()['paths']['strict $.modules[#-1].slug']['wellFormed']),
    'json path strict lax negative index current source next110 rejects lax path' => static fn (TestRunner $t) => $t->same(false, $plan()['paths']['lax $.modules[#-1].slug']['wellFormed']),
    'json path strict lax negative index current source next110 rejects bracket negative path' => static fn (TestRunner $t) => $t->same(false, $plan()['paths']['$.modules[-1].slug']['wellFormed']),
    'json path strict lax negative index current source next110 strict error is explicit' => static fn (TestRunner $t) => $t->same('SQLite JSON path does not accept SQL/JSON strict prefix', $plan()['paths']['strict $.modules[#-1].slug']['error']),
    'json path strict lax negative index current source next110 lax error is explicit' => static fn (TestRunner $t) => $t->same('SQLite JSON path does not accept SQL/JSON lax prefix', $plan()['paths']['lax $.modules[#-1].slug']['error']),
    'json path strict lax negative index current source next110 negative error is explicit' => static fn (TestRunner $t) => $t->same('SQLite JSON path negative array index must use #-N form', $plan()['paths']['$.modules[-1].slug']['error']),
    'json path strict lax negative index current source next110 current row count' => static fn (TestRunner $t) => $t->same(3, $plan()['currentRowCount']),
    'json path strict lax negative index current source next110 next row count' => static fn (TestRunner $t) => $t->same(3, $plan()['nextRowCount']),
    'json path strict lax negative index current source next110 current found rowids' => static fn (TestRunner $t) => $t->same([1, 2, 3], $plan()['current']['foundRowids']),
    'json path strict lax negative index current source next110 next found rowids' => static fn (TestRunner $t) => $t->same([1, 2, 3], $plan()['next']['foundRowids']),
    'json path strict lax negative index current source next110 current has no json errors' => static fn (TestRunner $t) => $t->same([], $plan()['current']['jsonErrorRowids']),
    'json path strict lax negative index current source next110 next has no json errors' => static fn (TestRunner $t) => $t->same([], $plan()['next']['jsonErrorRowids']),
    'json path strict lax negative index current source next110 detects changed source' => static fn (TestRunner $t) => $t->same(true, $plan()['changed']),
    'json path strict lax negative index current source next110 requires reprepare for invalid path' => static fn (TestRunner $t) => $t->same(true, $plan()['reprepareRequired']),
    'json path strict lax negative index current source next110 malformed reason wins' => static fn (TestRunner $t) => $t->same('json-path-prefix-or-negative-index-malformed', $plan()['reprepareReason']),
    'json path strict lax negative index current source next110 preserves current reader policy' => static fn (TestRunner $t) => $t->same('keep-current-json-path-source-until-statement-reset', $plan()['currentReaderPolicy']),
    'json path strict lax negative index current source next110 next policy aborts before row yield' => static fn (TestRunner $t) => $t->same('next-json-path-source-errors-before-row-yield', $plan()['nextReaderPolicy']),
    'json path strict lax negative index current source next110 records dependencies' => static fn (TestRunner $t) => $t->same(['SQLiteJsonInspection', 'SQLiteJsonPath'], $plan()['dependencies']),
    'json path strict lax negative index current source next110 direct locate accepts sqlite reverse index' => static fn (TestRunner $t) => $t->same('forms', SQLiteJsonInspection::locatePath($currentRows[0]['key_value'], '$.modules[#-1].slug')['value']),
    'json path strict lax negative index current source next110 direct locate accepts jsonb reverse index' => static fn (TestRunner $t) => $t->same('patterns', SQLiteJsonInspection::locatePath($currentRows[1]['key_value'], '$.modules[#-1].slug')['value']),
    'json path strict lax negative index current source next110 direct locate rejects strict prefix' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::locatePath($currentRows[0]['key_value'], 'strict $.modules[#-1].slug')),
    'json path strict lax negative index current source next110 direct locate rejects lax prefix' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::locatePath($currentRows[0]['key_value'], 'lax $.modules[#-1].slug')),
    'json path strict lax negative index current source next110 direct locate rejects negative bracket index' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::locatePath($currentRows[0]['key_value'], '$.modules[-1].slug')),
    'json path strict lax negative index current source next110 stable valid-only source is runnable' => static function (TestRunner $t) use ($currentRows): void {
        $stable = SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare($currentRows, $currentRows, ['$.modules[#-1].slug']);
        $t->same(false, $stable['changed']);
        $t->same(false, $stable['reprepareRequired']);
        $t->same('stable-json-path-current-source', $stable['reprepareReason']);
        $t->same('next-json-path-source-is-runnable', $stable['nextReaderPolicy']);
    },
    'json path strict lax negative index current source next110 changed valid-only source reparses' => static function (TestRunner $t) use ($currentRows, $nextRows): void {
        $changed = SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare($currentRows, $nextRows, ['$.modules[#-1].slug']);
        $t->same(true, $changed['changed']);
        $t->same(true, $changed['reprepareRequired']);
        $t->same('json-path-current-source-result-changed', $changed['reprepareReason']);
        $t->same('next-json-path-source-is-runnable', $changed['nextReaderPolicy']);
    },
    'json path strict lax negative index current source next110 rejects empty path list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare($currentRows, $nextRows, [])),
    'json path strict lax negative index current source next110 rejects missing setting id' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare([['key_value' => '{}']], [], ['$.x'])),
    'json path strict lax negative index current source next110 rejects missing key value' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare([['setting_id' => 1]], [], ['$.x'])),
    'json path strict lax negative index current source next110 records malformed json source rowid' => static function (TestRunner $t): void {
        $malformed = SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare([['setting_id' => 1, 'key_value' => '{"modules":']], [], ['$.modules[#-1]']);
        $t->same([1], $malformed['current']['jsonErrorRowids']);
        $t->same(false, $malformed['current']['rows'][1]['paths']['$.modules[#-1]']['found']);
    },
];

foreach ([
    'current cache last slug' => ['current', 1, '$.modules[#-1].slug', 'forms', 'text'],
    'current theme last slug' => ['current', 2, '$.modules[#-1].slug', 'patterns', 'text'],
    'current empty last slug missing' => ['current', 3, '$.modules[#-1].slug', null, null],
    'next cache last slug stable' => ['next', 1, '$.modules[#-1].slug', 'forms', 'text'],
    'next theme last slug changed' => ['next', 2, '$.modules[#-1].slug', 'stylebook', 'text'],
    'next imported last slug appears' => ['next', 3, '$.modules[#-1].slug', 'imported', 'text'],
    'current cache reverse second enabled' => ['current', 1, '$.modules[#-2].enabled', 1, 'true'],
    'current theme reverse second enabled' => ['current', 2, '$.modules[#-2].enabled', 1, 'true'],
    'current empty reverse second missing' => ['current', 3, '$.modules[#-2].enabled', null, null],
    'next theme reverse second enabled' => ['next', 2, '$.modules[#-2].enabled', 1, 'true'],
    'current cache version' => ['current', 1, '$.meta.version', 1, 'integer'],
    'next theme version changed' => ['next', 2, '$.meta.version', 4, 'integer'],
] as $label => [$source, $rowid, $path, $value, $type]) {
    $tests['json path strict lax negative index current source next110 generated ' . $label] = static function (TestRunner $t) use ($plan, $source, $rowid, $path, $value, $type): void {
        $result = $plan()[$source]['rows'][$rowid]['paths'][$path];
        $t->same($value !== null, $result['found']);
        $t->same($value, $result['value']);
        $t->same($type, $result['type']);
    };
}

return $tests;
