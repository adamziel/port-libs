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

$layoutPayload = static function (string $label, bool $current): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => $current
            ? [
                ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => "{$label} current layout row payload must stay hidden"],
                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
            ]
            : [
                ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => "{$label} stale layout row payload must stay hidden"],
            ],
        'raw_payload' => "{$label} layout payload must stay hidden",
    ];
};

$orderPayload = static function (string $label, bool $current): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => $current
            ? [
                ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => "{$label} current order row payload must stay hidden"],
            ]
            : [
                ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => "{$label} stale order row payload must stay hidden"],
            ],
        'raw_payload' => "{$label} order payload must stay hidden",
    ];
};

$coverPage = $page(18400, [
    ['text' => 'Current-key payload cover page should not import.', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
]);
$selectedPage = $page(18401, [
    ['text' => 'Second current-key payload body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First current-key payload heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);

$path = sys_get_temp_dir() . '/markerpdf-current-key-payload-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% current-key payload layout order smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['metadata' => ['page' => 18401], 'image' => 'current-key-payload-layout-render'],
            ],
            'layout_results' => [[
                'metadata' => ['page' => 18401],
                'layout_result' => [
                    'dictionary_output' => [
                        '18401' => $layoutPayload('current-key selected', true),
                        'stale_unmarked' => $layoutPayload('current-key unmarked stale', false),
                    ],
                ],
                'raw_payload' => 'outer current-key layout wrapper payload must stay hidden',
            ]],
            'order_images' => [
                ['metadata' => ['page' => 18401], 'image' => 'current-key-payload-order-render'],
            ],
            'order_results' => [[
                'metadata' => ['page' => 18401],
                'order_result' => [
                    'dictionary_output' => [
                        '18401' => $orderPayload('current-key selected', true),
                        'stale_unmarked' => $orderPayload('current-key unmarked stale', false),
                    ],
                ],
                'raw_payload' => 'outer current-key order wrapper payload must stay hidden',
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$headingPosition = strpos($text, '# First Current-Key Payload Heading.');
$bodyPosition = strpos($text, 'Second current-key payload body.');
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-current-key-payload-currentbase',
    'source_truth' => 'markerPDF zips one layout/order prediction to each selected pdftext page; exact source-keyed nested payloads should beat stale unmarked sidecar rows inside typed adapter wrappers',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'layout_current_key_selected' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'order_current_key_selected' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'heading_before_body' => $headingPosition !== false && $bodyPosition !== false && $headingPosition < $bodyPosition,
    'cover_excluded' => !str_contains($text, 'Current-key payload cover page should not import.'),
    'payload_excluded' => !str_contains($encoded, '__markerpdf_envelope_page_key_marker')
        && !str_contains($encoded, 'current-key selected layout payload')
        && !str_contains($encoded, 'current-key unmarked stale layout payload')
        && !str_contains($encoded, 'current-key selected order payload')
        && !str_contains($encoded, 'current-key unmarked stale order payload')
        && !str_contains($encoded, 'outer current-key layout wrapper payload')
        && !str_contains($encoded, 'outer current-key order wrapper payload'),
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['layout_current_key_selected']
    || !$flags['order_current_key_selected']
    || !$flags['heading_before_body']
    || !$flags['cover_excluded']
    || !$flags['payload_excluded']
) {
    throw new RuntimeException('Expected current-key nested payload layout/order smoke flags to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-current-key-payload-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
