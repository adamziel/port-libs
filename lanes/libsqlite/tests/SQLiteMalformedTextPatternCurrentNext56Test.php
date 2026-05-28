<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteSelectPredicate;

$tests = [];

$malformedTail = "plugin_é\xc3";
$malformedMiddle = "plugin_\xc3é";
$truncatedThreeByte = "plugin_€\xe2\x82";
$badContinuation = "plugin_\xe2ABC";
$overlongSlash = "plugin_\xc0\xaf";

$likeCases = [
    'like consumes valid prefix before malformed tail wildcard' => [$malformedTail, 'plugin_é_', null, false, true],
    'like percent consumes valid prefix before malformed tail' => [$malformedTail, 'plugin_é%', null, false, true],
    'like underscore sees malformed tail as one byte character' => [$malformedTail, 'plugin_é__', null, false, false],
    'like exact malformed byte after valid prefix matches literal byte' => [$malformedTail, "plugin_é\xc3", null, false, true],
    'like valid multibyte prefix no longer decomposes into bytes' => [$malformedTail, "plugin_\xc3_", null, false, false],
    'like malformed middle still leaves following valid codepoint intact' => [$malformedMiddle, "plugin__é", null, false, true],
    'like malformed middle exact byte then valid codepoint matches' => [$malformedMiddle, "plugin_\xc3é", null, false, true],
    'like malformed middle does not make following valid codepoint two bytes' => [$malformedMiddle, "plugin_\xc3__", null, false, false],
    'like truncated three byte sequence needs both malformed tail bytes' => [$truncatedThreeByte, 'plugin_€_', null, false, false],
    'like truncated three byte tail is two malformed byte characters' => [$truncatedThreeByte, 'plugin_€__', null, false, true],
    'like truncated three byte tail rejects one extra wildcard' => [$truncatedThreeByte, 'plugin_€___', null, false, false],
    'like bad continuation keeps invalid lead byte separate from ascii' => [$badContinuation, "plugin_\xe2ABC", null, false, true],
    'like bad continuation wildcard consumes invalid lead byte only' => [$badContinuation, 'plugin__ABC', null, false, true],
    'like bad continuation does not consume invalid lead plus ascii as one char' => [$badContinuation, 'plugin__BC', null, false, false],
    'like overlong bytes are matched one byte at a time' => [$overlongSlash, 'plugin___', null, false, true],
    'like overlong slash is not treated as slash codepoint' => [$overlongSlash, 'plugin_/', null, false, false],
    'like escaped underscore follows valid prefix and malformed tail' => [$malformedTail, 'plugin\_é_', '\\', false, true],
    'like escaped percent literal survives malformed tail' => ["plugin_é\xc3%", 'plugin\_é_\%', '\\', false, true],
    'like nocase folds ascii around malformed byte' => ["PLUGIN_é\xc3", 'plugin_é_', null, false, true],
    'like case sensitive keeps ascii case around malformed byte' => ["PLUGIN_é\xc3", 'plugin_é_', null, true, false],
    'like malformed value empty pattern remains false' => [$malformedTail, '', null, false, false],
    'like empty malformed pattern byte does not happen' => ['', "\xc3", null, false, false],
    'like percent alone matches malformed text' => [$malformedTail, '%', null, false, true],
    'like underscore alone does not match valid plus malformed text' => [$malformedTail, '_', null, false, false],
    'like three underscores match valid e acute plus malformed byte without prefix' => ["é\xc3", '__', null, false, true],
    'like two underscores do not split valid e acute into bytes' => ['é', '__', null, false, false],
];

foreach ($likeCases as $name => [$value, $pattern, $escape, $caseSensitive, $expected]) {
    $tests['malformed text pattern current next56 ' . $name] = static function (TestRunner $t) use ($value, $pattern, $escape, $caseSensitive, $expected): void {
        $t->same($expected, SQLiteDatabase::likeMatches($value, $pattern, $escape, $caseSensitive));
    };
}

