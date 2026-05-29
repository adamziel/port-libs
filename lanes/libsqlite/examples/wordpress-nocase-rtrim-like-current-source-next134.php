<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteNocaseRtrimLikeCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteNocaseRtrimLikeCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad encoding'),
        },
    ];
};

$currentRows = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache ', 'UTF-16BE'),
    $row(3, 'PLUGIN_CACHE  ', 'UTF-8'),
    $row(4, "plugin_cache\t", 'UTF-16LE'),
    $row(5, 'theme_cache', 'UTF-8'),
];

$nextRows = [
    $row(1, 'Plugin_Cache ', 'UTF-16BE'),
    $row(2, 'plugin_cache ', 'UTF-16LE'),
    $row(3, 'PLUGIN_CACHE', 'UTF-8'),
    $row(4, "plugin_cache\t", 'UTF-16LE'),
    $row(6, 'Plugin_New ', 'UTF-16LE'),
];

$plan = SQLiteNocaseRtrimLikeCurrentSourceNextPlan::wordpressOptionNamePlan(
    $currentRows,
    $nextRows,
    'plugin_%',
    'plugin_%',
    'NOCASE',
    'RTRIM',
    null,
    null,
    false,
    'main.wp_options@133',
    'main.wp_options@134',
);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['currentIndexUsable'] !== true) {
        throw new RuntimeException('NOCASE LIKE range should be usable');
    }
    if ($plan['nextIndexUsable'] !== false || $plan['nextRejectedReason'] !== 'default_like_requires_nocase_index') {
        throw new RuntimeException('RTRIM LIKE should force residual full scan for default LIKE');
    }
    if ($plan['currentRowids'] !== [1, 4, 2, 3]) {
        throw new RuntimeException('Unexpected current rowids');
    }
    if ($plan['nextRowids'] !== [3, 1, 6, 2, 4]) {
        throw new RuntimeException('Unexpected next rowids');
    }
    if (!in_array('collation-switch', $plan['invalidationReasons'], true) || !in_array('full-scan-rtrim-like', $plan['invalidationReasons'], true)) {
        throw new RuntimeException('Missing current-source invalidation reasons');
    }
    echo "wordpress-nocase-rtrim-like-current-source-next134 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-nocase-rtrim-like-current-source-next134',
    'wordpressUse' => 'Copied wp_options scans can invalidate a stale NOCASE LIKE prefix cursor when the next source is rebuilt under RTRIM collation, falling back to a residual LIKE scan without trimming trailing spaces.',
    'currentIndexUsable' => $plan['currentIndexUsable'],
    'nextIndexUsable' => $plan['nextIndexUsable'],
    'nextRejectedReason' => $plan['nextRejectedReason'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'retainedEncodingChangedRowids' => $plan['retainedEncodingChangedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
