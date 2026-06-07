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

$path = sys_get_temp_dir() . '/markerpdf-page-map-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% source-keyed page-map pdftext layout order smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            'dictionary_output' => [
                '8121' => $page(8121, [
                    ['text' => 'Second page-map WordPress body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                    ['text' => 'First page-map WordPress heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                ]),
                '8120' => $page(8120, [
                    ['text' => 'Page-map WordPress cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                '8122' => $page(8122, [
                    ['text' => 'Page-map WordPress appendix should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
            ],
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => 8121, 'image' => 'page-map-layout-render'],
            ],
            'layout_results' => [[
                'page' => 8121,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'page-map smoke title layout payload should stay hidden'],
                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                ],
            ]],
            'order_images' => [
                ['page' => 8121, 'image' => 'page-map-order-render'],
            ],
            'order_results' => [[
                'page' => 8121,
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'page-map smoke order payload should stay hidden'],
                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                ],
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$headingPosition = strpos($text, '# First Page-Map Wordpress Heading.');
$bodyPosition = strpos($text, 'Second page-map WordPress body.');
$headingBeforeBody = $headingPosition !== false && $bodyPosition !== false && $headingPosition < $bodyPosition;
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-page-map-currentbase',
    'source_truth' => 'markerPDF receives pdftext.dictionary_output in page order before start_page/max_pages slicing and then zips selected pages with layout/order results',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'source_keyed_dictionary_output_ordered' => ($result['metadata']['page_range'] ?? null) === [1],
    'layout_assigned' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'order_assigned' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'heading_before_body' => $headingBeforeBody,
    'cover_excluded' => !str_contains($text, 'Page-map WordPress cover should stay skipped.'),
    'appendix_excluded' => !str_contains($text, 'Page-map WordPress appendix should stay skipped.'),
    'payload_excluded' => !str_contains($encoded, 'page-map smoke title layout payload')
        && !str_contains($encoded, 'page-map smoke order payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['source_keyed_dictionary_output_ordered']
    || !$flags['layout_assigned']
    || !$flags['order_assigned']
    || !$flags['heading_before_body']
    || !$flags['cover_excluded']
    || !$flags['appendix_excluded']
    || !$flags['payload_excluded']
) {
    throw new RuntimeException('Expected source-keyed pdftext dictionary page maps to preserve selected WordPress layout/order import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-page-map-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
