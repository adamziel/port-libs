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

$path = sys_get_temp_dir() . '/markerpdf-dictionary-output-envelope-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% dictionary_output envelope pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(4810, [
                ['text' => 'Dictionary-output envelope cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(4811, [
                ['text' => 'Second envelope order column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First envelope order column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
            $page(4812, [
                ['text' => 'Dictionary-output envelope appendix should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [[
                'dictionary_output' => [
                    ['page' => 4810, 'image' => 'dictionary-output-cover-layout-render'],
                    ['page' => 4811, 'image' => 'dictionary-output-selected-layout-render'],
                ],
                'raw_payload' => 'dictionary-output layout image envelope payload must stay hidden',
            ]],
            'layout_results' => [[
                'metadata' => ['source' => 'cached layout dictionary_output envelope'],
                'dictionary_output' => [
                    [
                        'page' => 4810,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ],
                        'raw_payload' => 'dictionary-output cover layout payload must stay hidden',
                    ],
                    [
                        'page' => 4811,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'dictionary-output selected layout payload must stay hidden',
                    ],
                ],
                'raw_payload' => 'dictionary-output layout envelope payload must stay hidden',
            ]],
            'order_images' => [[
                'dictionary_output' => [
                    ['page' => 4810, 'image' => 'dictionary-output-cover-order-render'],
                    ['page' => 4811, 'image' => 'dictionary-output-selected-order-render'],
                ],
                'raw_payload' => 'dictionary-output order image envelope payload must stay hidden',
            ]],
            'order_results' => [[
                'metadata' => ['source' => 'cached order dictionary_output envelope'],
                'dictionary_output' => [
                    [
                        'page' => 4810,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ],
                        'raw_payload' => 'dictionary-output cover order payload must stay hidden',
                    ],
                    [
                        'page' => 4811,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                        'raw_payload' => 'dictionary-output selected order payload must stay hidden',
                    ],
                ],
                'raw_payload' => 'dictionary-output order envelope payload must stay hidden',
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$firstPosition = strpos($text, 'First envelope order column.');
$secondPosition = strpos($text, 'Second envelope order column.');

if (str_contains($text, 'Dictionary-output envelope cover should not import.')
    || str_contains($text, 'Dictionary-output envelope appendix should not import.')
    || $firstPosition === false
    || $secondPosition === false
    || $firstPosition > $secondPosition
    || str_contains($encoded, 'dictionary-output cover layout payload')
    || str_contains($encoded, 'dictionary-output selected layout payload')
    || str_contains($encoded, 'dictionary-output cover order payload')
    || str_contains($encoded, 'dictionary-output selected order payload')
) {
    throw new RuntimeException('Expected dictionary_output artifact envelopes to unwrap before selected WordPress layout/order assignment.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-envelope-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-envelope-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; cached dictionary_output/page-list envelopes must unwrap before native selected-page artifact matching',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'selected_page_ordered_by_unwrapped_dictionary_output' => $firstPosition !== false
        && $secondPosition !== false
        && $firstPosition < $secondPosition,
    'cover_excluded' => !str_contains($text, 'Dictionary-output envelope cover should not import.'),
    'appendix_excluded' => !str_contains($text, 'Dictionary-output envelope appendix should not import.'),
    'envelope_payload_excluded' => !str_contains($encoded, 'dictionary-output layout envelope payload')
        && !str_contains($encoded, 'dictionary-output order envelope payload')
        && !str_contains($encoded, 'dictionary-output selected order payload'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
