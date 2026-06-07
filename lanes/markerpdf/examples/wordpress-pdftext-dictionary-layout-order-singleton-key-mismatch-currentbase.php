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

$path = sys_get_temp_dir() . '/markerpdf-singleton-key-mismatch-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% singleton source-key mismatch pdftext layout order smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(5520, [
                ['text' => 'Singleton-key mismatch cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(5521, [
                ['text' => 'Second singleton-key mismatch column stays source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First singleton-key mismatch column has no supplied order.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [[
                'pages' => [
                    '5520' => ['image' => 'singleton-key-stale-layout-render'],
                ],
            ]],
            'layout_results' => [[
                'pages' => [
                    '5520' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'singleton stale layout payload must stay hidden',
                    ],
                ],
                'raw_payload' => 'singleton stale layout wrapper payload must stay hidden',
            ]],
            'order_images' => [[
                'dictionary_output' => [
                    '5520' => ['image' => 'singleton-key-stale-order-render'],
                ],
            ]],
            'order_results' => [[
                'dictionary_output' => [
                    '5520' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                        'raw_payload' => 'singleton stale order payload must stay hidden',
                    ],
                ],
                'raw_payload' => 'singleton stale order wrapper payload must stay hidden',
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-singleton-key-mismatch-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before zip-style layout/order assignment; singleton keyed supplied payloads must keep their source-page key through native selection',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'singleton_key_mismatch_rejected' => ($result['metadata']['supplied_boundaries'] ?? null) === [],
    'layout_stage_skipped' => !array_key_exists('layout_plan', $result['metadata']),
    'order_stage_skipped' => !array_key_exists('order_plan', $result['metadata']),
    'source_order_preserved' => strpos($text, 'Second singleton-key mismatch column stays source ordered.') < strpos($text, 'First singleton-key mismatch column has no supplied order.'),
    'cover_excluded' => !str_contains($text, 'Singleton-key mismatch cover should not import.'),
    'payload_excluded' => !str_contains($encoded, 'singleton stale layout payload')
        && !str_contains($encoded, 'singleton stale layout wrapper payload')
        && !str_contains($encoded, 'singleton stale order payload')
        && !str_contains($encoded, 'singleton stale order wrapper payload')
        && !str_contains($encoded, '__markerpdf_envelope_page_key_marker'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (!$flags['singleton_key_mismatch_rejected']
    || !$flags['layout_stage_skipped']
    || !$flags['order_stage_skipped']
    || !$flags['source_order_preserved']
    || !$flags['cover_excluded']
    || !$flags['payload_excluded']
) {
    throw new RuntimeException('Expected singleton keyed pdftext layout/order payload mismatch to stay unassigned: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-singleton-key-mismatch-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
