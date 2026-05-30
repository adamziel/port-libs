<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};

$current = [
    $row(1, 'Plugin_Cache  ', 'UTF-16LE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', 'UTF-8'),
];
$next = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', 'UTF-8'),
    $row(4, 'PLUGIN_CACHE', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameDuplicateKeyReplayPlan(
    $current,
    $next,
    'plugin!_cache%',
    '!',
    ['key' => 'plugin_cache', 'rowid' => 2],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next171');
    assert($plan['duplicateRtrimNocaseKeys']['plugin_cache'] === [1, 2, 4]);
    assert($plan['changedEncodingRowids'] === [1]);
    assert($plan['changedBytesRowids'] === [1, 2]);
    assert($plan['mustReprepareBeforeReplay'] === true);
    echo "application-utf16-nocase-like-rtrim-current-source-next171 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'duplicateRtrimNocaseKeys' => $plan['duplicateRtrimNocaseKeys'],
    'replayInvalidationReasons' => $plan['replayInvalidationReasons'],
    'replayPlanMode' => $plan['replayPlanMode'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
