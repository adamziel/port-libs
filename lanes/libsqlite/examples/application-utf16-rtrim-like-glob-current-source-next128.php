<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16RtrimLikeGlobCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};

$current = [
    $row(1, 'module_cache', 'UTF-16LE'),
    $row(2, 'module_cache ', 'UTF-16BE'),
    $row(3, "module_cache\t", 'UTF-16LE'),
    $row(4, 'module_cache_extra', 'UTF-16BE'),
];

$next = [
    $row(1, 'module_cache', 'UTF-16BE'),
    $row(2, 'module_cache ', 'UTF-16BE'),
    $row(3, 'module_cache', 'UTF-16LE'),
    $row(5, 'module_cache_new', 'UTF-16LE'),
    $row(4, 'module_cache_extra_v2', 'UTF-16BE'),
];

$plan = SQLiteUtf16RtrimLikeGlobCurrentSourceNextPlan::keyValueRowKeyOperatorSwitchPlan(
    $current,
    $next,
    'module!_cache%',
    'module_cache*',
    'LIKE',
    'GLOB',
    '!',
    null,
    true,
    'main.app_settings@cookie127',
    'main.app_settings@cookie128',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentOperator'] === 'LIKE');
    assert($plan['nextOperator'] === 'GLOB');
    assert($plan['currentRowids'] === [1, 2, 3, 4]);
    assert($plan['nextRowids'] === [1, 2, 3, 4, 5]);
    assert(in_array('operator-switch', $plan['invalidationReasons'], true));
    assert($plan['residualDoesNotTrimTrailingSpaces'] === true);
    echo "application-utf16-rtrim-like-glob-current-source-next128 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
