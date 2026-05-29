<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$row = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

$currentRows = [
    $row(1, 'plugin_cache  ', 'UTF-16LE'),
    $row(2, "plugin_cache\u{00a0}", 'UTF-16BE'),
    $row(3, 'plugin_cache_alpha', 'UTF-8'),
];
$nextRows = [
    $row(1, 'plugin_cache', 'UTF-16LE'),
    $row(2, "plugin_cache\u{00a0}", 'UTF-16BE'),
    $row(4, "PLUGIN_CACHE\u{3000}", 'UTF-16LE'),
    $row(3, 'plugin_cache_alpha', 'UTF-8'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameUnicodeSpaceRtrimPlan(
    $currentRows,
    $nextRows,
    'plugin!_cache%',
    SQLiteEncodingCollationSourceCursor::encodeText('!', 'UTF-16LE'),
    'UTF-16LE',
    3,
    1,
    'plugin_cache',
    'copied-wp-options@current',
    'copied-wp-options@next',
    228,
    229,
);

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    assert($plan['currentUnicodeSpaceMatchedRowids'] === [2]);
    assert($plan['nextUnicodeSpaceMatchedRowids'] === [2, 4]);
    assert($plan['currentAsciiSpaceTrimmedRowids'] === [1]);
    assert($plan['nextAsciiSpaceTrimmedRowids'] === []);
    assert(in_array('unicode-space-rowset', $plan['invalidationReasons'], true));
    echo "wordpress-utf16-nocase-like-rtrim-current-source-next229 self-test passed\n";
}

return [
    'scenario' => 'wordpress-utf16-nocase-like-rtrim-current-source-next229',
    'wordpressUse' => 'Copied wp_options scans keep UTF-16 non-ASCII whitespace distinct from SQLite RTRIM ASCII-space trimming while still invalidating a refreshed NOCASE LIKE cursor when visually similar option_name rows enter or leave the page.',
    'currentUnicodeSpaceMatchedRowids' => $plan['currentUnicodeSpaceMatchedRowids'],
    'nextUnicodeSpaceMatchedRowids' => $plan['nextUnicodeSpaceMatchedRowids'],
    'currentAsciiSpaceTrimmedRowids' => $plan['currentAsciiSpaceTrimmedRowids'],
    'nextAsciiSpaceTrimmedRowids' => $plan['nextAsciiSpaceTrimmedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
];
