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

$payload = static function (int $position = 1): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => $position, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => $position === 1 ? 2 : 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ],
        'raw_payload' => 'duplicate page-key layout/order payload should stay hidden',
    ];
};

$path = sys_get_temp_dir() . '/markerpdf-layout-order-duplicate-page-key-smoke-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% duplicate normalized layout order page-key smoke\n%%EOF");

$converter = new SuppliedDocumentConverter();
$pdftextPages = [
    'dictionary_output' => [
        '+9910.0' => $page(9910, [
            ['text' => 'Duplicate page-key WordPress cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
        ]),
        '+9911.0' => $page(9911, [
            ['text' => 'Second duplicate page-key WordPress body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
            ['text' => 'First duplicate page-key WordPress heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
        ]),
    ],
];

$lowresImages = [[
    'dictionary_output' => [
        '+9910.0' => ['image' => 'duplicate-page-key-cover-render'],
        '+9911.0' => ['image' => 'duplicate-page-key-selected-render'],
    ],
]];
$uniqueLayoutResults = [[
    'dictionary_output' => [
        '+9910.0' => $payload(2),
        '+9911.0' => [
            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
            'bboxes' => [
                ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
            ],
        ],
    ],
]];
$uniqueOrderResults = [[
    'dictionary_output' => [
        '+9910.0' => $payload(2),
        '+9911.0' => $payload(1),
    ],
]];
$duplicatePageKeyArtifacts = [[
    'dictionary_output' => [
        '09911' => $payload(1),
        '+9911.0' => $payload(2),
    ],
]];
$settings = new MarkerSettings(['EXTRACT_IMAGES' => false]);
$baseOptions = [
    'metadata' => ['languages' => ['English']],
    'max_pages' => 1,
    'start_page' => 1,
    'lowres_images' => $lowresImages,
];

$duplicateLayoutRejected = false;
$duplicateOrderRejected = false;

try {
    try {
        $converter->convert(
            $path,
            $pdftextPages,
            $baseOptions + [
                'layout_results' => $duplicatePageKeyArtifacts,
                'order_images' => $lowresImages,
                'order_results' => $uniqueOrderResults,
            ],
            $settings
        );
    } catch (InvalidArgumentException) {
        $duplicateLayoutRejected = true;
    }

    try {
        $converter->convert(
            $path,
            $pdftextPages,
            $baseOptions + [
                'layout_results' => $uniqueLayoutResults,
                'order_images' => $lowresImages,
                'order_results' => $duplicatePageKeyArtifacts,
            ],
            $settings
        );
    } catch (InvalidArgumentException) {
        $duplicateOrderRejected = true;
    }

    $result = $converter->convert(
        $path,
        $pdftextPages,
        $baseOptions + [
            'layout_results' => $uniqueLayoutResults,
            'order_images' => $lowresImages,
            'order_results' => $uniqueOrderResults,
        ],
        $settings
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$headingPosition = strpos($text, '# First Duplicate Page-Key Wordpress Heading.');
$bodyPosition = strpos($text, 'Second duplicate page-key WordPress body.');
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-duplicate-page-key-currentbase',
    'source_truth' => 'markerPDF consumes one layout/order prediction per selected pdftext page; native source-page keyed artifact maps with duplicate normalized keys are ambiguous and fail before WordPress import',
    'duplicate_layout_artifact_keys_rejected' => $duplicateLayoutRejected,
    'duplicate_order_artifact_keys_rejected' => $duplicateOrderRejected,
    'unique_keyed_layout_imported' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'unique_keyed_order_imported' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'selected_page_range' => $result['metadata']['page_range'] ?? [],
    'heading_before_body' => $headingPosition !== false && $bodyPosition !== false && $headingPosition < $bodyPosition,
    'cover_excluded' => !str_contains($text, 'Duplicate page-key WordPress cover should stay skipped.'),
    'payload_excluded' => !str_contains($encoded, 'duplicate page-key layout/order payload')
        && !str_contains($encoded, '__markerpdf_envelope_page_key_marker'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['duplicate_layout_artifact_keys_rejected']
    || !$flags['duplicate_order_artifact_keys_rejected']
    || !$flags['unique_keyed_layout_imported']
    || !$flags['unique_keyed_order_imported']
    || !$flags['heading_before_body']
    || !$flags['cover_excluded']
    || !$flags['payload_excluded']
) {
    throw new RuntimeException('Expected duplicate normalized layout/order artifact page keys to fail while unique maps import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-duplicate-page-key-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
