<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

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

$coverPage = $page(19200, [
    ['text' => 'Reading-order alias cover page should not import.', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
]);
$selectedPage = $page(19201, [
    ['text' => 'Second reading-order alias body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First reading-order alias heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);

$path = sys_get_temp_dir() . '/markerpdf-reading-order-alias-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% reading-order alias layout order smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['metadata' => ['page' => 19201], 'image' => 'reading-order-alias-layout-render'],
            ],
            'layout_results' => [[
                'metadata' => ['page' => 19201],
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'reading-order alias layout title payload must stay hidden'],
                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                ],
                'raw_payload' => 'reading-order alias layout payload must stay hidden',
            ]],
            'order_images' => [
                ['metadata' => ['page' => 19201], 'image' => 'reading-order-alias-order-render'],
            ],
            'order_results' => [[
                'metadata' => ['page' => 19201],
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['reading_order' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'reading-order alias right row payload must stay hidden'],
                    ['order_position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'reading-order alias left row payload must stay hidden'],
                ],
                'raw_payload' => 'reading-order alias order payload must stay hidden',
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$headingPosition = strpos($text, '# First Reading-Order Alias Heading.');
$bodyPosition = strpos($text, 'Second reading-order alias body.');
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-reading-order-alias-currentbase',
    'source_truth' => 'markerPDF zips selected Surya order predictions with selected pdftext pages; current sidecars may expose row reading order as reading_order, readingOrder, or order_position before sort_blocks_in_reading_order',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'reading_order_alias_applied' => $headingPosition !== false && $bodyPosition !== false && $headingPosition < $bodyPosition,
    'order_position_alias_applied' => $headingPosition !== false && $bodyPosition !== false && $headingPosition < $bodyPosition,
    'layout_assigned' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'order_assigned' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'cover_excluded' => !str_contains($text, 'Reading-order alias cover page should not import.'),
    'payload_excluded' => !str_contains($encoded, 'reading-order alias layout title payload')
        && !str_contains($encoded, 'reading-order alias layout payload')
        && !str_contains($encoded, 'reading-order alias order payload')
        && !str_contains($encoded, 'reading-order alias right row payload')
        && !str_contains($encoded, 'reading-order alias left row payload'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['reading_order_alias_applied']
    || !$flags['order_position_alias_applied']
    || !$flags['layout_assigned']
    || !$flags['order_assigned']
    || !$flags['cover_excluded']
    || !$flags['payload_excluded']
) {
    throw new RuntimeException('Expected reading-order alias layout/order smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-reading-order-alias-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
