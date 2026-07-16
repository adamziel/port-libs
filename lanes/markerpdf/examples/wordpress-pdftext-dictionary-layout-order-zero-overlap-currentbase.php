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
                        'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$document = (new PdfTextDocumentExtractor())->getOrderedTextBlocks(
    [
        $page(4200, [
            ['text' => 'Zero overlap cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
        ]),
        $page(4201, [
            ['text' => 'Right zero-overlap smoke column has the supplied bbox.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
            ['text' => 'Left zero-overlap smoke column shares the first order group.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
        ]),
    ],
    [
        [
            'page' => 4201,
            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
            'bboxes' => [
                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ],
        ],
    ],
    orderImages: [
        ['page' => 4201, 'image' => 'zero-overlap-order-render'],
    ],
    maxPages: 1,
    startPage: 1
);

$processor = new MarkdownPostProcessor();
$blocks = $processor->mergeBlocks($processor->mergeSpans($document['pages']));
$text = $blocks[0]['text'] ?? '';
$orderedText = array_map(
    static fn (array $block): string => (string) ($block['lines'][0]['spans'][0]['text'] ?? ''),
    $document['pages'][0]['blocks'] ?? []
);
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-zero-overlap-currentbase',
    'source_truth' => 'marker.layout.order.sort_blocks_in_reading_order initializes a block position from the first order box even when overlap is zero, then replaces it only for a larger overlap',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $document['page_range'],
    'pdf_page_number' => $document['pages'][0]['pnum'] ?? null,
    'partial_order_prediction_assigned' => ($document['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'zero_overlap_left_block_in_first_group' => ($orderedText[0] ?? null) === 'Left zero-overlap smoke column shares the first order group.',
    'right_overlap_block_preserved' => ($orderedText[1] ?? null) === 'Right zero-overlap smoke column has the supplied bbox.',
    'cover_excluded' => !str_contains($text, 'Zero overlap cover page should not import.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['partial_order_prediction_assigned']
    || !$flags['zero_overlap_left_block_in_first_group']
    || !$flags['right_overlap_block_preserved']
    || !$flags['cover_excluded']
) {
    throw new RuntimeException('Expected upstream zero-overlap order grouping before WordPress paragraph merge: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

foreach ($blocks as $block) {
    if (($block['block_type'] ?? '') !== 'Text' || trim((string) ($block['text'] ?? '')) === '') {
        continue;
    }

    echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":' . (int) ($block['pnum'] ?? 0) . '}} -->' . "\n";
    echo '<p>' . htmlspecialchars((string) $block['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-zero-overlap-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
