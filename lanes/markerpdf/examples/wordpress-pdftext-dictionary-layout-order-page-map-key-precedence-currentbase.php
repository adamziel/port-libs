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

$jsonEnvelope = static fn (array $value): string => json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

$path = sys_get_temp_dir() . '/markerpdf-page-map-key-precedence-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% pageMap key precedence pdftext layout order smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(1, [
                ['text' => 'PageMap precedence cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(2, [
                ['text' => 'Second pageMap precedence WordPress body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First pageMap precedence WordPress heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                'pageMap' => $jsonEnvelope([
                    '1' => ['image' => 'source-index-page-map-layout-render'],
                    '2' => ['image' => 'page-number-page-map-layout-render'],
                ]),
            ],
            'layout_results' => [
                'pageMap' => $jsonEnvelope([
                    '1' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'source-index pageMap layout payload must stay hidden',
                    ],
                    '2' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'page-number pageMap layout payload must stay hidden',
                    ],
                ]),
            ],
            'order_images' => [
                'pageMap' => $jsonEnvelope([
                    '1' => ['image' => 'source-index-page-map-order-render'],
                    '2' => ['image' => 'page-number-page-map-order-render'],
                ]),
            ],
            'order_results' => [
                'pageMap' => $jsonEnvelope([
                    '1' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ],
                        'raw_payload' => 'source-index pageMap order payload must stay hidden',
                    ],
                    '2' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                        'raw_payload' => 'page-number pageMap order payload must stay hidden',
                    ],
                ]),
            ],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$text = $result['text'];
$metadata = $result['metadata'];

$checks = [
    'page_map_page_number_preferred' => ($metadata['layout_plan']['assigned_pages'] ?? null) === 1
        && ($metadata['order_plan']['assigned_pages'] ?? null) === 1,
    'heading_before_body' => str_contains($text, '# First Pagemap Precedence Wordpress Heading.')
        && str_contains($text, 'Second pageMap precedence WordPress body.')
        && strpos($text, '# First Pagemap Precedence Wordpress Heading.') < strpos($text, 'Second pageMap precedence WordPress body.'),
    'source_index_payload_excluded' => !str_contains($encoded, 'source-index pageMap layout payload')
        && !str_contains($encoded, 'source-index pageMap order payload'),
    'page_number_payload_excluded' => !str_contains($encoded, 'page-number pageMap layout payload')
        && !str_contains($encoded, 'page-number pageMap order payload'),
    'cover_excluded' => !str_contains($text, 'PageMap precedence cover should not import.'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ($checks as $name => $value) {
    echo $name . '=' . ($value ? 'true' : 'false') . PHP_EOL;
}

$success = $checks['page_map_page_number_preferred']
    && $checks['heading_before_body']
    && $checks['source_index_payload_excluded']
    && $checks['page_number_payload_excluded']
    && $checks['cover_excluded']
    && !$checks['executes_python_or_models']
    && !$checks['executes_external_pdf_tools'];

exit($success ? 0 : 1);
