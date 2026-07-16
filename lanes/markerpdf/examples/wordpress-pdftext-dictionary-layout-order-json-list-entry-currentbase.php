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

$jsonArtifact = static fn (array $value): string => json_encode(
    $value,
    JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
);

$path = sys_get_temp_dir() . '/markerpdf-json-list-entry-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% JSON list-entry pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(12400, [
                ['text' => 'JSON list-entry WordPress cover should stay skipped.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(12401, [
                ['text' => 'Second converter JSON list-entry body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First converter JSON list-entry heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                $jsonArtifact([
                    'document_page' => 12401,
                    'image' => 'json-list-entry-layout-render',
                    'raw_payload' => 'json list-entry layout image payload must stay hidden',
                ]),
            ],
            'layout_results' => [
                $jsonArtifact([
                    'document_page' => 12401,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'json list-entry title layout row payload must stay hidden'],
                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                    'raw_payload' => 'json list-entry layout payload must stay hidden',
                ]),
            ],
            'order_images' => [
                $jsonArtifact([
                    'document_page' => 12401,
                    'image' => 'json-list-entry-order-render',
                    'raw_payload' => 'json list-entry order image payload must stay hidden',
                ]),
            ],
            'order_results' => [
                $jsonArtifact([
                    'document_page' => 12401,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'json list-entry right order row payload must stay hidden'],
                    ],
                    'raw_payload' => 'json list-entry order payload must stay hidden',
                ]),
            ],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$firstPosition = strpos($text, '# First Converter Json List-Entry Heading.');
$secondPosition = strpos($text, 'Second converter JSON list-entry body.');

if (str_contains($text, 'JSON list-entry WordPress cover should stay skipped.')
    || $firstPosition === false
    || $secondPosition === false
    || $firstPosition > $secondPosition
    || ($result['metadata']['page_range'] ?? null) !== [1]
    || ($result['metadata']['layout_plan']['assigned_pages'] ?? null) !== 1
    || ($result['metadata']['order_plan']['assigned_pages'] ?? null) !== 1
    || str_contains($encoded, 'json list-entry layout image payload')
    || str_contains($encoded, 'json list-entry title layout row payload')
    || str_contains($encoded, 'json list-entry layout payload')
    || str_contains($encoded, 'json list-entry order image payload')
    || str_contains($encoded, 'json list-entry right order row payload')
    || str_contains($encoded, 'json list-entry order payload')
) {
    throw new RuntimeException('Expected raw JSON list-entry artifacts to decode before selected WordPress layout/order assignment.');
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-json-list-entry-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-json-list-entry-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before zipping supplied layout/order predictions; JSONL-style adapter cache entries must decode before selected-page artifact matching',
    'support_component' => 'pdf-text-dictionary-layout-order-json-list-entry-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifacts_decoded' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_decoded' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'selected_page_ordered_by_decoded_json_list_entry' => $firstPosition !== false
        && $secondPosition !== false
        && $firstPosition < $secondPosition,
    'cover_page_excluded' => !str_contains($text, 'JSON list-entry WordPress cover should stay skipped.'),
    'payloads_excluded' => !str_contains($encoded, 'json list-entry layout image payload')
        && !str_contains($encoded, 'json list-entry layout payload')
        && !str_contains($encoded, 'json list-entry order image payload')
        && !str_contains($encoded, 'json list-entry order payload'),
    'supplied_boundaries' => $result['metadata']['supplied_boundaries'] ?? [],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
