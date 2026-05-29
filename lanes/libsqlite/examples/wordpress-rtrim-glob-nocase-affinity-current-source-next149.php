<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteRtrimGlobNocaseAffinityCurrentSourceNextPlan;

$code = static fn (string $encoding): int => match ($encoding) {
    'UTF-8' => 1,
    'UTF-16LE' => 2,
    'UTF-16BE' => 3,
};

$row = static function (int $id, string $name, string $value, string $nameEncoding, string $valueEncoding) use ($code): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $nameEncoding),
        'option_value_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($value, $valueEncoding),
        'name_text_encoding' => $code($nameEncoding),
        'value_text_encoding' => $code($valueEncoding),
    ];
};

$current = [
    $row(1, 'plugin_cache', '10', 'UTF-8', 'UTF-8'),
    $row(2, 'Plugin_Cache', '11', 'UTF-16LE', 'UTF-16BE'),
    $row(3, 'plugin-cache', '12', 'UTF-16BE', 'UTF-8'),
    $row(4, 'plugin:cache', '13', 'UTF-8', 'UTF-16BE'),
];

$next = [
    $row(1, 'plugin_cache ', '10.0', 'UTF-16BE', 'UTF-16LE'),
    $row(3, 'plugin-cache', '12', 'UTF-16BE', 'UTF-8'),
    $row(5, 'plugin-cache-new', '14', 'UTF-16LE', 'UTF-8'),
];

$plan = SQLiteRtrimGlobNocaseAffinityCurrentSourceNextPlan::wordpressOptionNameValuePlan(
    $current,
    $next,
    'plugin[-_]cache*',
    10,
    14,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentCandidateRowids'] === [1, 2, 3, 4]);
    assert($plan['currentMatchedRowids'] === [1, 3]);
    assert($plan['nextAffinityMatchedRowids'] === [1, 3, 5]);
    assert($plan['globCharacterClasses'][0]['raw'] === '[-_]');
    assert(in_array('glob-character-class-residual', $plan['invalidationReasons'], true));
    echo "wordpress-rtrim-glob-nocase-affinity-current-source-next149 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-rtrim-glob-nocase-affinity-current-source-next149',
    'wordpressUse' => 'Copied wp_options import scans can use an RTRIM+NOCASE option_name key to select candidates while bytewise GLOB character classes and NUMERIC option_value affinity decide the final current-source next149 rowset.',
    'pattern' => $plan['pattern'],
    'range' => $plan['range'],
    'globCharacterClasses' => $plan['globCharacterClasses'],
    'currentCandidateRowids' => $plan['currentCandidateRowids'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextAffinityMatchedRowids' => $plan['nextAffinityMatchedRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
