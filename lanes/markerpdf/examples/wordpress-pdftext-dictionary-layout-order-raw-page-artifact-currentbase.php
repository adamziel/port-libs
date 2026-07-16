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

$rawPdftextArtifactMap = [
    11420 => $page(11420, [
        ['text' => 'Raw sidecar cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
    ]),
    11421 => $page(11421, [
        ['text' => 'Raw sidecar selected page text must stay out of layout metadata.', 'bbox' => [72.0, 170.0, 520.0, 184.0]],
    ]),
];

$path = sys_get_temp_dir() . '/markerpdf-raw-pdftext-artifact-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% raw pdftext page artifact layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(11420, [
                ['text' => 'Source cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(11421, [
                ['text' => 'Second raw artifact body remains untyped.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First raw artifact heading remains untyped.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => $rawPdftextArtifactMap,
            'layout_results' => $rawPdftextArtifactMap,
            'order_images' => $rawPdftextArtifactMap,
            'order_results' => $rawPdftextArtifactMap,
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$rawArtifactsRejected = !isset($result['metadata']['layout_plan'])
    && !isset($result['metadata']['order_plan'])
    && ($result['metadata']['supplied_boundaries'] ?? []) === [];

if (!$rawArtifactsRejected
    || str_contains($text, 'Source cover should stay skipped.')
    || str_contains($text, '# First Raw Artifact Heading Remains Untyped.')
    || str_contains($encoded, 'Raw sidecar selected page text must stay out')
) {
    throw new RuntimeException('Expected raw pdftext page sidecars to stay out of layout/order artifact assignment.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-raw-page-artifact-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-raw-page-artifact-currentbase',
    'source_truth' => 'markerPDF keeps pdftext.dictionary_output pages separate from Surya layout/order predictions; raw pdftext page maps are not layout/order artifacts',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'raw_pdftext_sidecars_rejected' => $rawArtifactsRejected,
    'layout_plan_absent' => !isset($result['metadata']['layout_plan']),
    'order_plan_absent' => !isset($result['metadata']['order_plan']),
    'raw_sidecar_text_hidden' => !str_contains($encoded, 'Raw sidecar selected page text must stay out'),
    'cover_excluded' => !str_contains($text, 'Source cover should stay skipped.'),
    'first_heading_not_promoted' => !str_contains($text, '# First Raw Artifact Heading Remains Untyped.'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
