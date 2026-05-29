<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$current = [
    $row(1, 'plugin', 'UTF-16LE'),
    $row(2, 'Plugin  ', 'UTF-16BE'),
    $row(3, 'plugin_extra', 'UTF-8'),
];
$next = [
    $row(1, 'plugin', 'UTF-16BE'),
    $row(2, 'Plugin', 'UTF-16LE'),
    $row(3, 'plugin_extra', 'UTF-8'),
    $row(4, 'plugin!', 'UTF-16LE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameDanglingEscapePlan($current, $next);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-current-source-next187');
    assert($plan['patternEndsWithEscape'] === true);
    assert($plan['currentMatchedRowids'] === []);
    assert($plan['nextMatchedRowids'] === []);
    assert($plan['currentDanglingEscapeResidualMissRowids'] === [1, 2, 3]);
    assert($plan['nextDanglingEscapeResidualMissRowids'] === [1, 2, 3, 4]);
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next187 self-test passed\n";

    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
