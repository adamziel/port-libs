<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\TableRecognizer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$recognizedTable = [
    'rows' => [
        ['bbox' => [0.0, 0.0, 240.0, 30.0]],
        ['bbox' => [0.0, 40.0, 240.0, 70.0]],
    ],
    'cols' => [
        ['bbox' => [0.0, 0.0, 100.0, 80.0]],
        ['bbox' => [120.0, 0.0, 240.0, 80.0]],
    ],
    'cells' => [
        [
            'bbox' => [130.0, 5.0, 230.0, 25.0],
            'text' => 'Feature',
            'row_ids' => [0],
            'col_ids' => [0],
            'order' => 1,
        ],
        [
            'bbox' => [10.0, 5.0, 90.0, 25.0],
            'text' => 'Status',
            'row_ids' => [0],
            'col_ids' => [1],
            'order' => 0,
        ],
        [
            'bbox' => [130.0, 45.0, 230.0, 65.0],
            'text' => 'Images',
            'row_ids' => [1],
            'col_ids' => [0],
            'order' => 3,
        ],
        [
            'bbox' => [10.0, 45.0, 90.0, 65.0],
            'text' => 'Ready',
            'row_ids' => [1],
            'col_ids' => [1],
            'order' => 2,
        ],
    ],
];

$recognizer = new TableRecognizer();
$formatted = $recognizer->formatRecognizedTables([$recognizedTable], [['width' => 240, 'height' => 80]]);
$assigned = $formatted['assigned_cells'][0];
$markdown = $formatted['markdown_tables'][0];
$localizedTable = $formatted['recognized_tables'][0];
$gridReview = $recognizer->spanningGridReview(
    $assigned,
    $localizedTable['rows'],
    $localizedTable['cols'],
    ['width' => 240, 'height' => 80]
);

$assignedByText = [];
foreach ($assigned as $cell) {
    $assignedByText[$cell['text']] = $cell;
}

if (($assignedByText['Feature']['col_ids'] ?? null) !== [0] || ($assignedByText['Status']['col_ids'] ?? null) !== [1]) {
    throw new RuntimeException('Expected supplied row_ids/col_ids to survive table formatting without geometry reassignment.');
}
if (!str_contains($markdown, '| Feature | Status |') || !str_contains($markdown, '| Images  | Ready  |')) {
    throw new RuntimeException('Expected Markdown table to follow supplied assigned row/column ids.');
}
if (str_contains($markdown, '| Status  | Feature |')) {
    throw new RuntimeException('Unexpected geometry-derived table order for supplied assigned cells.');
}

echo json_encode([
    'scenario' => 'wordpress-table-assigned-cell-geometry-boundary-currentbase',
    'native_boundary' => 'saved tabled/marker SpanTableCell row_ids and col_ids are trusted when every supplied cell already has an assignment',
    'source_truth' => [
        'tabled.schema.SpanTableCell fields include text, row_ids, col_ids, and optional order',
        'tabled.formatters sort cells by assigned row_ids/col_ids anchors before Markdown/HTML emission',
    ],
    'gutenberg_blocks' => [
        [
            'blockName' => 'core/table',
            'innerHTML' => '<figure class="wp-block-table"><table><tbody><tr><td>Feature</td><td>Status</td></tr><tr><td>Images</td><td>Ready</td></tr></tbody></table></figure>',
        ],
    ],
    'assigned_cell_ids' => [
        'Feature' => ['row_ids' => $assignedByText['Feature']['row_ids'], 'col_ids' => $assignedByText['Feature']['col_ids'], 'order' => $assignedByText['Feature']['order'] ?? null],
        'Status' => ['row_ids' => $assignedByText['Status']['row_ids'], 'col_ids' => $assignedByText['Status']['col_ids'], 'order' => $assignedByText['Status']['order'] ?? null],
        'Images' => ['row_ids' => $assignedByText['Images']['row_ids'], 'col_ids' => $assignedByText['Images']['col_ids'], 'order' => $assignedByText['Images']['order'] ?? null],
        'Ready' => ['row_ids' => $assignedByText['Ready']['row_ids'], 'col_ids' => $assignedByText['Ready']['col_ids'], 'order' => $assignedByText['Ready']['order'] ?? null],
    ],
    'header_texts' => array_column($gridReview['header_cells'] ?? [], 'text'),
    'data_texts' => array_column($gridReview['data_cells'] ?? [], 'text'),
    'geometry_reassignment_skipped' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'markdown' => $markdown,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
