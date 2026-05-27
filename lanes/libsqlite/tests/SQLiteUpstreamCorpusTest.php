<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteCoreScalarFunction;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonExtract;
use PortLibs\LibSqlite\SQLiteJsonInspection;
use PortLibs\LibSqlite\SQLiteJsonValidity;
use PortLibs\LibSqlite\SQLiteSelectResult;
use PortLibs\LibSqlite\SQLiteWindowFunction;

$normalize = static function (mixed $value): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return ['blob' => bin2hex($value->bytes)];
    }

    if (is_array($value)) {
        return array_map(static function (mixed $item) use (&$normalize): mixed {
            return $normalize($item);
        }, $value);
    }

    return $value;
};

$tests = [];

$coreCases = [
    'typeof null' => ['typeof', [null], 'null'],
    'typeof integer' => ['typeof', [12], 'integer'],
    'typeof boolean integer' => ['typeof', [true], 'integer'],
    'typeof real' => ['typeof', [12.5], 'real'],
    'typeof text' => ['typeof', ['cache'], 'text'],
    'typeof blob' => ['typeof', [new SQLiteBlobValue("ab")], 'blob'],
    'quote null' => ['quote', [null], 'NULL'],
    'quote apostrophe text' => ['quote', ["canary's"], "'canary''s'"],
    'quote integer' => ['quote', [-17], '-17'],
    'quote real' => ['quote', [2.5], '2.5'],
    'quote boolean' => ['quote', [true], '1'],
    'quote blob' => ['quote', [new SQLiteBlobValue("\x0a\xff")], "X'0AFF'"],
    'coalesce first non null' => ['coalesce', [null, 'home'], 'home'],
    'coalesce later non null' => ['coalesce', [null, null, 7], 7],
    'coalesce all null' => ['coalesce', [null, null], null],
    'ifnull fallback' => ['ifnull', [null, 'fallback'], 'fallback'],
    'ifnull preserves first' => ['ifnull', ['siteurl', 'fallback'], 'siteurl'],
    'nullif equal integer' => ['nullif', [5, 5], null],
    'nullif different integer' => ['nullif', [5, 6], 5],
    'min numeric' => ['min', [3, 2, 7], 2],
    'max numeric' => ['max', [3, 2, 7], 7],
    'min propagates null' => ['min', [3, null, 7], null],
    'lower ascii' => ['lower', ['CACHE_Key'], 'cache_key'],
    'lower digits unchanged' => ['lower', ['WP_123'], 'wp_123'],
    'upper ascii' => ['upper', ['cache_key'], 'CACHE_KEY'],
    'upper digits unchanged' => ['upper', ['wp_123'], 'WP_123'],
    'length text' => ['length', ['abc'], 3],
    'length empty text' => ['length', [''], 0],
    'length blob bytes' => ['length', [new SQLiteBlobValue("\0abc")], 4],
    'length null' => ['length', [null], null],
    'substr middle' => ['substr', ['abcdef', 2, 3], 'bcd'],
    'substr negative start' => ['substr', ['abcdef', -2, 2], 'ef'],
    'substr omitted length' => ['substr', ['abcdef', 4], 'def'],
    'trim spaces' => ['trim', ['  cache  '], 'cache'],
    'ltrim custom' => ['ltrim', ['xxcachex', 'x'], 'cachex'],
    'rtrim custom' => ['rtrim', ['xxcachex', 'x'], 'xxcache'],
    'replace repeated' => ['replace', ['banana', 'na', 'X'], 'baXX'],
    'replace empty pattern' => ['replace', ['banana', '', 'X'], 'banana'],
    'replace null' => ['replace', [null, 'a', 'b'], null],
    'instr text hit' => ['instr', ['abcdef', 'cd'], 3],
    'instr text empty needle' => ['instr', ['abcdef', ''], 1],
    'instr text miss' => ['instr', ['abcdef', 'xy'], 0],
    'instr blob hit' => ['instr', [new SQLiteBlobValue("\x00abc"), new SQLiteBlobValue('ab')], 2],
    'concat skips null' => ['concat', ['a', null, 'b', 3], 'ab3'],
    'concat ws skips null' => ['concat_ws', [',', 'a', null, 'b'], 'a,b'],
    'concat ws null separator' => ['concat_ws', [null, 'a', 'b'], null],
    'hex blob' => ['hex', [new SQLiteBlobValue('AZ')], '415A'],
    'hex text' => ['hex', ['AZ'], '415A'],
    'unhex text' => ['unhex', ['4142'], new SQLiteBlobValue('AB')],
    'unhex invalid' => ['unhex', ['414'], null],
    'char ascii' => ['char', [65, 66], 'AB'],
    'unicode ascii' => ['unicode', ['ABC'], 65],
    'unicode empty' => ['unicode', [''], null],
    'octet length text' => ['octet_length', ['ABC'], 3],
    'zeroblob length' => ['zeroblob', [3], new SQLiteBlobValue("\0\0\0")],
    'iif true branch' => ['iif', [1, 'yes', 'no'], 'yes'],
    'iif false branch' => ['iif', [0, 'yes', 'no'], 'no'],
    'iif false without else' => ['iif', [0, 'yes'], null],
    'compile option enabled' => ['sqlite_compileoption_used', ['ENABLE_JSON1'], 0],
    'compile option target' => ['sqlite_compileoption_used', ['ENABLE_FTS5'], 1],
];

