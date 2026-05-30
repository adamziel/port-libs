<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteRtrimNocaseGlobCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding, string $autoload = 'yes'): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
        'autoload' => $autoload,
    ];
};

$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'plugin_cache', 'UTF-8'),
    $row(2, 'Plugin_Cache   ', 'UTF-16LE'),
    $row(3, 'PLUGIN_cache', 'UTF-16BE'),
    $row(4, 'plugin_cache ', 'UTF-16LE', 'no'),
    $row(5, "plugin_cache\t", 'UTF-16BE', 'no'),
    $row(6, 'plugin_cache_extra', 'UTF-8'),
    $row(7, 'Plugin_Cache_extra', 'UTF-16LE'),
    $row(8, 'plugin_éclair', 'UTF-16BE'),
    $row(9, 'PLUGIN_ÉCLAIR ', 'UTF-16LE'),
    $row(10, 'theme_cache', 'UTF-8'),
    $bad(11, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00c", 2),
];

$nextRows = [
    $row(1, 'plugin_cache  ', 'UTF-16BE'),
    $row(2, 'Plugin_Cache', 'UTF-16BE'),
    $row(3, 'PLUGIN_cache', 'UTF-16BE'),
    $row(4, "plugin_cache\t", 'UTF-16LE', 'no'),
    $row(5, 'plugin_cache ', 'UTF-16LE', 'no'),
    $row(6, 'plugin_cache_extra', 'UTF-8'),
    $row(7, 'Plugin_Cache_extra', 'UTF-16LE'),
    $row(8, 'plugin_éclair', 'UTF-16BE'),
    $row(9, 'PLUGIN_ÉCLAIR ', 'UTF-16LE'),
    $row(12, 'plugin_cache_new', 'UTF-16LE'),
    $row(13, 'Plugin_Cache_New', 'UTF-16BE'),
    $bad(14, "\x3d\xd8", 2),
];

