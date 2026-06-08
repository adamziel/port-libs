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

$path = sys_get_temp_dir() . '/markerpdf-direct-option-envelope-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% direct option envelope pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(12300, [
                ['text' => 'Direct option envelope cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(12301, [
                ['text' => 'Second direct option envelope WordPress body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First direct option envelope WordPress heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                'dictionary_output' => [
                    '12300' => ['image' => 'direct-option-cover-layout-render'],
                    '12301' => ['image' => 'direct-option-selected-layout-render'],
                    'metadata' => ['raw_payload' => 'direct option layout image metadata must stay hidden'],
                ],
            ],
            'layout_results' => [
                'pages' => [
                    [
                        'page' => 12300,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ],
                        'raw_payload' => 'direct option cover layout payload must stay hidden',
                    ],
                    [
                        'page' => 12301,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'direct option selected layout payload must stay hidden',
                    ],
                ],
            ],
            'order_images' => [
                'pdftext' => [
                    'pages' => [
                        ['page' => 12300, 'image' => 'direct-option-cover-order-render'],
                        ['page' => 12301, 'image' => 'direct-option-selected-order-render'],
                    ],
                ],
            ],
            'order_results' => [
                'page_map' => [
                    '12300' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ],
                        'raw_payload' => 'direct option cover order payload must stay hidden',
                    ],
                    '12301' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                        'raw_payload' => 'direct option selected order payload must stay hidden',
                    ],
                ],
            ],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$heading = '# First Direct Option Envelope Wordpress Heading.';
$body = 'Second direct option envelope WordPress body.';

if (!str_contains($text, $heading)
    || !str_contains($text, $body)
    || strpos($text, $heading) > strpos($text, $body)
    || str_contains($text, 'Direct option envelope cover should not import.')
    || str_contains($encoded, 'direct option cover layout payload')
    || str_contains($encoded, 'direct option selected layout payload')
    || str_contains($encoded, 'direct option cover order payload')
    || str_contains($encoded, 'direct option selected order payload')
) {
    throw new RuntimeException('Expected direct artifact option envelopes to unwrap before WordPress layout/order import.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-direct-option-envelope-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-direct-option-envelope-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; direct supplied option envelopes should unwrap before selected-page artifact matching instead of requiring an extra list wrapper',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_direct_option_envelope_unwrapped' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_direct_option_envelope_unwrapped' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'heading_before_body' => strpos($text, $heading) < strpos($text, $body),
    'cover_excluded' => !str_contains($text, 'Direct option envelope cover should not import.'),
    'payload_excluded' => !str_contains($encoded, 'direct option cover layout payload')
        && !str_contains($encoded, 'direct option selected layout payload')
        && !str_contains($encoded, 'direct option cover order payload')
        && !str_contains($encoded, 'direct option selected order payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
