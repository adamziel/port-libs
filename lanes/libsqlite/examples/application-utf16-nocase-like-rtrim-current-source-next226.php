<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

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

$decomposed = "e\xcc\x81";
$composed = "\xc3\xa9";

$current = [
    $row(1, 'plugin_caf' . $composed, 'UTF-16LE'),
    $row(2, 'Plugin_Caf' . $composed . '  ', 'UTF-16BE'),
    $row(3, 'plugin_caf' . $decomposed, 'UTF-16LE'),
];
$next = [
    $row(1, 'plugin_caf' . $decomposed, 'UTF-16BE'),
    $row(2, 'Plugin_Caf' . $composed, 'UTF-16LE'),
    $row(4, 'PLUGIN_CAF' . $composed, 'UTF-16BE'),
];

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameCombiningMarkPlan($current, $next);

$payload = [
    'status' => $plan['status'],
    'pattern' => $plan['pattern'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'currentNormalizationTrapRowids' => $plan['currentNormalizationTrapRowids'],
    'nextNormalizationTrapRowids' => $plan['nextNormalizationTrapRowids'],
    'combiningMarkRemainsSeparateLikeCharacter' => $plan['combiningMarkRemainsSeparateLikeCharacter'],
    'unicodeNormalizationIsNotApplied' => $plan['unicodeNormalizationIsNotApplied'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
    'applicationUse' => 'Copied wp_options scans keep SQLite-compatible UTF-16 LIKE semantics: composed and decomposed accents are not normalized, a combining mark remains its own LIKE character, and NOCASE/RTRIM stay ASCII scoped.',
];

if (($argv[1] ?? null) === '--self-test') {
    assert($payload['status'] === 'utf16-nocase-like-rtrim-current-source-next226');
    assert($payload['pattern'] === 'plugin_caf_');
    assert($payload['currentMatchedRowids'] === [1, 2]);
    assert($payload['nextMatchedRowids'] === [2, 4]);
    assert($payload['currentNormalizationTrapRowids'] === [3]);
    assert($payload['nextNormalizationTrapRowids'] === [1]);
    assert($payload['combiningMarkRemainsSeparateLikeCharacter'] === true);
    assert($payload['unicodeNormalizationIsNotApplied'] === true);
    assert(in_array('unicode-normalization-not-applied', $payload['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-current-source-next226 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
