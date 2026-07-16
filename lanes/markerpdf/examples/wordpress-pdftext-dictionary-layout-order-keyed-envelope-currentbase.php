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

$path = sys_get_temp_dir() . '/markerpdf-keyed-envelope-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% keyed direct envelope payload pdftext layout order smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(5400, [
                ['text' => 'Keyed envelope cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(5401, [
                ['text' => 'Second keyed envelope body.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
                ['text' => 'First keyed envelope heading.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['page' => 5401, 'image' => 'keyed-envelope-layout-render'],
            ],
            'layout_results' => [[
                'page' => 5401,
                'pages' => [
                    '5401' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Title', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => 'keyed envelope layout row payload must stay hidden'],
                        ],
                        'raw_payload' => 'keyed envelope layout payload must stay hidden',
                    ],
                ],
                'raw_payload' => 'keyed envelope layout wrapper payload must stay hidden',
            ]],
            'order_images' => [
                ['page' => 5401, 'image' => 'keyed-envelope-order-render'],
            ],
            'order_results' => [[
                'page' => 5401,
                'dictionary_output' => [
                    '5401' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'keyed envelope order row payload must stay hidden'],
                            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ],
                        'raw_payload' => 'keyed envelope order payload must stay hidden',
                    ],
                ],
                'raw_payload' => 'keyed envelope order wrapper payload must stay hidden',
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$headingBeforeBody = strpos($text, '# First Keyed Envelope Heading.') < strpos($text, 'Second keyed envelope body.');
$layoutRequestedBboxes = $result['metadata']['order_plan']['requested_bboxes'][0] ?? null;
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-keyed-envelope-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; native adapters may cache one selected layout/order payload under a source-page keyed pages/dictionary_output envelope',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_keyed_payload_unwrapped' => $layoutRequestedBboxes === [[60.0, 92.0, 290.0, 150.0], [318.0, 92.0, 570.0, 150.0]],
    'order_keyed_payload_unwrapped' => $headingBeforeBody,
    'cover_excluded' => !str_contains($text, 'Keyed envelope cover should not import.'),
    'payload_excluded' => !str_contains($encoded, 'keyed envelope layout payload')
        && !str_contains($encoded, 'keyed envelope layout row payload')
        && !str_contains($encoded, 'keyed envelope layout wrapper payload')
        && !str_contains($encoded, 'keyed envelope order payload')
        && !str_contains($encoded, 'keyed envelope order row payload')
        && !str_contains($encoded, 'keyed envelope order wrapper payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (!$flags['layout_keyed_payload_unwrapped']
    || !$flags['order_keyed_payload_unwrapped']
    || !$flags['cover_excluded']
    || !$flags['payload_excluded']
) {
    throw new RuntimeException('Expected keyed direct layout/order envelopes to drive selected WordPress import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-keyed-envelope-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
