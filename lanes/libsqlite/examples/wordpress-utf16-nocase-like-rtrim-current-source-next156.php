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

$plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameNoCasePlan(
    [
        $row(1, 'Plugin_Cache', 'UTF-16LE'),
        $row(2, 'plugin_cache  ', 'UTF-16BE'),
        $row(3, 'plugin_cache_extra', 'UTF-8'),
    ],
    [
        $row(1, 'Plugin_Cache', 'UTF-16BE'),
        $row(2, 'plugin_cache', 'UTF-16BE'),
        $row(4, 'PLUGIN_CACHE', 'UTF-16LE'),
    ],
    'plugin\\_cache',
);

echo json_encode([
    'status' => $plan['status'],
    'indexCollation' => $plan['indexCollation'],
    'residualCollation' => $plan['residualCollation'],
    'currentCandidates' => $plan['currentCandidateRowids'],
    'currentMatches' => $plan['currentMatchedRowids'],
    'nextMatches' => $plan['nextMatchedRowids'],
    'invalidated' => $plan['cursorInvalidated'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
