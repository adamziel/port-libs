<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteLikeGlobCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteLikeGlobCurrentSourceNextPlan.php';

$encodingNumber = static fn (string $encoding): int => match ($encoding) {
    'UTF-8' => 1,
    'UTF-16LE' => 2,
    'UTF-16BE' => 3,
    default => throw new InvalidArgumentException('bad fixture encoding'),
};

$row = static function (int $id, string $name, string $encoding) use ($encodingNumber): array {
    return [
        'setting_id' => $id,
        'key_name' => $name,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => $encodingNumber($encoding),
    ];
};

$currentRows = [
    $row(1, 'module_%_cache', 'UTF-8'),
    $row(2, 'Module_Z_cache', 'UTF-16LE'),
    $row(3, 'module_abc_cache', 'UTF-16BE'),
    $row(4, 'module_%_cache_extra', 'UTF-16LE'),
];

$nextRows = [
    $row(1, 'module_%_cache', 'UTF-16LE'),
    $row(2, 'Module_Z_cache', 'UTF-16BE'),
    $row(3, 'module_abc_cache', 'UTF-16BE'),
    $row(4, 'module_%_cache_v2', 'UTF-8'),
    $row(5, 'module_%_cache_new', 'UTF-16BE'),
];

$like = [
    'source' => 'main.app_settings@schema147',
    'operator' => 'LIKE',
    'pattern' => 'module!_!%!_cache%',
    'collation' => 'NOCASE',
    'escape' => '!',
];
$glob = [
    'source' => 'main.app_settings@schema147',
    'operator' => 'GLOB',
    'pattern' => 'module_[A-z]*_cache*',
    'collation' => 'NOCASE',
];

$likePlan = SQLiteLikeGlobCurrentSourceNextPlan::keyValueRowKeyStatement($currentRows, $nextRows, $like, $like);
$globPlan = SQLiteLikeGlobCurrentSourceNextPlan::keyValueRowKeyStatement($currentRows, $nextRows, $glob, $glob);

$summary = [
    'scenario' => 'application-like-escape-glob-candidates-current-source-next147',
    'likeCandidateRowids' => $likePlan['current']['candidateRowids'],
    'likeMatchedRowids' => $likePlan['current']['rowids'],
    'likeEnteredRowids' => $likePlan['enteredRowids'],
    'likeCandidateChangedBytes' => $likePlan['candidateChangedBytesRowids'],
    'globCandidateRowids' => $globPlan['current']['candidateRowids'],
    'globMatchedRowids' => $globPlan['current']['rowids'],
    'globFalsePositiveRowids' => $globPlan['current']['falsePositiveRowids'],
    'globCandidateChangedEncodings' => $globPlan['candidateChangedEncodingRowids'],
    'dependencies' => $likePlan['dependencies'],
    'applicationUse' => 'Copied app_settings key_name LIKE ESCAPE and GLOB probes can keep index-range candidate rows separate from residual matches so source-cookie, encoding, and byte changes still reprepare stale cursors.',
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['likeCandidateRowids'] === [1, 4]);
    assert($summary['likeMatchedRowids'] === [1, 4]);
    assert($summary['likeEnteredRowids'] === [5]);
    assert($summary['likeCandidateChangedBytes'] === [1, 4]);
    assert($summary['globCandidateRowids'] === [1, 4, 3, 2]);
    assert($summary['globMatchedRowids'] === [3]);
    assert($summary['globFalsePositiveRowids'] === [1, 4, 2]);
    assert($summary['globCandidateChangedEncodings'] === [1, 2, 4]);
    assert($summary['dependencies'][3] === 'sqlite-like-glob-range-candidates');
    echo "application-like-escape-glob-candidates-current-source-next147 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
