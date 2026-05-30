<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteRtrimNocaseGlobCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRtrimNocaseGlobCurrentSourceNextPlan;

$currentRows = [
    ['setting_id' => 1, 'key_name' => 'plugin_cache', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'Plugin_Cache', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'plugin_cache ', 'load_policy' => 'no'],
    ['setting_id' => 4, 'key_name' => "plugin_cache\t", 'load_policy' => 'no'],
    ['setting_id' => 5, 'key_name' => 'plugin_cache_extra', 'load_policy' => 'yes'],
    ['setting_id' => 6, 'key_name' => 'PLUGIN_cache_extra', 'load_policy' => 'yes'],
];

$nextRows = [
    ['setting_id' => 1, 'key_name' => 'plugin_cache', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'Plugin_Cache', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'plugin_cache  ', 'load_policy' => 'no'],
    ['setting_id' => 4, 'key_name' => "plugin_cache\t", 'load_policy' => 'no'],
    ['setting_id' => 5, 'key_name' => 'plugin_cache_extra', 'load_policy' => 'yes'],
    ['setting_id' => 6, 'key_name' => 'PLUGIN_cache_extra', 'load_policy' => 'yes'],
    ['setting_id' => 7, 'key_name' => 'plugin_cache_new', 'load_policy' => 'yes'],
];

$nocase = SQLiteRtrimNocaseGlobCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, $nextRows, 'plugin_*', 'NOCASE');
$rtrim = SQLiteRtrimNocaseGlobCurrentSourceNextPlan::keyValueRowKeyPlan($currentRows, $nextRows, 'plugin_cache', 'RTRIM');

if (($argv[1] ?? null) === '--self-test') {
    foreach ([
        'nocase candidates' => [$nocase['currentCandidateRowids'], [1, 2, 4, 3, 5, 6]],
        'nocase matched' => [$nocase['currentMatchedRowids'], [1, 4, 3, 5]],
        'nocase false positives' => [$nocase['currentFalsePositiveRowids'], [2, 6]],
        'nocase entered' => [$nocase['enteredMatchedRowids'], [7]],
        'rtrim candidates' => [$rtrim['currentCandidateRowids'], [1, 3, 4, 5]],
        'rtrim matched' => [$rtrim['currentMatchedRowids'], [1]],
        'rtrim false positives' => [$rtrim['currentFalsePositiveRowids'], [3, 4, 5]],
    ] as $name => [$actual, $expected]) {
        if ($actual !== $expected) {
            fwrite(STDERR, "{$name} mismatch\n");
            exit(1);
        }
    }
    echo "application-rtrim-nocase-glob-current-source-next119 self-test passed\n";
    return;
}

echo json_encode([
    'nocase' => [
        'pattern' => $nocase['pattern'],
        'candidateRowids' => $nocase['currentCandidateRowids'],
        'matchedRowids' => $nocase['currentMatchedRowids'],
        'falsePositiveRowids' => $nocase['currentFalsePositiveRowids'],
        'enteredMatchedRowids' => $nocase['enteredMatchedRowids'],
    ],
    'rtrim' => [
        'pattern' => $rtrim['pattern'],
        'candidateRowids' => $rtrim['currentCandidateRowids'],
        'matchedRowids' => $rtrim['currentMatchedRowids'],
        'falsePositiveRowids' => $rtrim['currentFalsePositiveRowids'],
    ],
], JSON_PRETTY_PRINT) . "\n";
