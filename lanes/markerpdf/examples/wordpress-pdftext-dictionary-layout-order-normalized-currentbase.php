<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, array $lines): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'width' => 612.0,
        'height' => 792.0,
        'rotation' => 0,
        'blocks' => [[
            'lines' => array_map(
                static fn (array $line): array => [
                    'bbox' => $line['bbox'],
                    'spans' => [[
                        'text' => $line['text'],
                        'bbox' => $line['bbox'],
                        'font' => ['name' => 'Times-Roman', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$document = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
    [
        $page(1200, [
            ['text' => 'Skipped normalized order cover.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
        ]),
        $page(1201, [
            ['text' => 'Second column arrives from normalized layout order.', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
            ['text' => 'First column remains the opening paragraph.', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
        ]),
    ],
    [
        [
            'page' => 1201,
            'image_bbox' => [0.0, 0.0, 1224.0, 1584.0],
            'bboxes' => [
                ['position' => 1, 'bbox' => [0.098, 0.121, 0.474, 0.182]],
                ['position' => 2, 'bbox' => [0.519, 0.121, 0.932, 0.182]],
            ],
        ],
    ],
    orderImages: [
        ['page' => 1201, 'image' => 'normalized-layout-order-render'],
    ],
    maxPages: 1,
    startPage: 1
);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$orderedText = array_map(
    static fn (array $block): string => (string) ($block['lines'][0]['spans'][0]['text'] ?? ''),
    $document['pages'][0]['blocks'] ?? []
);
$order = $document['pages'][0]['order'] ?? [];

if (
    $orderedText !== [
        'First column remains the opening paragraph.',
        'Second column arrives from normalized layout order.',
    ]
    || ($order['image_bbox'] ?? null) !== [0.0, 0.0, 1224.0, 1584.0]
    || ($order['bboxes'] ?? []) !== [
        ['position' => 1, 'bbox' => [0.098, 0.121, 0.474, 0.182]],
        ['position' => 2, 'bbox' => [0.519, 0.121, 0.932, 0.182]],
    ]
    || ($document['metadata']['order_plan']['assigned_pages'] ?? null) !== 1
) {
    throw new RuntimeException('Expected normalized supplied layout-order boxes to assign WordPress paragraphs before merge.');
}

foreach ($blocks as $block) {
    if (($block['block_type'] ?? '') !== 'Text' || trim((string) ($block['text'] ?? '')) === '') {
        continue;
    }

    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-normalized-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-normalized-currentbase',
    'source_truth' => 'marker.pdf.extract_text supplies pdftext dictionary pages to marker.layout.order; adapter-provided normalized order boxes must be expanded against the supplied order image before reading-order assignment',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $document['page_range'],
    'pdf_page_number' => $document['pages'][0]['pnum'] ?? null,
    'ordered_text' => $orderedText,
    'normalized_order_bboxes_preserved_for_review' => ($order['bboxes'] ?? []) === [
        ['position' => 1, 'bbox' => [0.098, 0.121, 0.474, 0.182]],
        ['position' => 2, 'bbox' => [0.519, 0.121, 0.932, 0.182]],
    ],
    'normalized_order_bboxes_scaled_for_assignment' => $orderedText === [
        'First column remains the opening paragraph.',
        'Second column arrives from normalized layout order.',
    ],
    'order_plan' => $document['metadata']['order_plan'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
