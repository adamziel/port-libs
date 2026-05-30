<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';

$enc = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$code = static fn (int|string $encoding): int => match ($encoding) {
    1, 'UTF-8' => 1,
    2, 'UTF-16LE' => 2,
    3, 'UTF-16BE' => 3,
};
$row = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $code($encoding),
];

$current = [
    $row(1, 'Plugin_Cache  ', 'UTF-16LE'),
    $row(2, 'plugin_cache_alpha', 'UTF-16BE'),
    $row(3, 'plugin_cache_beta', 'UTF-8'),
];
$next = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache_alpha', 'UTF-16BE'),
    $row(3, 'plugin_cache_beta  ', 'UTF-8'),
    $row(4, 'PLUGIN_CACHE_GAMMA', 'UTF-16LE'),
];
$tokenBytes = $enc('Plugin_Cache  ', 'UTF-16LE');

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameCanonicalTokenPlan(
    $current,
    $next,
    'plugin!_cache%',
    '!',
    [
        'key' => 'Plugin_Cache  ',
        'rowid' => 1,
        'bytesHex' => bin2hex($tokenBytes),
        'encoding' => 'UTF-16LE',
        'keyBytes' => $tokenBytes,
        'keyEncoding' => 'UTF-16LE',
    ],
);

$payload = [
    'scenario' => 'application-utf16-nocase-like-rtrim-current-source-next178',
    'applicationUse' => 'Copied wp_options scans can canonicalize a yielded UTF-16 option_name token through RTRIM and ASCII NOCASE before replaying a LIKE cursor across current-source changes.',
    'status' => $plan['status'],
    'normalizedLastYielded' => $plan['normalizedLastYielded'],
    'tokenNormalizationReasons' => $plan['tokenNormalizationReasons'],
    'replayInvalidationReasons' => $plan['replayInvalidationReasons'],
    'replayPlanRowids' => $plan['replayPlanRowids'],
    'dependency_closure' => $plan['dependency_closure'],
    'non_overlap' => $plan['non_overlap'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($payload['status'] === 'utf16-nocase-like-rtrim-current-source-next178');
    assert($payload['normalizedLastYielded']['key'] === 'plugin_cache');
    assert($payload['tokenNormalizationReasons'] === ['token-key-not-canonical']);
    assert($payload['replayPlanRowids'] === [1, 2, 3, 4]);
    echo "application-utf16-nocase-like-rtrim-current-source-next178 self-test passed\n";
}

return $payload;
