<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteMalformedLikeGlobSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteMalformedLikeGlobSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => $encoding === 'UTF-16LE' ? 2 : 3,
    ];
};

$current = [
    $row(1, 'plugin_alpha', 'UTF-16LE'),
    ['option_id' => 2, 'option_name_bytes' => "p\x00l\x00u\x00g\x00i\x00n\x00_\x00\x00\xd8", 'text_encoding' => 2],
    ['option_id' => 3, 'option_name_bytes' => "t\x00h\x00e\x00m\x00e\x00_\x00\x00\xd8", 'text_encoding' => 2],
];

$next = [
    $row(1, 'plugin_alpha', 'UTF-16BE'),
    $row(2, 'plugin_fixed', 'UTF-16LE'),
    ['option_id' => 4, 'option_name_bytes' => "p\x00l\x00u\x00g\x00i\x00n\x00_\x00\x00\xd8", 'text_encoding' => 2],
];

$plan = SQLiteMalformedLikeGlobSourceNextPlan::optionRowNameCurrentNext(
    $current,
    $next,
    'plugin%',
    'LIKE',
    'NOCASE',
    currentSource: 'main.wp_options@cookie116',
    nextSource: 'main.wp_options@cookie117',
);

printf("current malformed candidates: %s\n", implode(',', $plan['currentMalformedCandidateRowids']));
printf("next malformed candidates: %s\n", implode(',', $plan['nextMalformedCandidateRowids']));
printf("reprepare reasons: %s\n", implode(',', $plan['reprepareReasons']));
