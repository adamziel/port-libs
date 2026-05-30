<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16LikeEscapeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16LikeEscapeCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};

$current = [
    $row(1, 'Plugin_100%_Enabled', 'UTF-16LE'),
    $row(2, 'plugin_100%_enabled', 'UTF-16BE'),
    $row(3, 'plugin_100X_enabled', 'UTF-8'),
    $row(4, 'plugin_100%_enabled_extra', 'UTF-8'),
];

$next = [
    $row(1, 'Plugin_100%_Enabled', 'UTF-16BE'),
    $row(2, 'plugin_100%_enabled', 'UTF-16BE'),
    $row(3, 'plugin_100%_enabled', 'UTF-8'),
    $row(4, 'plugin_100%_enabled_extra', 'UTF-8'),
    $row(5, 'plugin_100%_enabled_new', 'UTF-16LE'),
];

$plan = SQLiteUtf16LikeEscapeCurrentSourceNextPlan::optionRowNameLikeEscape(
    $current,
    $next,
    'plugin\\_100\\%\\_enabled%',
    '\\',
    'NOCASE',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['prefix'] === 'plugin_100%_enabled');
    assert($plan['range']['upperBound'] === 'plugin_100%_enablee');
    assert($plan['currentMatchedRowids'] === [1, 2, 4]);
    assert($plan['nextMatchedRowids'] === [1, 2, 3, 4, 5]);
    assert(in_array('matched-rowset', $plan['invalidationReasons'], true));
    echo "application-utf16-like-escape-current-source-next143 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
