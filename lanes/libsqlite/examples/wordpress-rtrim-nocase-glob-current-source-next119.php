<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteRtrimNocaseGlobCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteRtrimNocaseGlobCurrentSourceNextPlan;

$currentRows = [
    ['option_id' => 1, 'option_name' => 'plugin_cache', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_cache ', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => "plugin_cache\t", 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'plugin_cache_extra', 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => 'PLUGIN_cache_extra', 'autoload' => 'yes'],
];

$nextRows = [
    ['option_id' => 1, 'option_name' => 'plugin_cache', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'Plugin_Cache', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'plugin_cache  ', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => "plugin_cache\t", 'autoload' => 'no'],
    ['option_id' => 5, 'option_name' => 'plugin_cache_extra', 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => 'PLUGIN_cache_extra', 'autoload' => 'yes'],
    ['option_id' => 7, 'option_name' => 'plugin_cache_new', 'autoload' => 'yes'],
];

$nocase = SQLiteRtrimNocaseGlobCurrentSourceNextPlan::wordpressOptionNamePlan($currentRows, $nextRows, 'plugin_*', 'NOCASE');
$rtrim = SQLiteRtrimNocaseGlobCurrentSourceNextPlan::wordpressOptionNamePlan($currentRows, $nextRows, 'plugin_cache', 'RTRIM');

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
    echo "wordpress-rtrim-nocase-glob-current-source-next119 self-test passed\n";
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
