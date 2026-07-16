<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\LayoutAnnotator;
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
                        'font' => ['name' => 'Times-Roman', 'flags' => 0, 'weight' => 400, 'size' => 11.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$coverPage = $page(770, [
    ['text' => 'Trusted-layout cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);
$selectedPage = $page(771, [
    ['text' => 'Second trusted layout column is review metadata.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First trusted layout column starts the import.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);
$stalePayload = $page(770, [
    ['text' => 'Stale nested pdftext layout payload should stay hidden.', 'bbox' => [72.0, 160.0, 520.0, 176.0]],
]);

$layout = [
    'metadata' => ['document_page' => 771],
    'pdftext' => $stalePayload,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];
$order = [
    'metadata' => ['document_page' => 771],
    'pdftext' => $stalePayload,
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];

$layoutPreview = (new LayoutAnnotator())->runWithSuppliedLayouts(
    [[
        'metadata' => ['document_page' => 771],
        'pdftext' => $stalePayload,
        'image' => 'trusted-document-page-layout-render',
    ]],
    [[
        'pnum' => 771,
        'blocks' => [],
    ]],
    [$layout]
);
$layoutMetadata = $layoutPreview['pages'][0]['layout'] ?? [];
$layoutPreviewJson = json_encode($layoutPreview, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';

if (($layoutMetadata['document_page'] ?? null) !== 771 || array_key_exists('page', $layoutMetadata)) {
    throw new RuntimeException('Expected trusted document_page layout metadata to exclude stale nested pdftext.page.');
}
if (str_contains($layoutPreviewJson, 'Stale nested pdftext layout payload')) {
    throw new RuntimeException('Expected nested pdftext layout payload text to remain out of review metadata.');
}

$path = sys_get_temp_dir() . '/markerpdf-layout-payload-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% trusted layout payload pdftext boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [$coverPage, $selectedPage],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [[
                'metadata' => ['document_page' => 771],
                'pdftext' => $stalePayload,
                'image' => 'trusted-document-page-layout-render',
            ]],
            'layout_results' => [$layout],
            'order_images' => [[
                'metadata' => ['document_page' => 771],
                'pdftext' => $stalePayload,
                'image' => 'trusted-document-page-order-render',
            ]],
            'order_results' => [$order],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
if (str_contains($text, 'Trusted-layout cover page should not import.')
    || str_contains($text, 'Stale nested pdftext layout payload should stay hidden.')
    || strpos($text, 'First trusted layout column starts the import.') > strpos($text, 'Second trusted layout column is review metadata.')
) {
    throw new RuntimeException('Expected trusted layout/order metadata to preserve selected-page WordPress text only.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-layout-payload-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-layout-payload-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before supplied layout/order assignment; adapter page metadata is trusted before nested copied pdftext payloads during selected-page WordPress import',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_document_page' => $layoutMetadata['document_page'] ?? null,
    'stale_layout_pdftext_page_excluded' => !array_key_exists('page', $layoutMetadata),
    'layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'ordered_text' => [
        'first_before_second' => strpos($text, 'First trusted layout column starts the import.') < strpos($text, 'Second trusted layout column is review metadata.'),
        'cover_excluded' => !str_contains($text, 'Trusted-layout cover page should not import.'),
        'stale_payload_excluded' => !str_contains($text, 'Stale nested pdftext layout payload should stay hidden.'),
    ],
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
