<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteMalformedLikeGlobSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteMalformedLikeGlobSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad fixture encoding'),
        },
    ];
};

$currentRows = [
    $row(1, 'plugin_alpha', 'UTF-16LE'),
    ['setting_id' => 2, 'key_name_bytes' => "p\x00l\x00u\x00g\x00i\x00n\x00_", 'text_encoding' => 2],
    ['setting_id' => 3, 'key_name_bytes' => "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xd8\x3d", 'text_encoding' => 3],
    $row(4, 'theme_alpha', 'UTF-8'),
];

$nextRows = [
    $row(1, 'plugin_alpha', 'UTF-16LE'),
    $row(2, 'plugin_repaired', 'UTF-16BE'),
    ['setting_id' => 3, 'key_name_bytes' => "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xd8\x3d", 'text_encoding' => 3],
    $row(5, 'plugin_new', 'UTF-8'),
];

$plan = SQLiteMalformedLikeGlobSourceNextPlan::keyValueRowKeyCurrentNext(
    $currentRows,
    $nextRows,
    'plugin%',
    'LIKE',
    'NOCASE',
    null,
    false,
    'main.app_settings@cookie96',
    'main.app_settings@cookie97',
);

$output = [
    'scenario' => 'application malformed UTF-16 LIKE range current-source next97',
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'currentMalformedRowids' => $plan['currentMalformedRowids'],
    'nextMalformedRowids' => $plan['nextMalformedRowids'],
    'repairedRowids' => $plan['repairedRowids'],
    'newlyMalformedRowids' => $plan['newlyMalformedRowids'],
    'range' => $plan['currentRange'],
    'applicationUse' => 'Copied app_settings scans can advance a LIKE range over valid UTF-16 key_name index entries while quarantining odd-length and unpaired-surrogate UTF-16 text from the current/next source switch.',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($output['currentRowids'] !== [1] || $output['nextRowids'] !== [1, 5, 2]) {
        fwrite(STDERR, "unexpected current/next LIKE rowids\n");
        exit(1);
    }
    if ($output['repairedRowids'] !== [2] || $output['currentMalformedRowids'] !== [2, 3]) {
        fwrite(STDERR, "unexpected malformed UTF-16 repair evidence\n");
        exit(1);
    }
    echo "application-malformed-utf16-like-range-current-source-next97 self-test passed\n";
    exit(0);
}

echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
