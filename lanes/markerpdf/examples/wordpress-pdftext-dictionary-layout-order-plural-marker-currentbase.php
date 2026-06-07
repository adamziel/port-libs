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

$path = sys_get_temp_dir() . '/markerpdf-plural-marker-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% plural marker pdftext layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(5600, [
                ['text' => 'Plural marker smoke cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(5601, [
                ['text' => 'Second plural marker first selected page stays source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First plural marker first selected page has no supplied order.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
            $page(5602, [
                ['text' => 'Second plural marker second selected page body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First plural marker second selected page title.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
            $page(5603, [
                ['text' => 'Plural marker smoke appendix should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 2,
            'start_page' => 1,
            'lowres_images' => [
                ['metadata' => ['selected_page_numbers' => [2]], 'image' => 'plural-marker-layout-render'],
            ],
            'layout_results' => [[
                'metadata' => ['selected_page_numbers' => [2]],
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0], 'raw_payload' => 'plural marker title layout payload must stay hidden'],
                    ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                ],
                'raw_payload' => 'plural marker layout payload must stay hidden',
            ]],
            'order_images' => [
                ['metadata' => ['selected_page_numbers' => [2]], 'image' => 'plural-marker-order-render'],
            ],
            'order_results' => [[
                'metadata' => ['selected_page_numbers' => [2]],
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                    ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0], 'raw_payload' => 'plural marker order row payload must stay hidden'],
                ],
                'raw_payload' => 'plural marker order payload must stay hidden',
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
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-plural-marker-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; native supplied adapters with plural selected_page_numbers markers must attach sparse artifacts to the post-trim selected page before zip-style ordering',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'assigned_only_second_selected_page' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1
        && ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1
        && ($result['metadata']['layout_plan']['page_count'] ?? null) === 2
        && ($result['metadata']['order_plan']['page_count'] ?? null) === 2,
    'first_selected_page_source_order_preserved' => strpos($text, 'Second plural marker first selected page stays source ordered.') < strpos($text, 'First plural marker first selected page has no supplied order.'),
    'second_selected_page_reordered' => strpos($text, 'First plural marker second selected page title.') < strpos($text, 'Second plural marker second selected page body.'),
    'cover_excluded' => !str_contains($text, 'Plural marker smoke cover should not import.'),
    'appendix_excluded' => !str_contains($text, 'Plural marker smoke appendix should not import.'),
    'payloads_excluded' => !str_contains($encoded, 'plural marker title layout payload')
        && !str_contains($encoded, 'plural marker layout payload')
        && !str_contains($encoded, 'plural marker order row payload')
        && !str_contains($encoded, 'plural marker order payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'layout_artifacts_trimmed',
    'order_artifacts_trimmed',
    'assigned_only_second_selected_page',
    'first_selected_page_source_order_preserved',
    'second_selected_page_reordered',
    'cover_excluded',
    'appendix_excluded',
    'payloads_excluded',
] as $flag) {
    if (!$flags[$flag]) {
        throw new RuntimeException('Expected plural marker supplied-boundary flag to pass: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-plural-marker-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
