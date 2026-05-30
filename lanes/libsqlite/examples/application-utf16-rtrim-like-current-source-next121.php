<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16RtrimLikeCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding, string $autoload = 'yes'): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad encoding'),
        },
        'autoload' => $autoload,
    ];
};

$current = [
    $row(1, 'plugin_cache', 'UTF-16LE'),
    $row(2, 'plugin_cache ', 'UTF-16BE'),
    $row(3, "plugin_cache\t", 'UTF-16LE'),
    $row(4, 'Plugin_Cache', 'UTF-8'),
];

$next = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'plugin_cache ', 'UTF-16BE'),
    $row(4, 'Plugin_Cache', 'UTF-8'),
    $row(5, 'plugin_cache_new', 'UTF-16LE'),
];

$plan = SQLiteUtf16RtrimLikeCurrentSourceNextPlan::optionRowNamePlan(
    $current,
    $next,
    'plugin_cache%',
    null,
    true,
    'main.wp_options@120',
    'main.wp_options@121',
);

$summary = [
    'scenario' => 'application-utf16-rtrim-like-current-source-next121',
    'indexUsable' => $plan['indexUsable'],
    'rejectedReason' => $plan['rejectedReason'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'exitedRowids' => $plan['exitedRowids'],
    'changedEncodingRowids' => $plan['changedEncodingRowids'],
    'likeDoesNotTrimTrailingSpaces' => $plan['likeDoesNotTrimTrailingSpaces'],
    'applicationUse' => 'Copied wp_options option_name scans can fall back from an unusable RTRIM LIKE index range to a UTF-16 residual scan while preserving SQLite LIKE trailing-space semantics before import/copy diffing.',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['indexUsable'] === false);
    assert($summary['rejectedReason'] === 'case_sensitive_like_requires_binary_index');
    assert($summary['currentRowids'] === [1, 2, 3]);
    assert($summary['nextRowids'] === [1, 2, 5]);
    assert($summary['enteredRowids'] === [5]);
    assert($summary['exitedRowids'] === [3]);
    assert($summary['changedEncodingRowids'] === [1]);
    assert($summary['likeDoesNotTrimTrailingSpaces'] === true);
    echo "application-utf16-rtrim-like-current-source-next121 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
