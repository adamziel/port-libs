<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteLikeGlobCurrentSourceNextPlan;

$tests = [];

$encodingNumber147 = static fn (string $encoding): int => match ($encoding) {
    'UTF-8' => 1,
    'UTF-16LE' => 2,
    'UTF-16BE' => 3,
    default => throw new InvalidArgumentException('bad fixture encoding'),
};

$row147 = static function (int $id, string $name, string $encoding, string $autoload = 'yes') use ($encodingNumber147): array {
    return [
        'option_id' => $id,
        'option_name' => $name,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => $encodingNumber147($encoding),
        'autoload' => $autoload,
    ];
};

$currentRows147 = [
    $row147(1, 'plugin_%_cache', 'UTF-8'),
    $row147(2, 'plugin_abc_cache', 'UTF-16LE'),
    $row147(3, 'Plugin_%_Cache', 'UTF-16BE'),
    $row147(4, 'plugin_%_cache ', 'UTF-16LE'),
    $row147(5, 'plugin__cache', 'UTF-8'),
    $row147(6, 'plugin_%_cache_extra', 'UTF-16BE'),
    $row147(7, 'theme_%_cache', 'UTF-8'),
    $row147(8, 'plugin_😀_cache', 'UTF-16LE'),
    $row147(9, 'plugin_é_cache', 'UTF-8'),
    $row147(10, 'Plugin_Z_cache', 'UTF-8'),
];

$nextRows147 = [
    $row147(1, 'plugin_%_cache', 'UTF-16LE'),
    $row147(2, 'plugin_abc_cache', 'UTF-16BE'),
    $row147(3, 'Plugin_%_Cache', 'UTF-16LE'),
    $row147(4, 'plugin_%_cache', 'UTF-8'),
    $row147(5, 'plugin__cache', 'UTF-8'),
    $row147(6, 'plugin_%_cache_v2', 'UTF-16BE'),
    $row147(7, 'theme_%_cache', 'UTF-8'),
    $row147(8, 'plugin_😀_cache', 'UTF-16BE'),
    $row147(9, 'plugin_é_cache', 'UTF-16LE'),
    $row147(10, 'Plugin_Z_cache', 'UTF-8'),
    $row147(11, 'plugin_%_cache_new', 'UTF-16BE'),
];

$statement147 = static function (
    string $pattern,
    string $operator = 'LIKE',
    string $collation = 'NOCASE',
    ?string $escape = '!',
    bool $caseSensitiveLike = false,
    string $source = 'main.app_settings@cookie147',
): array {
    return [
        'source' => $source,
        'operator' => $operator,
        'pattern' => $pattern,
        'collation' => $collation,
        'escape' => $escape,
        'caseSensitiveLike' => $caseSensitiveLike,
    ];
};

$plan147 = static fn (
    array $currentStatement,
    array $nextStatement,
    ?array $currentRows = null,
    ?array $nextRows = null,
): array => SQLiteLikeGlobCurrentSourceNextPlan::keyValueRowKeyStatement(
    $currentRows ?? $currentRows147,
    $nextRows ?? $nextRows147,
    $currentStatement,
    $nextStatement,
);

$escaped147 = $statement147('plugin!_!%!_cache%');
$glob147 = $statement147('plugin_[A-z]*_cache*', 'GLOB', 'NOCASE', null);
$globBinary147 = $statement147('plugin_[A-z]*_cache*', 'GLOB', 'BINARY', null);

