<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteVarint;
use PortLibs\LibSqlite\SQLiteWordPressOption;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$likePattern = $argv[2] ?? '\_transient\_%';
$globPattern = $argv[3] ?? '_Transient_[A-Z][A-Z][A-Z]';

if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/wordpress-option-name-like-glob.php path/to/wordpress.sqlite [like_pattern] [glob_pattern]\n");
    fwrite(STDERR, "Use --self-test to run a small in-memory wp_options fixture.\n");
    exit(1);
}

$database = $databasePath === '--self-test'
    ? SQLiteDatabase::fromBytes(exampleWordPressOptionPatternFixture())
    : SQLiteDatabase::fromFile($databasePath);

$likeOptions = $database->wordpressOptionsByNameLike($likePattern, '\\');
$globOptions = $database->wordpressOptionsByNameGlob($globPattern);

echo json_encode([
    'path' => $databasePath,
    'likePattern' => $likePattern,
    'globPattern' => $globPattern,
    'likeOptions' => array_map(
        static fn (SQLiteWordPressOption $option): array => $option->toArray(),
        $likeOptions,
    ),
    'globOptions' => array_map(
        static fn (SQLiteWordPressOption $option): array => $option->toArray(),
        $globOptions,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

function exampleWordPressOptionPatternFixture(): string
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
    ], 100, $page1);

    $optionCells = [
        $schemaCell([null, '_transient_feed', 'cached feed', 'no'], 1),
        $schemaCell([null, '_Transient_API', 'cached api', 'no'], 2),
        $schemaCell([null, 'siteurl', 'https://example.test', 'yes'], 3),
    ];
    for ($rowId = 4; $rowId <= 104; $rowId++) {
        $optionCells[] = $schemaCell([null, sprintf('filler_%03d', $rowId), 'skip', 'no'], $rowId);
    }
    $optionCells[] = $schemaCell([null, '_transient_late', 'late cached value', 'no'], 105);

    $page2 = $tableLeafPage($optionCells);

    return $page1 . $page2;
}
