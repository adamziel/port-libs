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

$layoutArtifact = static function (int $stalePage): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
        'page_map' => [
            (string) $stalePage => [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                ],
                'raw_payload' => 'stale page_map direct owner layout row must stay hidden',
            ],
        ],
        'raw_payload' => 'stale page_map direct owner layout payload must stay hidden',
    ];
};

$orderArtifact = static function (int $stalePage): array {
    return [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ],
        'pageMap' => [
            (string) $stalePage => [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                ],
                'raw_payload' => 'stale pageMap direct owner order row must stay hidden',
            ],
        ],
        'raw_payload' => 'stale pageMap direct owner order payload must stay hidden',
    ];
};

$path = sys_get_temp_dir() . '/markerpdf-page-map-direct-payload-owner-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% page_map direct payload owner WordPress smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(13200, [
                ['text' => 'Stale page_map owner cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(13201, [
                ['text' => 'Second direct owner WordPress body stays source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First direct owner WordPress heading has no trusted layout.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [[
                'image' => 'stale-page-map-owner-layout-render',
                'page_map' => [
                    '13200' => ['image' => 'stale-page-map-owner-layout-render'],
                ],
            ]],
            'layout_results' => [$layoutArtifact(13200)],
            'order_images' => [[
                'image' => 'stale-page-map-owner-order-render',
                'pageMap' => [
                    '13200' => ['image' => 'stale-page-map-owner-order-render'],
                ],
            ]],
            'order_results' => [$orderArtifact(13200)],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$text = $result['text'];
$metadata = $result['metadata'];

$sourceOrderPreserved = str_contains($text, 'Second direct owner WordPress body stays source ordered.')
    && str_contains($text, 'First direct owner WordPress heading has no trusted layout.')
    && strpos($text, 'Second direct owner WordPress body stays source ordered.') < strpos($text, 'First direct owner WordPress heading has no trusted layout.');
$suppliedArtifactsExcluded = ($metadata['supplied_boundaries'] ?? []) === []
    && !array_key_exists('layout_plan', $metadata)
    && !array_key_exists('order_plan', $metadata);
$stalePayloadExcluded = !str_contains($text, 'Stale page_map owner cover should not import.')
    && !str_contains($encoded, 'stale page_map direct owner layout')
    && !str_contains($encoded, 'stale pageMap direct owner order')
    && !str_contains($encoded, '__markerpdf_envelope_page_key_marker');

if (!$sourceOrderPreserved || !$suppliedArtifactsExcluded || !$stalePayloadExcluded) {
    throw new RuntimeException('Expected stale page_map/pageMap direct-payload owners to be excluded before WordPress import.');
}

$paragraph = trim(str_replace("\n\n", ' ', $text));
echo '<!-- wp:paragraph {"metadata":{"markerpdfPage":13201}} -->' . "\n";
echo '<p>' . htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- markerpdf-pdftext-dictionary-layout-order-page-map-direct-payload-owner-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-page-map-direct-payload-owner-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; native direct-payload page_map/pageMap owners must match the selected page before assignment',
    'page_range' => $metadata['page_range'] ?? [],
    'selected_page' => 13201,
    'supplied_artifacts_excluded' => $suppliedArtifactsExcluded,
    'source_order_preserved_without_matching_order' => $sourceOrderPreserved,
    'stale_payload_excluded' => $stalePayloadExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
