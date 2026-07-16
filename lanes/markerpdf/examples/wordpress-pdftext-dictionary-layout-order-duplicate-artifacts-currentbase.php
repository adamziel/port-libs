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

$pdftextPages = [
    $page(4100, [
        ['text' => 'Duplicate-artifact cover page should not import.', 'bbox' => [72.0, 80.0, 320.0, 94.0]],
    ]),
    $page(4101, [
        ['text' => 'Second duplicate artifact column stays source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
        ['text' => 'First duplicate artifact has no trusted model order.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
    ]),
];

$layoutResults = [
    [
        'metadata' => ['document_page' => 4101],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
        'raw_payload' => 'first duplicate layout payload must stay hidden',
    ],
    [
        'metadata' => ['document_page' => 4101],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Title', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
        'raw_payload' => 'second duplicate layout payload must stay hidden',
    ],
];

$orderResults = [
    [
        'metadata' => ['document_page' => 4101],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ],
        'raw_payload' => 'first duplicate order payload must stay hidden',
    ],
    [
        'metadata' => ['document_page' => 4101],
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
        ],
        'raw_payload' => 'second duplicate order payload must stay hidden',
    ],
];

$path = sys_get_temp_dir() . '/markerpdf-duplicate-artifact-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% duplicate artifact pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        $pdftextPages,
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['metadata' => ['document_page' => 4101], 'image' => 'first-duplicate-layout-render'],
                ['metadata' => ['document_page' => 4101], 'image' => 'second-duplicate-layout-render'],
            ],
            'layout_results' => $layoutResults,
            'order_images' => [
                ['metadata' => ['document_page' => 4101], 'image' => 'first-duplicate-order-render'],
                ['metadata' => ['document_page' => 4101], 'image' => 'second-duplicate-order-render'],
            ],
            'order_results' => $orderResults,
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

if (str_contains($text, 'Duplicate-artifact cover page should not import.')) {
    throw new RuntimeException('Expected skipped cover text to remain outside WordPress paragraphs.');
}
if (strpos($text, 'Second duplicate artifact column stays source ordered.') > strpos($text, 'First duplicate artifact has no trusted model order.')) {
    throw new RuntimeException('Expected duplicate matching model artifacts to preserve source-order text.');
}
foreach (['first duplicate layout payload', 'second duplicate layout payload', 'first duplicate order payload', 'second duplicate order payload'] as $payloadMarker) {
    if (str_contains($encoded, $payloadMarker)) {
        throw new RuntimeException('Expected duplicate artifact payloads to remain hidden from import metadata.');
    }
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-duplicate-artifacts-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-duplicate-artifacts-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; each rendered selected page has one layout/order result, so duplicate keyed native artifacts for the same page are ambiguous and must fail closed',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'layout_plan_present' => array_key_exists('layout_plan', $result['metadata'] ?? []),
    'order_plan_present' => array_key_exists('order_plan', $result['metadata'] ?? []),
    'source_order_preserved' => strpos($text, 'Second duplicate artifact column stays source ordered.') < strpos($text, 'First duplicate artifact has no trusted model order.'),
    'duplicate_payloads_excluded' => !str_contains($encoded, 'duplicate layout payload') && !str_contains($encoded, 'duplicate order payload'),
    'cover_excluded' => !str_contains($text, 'Duplicate-artifact cover page should not import.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
