<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$row = static function (int $id, string $name, int $encoding): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => $encoding,
    ];
};

$current = [
    $row(1, 'Plugin_Cache', 2),
    $row(2, 'plugin_cache_alpha', 3),
    $row(3, 'plugin_cache_beta', 2),
];
$next = [
    $row(1, 'Plugin_Cache', 2),
    $row(2, 'plugin_cache_alpha  ', 3),
    $row(3, 'plugin_cache_beta', 2),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameTokenFingerprintPlan(
    $current,
    $next,
    'plugin!_cache%',
    '!',
    [
        'key' => 'plugin_cache_alpha',
        'rowid' => 2,
        'bytesHex' => bin2hex(SQLiteEncodingCollationSourceCursor::encodeText('plugin_cache_alpha', 3)),
        'encoding' => 'UTF-16BE',
    ],
    'stable',
    'stable',
    175,
    175,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next175');
    assert($plan['tokenFingerprintReasons'] === ['yielded-token-bytes-changed', 'current-next-token-bytes-changed']);
    assert($plan['mustReprepareBeforeReplay'] === true);
    assert($plan['safeToReplayFromToken'] === false);
    echo "wordpress-utf16-nocase-like-rtrim-token-current-source-next175 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'nextTokenFingerprint' => $plan['nextTokenFingerprint'],
    'tokenFingerprintReasons' => $plan['tokenFingerprintReasons'],
    'replayInvalidationReasons' => $plan['replayInvalidationReasons'],
    'replayPlanMode' => $plan['replayPlanMode'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
