<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreePageHeader;
use PortLibs\LibSqlite\SQLiteHeader;
use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTableLeafCell;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$varint = static function (int $value): string {
    if ($value <= 0x7f) {
        return chr($value);
    }
    $groups = [$value & 0x7f];
    $value >>= 7;
    while ($value > 0) {
        array_unshift($groups, 0x80 | ($value & 0x7f));
        $value >>= 7;
    }

    return implode('', array_map(chr(...), $groups));
};
$record = static function (array $values) use ($varint): string {
    $serialTypes = [];
    $body = '';
    foreach ($values as $value) {
        if (is_int($value)) {
            $serialTypes[] = $value === 1 ? 9 : 1;
            if ($value !== 1) {
                $body .= chr($value);
            }
        } else {
            $serialTypes[] = 13 + (strlen($value) * 2);
            $body .= $value;
        }
    }
    $header = implode('', array_map($varint, $serialTypes));

    return $varint(strlen($header) + 1) . $header . $body;
};

$page = str_repeat("\0", 512);
$page = substr_replace($page, "SQLite format 3\0", 0, 16);
$page = substr_replace($page, pack('n', 512), 16, 2);
$page[18] = "\x01";
$page[19] = "\x01";
$page = substr_replace($page, pack('N', 1), 28, 4);
$page = substr_replace($page, pack('N', 1), 56, 4);

$payload = $record(['table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer primary key, option_name text, option_value text)']);
$cell = $varint(strlen($payload)) . $varint(1) . $payload;
$offset = 512 - strlen($cell);
$page[100] = "\x0d";
$page = substr_replace($page, pack('n', 0), 101, 2);
$page = substr_replace($page, pack('n', 1), 103, 2);
$page = substr_replace($page, pack('n', $offset), 105, 2);
$page = substr_replace($page, pack('n', $offset), 108, 2);
$page = substr_replace($page, $cell, $offset, strlen($cell));

$header = SQLiteBTreePageHeader::parseFirstPage($page);
$schema = SQLiteSchemaRecord::fromTableLeafCell(SQLiteTableLeafCell::parsePageCells($page, $header)[0], SQLiteHeader::parse($page)->textEncoding);

echo json_encode([
    'name' => $schema->name,
    'rootPage' => $schema->rootPage,
    'isOptionsTable' => $schema->isTable('wp_options'),
], JSON_PRETTY_PRINT) . "\n";
