<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan;

$currentRows = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_cache_settings',
        'option_value' => '{"plugins":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":true},{"slug":"forms","enabled":false}],"meta":{"version":1}}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_theme_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugins' => [
                ['slug' => 'blocks', 'enabled' => true],
                ['slug' => 'patterns', 'enabled' => false],
            ],
            'meta' => ['version' => 2],
        ])),
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"plugins":[],"meta":{"version":3}}',
    ],
];

$nextRows = [
    $currentRows[0],
    [
        'option_id' => 2,
        'option_name' => 'plugin_theme_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugins' => [
                ['slug' => 'blocks', 'enabled' => true],
                ['slug' => 'patterns', 'enabled' => true],
                ['slug' => 'stylebook', 'enabled' => true],
            ],
            'meta' => ['version' => 4],
        ])),
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"plugins":[{"slug":"imported","enabled":true}],"meta":{"version":3}}',
    ],
];

$paths = [
    '$.plugins[#-1].slug',
    '$.plugins[#-2].enabled',
    '$.meta.version',
    'strict $.plugins[#-1].slug',
    'lax $.plugins[#-1].slug',
    '$.plugins[-1].slug',
    '$.plugins[#-].slug',
];

$plan = static fn (): array => SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare($currentRows, $nextRows, $paths);