$cases147 = [
    'escaped current range lower includes literal underscore percent' => [$escaped147, $escaped147, 'current.range.lowerInclusive', 'plugin_%_cache'],
    'escaped current range upper advances literal prefix' => [$escaped147, $escaped147, 'current.range.upperBound', 'plugin_%_cachf'],
    'escaped current candidates include residual false positives' => [$escaped147, $escaped147, 'current.candidateRowids', [1, 3, 4, 6]],
    'escaped current matches include percent cache rows' => [$escaped147, $escaped147, 'current.rowids', [1, 3, 4, 6]],
    'escaped current false positives empty when wildcard suffix matches all candidates' => [$escaped147, $escaped147, 'current.falsePositiveRowids', []],
    'escaped next candidates include new source row' => [$escaped147, $escaped147, 'next.candidateRowids', [1, 3, 4, 11, 6]],
    'escaped next matches include new source row' => [$escaped147, $escaped147, 'next.rowids', [1, 3, 4, 11, 6]],
    'escaped retained rowids preserve current order' => [$escaped147, $escaped147, 'retainedRowids', [1, 3, 4, 6]],
    'escaped entered rowids expose new row' => [$escaped147, $escaped147, 'enteredRowids', [11]],
    'escaped changed encoding tracks matched rows' => [$escaped147, $escaped147, 'changedEncodingRowids', [1, 3, 4]],
    'escaped candidate changed encoding equals matched rows' => [$escaped147, $escaped147, 'candidateChangedEncodingRowids', [1, 3, 4]],
    'escaped candidate changed bytes include encoding and rename' => [$escaped147, $escaped147, 'candidateChangedBytesRowids', [1, 3, 4, 6]],
    'escaped reprepare includes text encoding reason' => [$escaped147, $escaped147, 'reprepareReasons.0', 'text-encoding'],
    'escaped reprepare includes key bytes reason' => [$escaped147, $escaped147, 'reprepareReasons.1', 'key-bytes'],
    'escaped reprepare includes matched rowset reason' => [$escaped147, $escaped147, 'reprepareReasons.2', 'matched-rowset'],
    'escaped dependency records candidate scan' => [$escaped147, $escaped147, 'dependencies.3', 'sqlite-like-glob-range-candidates'],
    'escaped current candidate bytes expose utf16be uppercase row' => [$escaped147, $escaped147, 'current.candidateBytesHex.3', '0050006c007500670069006e005f0025005f00430061006300680065'],
    'escaped next candidate bytes expose utf16le uppercase row' => [$escaped147, $escaped147, 'next.candidateBytesHex.3', '50006c007500670069006e005f0025005f0043006100630068006500'],
    'escaped current candidate encoding row four is utf16le' => [$escaped147, $escaped147, 'current.candidateEncodings.4', 'UTF-16LE'],
    'escaped next candidate encoding row four is utf8' => [$escaped147, $escaped147, 'next.candidateEncodings.4', 'UTF-8'],
    'escaped source switch reason precedes row changes' => [$escaped147, $statement147('plugin!_!%!_cache%', source: 'main.app_settings@cookie148'), 'reprepareReasons.0', 'source-name'],
    'escape character switch records pattern' => [$escaped147, $statement147('plugin#_#%#_cache%', escape: '#'), 'reprepareReasons.0', 'pattern'],
    'escape character switch records escape' => [$escaped147, $statement147('plugin#_#%#_cache%', escape: '#'), 'reprepareReasons.1', 'escape'],
    'case sensitive like has binary-style candidate lower' => [$statement147('plugin!_!%!_cache%', collation: 'BINARY', caseSensitiveLike: true), $statement147('plugin!_!%!_cache%', collation: 'BINARY', caseSensitiveLike: true), 'current.range.lowerInclusive', 'plugin_%_cache'],
    'case sensitive like skips uppercase matched row' => [$statement147('plugin!_!%!_cache%', collation: 'BINARY', caseSensitiveLike: true), $statement147('plugin!_!%!_cache%', collation: 'BINARY', caseSensitiveLike: true), 'current.rowids', [1, 4, 6]],
    'case sensitive like skips uppercase candidate row' => [$statement147('plugin!_!%!_cache%', collation: 'BINARY', caseSensitiveLike: true), $statement147('plugin!_!%!_cache%', collation: 'BINARY', caseSensitiveLike: true), 'current.candidateRowids', [1, 4, 6]],
    'glob nocase current candidates include prefix range rows' => [$glob147, $glob147, 'current.candidateRowids', [1, 3, 4, 6, 5, 2, 10, 9, 8]],
    'glob nocase current matches only lowercase glob row' => [$glob147, $glob147, 'current.rowids', [2]],
    'glob nocase false positives expose collation range rows' => [$glob147, $glob147, 'current.falsePositiveRowids', [1, 3, 4, 6, 5, 10, 9, 8]],
    'glob nocase next candidates include prefix range rows' => [$glob147, $glob147, 'next.candidateRowids', [1, 3, 4, 11, 6, 5, 2, 10, 9, 8]],
    'glob nocase next matches only lowercase glob row' => [$glob147, $glob147, 'next.rowids', [2]],
    'glob nocase next false positives expose range rows' => [$glob147, $glob147, 'next.falsePositiveRowids', [1, 3, 4, 11, 6, 5, 10, 9, 8]],
    'glob nocase candidate changed encoding captures range rows' => [$glob147, $glob147, 'candidateChangedEncodingRowids', [1, 2, 3, 4, 8, 9]],
    'glob nocase changed encoding captures row two' => [$glob147, $glob147, 'changedEncodingRowids', [2]],
    'glob nocase candidate bytes capture range rows' => [$glob147, $glob147, 'candidateChangedBytesRowids', [1, 2, 3, 4, 6, 8, 9]],
    'glob nocase range lower is literal prefix' => [$glob147, $glob147, 'current.range.lowerInclusive', 'plugin_'],
    'glob nocase range upper is next binary prefix' => [$glob147, $glob147, 'current.range.upperBound', 'plugin`'],
    'glob binary candidates skip uppercase range row' => [$globBinary147, $globBinary147, 'current.candidateRowids', [1, 4, 6, 5, 2, 9, 8]],
    'glob binary false positives expose prefix rows' => [$globBinary147, $globBinary147, 'current.falsePositiveRowids', [1, 4, 6, 5, 9, 8]],
    'glob ignores supplied escape in normalized current' => [$statement147('plugin_[A-z]*_cache*', 'GLOB', 'NOCASE', '!'), $glob147, 'current.escape', null],
    'glob ignores supplied escape in normalized next' => [$glob147, $statement147('plugin_[A-z]*_cache*', 'GLOB', 'NOCASE', '!'), 'next.escape', null],
    'glob stable false positive only source is reusable when row bytes unchanged' => [$glob147, $glob147, 'status', 'reprepare-required'],
    'leading wildcard has no candidates' => [$statement147('%!_cache'), $statement147('%!_cache'), 'current.candidateRowids', []],
    'leading wildcard has null range' => [$statement147('%!_cache'), $statement147('%!_cache'), 'current.range', null],
    'theme escaped pattern candidates theme row' => [$statement147('theme!_!%!_cache%'), $statement147('theme!_!%!_cache%'), 'current.candidateRowids', [7]],
    'theme escaped pattern matches theme row' => [$statement147('theme!_!%!_cache%'), $statement147('theme!_!%!_cache%'), 'current.rowids', [7]],
    'theme escaped stable cursor reusable' => [$statement147('theme!_!%!_cache%', source: 'stable'), $statement147('theme!_!%!_cache%', source: 'stable'), 'status', 'cursor-reusable'],
    'theme escaped stable has no reasons' => [$statement147('theme!_!%!_cache%', source: 'stable'), $statement147('theme!_!%!_cache%', source: 'stable'), 'reprepareReasons', []],
    'emoji literal escaped wildcard does not match emoji row' => [$statement147('plugin!_!%!_cache%'), $statement147('plugin!_!%!_cache%'), 'current.candidateEncodings.8', null],
    'emoji glob matches emoji row with star prefix' => [$statement147('plugin_😀*', 'GLOB', 'BINARY', null), $statement147('plugin_😀*', 'GLOB', 'BINARY', null), 'current.rowids', [8]],
    'emoji glob candidate changed encoding tracks row eight' => [$statement147('plugin_😀*', 'GLOB', 'BINARY', null), $statement147('plugin_😀*', 'GLOB', 'BINARY', null), 'candidateChangedEncodingRowids', [8]],
    'latin like escaped prefix matches e acute row' => [$statement147('plugin!_é%', collation: 'BINARY', escape: '!', caseSensitiveLike: true), $statement147('plugin!_é%', collation: 'BINARY', escape: '!', caseSensitiveLike: true), 'current.rowids', [9]],
    'latin like next encoding change tracks e acute row' => [$statement147('plugin!_é%', collation: 'BINARY', escape: '!', caseSensitiveLike: true), $statement147('plugin!_é%', collation: 'BINARY', escape: '!', caseSensitiveLike: true), 'candidateChangedEncodingRowids', [9]],
];

