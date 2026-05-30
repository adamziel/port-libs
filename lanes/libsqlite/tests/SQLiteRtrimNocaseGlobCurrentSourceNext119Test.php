<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRtrimNocaseGlobCurrentSourceNextPlan;

$tests = [];

$currentRows = [
    ['option_id' => 1, 'option_name' => 'plugin_cache', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_cache ', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => "plugin_cache\t", 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'plugin_cache_extra', 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => 'plugin_Cache_extra', 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'plugin_éclair', 'autoload' => 'yes'],
    ['option_id' => 8, 'option_name' => 'PLUGIN_éclair', 'autoload' => 'yes'],
    ['option_id' => 9, 'option_name' => "plugin_bad_\xc3\x28", 'autoload' => 'no'],
    ['option_id' => 10, 'option_name' => 'theme_cache', 'autoload' => 'yes'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'plugin_cache', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_cache  ', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => "plugin_cache\t", 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'plugin_cache_extra', 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => 'plugin_Cache_extra', 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'plugin_éclair', 'autoload' => 'yes'],
    ['option_id' => 8, 'option_name' => 'PLUGIN_éclair', 'autoload' => 'yes'],
    ['option_id' => 9, 'option_name' => 'plugin_bad_fixed', 'autoload' => 'no'],
    ['option_id' => 11, 'option_name' => 'plugin_cache_new', 'autoload' => 'yes'],
    ['option_id' => 12, 'option_name' => "plugin_new_\xc3\x28", 'autoload' => 'no'],
];

$plan = static fn (
    string $pattern = 'plugin_*',
    string $collation = 'NOCASE',
    string $currentSource = 'main.wp_options@cookie118',
    string $nextSource = 'main.wp_options@cookie119',
): array => SQLiteRtrimNocaseGlobCurrentSourceNextPlan::keyValueRowKeyPlan(
    $currentRows,
    $nextRows,
    $pattern,
    $collation,
    $currentSource,
    $nextSource,
);

$cases = [
    'records current source' => ['plugin_*', 'NOCASE', 'currentSource', 'main.wp_options@cookie118'],
    'records next source' => ['plugin_*', 'NOCASE', 'nextSource', 'main.wp_options@cookie119'],
    'records pattern' => ['plugin_*', 'NOCASE', 'pattern', 'plugin_*'],
    'records collation' => ['plugin_*', 'NOCASE', 'collation', 'NOCASE'],
    'records lower range' => ['plugin_*', 'NOCASE', 'range.lowerInclusive', 'plugin_'],
    'records upper range' => ['plugin_*', 'NOCASE', 'range.upperBound', 'plugin`'],
    'nocase current candidates include ascii case peers' => ['plugin_*', 'NOCASE', 'currentCandidateRowids', [9, 1, 2, 4, 3, 5, 6, 7, 8]],
    'nocase next candidates include repaired and new malformed peers' => ['plugin_*', 'NOCASE', 'nextCandidateRowids', [9, 1, 2, 4, 3, 5, 6, 11, 12, 7, 8]],
    'nocase current matched keeps glob byte case sensitive' => ['plugin_*', 'NOCASE', 'currentMatchedRowids', [9, 1, 4, 3, 5, 6, 7]],
    'nocase next matched keeps glob byte case sensitive' => ['plugin_*', 'NOCASE', 'nextMatchedRowids', [9, 1, 4, 3, 5, 6, 11, 12, 7]],
    'nocase false positives are uppercase prefix rows' => ['plugin_*', 'NOCASE', 'currentFalsePositiveRowids', [2, 8]],
    'nocase next false positives preserve uppercase prefix rows' => ['plugin_*', 'NOCASE', 'nextFalsePositiveRowids', [2, 8]],
    'nocase retained matched rowids' => ['plugin_*', 'NOCASE', 'retainedMatchedRowids', [9, 1, 4, 3, 5, 6, 7]],
    'nocase entered matched rowids' => ['plugin_*', 'NOCASE', 'enteredMatchedRowids', [11, 12]],
    'nocase exited matched rowids empty' => ['plugin_*', 'NOCASE', 'exitedMatchedRowids', []],
    'nocase current order starts malformed bad prefix' => ['plugin_*', 'NOCASE', 'currentOrderRowids.0', 9],
    'nocase current order folds Plugin_Cache next to lowercase' => ['plugin_*', 'NOCASE', 'currentOrderRowids.2', 2],
    'nocase comparison key folds row two' => ['plugin_*', 'NOCASE', 'currentComparisonKeys.2', 'plugin_cache'],
    'nocase comparison key keeps greek byte case outside ascii folding' => ['plugin_*', 'NOCASE', 'currentComparisonKeys.8', 'plugin_éclair'],
    'nocase residual row two is false' => ['plugin_*', 'NOCASE', 'currentResidualMatches.2', false],
    'nocase residual row one is true' => ['plugin_*', 'NOCASE', 'currentResidualMatches.1', true],
    'nocase residual uppercase greek prefix is false' => ['plugin_*', 'NOCASE', 'currentResidualMatches.8', false],
    'nocase current malformed rowids' => ['plugin_*', 'NOCASE', 'currentMalformedRowids', [9]],
    'nocase next malformed rowids' => ['plugin_*', 'NOCASE', 'nextMalformedRowids', [12]],
    'nocase repaired malformed rowids' => ['plugin_*', 'NOCASE', 'repairedMalformedRowids', [9]],
    'nocase newly malformed rowids' => ['plugin_*', 'NOCASE', 'newlyMalformedRowids', [12]],
    'nocase cursor invalidated' => ['plugin_*', 'NOCASE', 'cursorInvalidated', true],
    'nocase invalidation source reason' => ['plugin_*', 'NOCASE', 'invalidationReasons.0', 'source-name'],
    'nocase invalidation candidate reason' => ['plugin_*', 'NOCASE', 'invalidationReasons.1', 'candidate-rowset'],
    'nocase invalidation matched reason' => ['plugin_*', 'NOCASE', 'invalidationReasons.2', 'matched-rowset'],
    'nocase invalidation malformed reason' => ['plugin_*', 'NOCASE', 'invalidationReasons.3', 'malformed-text'],
    'nocase dependency range' => ['plugin_*', 'NOCASE', 'dependencies.0', 'sqlite-glob-prefix-range'],
    'nocase dependency collation' => ['plugin_*', 'NOCASE', 'dependencies.1', 'sqlite-nocase-collation'],
    'nocase dependency residual' => ['plugin_*', 'NOCASE', 'dependencies.2', 'sqlite-glob-binary-residual'],
    'binary candidates exclude uppercase prefix rows' => ['plugin_*', 'BINARY', 'currentCandidateRowids', [6, 9, 1, 4, 3, 5, 7]],
    'binary matched equals binary candidates for prefix glob' => ['plugin_*', 'BINARY', 'currentMatchedRowids', [6, 9, 1, 4, 3, 5, 7]],
    'binary false positives empty' => ['plugin_*', 'BINARY', 'currentFalsePositiveRowids', []],
    'binary current order keeps uppercase before lowercase outside range' => ['plugin_*', 'BINARY', 'currentOrderRowids.0', 8],
    'binary comparison key preserves row two case' => ['plugin_*', 'BINARY', 'currentComparisonKeys.2', 'Plugin_Cache'],
    'rtrim exact candidates include space padded row' => ['plugin_cache', 'RTRIM', 'currentCandidateRowids', [1, 3, 4, 5]],
    'rtrim exact matched excludes space padded row' => ['plugin_cache', 'RTRIM', 'currentMatchedRowids', [1]],
    'rtrim exact false positive includes padded and longer prefix rows' => ['plugin_cache', 'RTRIM', 'currentFalsePositiveRowids', [3, 4, 5]],
    'rtrim exact next candidates include double space padded row' => ['plugin_cache', 'RTRIM', 'nextCandidateRowids', [1, 3, 4, 5, 11]],
    'rtrim exact next matched excludes double space padded row' => ['plugin_cache', 'RTRIM', 'nextMatchedRowids', [1]],
    'rtrim exact tab row not in exact range' => ['plugin_cache', 'RTRIM', 'currentResidualMatches.3', false],
    'rtrim key trims only ascii spaces' => ['plugin_cache', 'RTRIM', 'currentComparisonKeys.3', 'plugin_cache'],
    'rtrim tab comparison key keeps tab' => ['plugin_cache*', 'RTRIM', 'currentComparisonKeys.4', "plugin_cache\t"],
    'rtrim prefix matched includes space and tab rows' => ['plugin_cache*', 'RTRIM', 'currentMatchedRowids', [1, 3, 4, 5]],
    'rtrim prefix next entered includes new cache row' => ['plugin_cache*', 'RTRIM', 'enteredMatchedRowids', [11]],
    'source stable still invalidated by rowset and malformed changes' => ['plugin_*', 'NOCASE', 'invalidationReasons', ['candidate-rowset', 'matched-rowset', 'malformed-text'], 'stable', 'stable'],
    'source stable remains true invalidated due row changes' => ['plugin_*', 'NOCASE', 'cursorInvalidated', true, 'stable', 'stable'],
];

foreach ($cases as $name => $case) {
    $tests['rtrim nocase glob current source nextOneOneNine ' . $name] = static function (TestRunner $t) use ($plan, $case): void {
        [$pattern, $collation, $path, $expected] = $case;
        $currentSource = $case[4] ?? 'main.wp_options@cookie118';
        $nextSource = $case[5] ?? 'main.wp_options@cookie119';
        $value = $plan($pattern, $collation, $currentSource, $nextSource);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['rtrim nocase glob current source nextOneOneNine stable unchanged binary cursor is not invalidated'] = static function (TestRunner $t) use ($currentRows): void {
    $plan = SQLiteRtrimNocaseGlobCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, $currentRows, 'plugin_*', 'BINARY', 'stable', 'stable');
    $t->same(false, $plan['cursorInvalidated']);
    $t->same([], $plan['invalidationReasons']);
};

$tests['rtrim nocase glob current source nextOneOneNine leading wildcard has no range candidates'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $plan = SQLiteRtrimNocaseGlobCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, $nextRows, '*cache', 'NOCASE');
    $t->same(null, $plan['range']);
    $t->same([], $plan['currentCandidateRowids']);
    $t->same([], $plan['currentMatchedRowids']);
};

$tests['rtrim nocase glob current source nextOneOneNine rejects unsupported collation'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRtrimNocaseGlobCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, $nextRows, 'plugin_*', 'UNICODE_NOCASE'));
};

$tests['rtrim nocase glob current source nextOneOneNine rejects missing rowid'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRtrimNocaseGlobCurrentSourceNextPlan::keyValueRowKeyPlan([['option_name' => 'plugin_cache']], $nextRows, 'plugin_*', 'NOCASE'));
};

$tests['rtrim nocase glob current source nextOneOneNine rejects non text option name'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRtrimNocaseGlobCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => 1, 'option_name' => 42]], $nextRows, 'plugin_*', 'NOCASE'));
};

return $tests;
