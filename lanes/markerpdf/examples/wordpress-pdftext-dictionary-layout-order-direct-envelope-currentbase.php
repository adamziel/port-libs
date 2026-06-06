<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

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

$path = sys_get_temp_dir() . '/markerpdf-direct-envelope-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% direct envelope pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(4910, [
                ['text' => 'Direct envelope cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(4911, [
                ['text' => 'Second direct envelope WordPress body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First direct envelope WordPress heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => 4911, 'image' => 'direct-envelope-layout-render'],
            ],
            'layout_results' => [[
                'page' => 4911,
                'pages' => [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'direct pages title layout row payload must stay hidden'],
                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                    'raw_payload' => 'direct pages layout payload must stay hidden',
                ],
                'raw_payload' => 'direct pages layout envelope payload must stay hidden',
            ]],
            'order_images' => [
                ['page' => 4911, 'image' => 'direct-envelope-order-render'],
            ],
            'order_results' => [[
                'page' => 4911,
                'dictionary_output' => [
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'direct dictionary-output order row payload must stay hidden'],
                    ],
                    'raw_payload' => 'direct dictionary-output order payload must stay hidden',
                ],
                'raw_payload' => 'direct dictionary-output order envelope payload must stay hidden',
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

foreach (preg_split('/\R{2,}/', trim($result['text'])) ?: [] as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
echo '<!-- markerpdf-pdftext-dictionary-layout-order-direct-envelope-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-direct-envelope-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; native supplied-boundary adapters may keep selected page identity on the outer artifact while storing one layout/order payload under pages or dictionary_output envelopes',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'direct_pages_payload_unwrapped' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'direct_dictionary_output_payload_unwrapped' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'heading_promoted' => str_contains($text, '# First Direct Envelope Wordpress Heading.'),
    'heading_before_body' => strpos($text, '# First Direct Envelope Wordpress Heading.') < strpos($text, 'Second direct envelope WordPress body.'),
    'cover_excluded' => !str_contains($text, 'Direct envelope cover should stay skipped.'),
    'payload_excluded' => !str_contains($encoded, 'direct pages layout payload')
        && !str_contains($encoded, 'direct dictionary-output order payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