$tests = [
    'json path strict lax negative index current source next110 records surface' => static fn (TestRunner $t) => $t->same('json-path-strict-lax-negative-index-current-source-next110', $plan()['surface']),
    'json path strict lax negative index current source next110 counts paths' => static fn (TestRunner $t) => $t->same(7, $plan()['pathCount']),
    'json path strict lax negative index current source next110 counts valid paths' => static fn (TestRunner $t) => $t->same(3, $plan()['validPathCount']),
    'json path strict lax negative index current source next110 counts invalid paths' => static fn (TestRunner $t) => $t->same(4, $plan()['invalidPathCount']),
    'json path strict lax negative index current source next110 keeps valid path order' => static fn (TestRunner $t) => $t->same(['$.plugins[#-1].slug', '$.plugins[#-2].enabled', '$.meta.version'], $plan()['validPaths']),
    'json path strict lax negative index current source next110 keeps invalid path order' => static fn (TestRunner $t) => $t->same(['strict $.plugins[#-1].slug', 'lax $.plugins[#-1].slug', '$.plugins[-1].slug', '$.plugins[#-].slug'], $plan()['invalidPaths']),
    'json path strict lax negative index current source next110 classifies strict prefix' => static fn (TestRunner $t) => $t->same('strict-prefix', $plan()['paths']['strict $.plugins[#-1].slug']['classification']),
    'json path strict lax negative index current source next110 classifies lax prefix' => static fn (TestRunner $t) => $t->same('lax-prefix', $plan()['paths']['lax $.plugins[#-1].slug']['classification']),
    'json path strict lax negative index current source next110 classifies negative index' => static fn (TestRunner $t) => $t->same('negative-array-index', $plan()['paths']['$.plugins[-1].slug']['classification']),
    'json path strict lax negative index current source next110 classifies malformed reverse index' => static fn (TestRunner $t) => $t->same('malformed', $plan()['paths']['$.plugins[#-].slug']['classification']),
    'json path strict lax negative index current source next110 classifies sqlite reverse index' => static fn (TestRunner $t) => $t->same('sqlite-reverse-index', $plan()['paths']['$.plugins[#-1].slug']['classification']),
    'json path strict lax negative index current source next110 accepts reverse index path' => static fn (TestRunner $t) => $t->same(true, $plan()['paths']['$.plugins[#-1].slug']['wellFormed']),
    'json path strict lax negative index current source next110 accepts reverse second index path' => static fn (TestRunner $t) => $t->same(true, $plan()['paths']['$.plugins[#-2].enabled']['wellFormed']),
    'json path strict lax negative index current source next110 rejects strict path' => static fn (TestRunner $t) => $t->same(false, $plan()['paths']['strict $.plugins[#-1].slug']['wellFormed']),
    'json path strict lax negative index current source next110 rejects lax path' => static fn (TestRunner $t) => $t->same(false, $plan()['paths']['lax $.plugins[#-1].slug']['wellFormed']),
    'json path strict lax negative index current source next110 rejects bracket negative path' => static fn (TestRunner $t) => $t->same(false, $plan()['paths']['$.plugins[-1].slug']['wellFormed']),
    'json path strict lax negative index current source next110 strict error is explicit' => static fn (TestRunner $t) => $t->same('SQLite JSON path does not accept SQL/JSON strict prefix', $plan()['paths']['strict $.plugins[#-1].slug']['error']),
    'json path strict lax negative index current source next110 lax error is explicit' => static fn (TestRunner $t) => $t->same('SQLite JSON path does not accept SQL/JSON lax prefix', $plan()['paths']['lax $.plugins[#-1].slug']['error']),
    'json path strict lax negative index current source next110 negative error is explicit' => static fn (TestRunner $t) => $t->same('SQLite JSON path negative array index must use #-N form', $plan()['paths']['$.plugins[-1].slug']['error']),
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
    'json path strict lax negative index current source next110 direct locate accepts sqlite reverse index' => static fn (TestRunner $t) => $t->same('forms', SQLiteJsonInspection::locatePath($currentRows[0]['option_value'], '$.plugins[#-1].slug')['value']),
    'json path strict lax negative index current source next110 direct locate accepts jsonb reverse index' => static fn (TestRunner $t) => $t->same('patterns', SQLiteJsonInspection::locatePath($currentRows[1]['option_value'], '$.plugins[#-1].slug')['value']),
    'json path strict lax negative index current source next110 direct locate rejects strict prefix' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::locatePath($currentRows[0]['option_value'], 'strict $.plugins[#-1].slug')),
    'json path strict lax negative index current source next110 direct locate rejects lax prefix' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::locatePath($currentRows[0]['option_value'], 'lax $.plugins[#-1].slug')),
    'json path strict lax negative index current source next110 direct locate rejects negative bracket index' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonInspection::locatePath($currentRows[0]['option_value'], '$.plugins[-1].slug')),
    'json path strict lax negative index current source next110 stable valid-only source is runnable' => static function (TestRunner $t) use ($currentRows): void {
        $stable = SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare($currentRows, $currentRows, ['$.plugins[#-1].slug']);
        $t->same(false, $stable['changed']);
        $t->same(false, $stable['reprepareRequired']);
        $t->same('stable-json-path-current-source', $stable['reprepareReason']);
        $t->same('next-json-path-source-is-runnable', $stable['nextReaderPolicy']);
    },
    'json path strict lax negative index current source next110 changed valid-only source reparses' => static function (TestRunner $t) use ($currentRows, $nextRows): void {
        $changed = SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare($currentRows, $nextRows, ['$.plugins[#-1].slug']);
        $t->same(true, $changed['changed']);
        $t->same(true, $changed['reprepareRequired']);
        $t->same('json-path-current-source-result-changed', $changed['reprepareReason']);
        $t->same('next-json-path-source-is-runnable', $changed['nextReaderPolicy']);
    },
    'json path strict lax negative index current source next110 rejects empty path list' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare($currentRows, $nextRows, [])),
    'json path strict lax negative index current source next110 rejects missing option id' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare([['option_value' => '{}']], [], ['$.x'])),
    'json path strict lax negative index current source next110 rejects missing option value' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare([['option_id' => 1]], [], ['$.x'])),
    'json path strict lax negative index current source next110 records malformed json source rowid' => static function (TestRunner $t): void {
        $malformed = SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan::compare([['option_id' => 1, 'option_value' => '{"plugins":']], [], ['$.plugins[#-1]']);
        $t->same([1], $malformed['current']['jsonErrorRowids']);
        $t->same(false, $malformed['current']['rows'][1]['paths']['$.plugins[#-1]']['found']);
    },
];

foreach ([
    'current cache last slug' => ['current', 1, '$.plugins[#-1].slug', 'forms', 'text'],
    'current theme last slug' => ['current', 2, '$.plugins[#-1].slug', 'patterns', 'text'],
    'current empty last slug missing' => ['current', 3, '$.plugins[#-1].slug', null, null],
    'next cache last slug stable' => ['next', 1, '$.plugins[#-1].slug', 'forms', 'text'],
    'next theme last slug changed' => ['next', 2, '$.plugins[#-1].slug', 'stylebook', 'text'],
    'next imported last slug appears' => ['next', 3, '$.plugins[#-1].slug', 'imported', 'text'],
    'current cache reverse second enabled' => ['current', 1, '$.plugins[#-2].enabled', 1, 'true'],
    'current theme reverse second enabled' => ['current', 2, '$.plugins[#-2].enabled', 1, 'true'],
    'current empty reverse second missing' => ['current', 3, '$.plugins[#-2].enabled', null, null],
    'next theme reverse second enabled' => ['next', 2, '$.plugins[#-2].enabled', 1, 'true'],
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
