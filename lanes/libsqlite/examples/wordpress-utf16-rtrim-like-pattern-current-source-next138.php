<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan;

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
    $row(1, 'plugin_cache', 'UTF-16LE'),
    $row(2, 'plugin_cache ', 'UTF-16BE'),
    $row(3, 'plugin_%literal', 'UTF-16LE'),
    $row(4, 'theme_cache', 'UTF-16LE'),
];

$next = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'plugin_cache ', 'UTF-16BE'),
    $row(3, 'plugin_%literal', 'UTF-16BE'),
    $row(5, 'plugin_cache_new', 'UTF-16LE'),
];

$plan = SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::wordpressOptionNamePlan(
    $current,
    $next,
    SQLiteEncodingCollationSourceCursor::encodeText('plugin_cache%', 'UTF-16LE'),
    'UTF-16LE',
    null,
    null,
    true,
    'main.wp_options@137',
    'main.wp_options@138',
);

$literal = SQLiteUtf16RtrimLikePatternCurrentSourceNextPlan::wordpressOptionNamePlan(
    $current,
    $next,
    SQLiteEncodingCollationSourceCursor::encodeText('plugin_!%literal', 'UTF-16BE'),
    'UTF-16BE',
    SQLiteEncodingCollationSourceCursor::encodeText('!', 'UTF-16BE'),
    'UTF-16BE',
);

$summary = [
    'scenario' => 'wordpress-utf16-rtrim-like-pattern-current-source-next138',
    'decodedPattern' => $plan['decodedPattern'],
    'indexUsable' => $plan['indexUsable'],
    'rejectedReason' => $plan['rejectedReason'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'changedEncodingRowids' => $plan['changedEncodingRowids'],
    'literalPercentRowids' => $literal['currentRowids'],
    'literalEscapeEncoding' => $literal['escapeEncoding'],
    'wordpressUse' => 'Copied wp_options option_name scans can decode UTF-16 LIKE patterns before applying SQLite RTRIM-collated full-scan residual matching, including escaped wildcard imports.',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['decodedPattern'] === 'plugin_cache%');
    assert($summary['indexUsable'] === false);
    assert($summary['rejectedReason'] === 'case_sensitive_like_requires_binary_index');
    assert($summary['currentRowids'] === [1, 2]);
    assert($summary['nextRowids'] === [1, 2, 5]);
    assert($summary['enteredRowids'] === [5]);
    assert($summary['changedEncodingRowids'] === [1, 3]);
    assert($summary['literalPercentRowids'] === [3]);
    assert($summary['literalEscapeEncoding'] === 'UTF-16BE');
    echo "wordpress-utf16-rtrim-like-pattern-current-source-next138 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
