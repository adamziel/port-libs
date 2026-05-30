<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteMalformedLikeGlobSourceNextPlan;

$tests = [];

$row = static function (int $id, string $bytes, int|string $encoding, string $autoload = 'yes'): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => is_int($encoding) ? $bytes : SQLiteEncodingCollationSourceCursor::encodeText($bytes, $encoding),
        'text_encoding' => is_int($encoding) ? $encoding : match (strtoupper($encoding)) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad fixture encoding'),
        },
        'autoload' => $autoload,
    ];
};

$currentRows = [
    $row(1, 'plugin_alpha', 'UTF-8'),
    $row(2, "plugin_\xc3", 1),
    $row(3, "plugin_\xe2\x82", 1),
    $row(4, "plugin_\x3d\xd8", 2),
    $row(5, 'plugin_beta', 'UTF-16LE'),
    $row(6, 'theme_alpha', 'UTF-8'),
    $row(7, 'plugin_éclair', 'UTF-8'),
    $row(8, 'Plugin_Beta', 'UTF-8', 'no'),
];

$nextRows = [
    $row(1, 'plugin_alpha', 'UTF-8'),
    $row(2, 'plugin_repaired', 'UTF-8'),
    $row(3, 'plugin_euro', 'UTF-16BE'),
    $row(4, 'plugin_surrogate_fixed', 'UTF-16LE'),
    $row(5, "plugin_\x00\xd8", 3),
    $row(6, 'theme_alpha', 'UTF-8'),
    $row(7, 'plugin_éclair', 'UTF-8'),
    $row(8, 'Plugin_Beta', 'UTF-8', 'no'),
    $row(9, 'plugin_new', 'UTF-8'),
];

$plan = static fn (
    string $pattern = 'plugin%',
    string $operator = 'LIKE',
    string $collation = 'NOCASE',
    ?string $escape = null,
    bool $caseSensitiveLike = false,
    string $currentSource = 'main.wp_options@cookie88',
    string $nextSource = 'main.wp_options@cookie91',
    ?array $current = null,
    ?array $next = null,
): array => SQLiteMalformedLikeGlobSourceNextPlan::optionRowNameCurrentNext(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $operator,
    $collation,
    $escape,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
);

