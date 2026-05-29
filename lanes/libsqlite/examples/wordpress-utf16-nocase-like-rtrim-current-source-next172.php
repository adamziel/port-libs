<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNext160Plan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';

$enc = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$code = static fn (string $encoding): int => match ($encoding) {
    'UTF-8' => 1,
    'UTF-16LE' => 2,
    'UTF-16BE' => 3,
};
$row = static function (int $id, string $name, string $encoding) use ($enc, $code): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => $enc($name, $encoding),
        'text_encoding' => $code($encoding),
    ];
};

$currentRows = [
    $row(1, 'plugin_cache', 'UTF-16LE'),
    $row(2, 'plugin_cache_alpha', 'UTF-16BE'),
    $row(3, 'plugin_cache_beta', 'UTF-16LE'),
];
$nextRows = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'plugin_cache_zulu', 'UTF-16LE'),
    $row(3, 'plugin_cache_beta', 'UTF-16LE'),
    $row(4, 'plugin_cache_gamma', 'UTF-16BE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameYieldTokenPlan(
    $currentRows,
    $nextRows,
    $enc('plugin\\_cache%', 'UTF-16LE'),
    $code('UTF-16LE'),
    $enc('plugin\\_cache%', 'UTF-16BE'),
    $code('UTF-16BE'),
    $enc('\\', 'UTF-16LE'),
    $code('UTF-16LE'),
    $enc('\\', 'UTF-16BE'),
    $code('UTF-16BE'),
    ['key' => 'plugin_cache_alpha', 'rowid' => 2],
    'stable',
    'stable',
    172,
    172,
);

$summary = [
    'scenario' => 'wordpress-utf16-nocase-like-rtrim-current-source-next172',
    'yieldedReenteredAfterToken' => $plan['yieldedReenteredAfterToken'],
    'yieldTokenReasons' => $plan['yieldTokenReasons'],
    'safeToResumeFromToken' => $plan['safeToResumeFromToken'],
    'resumePlanMode' => $plan['resumePlanMode'],
    'resumePlanRowids' => $plan['resumePlanRowids'],
    'wordpressUse' => 'Copied wp_options scans can avoid yielding an already returned option row twice when a UTF-16 RTRIM/NOCASE LIKE key moves after the saved cursor token between current and next sources.',
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['yieldedReenteredAfterToken'] === true);
    assert($summary['yieldTokenReasons'] === ['yielded-token-reentered-after-token', 'yielded-key-changed']);
    assert($summary['safeToResumeFromToken'] === false);
    assert($summary['resumePlanMode'] === 'reprepare-from-range-start');
    assert($summary['resumePlanRowids'] === [1, 3, 4, 2]);
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next172 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
