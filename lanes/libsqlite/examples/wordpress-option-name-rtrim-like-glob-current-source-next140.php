<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteEncodingLikeGlobRtrimCurrentSourceNextPlan;

$encodingNumber = static fn (string $encoding): int => match ($encoding) {
    'UTF-8' => 1,
    'UTF-16LE' => 2,
    'UTF-16BE' => 3,
};

$row = static function (int $id, string $name, string $encoding) use ($encodingNumber): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => $encodingNumber($encoding),
        'autoload' => 'yes',
    ];
};

$current = [
    $row(1, 'plugin_cache', 'UTF-8'),
    $row(2, 'plugin_cache  ', 'UTF-16LE'),
    $row(3, "plugin_cache\t", 'UTF-16BE'),
    $row(4, 'plugin_cache_extra', 'UTF-8'),
    $row(5, 'theme_cache', 'UTF-8'),
];

$next = [
    $row(1, 'plugin_cache ', 'UTF-16BE'),
    $row(2, 'plugin_cache', 'UTF-16LE'),
    $row(3, "plugin_cache\t", 'UTF-16BE'),
    $row(4, 'plugin_cache_extra_v2', 'UTF-8'),
    $row(6, 'plugin_cache_new', 'UTF-8'),
];

$like = SQLiteEncodingLikeGlobRtrimCurrentSourceNextPlan::wordpressOptionNamePlan(
    $current,
    $next,
    'LIKE',
    'plugin\_cache',
    '\\',
);
$glob = SQLiteEncodingLikeGlobRtrimCurrentSourceNextPlan::wordpressOptionNamePlan(
    $current,
    $next,
    'GLOB',
    'plugin_cache*',
);

$summary = [
    'scenario' => 'wordpress-option-name-rtrim-like-glob-current-source-next140',
    'likeCandidateRowids' => $like['currentCandidateRowids'],
    'likeMatchedRowids' => $like['currentMatchedRowids'],
    'likeFalsePositiveRowids' => $like['currentFalsePositiveRowids'],
    'likeNextMatchedRowids' => $like['nextMatchedRowids'],
    'globNextMatchedRowids' => $glob['nextMatchedRowids'],
    'changedEncodingRowids' => $like['changedEncodingRowids'],
    'invalidationReasons' => $like['invalidationReasons'],
    'wordpressUse' => 'Copied wp_options option_name scans can reuse an RTRIM expression-index range while still applying LIKE/GLOB residual matching to the original untrimmed decoded text.',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['likeCandidateRowids'] === [1, 2, 3, 4]);
    assert($summary['likeMatchedRowids'] === [1]);
    assert($summary['likeFalsePositiveRowids'] === [2, 3, 4]);
    assert($summary['likeNextMatchedRowids'] === [2]);
    assert($summary['globNextMatchedRowids'] === [1, 2, 3, 4, 6]);
    assert($summary['changedEncodingRowids'] === [1]);
    assert(in_array('matched-rowset', $summary['invalidationReasons'], true));
    echo "wordpress-option-name-rtrim-like-glob-current-source-next140 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
