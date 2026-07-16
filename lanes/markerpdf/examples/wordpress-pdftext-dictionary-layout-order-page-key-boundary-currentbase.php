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

$layoutPayload = static function (): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
        'raw_payload' => 'invalid page-key WordPress layout payload should stay hidden',
    ];
};

$orderPayload = static function (int $position = 1): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => $position, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => $position === 1 ? 2 : 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ],
        'raw_payload' => 'invalid page-key WordPress order payload should stay hidden',
    ];
};

$path = sys_get_temp_dir() . '/markerpdf-layout-order-page-key-boundary-smoke-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% layout order page-key boundary smoke\n%%EOF");

$converter = new SuppliedDocumentConverter();
$settings = new MarkerSettings(['EXTRACT_IMAGES' => false]);
$pdftextPages = [
    'dictionary_output' => [
        '0' => $page(0, [
            ['text' => 'Second page-key WordPress body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
            ['text' => 'First page-key WordPress heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
        ]),
    ],
];
$lowresImages = [[
    'dictionary_output' => [
        '-0.0' => ['image' => 'page-key-boundary-layout-render'],
    ],
]];
$validLayoutResults = [[
    'dictionary_output' => [
        '-0.0' => $layoutPayload(),
    ],
]];
$validOrderResults = [[
    'dictionary_output' => [
        '-0.0' => $orderPayload(1),
    ],
]];
$overflowOrderResults = [[
    'dictionary_output' => [
        (string) PHP_INT_MAX . '0' => $orderPayload(2),
        '0' => $orderPayload(1),
    ],
]];
$negativeLayoutResults = [[
    'dictionary_output' => [
        '-1' => $layoutPayload(),
        '0' => $layoutPayload(),
    ],
]];
$negativeOrderResults = [[
    'dictionary_output' => [
        '-1.0' => $orderPayload(2),
        '0' => $orderPayload(1),
    ],
]];
$baseOptions = [
    'metadata' => ['languages' => ['English']],
    'max_pages' => 1,
    'lowres_images' => $lowresImages,
];

$overflowOrderRejected = false;
$negativeLayoutRejected = false;
$negativeOrderRejected = false;

try {
    try {
        $converter->convert(
            $path,
            $pdftextPages,
            $baseOptions + [
                'layout_results' => $validLayoutResults,
                'order_images' => $lowresImages,
                'order_results' => $overflowOrderResults,
            ],
            $settings
        );
    } catch (InvalidArgumentException) {
        $overflowOrderRejected = true;
    }

    try {
        $converter->convert(
            $path,
            $pdftextPages,
            $baseOptions + [
                'layout_results' => $negativeLayoutResults,
                'order_images' => $lowresImages,
                'order_results' => $validOrderResults,
            ],
            $settings
        );
    } catch (InvalidArgumentException) {
        $negativeLayoutRejected = true;
    }

    try {
        $converter->convert(
            $path,
            $pdftextPages,
            $baseOptions + [
                'layout_results' => $validLayoutResults,
                'order_images' => $lowresImages,
                'order_results' => $negativeOrderResults,
            ],
            $settings
        );
    } catch (InvalidArgumentException) {
        $negativeOrderRejected = true;
    }

    $result = $converter->convert(
        $path,
        $pdftextPages,
        $baseOptions + [
            'layout_results' => $validLayoutResults,
            'order_images' => $lowresImages,
            'order_results' => $validOrderResults,
        ],
        $settings
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$headingPosition = strpos($text, '# First Page-Key Wordpress Heading.');
$bodyPosition = strpos($text, 'Second page-key WordPress body.');
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-page-key-boundary-currentbase',
    'source_truth' => 'markerPDF zips one supplied layout/order prediction to each selected pdftext page; invalid source-page artifact map keys must fail before stale sidecars can be assigned',
    'overflow_order_key_rejected' => $overflowOrderRejected,
    'negative_layout_key_rejected' => $negativeLayoutRejected,
    'negative_order_key_rejected' => $negativeOrderRejected,
    'zero_keyed_layout_imported' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'zero_keyed_order_imported' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'selected_page_range' => $result['metadata']['page_range'] ?? [],
    'heading_before_body' => $headingPosition !== false && $bodyPosition !== false && $headingPosition < $bodyPosition,
    'payload_excluded' => !str_contains($encoded, 'invalid page-key WordPress layout payload')
        && !str_contains($encoded, 'invalid page-key WordPress order payload')
        && !str_contains($encoded, '__markerpdf_envelope_page_key_marker'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_pdftext' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['overflow_order_key_rejected']
    || !$flags['negative_layout_key_rejected']
    || !$flags['negative_order_key_rejected']
    || !$flags['zero_keyed_layout_imported']
    || !$flags['zero_keyed_order_imported']
    || !$flags['heading_before_body']
    || !$flags['payload_excluded']
) {
    throw new RuntimeException('Expected invalid layout/order artifact page keys to reject while zero-key maps import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-page-key-boundary-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
