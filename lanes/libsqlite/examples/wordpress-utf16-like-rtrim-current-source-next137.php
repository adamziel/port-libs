<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16LikeRtrimCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16LikeRtrimCurrentSourceNextPlan.php';

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

$current = [
    $row(1, 'plugin_cache', 'UTF-16LE'),
    $row(2, 'plugin_cache ', 'UTF-16BE'),
    $row(3, "plugin_cache\t", 'UTF-16LE'),
    $row(4, 'Plugin_Cache', 'UTF-16BE'),
];

$next = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'plugin_cache', 'UTF-16LE'),
    $row(3, "plugin_cache\t", 'UTF-16LE'),
    $row(5, 'plugin_cache_new', 'UTF-16LE'),
];

$plan = SQLiteUtf16LikeRtrimCurrentSourceNextPlan::wordpressOptionNamePlan(
    $current,
    $next,
    'plugin_cache',
    null,
    true,
    'main.wp_options@current',
    'main.wp_options@next',
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['indexUsable'] === false);
    assert($plan['candidateSource'] === 'full-scan');
    assert($plan['currentRowids'] === [1]);
    assert($plan['nextRowids'] === [1, 2]);
    assert($plan['currentResidualRejectedRowids'] === [4, 2, 3]);
    assert(in_array('matched-rowset', $plan['invalidationReasons'], true));
    echo "wordpress-utf16-like-rtrim-current-source-next137 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-utf16-like-rtrim-current-source-next137',
    'wordpressUse' => 'Copied wp_options option-name scans can retain SQLite LIKE residual semantics over UTF-16 text while RTRIM comparison keys sort candidate rows and current/next invalidation tracks endian and trailing-space repairs.',
    'pattern' => $plan['pattern'],
    'indexUsable' => $plan['indexUsable'],
    'candidateSource' => $plan['candidateSource'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'currentResidualRejectedRowids' => $plan['currentResidualRejectedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
