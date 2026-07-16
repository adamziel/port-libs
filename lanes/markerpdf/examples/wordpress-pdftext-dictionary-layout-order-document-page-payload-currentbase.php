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
        $pdftextPage(1310, [
            ['text' => 'Skipped payload source page should not reach WordPress.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
        ]),
        $pdftextPage(1311, [
            ['text' => 'Second document-page column for media review.', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
            ['text' => 'First document-page column for import.', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
        ]),
    ],
    [
        [
            'metadata' => ['document_page' => 1311],
            'pdftext' => $pdftextPage(1310, [
                ['text' => 'Stale nested pdftext page marker must stay hidden.', 'bbox' => [72.0, 160.0, 500.0, 174.0]],
            ]),
            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
            'bboxes' => [
                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ],
        ],
    ],
    orderImages: [
        [
            'metadata' => ['document_page' => 1311],
            'pdftext' => $pdftextPage(1310, [
                ['text' => 'Stale nested pdftext render marker must stay hidden.', 'bbox' => [72.0, 180.0, 500.0, 194.0]],
            ]),
            'image' => 'document-page-payload-order-render',
        ],
    ],
    maxPages: 1,
    startPage: 1
);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$encodedDocument = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$visibleText = $blocks[0]['text'] ?? '';
$order = $document['pages'][0]['order'] ?? [];
$visibleOrderIsClean = str_contains($visibleText, 'First document-page column for import.')
    && str_contains($visibleText, 'Second document-page column for media review.')
    && strpos($visibleText, 'First document-page column for import.') < strpos($visibleText, 'Second document-page column for media review.');
$payloadExcluded = !str_contains($encodedDocument, 'Skipped payload source page')
    && !str_contains($encodedDocument, 'Stale nested pdftext page marker')
    && !str_contains($encodedDocument, 'Stale nested pdftext render marker');

if (
    !$visibleOrderIsClean
    || !$payloadExcluded
    || (($order['document_page'] ?? null) !== 1311)
    || array_key_exists('page', $order)
    || array_key_exists('pdftext', $order)
) {
    throw new RuntimeException('Expected trusted document_page order metadata without stale nested pdftext page markers.');
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-document-page-payload-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-document-page-payload-currentbase',
    'source_truth' => 'markerPDF builds selected pdftext.dictionary_output pages before layout/order; adapter metadata carries selected page identity while nested pdftext dictionaries are payload copies',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'selected_page' => $document['pages'][0]['pnum'] ?? null,
    'trusted_document_page_preserved' => ($order['document_page'] ?? null) === 1311,
    'stale_pdftext_page_marker_excluded' => !array_key_exists('page', $order),
    'nested_pdftext_payload_excluded' => !array_key_exists('pdftext', $order) && $payloadExcluded,
    'visible_columns_in_reading_order' => $visibleOrderIsClean,
    'order_plan' => $document['metadata']['order_plan'] ?? null,
    'visible_wordpress_text' => $visibleText,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n\n";

foreach ($blocks as $block) {
    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
