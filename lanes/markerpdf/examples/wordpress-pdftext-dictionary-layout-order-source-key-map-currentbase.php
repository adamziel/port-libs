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

$path = sys_get_temp_dir() . '/markerpdf-source-key-map-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% source-key map pdftext layout order smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(5500, [
                ['text' => 'Source-key map cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(5501, [
                ['text' => 'Second source-key map body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First source-key map heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [[
                'dictionary_output' => [
                    '5501' => ['image' => 'source-key-selected-layout-render'],
                    '5500' => ['image' => 'source-key-stale-layout-render'],
                ],
            ]],
            'layout_results' => [[
                'dictionary_output' => [
                    '5501' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'source-key selected layout payload must stay hidden',
                    ],
                    '5500' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'source-key stale layout payload must stay hidden',
                    ],
                ],
                'raw_payload' => 'source-key layout map wrapper payload must stay hidden',
            ]],
            'order_images' => [[
                'dictionary_output' => [
                    '5501' => ['image' => 'source-key-selected-order-render'],
                    '5500' => ['image' => 'source-key-stale-order-render'],
                ],
            ]],
            'order_results' => [[
                'dictionary_output' => [
                    '5501' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                        'raw_payload' => 'source-key selected order payload must stay hidden',
                    ],
                    '5500' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ],
                        'raw_payload' => 'source-key stale order payload must stay hidden',
                    ],
                ],
                'raw_payload' => 'source-key order map wrapper payload must stay hidden',
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-source-key-map-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; native adapter maps keyed by selected source page must align before zip-style layout/order processing',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_key_map_selected' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'order_key_map_selected' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'heading_before_body' => strpos($text, '# First Source-Key Map Heading.') < strpos($text, 'Second source-key map body.'),
    'cover_excluded' => !str_contains($text, 'Source-key map cover should not import.'),
    'payload_excluded' => !str_contains($encoded, 'source-key selected layout payload')
        && !str_contains($encoded, 'source-key stale layout payload')
        && !str_contains($encoded, 'source-key layout map wrapper payload')
        && !str_contains($encoded, 'source-key selected order payload')
        && !str_contains($encoded, 'source-key stale order payload')
        && !str_contains($encoded, 'source-key order map wrapper payload')
        && !str_contains($encoded, '__markerpdf_envelope_page_key_marker'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (!$flags['layout_key_map_selected']
    || !$flags['order_key_map_selected']
    || !$flags['heading_before_body']
    || !$flags['cover_excluded']
    || !$flags['payload_excluded']
) {
    throw new RuntimeException('Expected source-keyed pdftext layout/order maps to drive selected WordPress import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-source-key-map-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
