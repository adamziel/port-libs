<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\LayoutOrderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pages = [
    [
        'bbox' => [0.0, 0.0, 800.0, 600.0],
        'rotation' => 90,
        'order' => [
            'image_bbox' => [0.0, 0.0, 600.0, 800.0],
            'bboxes' => [
                ['position' => 0, 'bbox' => [20.0, 60.0, 50.0, 740.0]],
                ['position' => 1, 'bbox' => [310.0, 500.0, 500.0, 740.0]],
                ['position' => 2, 'bbox' => [310.0, 60.0, 500.0, 360.0]],
                ['position' => 9, 'bbox' => [560.0, 60.0, 590.0, 740.0]],
            ],
        ],
        'blocks' => [
            ['type' => 'Page-footer', 'text' => 'Internal rotated footer', 'bbox' => [70.0, 564.0, 730.0, 586.0]],
            ['type' => 'Text', 'text' => 'Media links belong in the second rotated column.', 'bbox' => [450.0, 320.0, 720.0, 338.0]],
            ['type' => 'Page-header', 'text' => 'Rotated export packet', 'bbox' => [70.0, 24.0, 730.0, 44.0]],
            ['type' => 'Text', 'text' => 'The import summary starts in the first rotated column.', 'bbox' => [72.0, 320.0, 280.0, 338.0]],
        ],
    ],
];

$sorted = (new LayoutOrderer())->sortBlocksInReadingOrder($pages);
$orderedText = array_column($sorted[0]['blocks'], 'text');
$visibleBody = [];

foreach ($sorted[0]['blocks'] as $block) {
    if (($block['type'] ?? $block['block_type'] ?? '') !== 'Text') {
        continue;
    }

    $visibleBody[] = (string) ($block['text'] ?? '');
}

foreach ($visibleBody as $paragraph) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-layout-rotated-columns-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-layout-rotated-columns-currentbase',
    'native_boundary' => 'unrotated page-image order boxes are rotated into marker/pdftext page space before upstream overlap sorting and page-edge pinning',
    'source_truth' => 'marker/layout/order.py rescales order.image_bbox to page.bbox and pins Page-header before body plus Page-footer after body; marker/pdf/extract_text.py swaps page bbox axes for 90/270-degree pages',
    'page_rotation' => 90,
    'order_image_bbox_unrotated' => [0.0, 0.0, 600.0, 800.0],
    'page_bbox_after_pdftext_rotation' => [0.0, 0.0, 800.0, 600.0],
    'ordered_text' => $orderedText,
    'visible_body' => $visibleBody,
    'header_pinned_first' => $orderedText[0] === 'Rotated export packet',
    'footer_pinned_last' => end($orderedText) === 'Internal rotated footer',
    'body_columns_in_reading_order' => $visibleBody === [
        'The import summary starts in the first rotated column.',
        'Media links belong in the second rotated column.',
    ],
    'edge_artifacts_hidden_from_visible_body' => !in_array('Rotated export packet', $visibleBody, true)
        && !in_array('Internal rotated footer', $visibleBody, true),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