$globCases = [
    'glob consumes valid prefix before malformed tail wildcard' => [$malformedTail, 'plugin_é?', true],
    'glob star consumes valid prefix before malformed tail' => [$malformedTail, 'plugin_é*', true],
    'glob question sees malformed tail as one byte character' => [$malformedTail, 'plugin_é??', false],
    'glob exact malformed byte after valid prefix matches literal byte' => [$malformedTail, "plugin_é\xc3", true],
    'glob valid multibyte prefix no longer decomposes into bytes' => [$malformedTail, "plugin_\xc3?", false],
    'glob malformed middle still leaves following valid codepoint intact' => [$malformedMiddle, 'plugin_?é', true],
    'glob malformed middle exact byte then valid codepoint matches' => [$malformedMiddle, "plugin_\xc3é", true],
    'glob malformed middle does not make following valid codepoint two bytes' => [$malformedMiddle, "plugin_\xc3??", false],
    'glob truncated three byte sequence preserves preceding euro codepoint' => [$truncatedThreeByte, 'plugin_€?', false],
    'glob truncated three byte tail is two malformed byte characters' => [$truncatedThreeByte, 'plugin_€??', true],
    'glob truncated three byte tail rejects one extra wildcard' => [$truncatedThreeByte, 'plugin_€???', false],
    'glob bad continuation keeps invalid lead byte separate from ascii' => [$badContinuation, "plugin_\xe2ABC", true],
    'glob bad continuation wildcard consumes invalid lead byte only' => [$badContinuation, 'plugin_?ABC', true],
    'glob bad continuation does not consume invalid lead plus ascii as one char' => [$badContinuation, 'plugin_?BC', false],
    'glob overlong bytes are matched one byte at a time' => [$overlongSlash, 'plugin_??', true],
    'glob overlong slash is not treated as slash codepoint' => [$overlongSlash, 'plugin_/', false],
    'glob class can match malformed lead byte literally' => [$malformedTail, "plugin_é[\xc3]", true],
    'glob negated class rejects malformed lead byte literally' => [$malformedTail, "plugin_é[^\xc3]", false],
    'glob class range still handles valid unicode prefix' => [$malformedTail, 'plugin_[À-ÿ]?', true],
    'glob class range does not split valid unicode prefix into bytes' => ['plugin_é', 'plugin_[À-ÿ]?', false],
    'glob star after malformed byte reaches following valid codepoint' => ["plugin_\xc3é_tail", "plugin_\xc3*tail", true],
    'glob bracket literal after malformed byte remains literal' => ["plugin_\xc3[", "plugin_\xc3[[]", true],
    'glob unmatched class after malformed prefix falls back to literal bracket' => ["plugin_\xc3[", "plugin_\xc3[", true],
    'glob question alone does not match valid plus malformed text' => [$malformedTail, '?', false],
    'glob two questions match valid e acute plus malformed byte without prefix' => ["é\xc3", '??', true],
    'glob one question does not split valid e acute into bytes' => ['é', '??', false],
];

foreach ($globCases as $name => [$value, $pattern, $expected]) {
    $tests['malformed text pattern current next56 ' . $name] = static function (TestRunner $t) use ($value, $pattern, $expected): void {
        $t->same($expected, SQLiteDatabase::globMatches($value, $pattern));
    };
}

$rows = [
    ['option_id' => 1, 'option_name' => $malformedTail],
    ['option_id' => 2, 'option_name' => $malformedMiddle],
    ['option_id' => 3, 'option_name' => 'plugin_é'],
    ['option_id' => 4, 'option_name' => 'plugin_plain'],
];

$predicateCases = [
    'select predicate like keeps valid prefix with malformed tail' => [
        ['operator' => 'LIKE', 'left' => ['type' => 'column', 'name' => 'option_name'], 'right' => ['type' => 'literal', 'value' => 'plugin_é_']],
        [1],
    ],
    'select predicate not like excludes only malformed tail match' => [
        ['operator' => 'NOT LIKE', 'left' => ['type' => 'column', 'name' => 'option_name'], 'right' => ['type' => 'literal', 'value' => 'plugin_é_']],
        [2, 3, 4],
    ],
    'select predicate glob keeps malformed middle and valid suffix' => [
        ['operator' => 'GLOB', 'left' => ['type' => 'column', 'name' => 'option_name'], 'right' => ['type' => 'literal', 'value' => 'plugin_?é']],
        [2],
    ],
    'select predicate not glob excludes malformed middle match' => [
        ['operator' => 'NOT GLOB', 'left' => ['type' => 'column', 'name' => 'option_name'], 'right' => ['type' => 'literal', 'value' => 'plugin_?é']],
        [1, 3, 4],
    ],
    'select predicate like percent includes valid and malformed unicode rows' => [
        ['operator' => 'LIKE', 'left' => ['type' => 'column', 'name' => 'option_name'], 'right' => ['type' => 'literal', 'value' => 'plugin_é%']],
        [1, 3],
    ],
    'select predicate glob class plus wildcard includes malformed byte rows' => [
        ['operator' => 'GLOB', 'left' => ['type' => 'column', 'name' => 'option_name'], 'right' => ['type' => 'literal', 'value' => 'plugin_[À-ÿ]?']],
        [1, 2],
    ],
];

foreach ($predicateCases as $name => [$predicate, $expectedIds]) {
    $tests['malformed text pattern current next56 ' . $name] = static function (TestRunner $t) use ($rows, $predicate, $expectedIds): void {
        $t->same($expectedIds, array_column(SQLiteSelectPredicate::filter($rows, $predicate), 'option_id'));
    };
}

return $tests;
