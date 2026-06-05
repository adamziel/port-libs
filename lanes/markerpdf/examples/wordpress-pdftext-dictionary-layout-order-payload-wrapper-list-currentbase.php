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

$path = sys_get_temp_dir() . '/markerpdf-payload-wrapper-list-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% typed payload wrapper-list pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(2600, [
                ['text' => 'Typed payload-list cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(2601, [
                ['text' => 'Second typed payload-list WordPress column remains source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First typed payload-list WordPress column has no trusted model payload.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['metadata' => ['document_page' => 2601], 'image' => 'typed-payload-list-layout-render'],
            ],
            'layout_results' => [[
                'metadata' => ['document_page' => 2601],
                'layout_result' => [
                    [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                    ],
                    ['raw_payload' => 'multi-dictionary typed layout payload must not be selected'],
                ],
            ]],
            'order_images' => [
                ['metadata' => ['document_page' => 2601], 'image' => 'typed-payload-list-order-render'],
            ],
            'order_results' => [[
                'metadata' => ['document_page' => 2601],
                'order_result' => [
                    [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                    ],
                    ['raw_payload' => 'multi-dictionary typed order payload must not be selected'],
                ],
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-payload-wrapper-list-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-payload-wrapper-list-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; native supplied-boundary adapters may serialize typed model payload wrappers, but multi-dictionary payload wrappers are ambiguous and remain unassigned',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_payload_wrapper_list_rejected' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 0,
    'order_payload_wrapper_list_rejected' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 0,
    'layout_artifact_review_count' => $result['metadata']['layout_plan']['layout_result_count'] ?? null,
    'order_artifact_review_count' => $result['metadata']['order_plan']['order_result_count'] ?? null,
    'source_order_preserved' => strpos($text, 'Second typed payload-list WordPress column remains source ordered.') < strpos($text, 'First typed payload-list WordPress column has no trusted model payload.'),
    'cover_excluded' => !str_contains($text, 'Typed payload-list cover page should not import.'),
    'payload_excluded' => !str_contains($encoded, 'multi-dictionary typed layout payload') && !str_contains($encoded, 'multi-dictionary typed order payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
