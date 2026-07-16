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
        $page(880, [
            ['text' => 'Skipped order geometry cover.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
        ]),
        $page(881, [
            ['text' => 'Second column arrives from pdftext.', 'bbox' => [330.0, 112.0, 560.0, 126.0]],
            ['text' => 'First column survives bbox normalization.', 'bbox' => [72.0, 112.0, 280.0, 126.0]],
        ]),
    ],
    [
        [
            'page' => '881',
            'image_bbox' => ['0', '0', '612.0', '792.0'],
            'bboxes' => [
                ['position' => '1', 'bbox' => ['60.0', '96.0', '290.0', '144.0']],
                ['position' => '2', 'bbox' => ['318.0', '96.0', '570.0', '144.0']],
                ['position' => '0', 'bbox' => ['bad', '96.0', '570.0', '144.0'], 'raw_payload' => 'malformed order bbox payload'],
                ['position' => 'bad', 'bbox' => ['0', '0', '612', '792'], 'raw_payload' => 'malformed order position payload'],
            ],
        ],
    ],
    orderImages: [
        ['page' => '881', 'image' => 'numeric-string-order-render'],
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
$encoded = json_encode($document, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$order = $document['pages'][0]['order'] ?? [];

if (
    $orderedText !== ['First column survives bbox normalization.', 'Second column arrives from pdftext.']
    || ($order['image_bbox'] ?? null) !== [0.0, 0.0, 612.0, 792.0]
    || count($order['bboxes'] ?? []) !== 2
    || str_contains($encoded, 'malformed order bbox payload')
    || str_contains($encoded, 'malformed order position payload')
) {
    throw new RuntimeException('Expected numeric-string order geometry to normalize while malformed order rows stay excluded.');
}

foreach ($blocks as $block) {
    if (($block['block_type'] ?? '') !== 'Text' || trim((string) ($block['text'] ?? '')) === '') {
        continue;
    }

    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars((string) ($block['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-bbox-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-bbox-currentbase',
    'source_truth' => 'pdftext dictionary pages feed marker.layout.order supplied order boxes; native adapters may provide numeric strings from JSON, while malformed rows must not influence reading order',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $document['page_range'],
    'pdf_page_number' => $document['pages'][0]['pnum'] ?? null,
    'ordered_text' => $orderedText,
    'numeric_string_image_bbox_normalized' => ($order['image_bbox'] ?? null) === [0.0, 0.0, 612.0, 792.0],
    'numeric_string_order_bboxes_normalized' => ($order['bboxes'] ?? []) === [
        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
    ],
    'malformed_order_rows_excluded' => !str_contains($encoded, 'malformed order bbox payload')
        && !str_contains($encoded, 'malformed order position payload'),
    'order_plan' => $document['metadata']['order_plan'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
