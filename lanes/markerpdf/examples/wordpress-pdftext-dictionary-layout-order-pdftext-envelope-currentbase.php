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

$path = sys_get_temp_dir() . '/markerpdf-explicit-pdftext-artifact-envelope-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% explicit pdftext artifact envelope layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(5310, [
                ['text' => 'Explicit pdftext envelope cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(5311, [
                ['text' => 'Second explicit pdftext envelope body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First explicit pdftext envelope heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
            $page(5312, [
                ['text' => 'Explicit pdftext envelope appendix should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [[
                'pdftext' => [
                    'pages' => [
                        ['page' => 5310, 'image' => 'explicit-pdftext-cover-layout-render'],
                        ['page' => 5311, 'image' => 'explicit-pdftext-selected-layout-render'],
                        ['page' => 5312, 'image' => 'explicit-pdftext-appendix-layout-render'],
                    ],
                ],
                'raw_payload' => 'explicit pdftext layout image envelope payload must stay hidden',
            ]],
            'layout_results' => [[
                'metadata' => ['source' => 'cached layout pdftext envelope'],
                'pdftext' => [
                    'pages' => [
                        [
                            'page' => 5310,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ],
                            'raw_payload' => 'explicit pdftext cover layout payload must stay hidden',
                        ],
                        [
                            'page' => 5311,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                            ],
                            'raw_payload' => 'explicit pdftext selected layout payload must stay hidden',
                        ],
                        [
                            'page' => 5312,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ],
                            'raw_payload' => 'explicit pdftext appendix layout payload must stay hidden',
                        ],
                    ],
                ],
                'raw_payload' => 'explicit pdftext layout envelope payload must stay hidden',
            ]],
            'order_images' => [[
                'pdftext' => [
                    'pages' => [
                        ['page' => 5310, 'image' => 'explicit-pdftext-cover-order-render'],
                        ['page' => 5311, 'image' => 'explicit-pdftext-selected-order-render'],
                        ['page' => 5312, 'image' => 'explicit-pdftext-appendix-order-render'],
                    ],
                ],
                'raw_payload' => 'explicit pdftext order image envelope payload must stay hidden',
            ]],
            'order_results' => [[
                'metadata' => ['source' => 'cached order pdftext envelope'],
                'pdftext' => [
                    'pages' => [
                        [
                            'page' => 5310,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'explicit pdftext cover order payload must stay hidden',
                        ],
                        [
                            'page' => 5311,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ],
                            'raw_payload' => 'explicit pdftext selected order payload must stay hidden',
                        ],
                        [
                            'page' => 5312,
                            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                            'bboxes' => [
                                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ],
                            'raw_payload' => 'explicit pdftext appendix order payload must stay hidden',
                        ],
                    ],
                ],
                'raw_payload' => 'explicit pdftext order envelope payload must stay hidden',
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$firstPosition = strpos($text, '# First Explicit Pdftext Envelope Heading.');
$secondPosition = strpos($text, 'Second explicit pdftext envelope body.');

if ($firstPosition === false
    || $secondPosition === false
    || $firstPosition > $secondPosition
    || str_contains($text, 'Explicit pdftext envelope cover should not import.')
    || str_contains($text, 'Explicit pdftext envelope appendix should not import.')
    || str_contains($encoded, 'explicit pdftext cover layout payload')
    || str_contains($encoded, 'explicit pdftext selected layout payload')
    || str_contains($encoded, 'explicit pdftext appendix layout payload')
    || str_contains($encoded, 'explicit pdftext layout envelope payload')
    || str_contains($encoded, 'explicit pdftext cover order payload')
    || str_contains($encoded, 'explicit pdftext selected order payload')
    || str_contains($encoded, 'explicit pdftext appendix order payload')
    || str_contains($encoded, 'explicit pdftext order envelope payload')
) {
    throw new RuntimeException('Expected explicit pdftext artifact envelopes to unwrap before selected WordPress layout/order assignment.');
}

foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    if (str_starts_with($paragraph, '# ')) {
        echo "<!-- wp:heading -->\n";
        echo '<h2>' . htmlspecialchars(substr($paragraph, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-pdftext-envelope-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-pdftext-envelope-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; cached explicit pdftext page-list envelopes must unwrap before native selected-page artifact matching',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'selected_page_ordered_by_unwrapped_pdftext' => $firstPosition !== false
        && $secondPosition !== false
        && $firstPosition < $secondPosition,
    'cover_excluded' => !str_contains($text, 'Explicit pdftext envelope cover should not import.'),
    'appendix_excluded' => !str_contains($text, 'Explicit pdftext envelope appendix should not import.'),
    'envelope_payload_excluded' => !str_contains($encoded, 'explicit pdftext layout envelope payload')
        && !str_contains($encoded, 'explicit pdftext order envelope payload')
        && !str_contains($encoded, 'explicit pdftext selected order payload'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
