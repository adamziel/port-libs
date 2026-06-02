<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$page = static function (int $page, array $lines): array {
    return [
        'page' => $page,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
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
        $page(6, [
            ['text' => 'Skipped editorial cover', 'bbox' => [72.0, 84.0, 300.0, 98.0]],
        ]),
        $page(7, [
            ['text' => 'Second column lists media attachments.', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
            ['text' => 'First column introduces the import.', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
        ]),
    ],
    [
        [
            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
            'bboxes' => [
                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ],
        ],
    ],
    orderImages: ['rendered-selected-page'],
    maxPages: 1,
    startPage: 1,
    toc: [['title' => 'Data liberation import', 'level' => 1, 'page_index' => 7]]
);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$orderedText = array_map(
    static fn (array $block): string => (string) ($block['lines'][0]['spans'][0]['text'] ?? ''),
    $document['pages'][0]['blocks'] ?? []
);
$spanIds = [];
foreach ($document['pages'][0]['blocks'] ?? [] as $block) {
    foreach ($block['lines'] ?? [] as $line) {
        foreach ($line['spans'] ?? [] as $span) {
            if (is_array($span) && isset($span['span_id'])) {
                $spanIds[] = (string) $span['span_id'];
            }
        }
    }
}

foreach ($blocks as $block) {
    if (($block['block_type'] ?? '') !== 'Text' || trim($block['text']) === '') {
        continue;
    }

    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars($block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-currentbase',
    'source_truth' => 'marker/pdf/extract_text.py enumerates selected pdftext dictionary pages, then marker/layout/order.py zips supplied order predictions with selected pages and sorts by rescaled order boxes',
    'page_range' => $document['page_range'],
    'pdf_page_number' => $document['pages'][0]['pnum'] ?? null,
    'span_ids_restart_for_selected_page' => in_array('0_0', $spanIds, true),
    'ordered_text' => $orderedText,
    'cover_page_excluded' => !in_array('Skipped editorial cover', $orderedText, true),
    'order_plan' => $document['metadata']['order_plan'] ?? null,
    'supplied_boundaries' => $document['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
