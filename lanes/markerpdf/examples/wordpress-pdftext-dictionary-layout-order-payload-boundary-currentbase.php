<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkdownPostProcessor;
use PortLibs\MarkerPDF\PdfTextDocumentExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdftextPage = static function (int $page, array $lines): array {
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
                        'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 12.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$document = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
    [
        $pdftextPage(510, [
            ['text' => 'Skipped source page should not reach WordPress.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
        ]),
        $pdftextPage(511, [
            ['text' => 'Second imported dictionary column.', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
            ['text' => 'First imported dictionary column.', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
        ]),
    ],
    [
        [
            'metadata' => [
                'page' => 511,
                'raw_private_payload' => 'hidden order adapter payload',
            ],
            'pdftext' => $pdftextPage(510, [
                ['text' => 'Stale nested pdftext order payload must stay hidden.', 'bbox' => [72.0, 160.0, 500.0, 174.0]],
            ]),
            'blocks' => [[
                'lines' => [[
                    'spans' => [[
                        'text' => 'Raw order block payload must stay hidden.',
                        'bbox' => [72.0, 180.0, 500.0, 194.0],
                    ]],
                ]],
            ]],
            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
            'bboxes' => [
                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ],
        ],
    ],
    orderImages: [
        [
            'metadata' => ['page' => 511],
            'pdftext' => $pdftextPage(510, [
                ['text' => 'Stale nested pdftext render payload must not block selected image.', 'bbox' => [72.0, 180.0, 500.0, 194.0]],
            ]),
            'image' => 'selected-order-render',
        ],
    ],
    maxPages: 1,
    startPage: 1
);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$encodedDocument = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$visibleText = $blocks[0]['text'] ?? '';
$visibleOrderIsClean = str_contains($visibleText, 'First imported dictionary column.')
    && str_contains($visibleText, 'Second imported dictionary column.')
    && strpos($visibleText, 'First imported dictionary column.') < strpos($visibleText, 'Second imported dictionary column.');

if (
    !$visibleOrderIsClean
    || str_contains($encodedDocument, 'hidden order adapter payload')
    || str_contains($encodedDocument, 'Stale nested pdftext order payload')
    || str_contains($encodedDocument, 'Stale nested pdftext render payload')
    || str_contains($encodedDocument, 'Raw order block payload')
    || (($document['pages'][0]['order']['page'] ?? null) !== 511)
    || array_key_exists('pdftext', $document['pages'][0]['order'] ?? [])
    || array_key_exists('metadata', $document['pages'][0]['order'] ?? [])
    || array_key_exists('blocks', $document['pages'][0]['order'] ?? [])
) {
    throw new RuntimeException('Expected layout order geometry to reorder selected pdftext blocks while excluding nested adapter payloads.');
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-payload-boundary-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-payload-boundary-currentbase',
    'source_truth' => 'markerPDF converts selected pdftext.dictionary_output pages, trims PDFium pages before layout/order rendering, then attaches Surya order geometry to pages; adapter metadata is page identity while nested pdftext payloads are not a second visible page source',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'selected_page' => $document['pages'][0]['pnum'] ?? null,
    'order_page_marker_preserved' => ($document['pages'][0]['order']['page'] ?? null) === 511,
    'order_geometry_preserved' => count($document['pages'][0]['order']['bboxes'] ?? []) === 2,
    'stale_pdftext_payload_ignored_for_identity' => ($document['metadata']['order_plan']['image_count'] ?? null) === 1
        && ($document['metadata']['order_plan']['order_result_count'] ?? null) === 1
        && ($document['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'visible_columns_in_reading_order' => $visibleOrderIsClean,
    'order_payload_excluded' => !str_contains($encodedDocument, 'hidden order adapter payload')
        && !str_contains($encodedDocument, 'Stale nested pdftext order payload')
        && !str_contains($encodedDocument, 'Stale nested pdftext render payload')
        && !str_contains($encodedDocument, 'Raw order block payload'),
    'visible_wordpress_text' => $visibleText,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
