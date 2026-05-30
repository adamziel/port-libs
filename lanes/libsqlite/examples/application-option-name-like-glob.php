<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteVarint;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$likePattern = $argv[2] ?? '\_transient\_%';
$globPattern = $argv[3] ?? '_Transient_[A-Z][A-Z][A-Z]';
$regexpPattern = $argv[4] ?? '^_(?:t|T)ransient_[[:alpha:]]+$';

if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-option-name-like-glob.php path/to/application.sqlite [like_pattern] [glob_pattern] [regexp_pattern]\n");
    fwrite(STDERR, "Use --self-test to run a small in-memory wp_options fixture.\n");
    exit(1);
}

$database = $databasePath === '--self-test'
    ? SQLiteDatabase::fromBytes(exampleOptionRowPatternFixture())
    : SQLiteDatabase::fromFile($databasePath);

$likeOptions = $database->optionRowsByNameLike($likePattern, '\\');
$likePlan = SQLiteDatabase::likePatternPlan($likePattern, '\\');
$unicodeLikePattern = 'plugin\_å%';
$unicodeLikeOptions = $database->optionRowsByNameLike($unicodeLikePattern, '\\');
$unicodeLikePlan = SQLiteDatabase::likePatternPlan($unicodeLikePattern, '\\');
$indexedLikeOptions = $database->optionRowsByIndexedNameLikePrefixRange($likePattern, '\\');
$upperCaseLikePattern = strtoupper($likePattern);
$indexedNoCaseLike = null;
$indexedNoCaseUpperCaseLike = null;
try {
    $indexedNoCaseLike = array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $database->optionRowsByIndexedNameLikePrefixRangeNoCase($likePattern, '\\'),
    );
    $indexedNoCaseUpperCaseLike = array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $database->optionRowsByIndexedNameLikePrefixRangeNoCase($upperCaseLikePattern, '\\'),
    );
} catch (InvalidArgumentException $exception) {
    $indexedNoCaseLike = ['error' => $exception->getMessage()];
    $indexedNoCaseUpperCaseLike = ['error' => $exception->getMessage()];
}
$globOptions = $database->optionRowsByNameGlob($globPattern);
$unicodeGlobOptions = $database->optionRowsByNameGlob('plugin_[À-ÿ]');
$unicodeGlobNegatedOptions = $database->optionRowsByNameGlob('plugin_[^À-ÿ]');
$indexedGlobOptions = null;
try {
    $indexedGlobOptions = array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $database->optionRowsByIndexedNameGlobPrefixRange($globPattern),
    );
} catch (InvalidArgumentException $exception) {
    $indexedGlobOptions = ['error' => $exception->getMessage()];
}
$globReversedRangeOptions = $database->optionRowsByNameGlob('plugin_[z-a]');
$regexp = static function (string $pattern, string $value): bool {
    $result = preg_match('/' . str_replace('/', '\\/', $pattern) . '/u', $value);
    if ($result === false) {
        throw new RuntimeException("Invalid REGEXP pattern: {$pattern}");
    }

    return $result === 1;
};
$regexpOptions = $database->optionRowsByNameRegexp($regexpPattern, $regexp);

echo json_encode([
    'path' => $databasePath,
    'likePattern' => $likePattern,
    'likePlan' => $likePlan,
    'likePrefixRange' => SQLiteDatabase::likePrefixRangeBounds($likePattern, '\\'),
    'likeNoCasePrefixRange' => SQLiteDatabase::likeNoCasePrefixRangeBounds($likePattern, '\\'),
    'unicodeLikePattern' => $unicodeLikePattern,
    'unicodeLikePlan' => $unicodeLikePlan,
    'unicodeLikeOptions' => array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $unicodeLikeOptions,
    ),
    'upperCaseLikePattern' => $upperCaseLikePattern,
    'globPattern' => $globPattern,
    'regexpPattern' => $regexpPattern,
    'likeOptions' => array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $likeOptions,
    ),
    'indexedLikeOptions' => array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $indexedLikeOptions,
    ),
    'indexedNoCaseLikeOptions' => $indexedNoCaseLike,
    'indexedNoCaseUpperCaseLikeOptions' => $indexedNoCaseUpperCaseLike,
    'globOptions' => array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $globOptions,
    ),
    'unicodeGlobPattern' => 'plugin_[À-ÿ]',
    'unicodeGlobOptions' => array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $unicodeGlobOptions,
    ),
    'unicodeGlobNegatedPattern' => 'plugin_[^À-ÿ]',
    'unicodeGlobNegatedOptions' => array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $unicodeGlobNegatedOptions,
    ),
    'globPrefixRange' => SQLiteDatabase::globPrefixRangeBounds($globPattern),
    'indexedGlobOptions' => $indexedGlobOptions,
    'globReversedRangePattern' => 'plugin_[z-a]',
    'globReversedRangeOptions' => array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $globReversedRangeOptions,
    ),
    'regexpOptions' => array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $regexpOptions,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

