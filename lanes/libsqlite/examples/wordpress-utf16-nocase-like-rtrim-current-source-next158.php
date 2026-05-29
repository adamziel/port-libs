<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$currentRows = [
    ['option_id' => 1, 'option_name_bytes' => $enc('Plugin_Cache  ', 2), 'text_encoding' => 2],
    ['option_id' => 2, 'option_name_bytes' => $enc('plugin_transient', 3), 'text_encoding' => 3],
    ['option_id' => 3, 'option_name_bytes' => $enc('siteurl', 2), 'text_encoding' => 2],
];

$nextRows = [
    ['option_id' => 1, 'option_name_bytes' => $enc('plugin_cache', 2), 'text_encoding' => 2],
    ['option_id' => 2, 'option_name_bytes' => $enc('PLUGIN_TRANSIENT  ', 3), 'text_encoding' => 3],
    ['option_id' => 4, 'option_name_bytes' => $enc('plugin_new_option  ', 3), 'text_encoding' => 3],
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameSourceDeltaPlan(
    $currentRows,
    $nextRows,
    'plugin!_%',
    '!',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentMatchedRowids'] === [1, 2]);
    assert($plan['nextMatchedRowids'] === [1, 4, 2]);
    assert(in_array('candidate-rowset', $plan['invalidationReasons'], true));
    assert($plan['dependency_closure'] !== '');
    echo "wordpress utf16 nocase like rtrim current source next158 smoke passed\n";
    exit(0);
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
