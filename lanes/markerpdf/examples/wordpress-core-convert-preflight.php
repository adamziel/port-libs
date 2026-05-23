<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\ConversionFinalizer;
use PortLibs\MarkerPDF\CorePdfConverter;
use PortLibs\MarkerPDF\MarkerSettings;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = __DIR__ . '/../fixtures/wordpress-import-content.pdf';
$page = [
    'pnum' => 0,
    'bbox' => [0.0, 0.0, 612.0, 792.0],
    'blocks' => [[
        'block_type' => 'Text',
        'bbox' => [72.0, 72.0, 540.0, 110.0],
        'lines' => [
            [
                'bbox' => [72.0, 72.0, 420.0, 84.0],
                'spans' => [[
                    'span_id' => 'wp_0',
                    'text' => 'WordPress import preflight keeps core conversion metadata.',
                    'font' => 'Times-Roman',
                    'font_weight' => 400.0,
                    'font_size' => 12.0,
                    'bbox' => [72.0, 72.0, 420.0, 84.0],
                ]],
            ],
        ],
    ]],
];

$converter = new CorePdfConverter();
$result = $converter->convertWithSuppliedPages(
    $pdf,
    [$page],
    [['title' => 'Import preflight', 'level' => 1, 'page_index' => 0]],
    static fn (array $pages): array => (new ConversionFinalizer())->finalizePages(
        $pages,
        [],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    ),
    maxPages: 1,
    startPage: 0,
    metadata: ['languages' => ['English']],
    documentPageCount: 1
);

$paragraph = trim($result['text']);

echo json_encode([
    'scenario' => 'wordpress-core-convert-preflight',
    'metadata' => $result['metadata'],
    'lowresImagePlan' => $result['context']['lowres_image_plan'],
    'block' => [
        'blockName' => 'core/paragraph',
        'innerHTML' => '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