foreach ($cases147 as $name => [$currentStatement, $nextStatement, $path, $expected]) {
    $tests['like escape glob candidate current source next147 ' . $name] = static function (TestRunner $t) use ($plan147, $currentStatement, $nextStatement, $path, $expected): void {
        $value = $plan147($currentStatement, $nextStatement);
        foreach (explode('.', $path) as $part) {
            $value = is_array($value) && array_key_exists($part, $value) ? $value[$part] : null;
        }
        $t->same($expected, $value);
    };
}

$tests['like escape glob candidate current source next147 rejects non text escape'] = static function (TestRunner $t) use ($statement147, $plan147): void {
    $bad = $statement147('plugin!_!%!_cache%');
    $bad['escape'] = 1;
    $t->throws(InvalidArgumentException::class, static fn () => $plan147($bad, $statement147('plugin!_!%!_cache%')));
};

$tests['like escape glob candidate current source next147 rejects multi character escape'] = static function (TestRunner $t) use ($statement147, $plan147): void {
    $bad = $statement147('plugin!_!%!_cache%', escape: '!!');
    $t->throws(InvalidArgumentException::class, static fn () => $plan147($bad, $statement147('plugin!_!%!_cache%')));
};

$tests['like escape glob candidate current source next147 rejects malformed utf16 candidate bytes'] = static function (TestRunner $t) use ($statement147, $plan147, $currentRows147): void {
    $next = [['option_id' => 1, 'option_name_bytes' => "\x70", 'text_encoding' => 2]];
    $t->throws(InvalidArgumentException::class, static fn () => $plan147($statement147('plugin%'), $statement147('plugin%'), $currentRows147, $next));
};

return $tests;