$cases = [
    'records pattern' => ['pattern', 'plugin%'],
    'records operator' => ['operator', 'LIKE'],
    'records collation' => ['collation', 'NOCASE'],
    'records current source' => ['currentSource', 'main.wp_options@cookie88'],
    'records next source' => ['nextSource', 'main.wp_options@cookie91'],
    'requires reprepare' => ['reprepareRequired', true],
    'source reason comes first' => ['reprepareReasons.0', 'source-name'],
    'malformed text reason comes second' => ['reprepareReasons.1', 'malformed-text'],
    'matched rowset reason comes third' => ['reprepareReasons.2', 'matched-rowset'],
    'current excludes malformed utf8 and utf16 rows' => ['currentRowids', [1, 5, 8, 7]],
    'next includes repaired rows and excludes newly malformed row' => ['nextRowids', [1, 8, 3, 9, 2, 4, 7]],
    'entered rowids are repaired and new matches' => ['enteredRowids', [3, 9, 2, 4]],
    'valid current row that becomes malformed exits' => ['exitedRowids', [5]],
    'retained rowids preserve current cursor order' => ['retainedRowids', [1, 8, 7]],
    'current malformed rowids include utf8 and utf16 failures' => ['currentMalformedRowids', [2, 3, 4]],
    'next malformed rowids include new bad utf16 row' => ['nextMalformedRowids', [5]],
    'repaired rowids leave malformed set' => ['repairedRowids', [2, 3, 4]],
    'newly malformed rowid enters malformed set' => ['newlyMalformedRowids', [5]],
    'current row two error is utf8 malformed' => ['currentErrors.2', 'SQLite encoding source UTF-8 text payload is malformed'],
    'current row four error is utf16 malformed' => ['currentErrors.4', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next row five error is utf16 malformed' => ['nextErrors.5', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'current row two bytes are retained for diagnostics' => ['currentBytesHex.2', '706c7567696e5fc3'],
    'current row three bytes are retained for diagnostics' => ['currentBytesHex.3', '706c7567696e5fe282'],
    'next repaired row two bytes are visible' => ['nextBytesHex.2', '706c7567696e5f7265706169726564'],
    'next malformed utf16be bytes are visible' => ['nextBytesHex.5', '706c7567696e5f00d8'],
    'dependency records source cursor' => ['dependencies.0', 'sqlite-encoding-source-cursor'],
    'dependency records collation matcher' => ['dependencies.1', 'sqlite-like-glob-collation'],
    'dependency records malformed current next' => ['dependencies.2', 'sqlite-malformed-current-source-next'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['malformed like glob source next91 ' . $name] = static function (TestRunner $t) use ($plan, $path, $expected): void {
        $value = $plan();
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$globCases = [
    'glob records operator' => ['plugin_*', 'GLOB', 'BINARY', 'operator', 'GLOB'],
    'glob current excludes malformed and uppercase binary peer' => ['plugin_*', 'GLOB', 'BINARY', 'currentRowids', [1, 5, 7]],
    'glob next includes repaired rows' => ['plugin_*', 'GLOB', 'BINARY', 'nextRowids', [1, 3, 9, 2, 4, 7]],
    'glob repaired rows enter' => ['plugin_*', 'GLOB', 'BINARY', 'enteredRowids', [3, 9, 2, 4]],
    'glob retained rows keep binary order' => ['plugin_*', 'GLOB', 'BINARY', 'retainedRowids', [1, 7]],
    'glob malformed current set is stable' => ['plugin_*', 'GLOB', 'BINARY', 'currentMalformedRowids', [2, 3, 4]],
    'glob malformed next set is low surrogate row' => ['plugin_*', 'GLOB', 'BINARY', 'nextMalformedRowids', [5]],
    'glob latin class matches e acute only' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', 'currentRowids', [7]],
    'glob latin class next still matches e acute only' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', 'nextRowids', [7]],
    'glob latin class still records repaired malformed bytes' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', 'repairedRowids', [2, 3, 4]],
];

foreach ($globCases as $name => [$pattern, $operator, $collation, $path, $expected]) {
    $tests['malformed like glob source next91 ' . $name] = static function (TestRunner $t) use ($plan, $pattern, $operator, $collation, $path, $expected): void {
        $value = $plan($pattern, $operator, $collation);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['malformed like glob source next91 stable valid sources are reusable'] = static function (TestRunner $t) use ($row, $plan): void {
    $rows = [$row(1, 'plugin_alpha', 'UTF-8'), $row(2, 'theme_alpha', 'UTF-8')];
    $result = $plan(currentSource: 'stable', nextSource: 'stable', current: $rows, next: $rows);
    $t->same(false, $result['reprepareRequired']);
    $t->same([], $result['reprepareReasons']);
    $t->same([1], $result['retainedRowids']);
    $t->same([], $result['currentMalformedRowids']);
};

$tests['malformed like glob source next91 same malformed set still keeps malformed reason out'] = static function (TestRunner $t) use ($row, $plan): void {
    $rows = [$row(1, "plugin_\xc3", 1), $row(2, 'plugin_alpha', 'UTF-8')];
    $result = $plan(currentSource: 'stable', nextSource: 'stable', current: $rows, next: $rows);
    $t->same(false, $result['reprepareRequired']);
    $t->same([], $result['reprepareReasons']);
    $t->same([1], $result['currentMalformedRowids']);
    $t->same([2], $result['retainedRowids']);
};

$tests['malformed like glob source next91 escaped percent keeps repaired literal row'] = static function (TestRunner $t) use ($row, $plan): void {
    $current = [$row(1, "plugin_100\xc3", 1)];
    $next = [$row(1, 'plugin_100%_enabled', 'UTF-8'), $row(2, 'plugin_100x_enabled', 'UTF-8')];
    $result = $plan('plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, current: $current, next: $next);
    $t->same([], $result['currentRowids']);
    $t->same([1], $result['nextRowids']);
    $t->same([1], $result['repairedRowids']);
    $t->same([1], $result['enteredRowids']);
};

$tests['malformed like glob source next91 case sensitive like skips uppercase repaired row'] = static function (TestRunner $t) use ($row, $plan): void {
    $current = [$row(1, "Plugin_\xc3", 1)];
    $next = [$row(1, 'Plugin_repaired', 'UTF-8'), $row(2, 'plugin_repaired', 'UTF-8')];
    $result = $plan('plugin%', 'LIKE', 'BINARY', null, true, current: $current, next: $next);
    $t->same([], $result['currentRowids']);
    $t->same([2], $result['nextRowids']);
    $t->same([1], $result['repairedRowids']);
    $t->same([2], $result['enteredRowids']);
};

$tests['malformed like glob source next91 rejects unsupported operator'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan(operator: 'REGEXP'));
};

$tests['malformed like glob source next91 rejects unsupported collation'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan(collation: 'WP_LOCALE'));
};

$tests['malformed like glob source next91 rejects malformed escape'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan(escape: 'xx'));
};

$tests['malformed like glob source next91 rejects missing option id'] = static function (TestRunner $t) use ($plan, $nextRows): void {
    $current = [['option_name_bytes' => 'plugin_alpha', 'text_encoding' => 1]];
    $t->throws(InvalidArgumentException::class, static fn () => $plan(current: $current, next: $nextRows));
};

$tests['malformed like glob source next91 rejects non string bytes'] = static function (TestRunner $t) use ($plan, $nextRows): void {
    $current = [['option_id' => 1, 'option_name_bytes' => 10, 'text_encoding' => 1]];
    $t->throws(InvalidArgumentException::class, static fn () => $plan(current: $current, next: $nextRows));
};

$tests['malformed like glob source next91 rejects missing encoding'] = static function (TestRunner $t) use ($plan, $nextRows): void {
    $current = [['option_id' => 1, 'option_name_bytes' => 'plugin_alpha']];
    $t->throws(InvalidArgumentException::class, static fn () => $plan(current: $current, next: $nextRows));
};

$tests['malformed like glob source next91 reports unsupported encoding as malformed source'] = static function (TestRunner $t) use ($plan, $nextRows): void {
    $current = [['option_id' => 1, 'option_name_bytes' => 'plugin_alpha', 'text_encoding' => 99]];
    $result = $plan(current: $current, next: $nextRows);
    $t->same([1], $result['currentMalformedRowids']);
    $t->same('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE', $result['currentErrors'][1]);
};

return $tests;
