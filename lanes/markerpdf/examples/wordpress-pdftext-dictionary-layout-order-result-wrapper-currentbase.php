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

$path = sys_get_temp_dir() . '/markerpdf-result-wrapper-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% typed result-wrapper pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(501, [
                ['text' => 'Typed result-wrapper cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(502, [
                ['text' => 'Second result-wrapper import column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First result-wrapper import column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
            $page(503, [
                ['text' => 'Typed result-wrapper appendix should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['layout_result' => ['document_page' => 501], 'image' => 'cover-layout-render'],
                ['layout_result' => ['document_page' => 502], 'image' => 'selected-layout-render'],
            ],
            'layout_results' => [
                [
                    'layout_result' => [
                        'document_page' => 501,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                    ],
                    'raw_payload' => 'hidden cover layout result-wrapper payload',
                ],
                [
                    'layout_result' => [
                        'document_page' => 502,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                    ],
                    'raw_payload' => 'hidden selected layout result-wrapper payload',
                ],
            ],
            'order_images' => [
                ['order_result' => ['document_page' => 501], 'image' => 'cover-order-render'],
                ['order_result' => ['document_page' => 502], 'image' => 'selected-order-render'],
            ],
            'order_results' => [
                [
                    'order_result' => [
                        'document_page' => 501,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ],
                    ],
                    'raw_payload' => 'hidden cover order result-wrapper payload',
                ],
                [
                    'order_result' => [
                        'document_page' => 502,
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                    ],
                    'raw_payload' => 'hidden selected order result-wrapper payload',
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
$firstBeforeSecond = strpos($text, 'First result-wrapper import column.') < strpos($text, 'Second result-wrapper import column.');
$payloadExcluded = !str_contains($encoded, 'hidden cover layout result-wrapper payload')
    && !str_contains($encoded, 'hidden selected layout result-wrapper payload')
    && !str_contains($encoded, 'hidden cover order result-wrapper payload')
    && !str_contains($encoded, 'hidden selected order result-wrapper payload');

if (
    ($result['metadata']['layout_plan']['assigned_pages'] ?? null) !== 1
    || ($result['metadata']['order_plan']['assigned_pages'] ?? null) !== 1
    || !$firstBeforeSecond
    || !$payloadExcluded
    || str_contains($text, 'Typed result-wrapper cover should not import.')
    || str_contains($text, 'Typed result-wrapper appendix should not import.')
) {
    throw new RuntimeException('Expected typed layout/order result wrappers to align to the selected pdftext page without exposing wrapper payloads.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-result-wrapper-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-result-wrapper-currentbase',
    'source_truth' => 'markerPDF zips Surya LayoutResult and OrderResult objects to selected pdftext pages; native adapters may wrap those typed result payloads but must still align them before WordPress import',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifact_count' => $result['metadata']['layout_plan']['layout_result_count'] ?? null,
    'layout_assigned_pages' => $result['metadata']['layout_plan']['assigned_pages'] ?? null,
    'order_artifact_count' => $result['metadata']['order_plan']['order_result_count'] ?? null,
    'order_assigned_pages' => $result['metadata']['order_plan']['assigned_pages'] ?? null,
    'first_before_second' => $firstBeforeSecond,
    'cover_excluded' => !str_contains($text, 'Typed result-wrapper cover should not import.'),
    'appendix_excluded' => !str_contains($text, 'Typed result-wrapper appendix should not import.'),
    'payload_excluded' => $payloadExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
