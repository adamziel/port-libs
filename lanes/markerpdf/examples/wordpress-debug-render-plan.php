<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\DebugRenderPlanner;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$bboxes = [
    [72.0, 48.0, 420.0, 84.0],
    [72.0, 112.0, 460.0, 154.0],
    [74.0, 180.0, 300.0, 240.0],
];
$labels = ['Title', 'Text', 'Picture'];

$plan = (new DebugRenderPlanner())->renderOnImagePlan(
    $bboxes,
    $labels,
    color: ['#2271b1', '#00a32a', '#d63638'],
    textSizer: static fn (string $label, int $fontSize): array => [strlen($label) * 6, 11]
);

echo json_encode([
    'scenario' => 'wordpress-debug-render-plan',
    'overlay_operations' => count($plan['operations']),
    'bbox_operations' => count(array_filter($plan['operations'], static fn (array $op): bool => ($op['role'] ?? '') === 'bbox')),
    'label_operations' => count(array_filter($plan['operations'], static fn (array $op): bool => ($op['role'] ?? '') === 'label')),
    'first_label' => [
        'text' => $plan['operations'][2]['text'],
        'position' => $plan['operations'][2]['position'],
        'color' => $plan['operations'][2]['fill'],
    ],
    'review_payload' => $plan,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