foreach ($coreCases as $name => [$function, $arguments, $expected]) {
    $tests['upstream corpus core scalar ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected, $normalize): void {
        $actual = SQLiteCoreScalarFunction::sqlFunctionArguments($function, $arguments);
        $t->same($normalize($expected), $normalize($actual));
    };
}

$jsonb = new SQLiteBlobValue(SQLiteJsonB::encode([
    'plugin' => [
        'enabled' => true,
        'name' => 'cache',
        'rules' => [
            ['name' => 'seo', 'priority' => 1],
            ['name' => 'cache', 'priority' => 5],
        ],
    ],
]));

$jsonValidCases = [
    'strict object' => ['{"a":1}', SQLiteJsonValidity::FLAG_STRICT_TEXT, true],
    'strict array' => ['[1,2,3]', SQLiteJsonValidity::FLAG_STRICT_TEXT, true],
    'strict rejects json5 label' => ['{a:1}', SQLiteJsonValidity::FLAG_STRICT_TEXT, false],
    'json5 accepts label' => ['{a:1}', SQLiteJsonValidity::FLAG_JSON5_TEXT, true],
    'json5 accepts trailing comma' => ['[1,2,]', SQLiteJsonValidity::FLAG_JSON5_TEXT, true],
    'json5 rejects broken comma' => ['[1,,2]', SQLiteJsonValidity::FLAG_JSON5_TEXT, false],
    'null input returns null' => [null, SQLiteJsonValidity::FLAG_STRICT_TEXT, null],
    'jsonb superficial accepted' => [$jsonb, SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB, true],
    'jsonb strict accepted' => [$jsonb, SQLiteJsonValidity::FLAG_STRICT_JSONB, true],
    'jsonb not text' => [$jsonb, SQLiteJsonValidity::FLAG_STRICT_TEXT, false],
    'text blob strict accepted' => [new SQLiteBlobValue('{"a":1}'), SQLiteJsonValidity::FLAG_STRICT_TEXT, true],
    'text blob superficial rejected' => [new SQLiteBlobValue('{"a":1}'), SQLiteJsonValidity::FLAG_SUPERFICIAL_JSONB, false],
];

foreach ($jsonValidCases as $name => [$value, $flags, $expected]) {
    $tests['upstream corpus json_valid ' . $name] = static function (TestRunner $t) use ($value, $flags, $expected): void {
        $t->same($expected, SQLiteJsonValidity::jsonValid($value, $flags));
    };
}

