<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdftextPage = static function (int $page, array $lines): array {
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

$jsonEnvelope = static fn (array $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$layoutPayloads = [
    [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'first unmarked layout row payload must stay hidden'],
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
        'raw_payload' => 'first unmarked layout payload must stay hidden',
    ],
    [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0], 'raw_payload' => 'second unmarked layout row payload must stay hidden'],
        ],
        'raw_payload' => 'second unmarked layout payload must stay hidden',
    ],
];

$orderPayloads = [
    [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'first unmarked order row payload must stay hidden'],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ],
        'raw_payload' => 'first unmarked order payload must stay hidden',
    ],
    [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0], 'raw_payload' => 'second unmarked order row payload must stay hidden'],
        ],
        'raw_payload' => 'second unmarked order payload must stay hidden',
    ],
];

$path = sys_get_temp_dir() . '/markerpdf-typed-payload-list-layout-order-smoke-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% typed payload-list layout-order smoke\n%%EOF");

try {
    $converted = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $pdftextPage(17200, [
                ['text' => 'Typed payload-list cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $pdftextPage(17201, [
                ['text' => 'Second typed payload-list body remains source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First typed payload-list text has no trusted layout.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['metadata' => ['page' => 17201], 'image' => 'typed-payload-list-layout-render'],
            ],
            'layout_results' => [[
                'metadata' => ['page' => 17201],
                'layout_result' => [
                    'pages' => $jsonEnvelope($layoutPayloads),
                ],
                'raw_payload' => 'outer layout wrapper payload must stay hidden',
            ]],
            'order_images' => [
                ['metadata' => ['page' => 17201], 'image' => 'typed-payload-list-order-render'],
            ],
            'order_results' => [[
                'metadata' => ['page' => 17201],
                'order_result' => [
                    'dictionary_output' => $jsonEnvelope($orderPayloads),
                ],
                'raw_payload' => 'outer order wrapper payload must stay hidden',
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$encoded = json_encode($converted, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$text = $converted['text'];
$sourceOrderPreserved = str_contains($text, 'Second typed payload-list body remains source ordered.')
    && str_contains($text, 'First typed payload-list text has no trusted layout.')
    && strpos($text, 'Second typed payload-list body remains source ordered.') < strpos($text, 'First typed payload-list text has no trusted layout.');
$payloadExcluded = !str_contains($encoded, 'unmarked layout payload')
    && !str_contains($encoded, 'unmarked layout row payload')
    && !str_contains($encoded, 'unmarked order payload')
    && !str_contains($encoded, 'unmarked order row payload')
    && !str_contains($encoded, 'outer layout wrapper payload')
    && !str_contains($encoded, 'outer order wrapper payload');
$coverExcluded = !str_contains($text, 'Typed payload-list cover should stay skipped.');
$headingNotPromoted = !str_contains($text, '# First Typed Payload-List Text Has No Trusted Layout.');
$layoutRejected = ($converted['metadata']['layout_plan']['assigned_pages'] ?? null) === 0;
$orderRejected = ($converted['metadata']['order_plan']['assigned_pages'] ?? null) === 0;

if (!$sourceOrderPreserved || !$payloadExcluded || !$coverExcluded || !$headingNotPromoted || !$layoutRejected || !$orderRejected) {
    throw new RuntimeException('Expected ambiguous typed JSON-list layout/order payloads to fail closed before WordPress import promotion.');
}

echo json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-typed-payload-list-currentbase',
    'native_boundary' => 'typed layout_result/order_result wrappers cannot assign a multi-entry unmarked direct JSON-list payload only because the outer wrapper metadata matches the selected pdftext page',
    'supplied_boundaries' => $converted['metadata']['supplied_boundaries'] ?? [],
    'page_range' => $converted['metadata']['page_range'] ?? [],
    'layout_assigned_pages_zero' => $layoutRejected,
    'order_assigned_pages_zero' => $orderRejected,
    'source_order_preserved' => $sourceOrderPreserved,
    'heading_not_promoted' => $headingNotPromoted,
    'payload_excluded' => $payloadExcluded,
    'cover_excluded' => $coverExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'wordpress_blocks' => [
        ['blockName' => 'core/paragraph', 'innerHTML' => '<p>Second typed payload-list body remains source ordered. First typed payload-list text has no trusted layout.</p>'],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