function exampleOptionRowPatternFixture(): string
{
    $pageSize = 4096;
    $varint = static fn (int $value): string => SQLiteVarint::encode($value);
    $recordPayload = static function (array $values) use ($varint): string {
        $serialTypes = [];
        $body = '';
        foreach ($values as $value) {
            if ($value === null) {
                $serialTypes[] = 0;
            } elseif (is_int($value)) {
                $serialTypes[] = $value === 1 ? 9 : 1;
                if ($value !== 1) {
                    $body .= pack('C', $value & 0xff);
                }
            } elseif (is_string($value)) {
                $serialTypes[] = 13 + (strlen($value) * 2);
                $body .= $value;
            } else {
                throw new RuntimeException('Unsupported example record value');
            }
        }

        $header = implode('', array_map($varint, $serialTypes));

        return $varint(strlen($header) + 1) . $header . $body;
    };
    $schemaCell = static function (array $values, int $rowId) use ($varint, $recordPayload): string {
        $payload = $recordPayload($values);

        return $varint(strlen($payload)) . $varint($rowId) . $payload;
    };
    $indexCell = static function (array $values) use ($varint, $recordPayload): string {
        $payload = $recordPayload($values);

        return $varint(strlen($payload)) . $payload;
    };
    $tableLeafPage = static function (array $cells, int $headerOffset = 0, ?string $basePage = null) use ($pageSize): string {
        $page = $basePage ?? str_repeat("\0", $pageSize);
        $offset = $pageSize;
        $pointers = [];
        foreach ($cells as $cell) {
            $offset -= strlen($cell);
            $page = substr_replace($page, $cell, $offset, strlen($cell));
            $pointers[] = $offset;
        }

        $page[$headerOffset] = "\x0d";
        $page = substr_replace($page, pack('n', 0), $headerOffset + 1, 2);
        $page = substr_replace($page, pack('n', count($cells)), $headerOffset + 3, 2);
        $page = substr_replace($page, pack('n', $pointers === [] ? $pageSize : min($pointers)), $headerOffset + 5, 2);
        $page[$headerOffset + 7] = "\x00";
        foreach ($pointers as $index => $pointer) {
            $page = substr_replace($page, pack('n', $pointer), $headerOffset + 8 + ($index * 2), 2);
        }

        return $page;
    };

    $page1 = str_repeat("\0", $pageSize);
    $page1 = substr_replace($page1, "SQLite format 3\0", 0, 16);
    $page1 = substr_replace($page1, pack('n', $pageSize), 16, 2);
    $page1[18] = "\x01";
    $page1[19] = "\x01";
    $page1[20] = "\x00";
    $page1[21] = "\x40";
    $page1[22] = "\x20";
    $page1[23] = "\x20";
    $page1 = substr_replace($page1, pack('N', 2), 28, 4);
    $page1 = substr_replace($page1, pack('N', 1), 56, 4);
    $page1 = $tableLeafPage([
        $schemaCell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text, autoload text)'], 1),
        $schemaCell(['index', 'wp_options_option_name', 'wp_options', 3, 'CREATE INDEX wp_options_option_name ON wp_options(option_name)'], 2),
        $schemaCell(['index', 'wp_options_option_name_nocase', 'wp_options', 4, 'CREATE INDEX wp_options_option_name_nocase ON wp_options(option_name COLLATE NOCASE)'], 3),
    ], 100, $page1);
    $page1 = substr_replace($page1, pack('N', 4), 28, 4);

    $optionCells = [
        $schemaCell([null, '_transient_feed', 'cached feed', 'no'], 1),
        $schemaCell([null, '_Transient_API', 'cached api', 'no'], 2),
        $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 3),
    ];
    for ($rowId = 4; $rowId <= 104; $rowId++) {
        $optionCells[] = $schemaCell([null, sprintf('filler_%03d', $rowId), 'skip', 'no'], $rowId);
    }
    $optionCells[] = $schemaCell([null, '_transient_late', 'late cached value', 'no'], 105);
    $optionCells[] = $schemaCell([null, 'plugin_z', 'reversed range start', 'no'], 106);
    $optionCells[] = $schemaCell([null, 'plugin_a', 'reversed range end excluded', 'no'], 107);
    $optionCells[] = $schemaCell([null, 'plugin_-', 'reversed range hyphen excluded', 'no'], 108);
    $optionCells[] = $schemaCell([null, 'plugin_å', 'unicode latin range payload', 'no'], 109);
    $optionCells[] = $schemaCell([null, 'plugin_β', 'unicode greek range payload', 'no'], 110);
    $optionCells[] = $schemaCell([null, 'plugin_Å', 'unicode latin uppercase payload', 'no'], 111);
    $optionCells[] = $schemaCell([null, 'plugin_%_literal', 'escaped wildcard payload', 'no'], 112);

    $page2 = $tableLeafPage($optionCells);
    $page3 = $tableLeafPage([
        $indexCell(['_Transient_API', 2]),
        $indexCell(['_transient_feed', 1]),
        $indexCell(['_transient_late', 105]),
        $indexCell(['plugin_-', 108]),
        $indexCell(['plugin_%_literal', 112]),
        $indexCell(['plugin_Å', 111]),
        $indexCell(['plugin_a', 107]),
        $indexCell(['plugin_z', 106]),
        $indexCell(['plugin_å', 109]),
        $indexCell(['plugin_β', 110]),
        $indexCell(['siteurl', 3]),
    ]);
    $page3[0] = "\x0a";
    $page4 = $tableLeafPage([
        $indexCell(['_Transient_API', 2]),
        $indexCell(['_transient_feed', 1]),
        $indexCell(['_transient_late', 105]),
        $indexCell(['plugin_-', 108]),
        $indexCell(['plugin_%_literal', 112]),
        $indexCell(['plugin_Å', 111]),
        $indexCell(['plugin_a', 107]),
        $indexCell(['plugin_z', 106]),
        $indexCell(['plugin_å', 109]),
        $indexCell(['plugin_β', 110]),
        $indexCell(['siteurl', 3]),
    ]);
    $page4[0] = "\x0a";

    return $page1 . $page2 . $page3 . $page4;
}