$jsonInspectionCases = [
    'root object type' => ['json_type', ['{"a":1}', '$'], 'object'],
    'integer type' => ['json_type', ['{"a":1}', '$.a'], 'integer'],
    'real type' => ['json_type', ['{"a":1.5}', '$.a'], 'real'],
    'text type' => ['json_type', ['{"a":"x"}', '$.a'], 'text'],
    'true type' => ['json_type', ['{"a":true}', '$.a'], 'true'],
    'false type' => ['json_type', ['{"a":false}', '$.a'], 'false'],
    'null type' => ['json_type', ['{"a":null}', '$.a'], 'null'],
    'missing type' => ['json_type', ['{"a":1}', '$.b'], null],
    'array type' => ['json_type', ['{"a":[1,2]}', '$.a'], 'array'],
    'object member type' => ['json_type', ['{"a":{"b":2}}', '$.a'], 'object'],
    'array length root' => ['json_array_length', ['[1,2,3]', '$'], 3],
    'array length member' => ['json_array_length', ['{"a":[1,2]}', '$.a'], 2],
    'array length scalar' => ['json_array_length', ['{"a":1}', '$.a'], 0],
    'array length missing' => ['json_array_length', ['{"a":1}', '$.b'], null],
    'jsonb root type' => ['json_type', [$jsonb, '$'], 'object'],
    'jsonb nested array length' => ['json_array_length', [$jsonb, '$.plugin.rules'], 2],
    'jsonb reverse member type' => ['json_type', [$jsonb, '$.plugin.rules[#-1].priority'], 'integer'],
    'json5 object type' => ['json_type', ['{a:[1,2,],}', '$'], 'object'],
];

foreach ($jsonInspectionCases as $name => [$function, $arguments, $expected]) {
    $tests['upstream corpus json inspection ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected): void {
        $t->same($expected, SQLiteJsonInspection::inspectionSqlFunctionArguments($function, $arguments));
    };
}

$jsonExtractCases = [
    'integer member' => ['json_extract', ['{"a":1}', '$.a'], 1],
    'real member' => ['json_extract', ['{"a":1.5}', '$.a'], 1.5],
    'text member' => ['json_extract', ['{"a":"x"}', '$.a'], 'x'],
    'true member' => ['json_extract', ['{"a":true}', '$.a'], 1],
    'false member' => ['json_extract', ['{"a":false}', '$.a'], 0],
    'null member' => ['json_extract', ['{"a":null}', '$.a'], null],
    'missing member' => ['json_extract', ['{"a":1}', '$.b'], null],
    'array as json text' => ['json_extract', ['{"a":[1,2]}', '$.a'], '[1,2]'],
    'object as json text' => ['json_extract', ['{"a":{"b":2}}', '$.a'], '{"b":2}'],
    'multi path array' => ['json_extract', ['{"a":1,"b":2}', '$.a', '$.b', '$.c'], '[1,2,null]'],
    'reverse array index' => ['json_extract', ['{"a":[1,2,3]}', '$.a[#-1]'], 3],
    'append array index missing' => ['json_extract', ['{"a":[1,2,3]}', '$.a[#-0]'], null],
    'json5 member' => ['json_extract', ['{a:"x",b:[4,],}', '$.b[0]'], 4],
    'jsonb scalar member' => ['jsonb_extract', [$jsonb, '$.plugin.name'], 'cache'],
    'jsonb boolean member' => ['jsonb_extract', [$jsonb, '$.plugin.enabled'], 1],
    'jsonb object blob' => ['jsonb_extract', [$jsonb, '$.plugin.rules[#-1]'], new SQLiteBlobValue(SQLiteJsonB::encode(['name' => 'cache', 'priority' => 5]))],
];

foreach ($jsonExtractCases as $name => [$function, $arguments, $expected]) {
    $tests['upstream corpus json extract ' . $name] = static function (TestRunner $t) use ($function, $arguments, $expected, $normalize): void {
        $actual = SQLiteJsonExtract::extractSqlFunction($function, $arguments[0], ...array_slice($arguments, 1));
        $t->same($normalize($expected), $normalize($actual));
    };
}

$selectRows = [
    ['name' => 'alpha', 'autoload' => 'yes', 'score' => 2],
    ['name' => 'beta', 'autoload' => 'no', 'score' => 1],
    ['name' => 'alpha', 'autoload' => 'yes', 'score' => 2],
    ['name' => 'gamma', 'autoload' => 'no', 'score' => null],
];

