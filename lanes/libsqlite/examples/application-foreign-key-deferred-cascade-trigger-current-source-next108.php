<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan;

$parents = [
    ['option_id' => 1, 'option_name' => 'active_plugins'],
    ['option_id' => 2, 'option_name' => 'rewrite_rules'],
    ['option_id' => 3, 'option_name' => 'theme_mods'],
];
$meta = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'autoload'],
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => 'checksum'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => 'rewrite-source'],
    ['meta_id' => 13, 'option_id' => null, 'meta_key' => 'loose'],
];
$detail = [
    ['detail_id' => 100, 'meta_id' => 10, 'label' => 'autoload-before'],
    ['detail_id' => 101, 'meta_id' => 11, 'label' => 'checksum-before'],
    ['detail_id' => 102, 'meta_id' => 12, 'label' => 'rewrite-before'],
];

$result = SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan::deleteParents(
    $parents,
    $meta,
    $detail,
    [['option_id' => 1]],
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_delete' => 'CASCADE', 'deferred' => true],
    [[
        'timing' => 'before',
        'event' => 'delete',
        'action' => 'insert-audit',
        'audit' => ['phase' => 'before', 'meta_id' => 'old.meta_id', 'remaining_detail' => 'grandchild_count'],
    ]],
    ['parent_key' => 'meta_id', 'child_key' => 'meta_id', 'on_delete' => 'CASCADE'],
    [
        [
            'operation' => 'insert',
            'table' => 'child',
            'row' => ['meta_id' => 20, 'option_id' => 1, 'meta_key' => 'late-current'],
        ],
        [
            'operation' => 'insert',
            'table' => 'grandchild',
            'row' => ['detail_id' => 103, 'meta_id' => 20, 'label' => 'late-current-detail'],
        ],
    ],
);

if (($argv[1] ?? null) === '--self-test') {
    assertSame([2, 3], array_column($result['after_commit']['parent'], 'option_id'), 'remaining parent option ids');
    assertSame([12, 13], array_column($result['after_commit']['child'], 'meta_id'), 'remaining child meta ids');
    assertSame([102], array_column($result['after_commit']['grandchild'], 'detail_id'), 'remaining detail ids');
    assertSame(['before', 'before', 'before'], array_column($result['audit'], 'phase'), 'trigger audit phases');
    assertSame(['insert-current-child', 'insert-current-grandchild'], array_column($result['current_source_actions'], 'action'), 'current source actions');

    echo "application-foreign-key-deferred-cascade-trigger-current-source-next108 self-test passed\n";
    return;
}

echo json_encode([
    'deleted_option_ids' => [1],
    'remaining_option_ids' => array_column($result['after_commit']['parent'], 'option_id'),
    'remaining_meta_ids' => array_column($result['after_commit']['child'], 'meta_id'),
    'remaining_detail_ids' => array_column($result['after_commit']['grandchild'], 'detail_id'),
    'current_source_actions' => array_column($result['current_source_actions'], 'action'),
    'cascade_actions' => array_column($result['cascade_actions'], 'action'),
    'audit_rows' => $result['audit'],
    'changes' => $result['changes'],
    'dependencies' => $result['dependencies'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

/**
 * @param mixed $expected
 * @param mixed $actual
 */
function assertSame($expected, $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $label . " mismatch\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}
