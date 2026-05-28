<?php

declare(strict_types=1);

$tests = require __DIR__ . '/../tests/SQLiteTriggerRecursiveViewUpsertCurrentSourceNext256Test.php';
unset($tests);

$plan = $released256(['current_source_view_upsert_handoff_batch_size_next256' => 2]);

$summary = [
    'status' => $plan['status_next256'],
    'visible_names' => array_column($plan['visible_returning_payloads_next256'], 'name'),
    'handoff_batches' => $plan['current_source_view_upsert_handoff_batches_next256'],
    'yield_boundary' => $plan['yield_boundary_next256'],
    'dependency_closure' => $plan['dependency_closure_next256'],
];

if ($summary['status'] !== 'trigger-recursive-view-upsert-current-source-next256-handoff-released') {
    fwrite(STDERR, "unexpected recursive view UPSERT next256 status\n");
    exit(1);
}
if ($summary['visible_names'] !== ['blogdescription_child', 'template_child', 'home', 'next_plugin']) {
    fwrite(STDERR, "unexpected recursive view UPSERT next256 visible rows\n");
    exit(1);
}
if (count($summary['handoff_batches']) !== 1 || $summary['handoff_batches'][0]['row_count'] !== 2) {
    fwrite(STDERR, "unexpected recursive view UPSERT next256 handoff batch\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
