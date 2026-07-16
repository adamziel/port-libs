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
                        'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$path = sys_get_temp_dir() . '/markerpdf-page-map-envelope-layout-order-smoke-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% page_map envelope pdftext layout order smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            'page_map' => [
                'metadata' => ['raw_payload' => 'pdftext page_map envelope metadata hidden from WordPress'],
                '10610' => $page(10610, [
                    ['text' => 'Page map envelope cover must not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
                ]),
                '10611' => $page(10611, [
                    ['text' => 'Second page map envelope import body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                    ['text' => 'First page map envelope import heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                ]),
            ],
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [[
                'page_map' => [
                    '10610' => ['image' => 'page-map-envelope-cover-layout-render'],
                    '10611' => ['image' => 'page-map-envelope-selected-layout-render'],
                    'metadata' => ['raw_payload' => 'layout image page_map envelope metadata hidden from WordPress'],
                ],
            ]],
            'layout_results' => [[
                'page_map' => [
                    '10610' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'stale page_map layout payload hidden from WordPress',
                    ],
                    '10611' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'selected page_map layout row hidden from WordPress'],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'selected page_map layout payload hidden from WordPress',
                    ],
                    'metadata' => ['raw_payload' => 'layout page_map envelope metadata hidden from WordPress'],
                ],
            ]],
            'order_images' => [[
                'page_map' => [
                    '10610' => ['image' => 'page-map-envelope-cover-order-render'],
                    '10611' => ['image' => 'page-map-envelope-selected-order-render'],
                    'metadata' => ['raw_payload' => 'order image page_map envelope metadata hidden from WordPress'],
                ],
            ]],
            'order_results' => [[
                'page_map' => [
                    '10610' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ],
                        'raw_payload' => 'stale page_map order payload hidden from WordPress',
                    ],
                    '10611' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'selected page_map order row hidden from WordPress'],
                        ],
                        'raw_payload' => 'selected page_map order payload hidden from WordPress',
                    ],
                    'metadata' => ['raw_payload' => 'order page_map envelope metadata hidden from WordPress'],
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
$headingPosition = strpos($text, '# First Page Map Envelope Import Heading.');
$bodyPosition = strpos($text, 'Second page map envelope import body.');
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-page-map-envelope-currentbase',
    'source_truth' => 'markerPDF trims pdftext.dictionary_output pages before zipping selected pages with layout/order predictions; native page_map envelopes are adapter cache wrappers around that selected-page boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_page_map_envelope_unwrapped' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1
        && ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_page_map_envelope_unwrapped' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1
        && ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'first_before_second' => $headingPosition !== false && $bodyPosition !== false && $headingPosition < $bodyPosition,
    'cover_excluded' => !str_contains($text, 'Page map envelope cover must not import.'),
    'payload_excluded' => !str_contains($encoded, 'pdftext page_map envelope metadata hidden')
        && !str_contains($encoded, 'layout image page_map envelope metadata hidden')
        && !str_contains($encoded, 'stale page_map layout payload hidden')
        && !str_contains($encoded, 'selected page_map layout row hidden')
        && !str_contains($encoded, 'selected page_map layout payload hidden')
        && !str_contains($encoded, 'layout page_map envelope metadata hidden')
        && !str_contains($encoded, 'order image page_map envelope metadata hidden')
        && !str_contains($encoded, 'stale page_map order payload hidden')
        && !str_contains($encoded, 'selected page_map order row hidden')
        && !str_contains($encoded, 'selected page_map order payload hidden')
        && !str_contains($encoded, 'order page_map envelope metadata hidden')
        && !str_contains($encoded, '__markerpdf_envelope_page_key_marker'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['layout_page_map_envelope_unwrapped']
    || !$flags['order_page_map_envelope_unwrapped']
    || !$flags['first_before_second']
    || !$flags['cover_excluded']
    || !$flags['payload_excluded']
) {
    throw new RuntimeException('Expected page_map layout/order envelopes to unwrap for selected WordPress import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $block) {
    $block = trim($block);
    if ($block === '') {
        continue;
    }

    if (str_starts_with($block, '# ')) {
        echo "<!-- wp:heading {\"level\":1} -->\n";
        echo '<h1>' . htmlspecialchars(substr($block, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h1>\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-page-map-envelope-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