$selectCases = [
    'distinct names' => [static fn (): mixed => array_column(SQLiteSelectResult::distinct($selectRows, ['name']), 'name'), ['alpha', 'beta', 'gamma']],
    'distinct composite' => [static fn (): mixed => count(SQLiteSelectResult::distinct($selectRows, ['name', 'autoload'])), 3],
    'order ascending null first' => [static fn (): mixed => array_column(SQLiteSelectResult::orderBy($selectRows, [['column' => 'score']]), 'name'), ['gamma', 'beta', 'alpha', 'alpha']],
    'order descending' => [static fn (): mixed => array_column(SQLiteSelectResult::orderBy($selectRows, [['column' => 'score', 'direction' => 'DESC']]), 'name'), ['alpha', 'alpha', 'beta', 'gamma']],
    'limit offset' => [static fn (): mixed => array_column(SQLiteSelectResult::limitOffset($selectRows, 2, 1), 'name'), ['beta', 'alpha']],
    'negative limit' => [static fn (): mixed => array_column(SQLiteSelectResult::limitOffset($selectRows, -1, 2), 'name'), ['alpha', 'gamma']],
    'where in' => [static fn (): mixed => array_column(SQLiteSelectResult::whereIn($selectRows, 'autoload', ['yes']), 'name'), ['alpha', 'alpha']],
    'where not in' => [static fn (): mixed => array_column(SQLiteSelectResult::whereIn($selectRows, 'autoload', ['yes'], true), 'name'), ['beta', 'gamma']],
    'where exists' => [static fn (): mixed => array_column(SQLiteSelectResult::whereExists($selectRows, static fn (array $row): array => $row['score'] === 2 ? [1] : []), 'name'), ['alpha', 'alpha']],
    'where not exists' => [static fn (): mixed => array_column(SQLiteSelectResult::whereExists($selectRows, static fn (array $row): array => $row['score'] === 2 ? [1] : [], true), 'name'), ['beta', 'gamma']],
];

foreach ($selectCases as $name => [$callback, $expected]) {
    $tests['upstream corpus select result ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$windowCases = [
    'row number' => [static fn (): mixed => SQLiteWindowFunction::rowNumber(['a', 'a', 'b', 'c']), [1, 2, 3, 4]],
    'rank peers' => [static fn (): mixed => SQLiteWindowFunction::rank(['a', 'a', 'b', 'c']), [1, 1, 3, 4]],
    'dense rank peers' => [static fn (): mixed => SQLiteWindowFunction::denseRank(['a', 'a', 'b', 'c']), [1, 1, 2, 3]],
    'percent rank peers' => [static fn (): mixed => SQLiteWindowFunction::percentRank(['a', 'a', 'b']), [0.0, 0.0, 1.0]],
    'cume dist peers' => [static fn (): mixed => SQLiteWindowFunction::cumeDist(['a', 'a', 'b', 'b']), [0.5, 0.5, 1.0, 1.0]],
    'ntile balanced' => [static fn (): mixed => SQLiteWindowFunction::ntile([1, 2, 3, 4, 5], 2), [1, 1, 1, 2, 2]],
    'ntile more buckets' => [static fn (): mixed => SQLiteWindowFunction::ntile([1, 2, 3], 5), [1, 2, 3]],
    'lag default' => [static fn (): mixed => SQLiteWindowFunction::lag(['a', 'b', 'c']), [null, 'a', 'b']],
    'lag offset default' => [static fn (): mixed => SQLiteWindowFunction::lag(['a', 'b', 'c'], 2, 'x'), ['x', 'x', 'a']],
    'lead default' => [static fn (): mixed => SQLiteWindowFunction::lead(['a', 'b', 'c']), ['b', 'c', null]],
    'lead offset default' => [static fn (): mixed => SQLiteWindowFunction::lead(['a', 'b', 'c'], 2, 'x'), ['c', 'x', 'x']],
    'first value' => [static fn (): mixed => SQLiteWindowFunction::firstValue(['a', 'b', 'c']), ['a', 'a', 'a']],
    'last value' => [static fn (): mixed => SQLiteWindowFunction::lastValue(['a', 'b', 'c']), ['c', 'c', 'c']],
    'nth value' => [static fn (): mixed => SQLiteWindowFunction::nthValue(['a', 'b', 'c'], 2), ['b', 'b', 'b']],
];

foreach ($windowCases as $name => [$callback, $expected]) {
    $tests['upstream corpus window ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
