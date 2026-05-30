<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDatabase;
use PortLibs\LibSqlite\SQLiteVarint;
use PortLibs\LibSqlite\SQLiteOptionRow;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$databasePath = $argv[1] ?? null;
$lookupName = $argv[2] ?? 'cache_token';
$upperInclusive = in_array('--inclusive', $argv, true);

if ($databasePath === null) {
    fwrite(STDERR, "Usage: php lanes/libsqlite/examples/application-rtrim-collation-option-lookup.php path/to/application.sqlite [lookup_name] [--inclusive]\n");
    fwrite(STDERR, "Use --self-test to run a small in-memory wp_options fixture.\n");
    exit(1);
}

$database = $databasePath === '--self-test'
    ? SQLiteDatabase::fromBytes(exampleApplicationRtrimCollationFixture())
    : SQLiteDatabase::fromFile($databasePath);

$option = $database->optionRowByIndexedName($lookupName);
$rangeOptions = $database->optionRowsByIndexedNameRange($lookupName, $lookupName, null, $upperInclusive);

echo json_encode([
    'path' => $databasePath,
    'lookupName' => $lookupName,
    'upperInclusive' => $upperInclusive,
    'option' => $option?->toArray(),
    'rangeOptions' => array_map(
        static fn (SQLiteOptionRow $option): array => $option->toArray(),
        $rangeOptions,
    ),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

function exampleApplicationRtrimCollationFixture(): string
{
    $pageSize = 512;
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
    $cell = static function (array $values, int $rowId, bool $table) use ($varint, $recordPayload): string {
        $payload = $recordPayload($values);

        return $table ? $varint(strlen($payload)) . $varint($rowId) . $payload : $varint(strlen($payload)) . $payload;
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
    $indexLeafPage = static function (array $cells) use ($pageSize): string {
        $page = str_repeat("\0", $pageSize);
        $offset = $pageSize;
        $pointers = [];
        foreach ($cells as $cell) {
            $offset -= strlen($cell);
            $page = substr_replace($page, $cell, $offset, strlen($cell));
            $pointers[] = $offset;
        }

        $page[0] = "\x0a";
        $page = substr_replace($page, pack('n', 0), 1, 2);
        $page = substr_replace($page, pack('n', count($cells)), 3, 2);
        $page = substr_replace($page, pack('n', $pointers === [] ? $pageSize : min($pointers)), 5, 2);
        $page[7] = "\x00";
        foreach ($pointers as $index => $pointer) {
            $page = substr_replace($page, pack('n', $pointer), 8 + ($index * 2), 2);
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
    $page1 = substr_replace($page1, pack('N', 3), 28, 4);
    $page1 = substr_replace($page1, pack('N', 1), 56, 4);
    $page1 = $tableLeafPage([
        $cell(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text COLLATE RTRIM UNIQUE, option_value text, autoload text)'], 1, true),
        $cell(['index', 'sqlite_autoindex_wp_options_1', 'wp_options', 3, null], 2, true),
    ], 100, $page1);

    $page2 = $tableLeafPage([
        $cell([null, 'cache_token  ', 'padded option key', 'no'], 1, true),
        $cell([null, 'siteurl', 'https://example.test', 'yes'], 2, true),
    ]);
    $page3 = $indexLeafPage([
        $cell(['cache_token  ', 1], 0, false),
        $cell(['siteurl', 2], 0, false),
    ]);

    return $page1 . $page2 . $page3;
}
