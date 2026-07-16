<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteMalformedLikeGlobSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteMalformedLikeGlobSourceNextPlan;

$row = static function (int $id, string $bytes, int|string $encoding, string $load_policy = 'yes'): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => is_int($encoding) ? $bytes : SQLiteEncodingCollationSourceCursor::encodeText($bytes, $encoding),
        'text_encoding' => is_int($encoding) ? $encoding : match (strtoupper($encoding)) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad fixture encoding'),
        },
        'load_policy' => $load_policy,
    ];
};

$plan = SQLiteMalformedLikeGlobSourceNextPlan::keyValueRowKeyCurrentNext(
    [
        $row(1, 'plugin_alpha', 'UTF-8'),
        $row(2, "plugin_\xc3", 1),
        $row(3, "plugin_\x3d\xd8", 2),
        $row(4, 'theme_alpha', 'UTF-8'),
    ],
    [
        $row(1, 'plugin_alpha', 'UTF-8'),
        $row(2, 'plugin_repaired', 'UTF-8'),
        $row(3, 'plugin_surrogate_fixed', 'UTF-16LE'),
        $row(4, 'theme_alpha', 'UTF-8'),
        $row(5, 'plugin_new', 'UTF-8'),
    ],
    'plugin%',
    'LIKE',
    'NOCASE',
    null,
    false,
    'main.app_settings@cookie88',
    'main.app_settings@cookie91',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['reprepareRequired'] === true);
    assert($plan['reprepareReasons'] === ['source-name', 'malformed-text', 'matched-rowset']);
    assert($plan['currentMalformedRowids'] === [2, 3]);
    assert($plan['repairedRowids'] === [2, 3]);
    assert($plan['nextRowids'] === [1, 5, 2, 3]);
    echo "application-setting-key-malformed-like-glob-source-next91 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