$plan = static fn (
    string $pattern = 'plugin_*',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@135',
    string $nextSource = 'main.app_settings@136',
    int $currentSchemaCookie = 11,
    int $nextSchemaCookie = 12,
    int $currentCollationVersion = 3,
    int $nextCollationVersion = 4,
): array => SQLiteRtrimNocaseGlobCurrentSourceNextPlan::keyValueRowKeyExpressionPlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $currentSource,
    $nextSource,
    $currentSchemaCookie,
    $nextSchemaCookie,
    $currentCollationVersion,
    $nextCollationVersion,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'records operator' => ['plugin_*', 'operator', 'GLOB'],
    'records expression' => ['plugin_*', 'expression', 'rtrim(option_name) COLLATE NOCASE'],
    'records collation' => ['plugin_*', 'collation', 'NOCASE'],
    'records range collation' => ['plugin_*', 'rangeCollation', 'RTRIM+NOCASE'],
    'records residual collation' => ['plugin_*', 'residualCollation', 'BINARY'],
    'records case sensitive residual' => ['plugin_*', 'globResidualCaseSensitive', true],
    'records pattern' => ['plugin_*', 'pattern', 'plugin_*'],
    'records lower range' => ['plugin_*', 'range.lowerInclusive', 'plugin_'],
    'records upper range' => ['plugin_*', 'range.upperBound', 'plugin`'],
    'index usable for fixed prefix' => ['plugin_*', 'indexUsable', true],
    'records current source' => ['plugin_*', 'currentSource', 'main.app_settings@135'],
    'records next source' => ['plugin_*', 'nextSource', 'main.app_settings@136'],
    'records current schema cookie' => ['plugin_*', 'currentSchemaCookie', 11],
    'records next schema cookie' => ['plugin_*', 'nextSchemaCookie', 12],
    'records current collation version' => ['plugin_*', 'currentCollationVersion', 3],
    'records next collation version' => ['plugin_*', 'nextCollationVersion', 4],
    'current order trims and folds ascii peers' => ['plugin_*', 'currentOrderRowids', [1, 2, 3, 4, 5, 6, 7, 9, 8, 10]],
    'next order trims and folds ascii peers' => ['plugin_*', 'nextOrderRowids', [1, 2, 3, 5, 4, 6, 7, 12, 13, 9, 8]],
    'current candidates include nocase false positives' => ['plugin_*', 'currentCandidateRowids', [1, 2, 3, 4, 5, 6, 7, 9, 8]],
    'next candidates include new nocase false positives' => ['plugin_*', 'nextCandidateRowids', [1, 2, 3, 5, 4, 6, 7, 12, 13, 9, 8]],
    'current matched keeps binary glob residual' => ['plugin_*', 'currentMatchedRowids', [1, 4, 5, 6, 8]],
    'next matched keeps binary glob residual' => ['plugin_*', 'nextMatchedRowids', [1, 5, 4, 6, 12, 8]],
    'current false positives include uppercase folded candidates' => ['plugin_*', 'currentFalsePositiveRowids', [2, 3, 7, 9]],
    'next false positives include uppercase folded candidates' => ['plugin_*', 'nextFalsePositiveRowids', [2, 3, 7, 13, 9]],
    'retained matched rowids' => ['plugin_*', 'retainedMatchedRowids', [1, 4, 5, 6, 8]],
    'entered matched rowids' => ['plugin_*', 'enteredMatchedRowids', [12]],
    'exited matched rowids empty' => ['plugin_*', 'exitedMatchedRowids', []],
    'row two comparison key trims and folds' => ['plugin_*', 'currentComparisonKeys.2', 'plugin_cache'],
    'row five tab comparison key stays distinct' => ['plugin_*', 'currentComparisonKeys.5', "plugin_cache\t"],
    'row nine non ascii uppercase is not folded' => ['plugin_*', 'currentComparisonKeys.9', 'plugin_Éclair'],
    'row eight non ascii lowercase key preserved' => ['plugin_*', 'currentComparisonKeys.8', 'plugin_éclair'],
    'row two text decoded from utf16le' => ['plugin_*', 'currentTexts.2', 'Plugin_Cache   '],
    'row two next text decoded from utf16be' => ['plugin_*', 'nextTexts.2', 'Plugin_Cache'],
    'row one current encoding' => ['plugin_*', 'currentEncodings.1', 'UTF-8'],
    'row one next encoding' => ['plugin_*', 'nextEncodings.1', 'UTF-16BE'],
    'row seven encoding remains utf16le' => ['plugin_*', 'currentEncodings.7', 'UTF-16LE'],
    'row one current utf8 bytes' => ['plugin_*', 'currentBytesHex.1', '706c7567696e5f6361636865'],
    'row two current bytes include utf16le spaces' => ['plugin_*', 'currentBytesHex.2', '50006c007500670069006e005f0043006100630068006500200020002000'],
    'row two next bytes switch endian' => ['plugin_*', 'nextBytesHex.2', '0050006c007500670069006e005f00430061006300680065'],
    'current residual row one true' => ['plugin_*', 'currentResidualMatches.1', true],
    'current residual row two false from uppercase P' => ['plugin_*', 'currentResidualMatches.2', false],
    'current residual row three false from uppercase prefix' => ['plugin_*', 'currentResidualMatches.3', false],
    'current residual row four true after space trim candidate' => ['plugin_*', 'currentResidualMatches.4', true],
    'current residual row seven false from uppercase prefix' => ['plugin_*', 'currentResidualMatches.7', false],
    'next residual new lowercase row true' => ['plugin_*', 'nextResidualMatches.12', true],
    'next residual new uppercase row false' => ['plugin_*', 'nextResidualMatches.13', false],
    'current malformed rowids' => ['plugin_*', 'currentMalformedRowids', [11]],
    'next malformed rowids' => ['plugin_*', 'nextMalformedRowids', [14]],
    'current malformed error' => ['plugin_*', 'currentErrors.11', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['plugin_*', 'nextErrors.14', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'repaired malformed rowids' => ['plugin_*', 'repairedMalformedRowids', [11]],
    'newly malformed rowids' => ['plugin_*', 'newlyMalformedRowids', [14]],
    'retained comparison key changes include tab and trim repair' => ['plugin_*', 'retainedComparisonKeyChangedRowids', [4, 5]],
    'retained encoding changes include row one two five' => ['plugin_*', 'retainedEncodingChangedRowids', [1, 2, 5]],
    'retained bytes changes include source switched rows' => ['plugin_*', 'retainedBytesChangedRowids', [1, 2, 4, 5]],
    'cursor invalidated' => ['plugin_*', 'cursorInvalidated', true],
    'cursor not reusable' => ['plugin_*', 'cursorReusable', false],
    'reason source' => ['plugin_*', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['plugin_*', 'invalidationReasons.1', 'schema-cookie'],
    'reason collation version' => ['plugin_*', 'invalidationReasons.2', 'collation-version'],
    'reason malformed' => ['plugin_*', 'invalidationReasons.3', 'malformed-text'],
    'reason candidate rowset' => ['plugin_*', 'invalidationReasons.4', 'candidate-rowset'],
    'reason matched rowset' => ['plugin_*', 'invalidationReasons.5', 'matched-rowset'],
    'reason comparison key' => ['plugin_*', 'invalidationReasons.6', 'comparison-key'],
    'reason text encoding' => ['plugin_*', 'invalidationReasons.7', 'text-encoding'],
    'reason key bytes' => ['plugin_*', 'invalidationReasons.8', 'key-bytes'],
    'dependency expression index' => ['plugin_*', 'dependencies.0', 'sqlite-rtrim-expression-index'],
    'dependency nocase collation' => ['plugin_*', 'dependencies.1', 'sqlite-nocase-collation'],
    'dependency glob residual' => ['plugin_*', 'dependencies.2', 'sqlite-glob-binary-residual'],
    'dependency encoding cursor' => ['plugin_*', 'dependencies.3', 'sqlite-encoding-source-cursor'],
    'dependency current source nextOneThreeSix' => ['plugin_*', 'dependencies.4', 'sqlite-current-source-nextoneThreeSix'],
];

foreach ($cases as $name => [$pattern, $path, $expected]) {
    $tests['rtrim nocase glob current source nextOneThreeSix ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $pattern, $path, $expected): void {
        $t->same($expected, $valueAt($plan($pattern), $path));
    };
}

$tests['rtrim nocase glob current source nextOneThreeSix stable identical rows are reusable'] = static function (TestRunner $t) use ($row, $plan): void {
    $rows = [$row(1, 'plugin_cache ', 'UTF-16LE'), $row(2, 'Plugin_Cache', 'UTF-16BE')];
    $result = $plan('plugin_*', $rows, $rows, 'stable', 'stable', 7, 7, 9, 9);
    $t->same([1, 2], $result['currentCandidateRowids']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([2], $result['currentFalsePositiveRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['rtrim nocase glob current source nextOneThreeSix leading wildcard disables index candidates'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('*cache');
    $t->same(false, $result['indexUsable']);
    $t->same(null, $result['range']);
    $t->same([], $result['currentCandidateRowids']);
    $t->same([], $result['currentMatchedRowids']);
};

$tests['rtrim nocase glob current source nextOneThreeSix exact rtrim range keeps residual exact'] = static function (TestRunner $t) use ($row, $plan): void {
    $rows = [$row(1, 'plugin_cache', 'UTF-8'), $row(2, 'plugin_cache   ', 'UTF-16LE'), $row(3, 'Plugin_Cache', 'UTF-16BE')];
    $result = $plan('plugin_cache', $rows, $rows, 'stable', 'stable', 1, 1, 1, 1);
    $t->same([1, 2, 3], $result['currentCandidateRowids']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([2, 3], $result['currentFalsePositiveRowids']);
    $t->same(true, $result['currentResidualMatches'][1]);
    $t->same(false, $result['currentResidualMatches'][2]);
    $t->same(false, $result['currentResidualMatches'][3]);
};

$tests['rtrim nocase glob current source nextOneThreeSix rejects non integer option id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRtrimNocaseGlobCurrentSourceNextPlan::keyValueRowKeyExpressionPlan([['option_id' => '1', 'option_name_bytes' => 'x', 'text_encoding' => 1]], $nextRows, 'plugin_*'));
};

$tests['rtrim nocase glob current source nextOneThreeSix rejects missing option bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRtrimNocaseGlobCurrentSourceNextPlan::keyValueRowKeyExpressionPlan([['option_id' => 1, 'text_encoding' => 1]], $nextRows, 'plugin_*'));
};

$tests['rtrim nocase glob current source nextOneThreeSix rejects missing text encoding'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteRtrimNocaseGlobCurrentSourceNextPlan::keyValueRowKeyExpressionPlan([['option_id' => 1, 'option_name_bytes' => 'plugin_cache']], $nextRows, 'plugin_*'));
};

return $tests;
