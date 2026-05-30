<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteMalformedLikeGlobSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteMalformedLikeGlobSourceNextPlan;

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

$plan = SQLiteMalformedLikeGlobSourceNextPlan::optionRowNameCurrentNext(
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
    'main.wp_options@cookie88',
    'main.wp_options@cookie91',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['reprepareRequired'] === true);
    assert($plan['reprepareReasons'] === ['source-name', 'malformed-text', 'matched-rowset']);
    assert($plan['currentMalformedRowids'] === [2, 3]);
    assert($plan['repairedRowids'] === [2, 3]);
    assert($plan['nextRowids'] === [1, 5, 2, 3]);
    echo "application-option-name-malformed-like-glob-source-next91 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
