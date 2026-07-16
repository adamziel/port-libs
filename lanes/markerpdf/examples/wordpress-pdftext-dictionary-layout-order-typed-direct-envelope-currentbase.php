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

$path = sys_get_temp_dir() . '/markerpdf-typed-direct-envelope-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% typed direct envelope pdftext layout order current-base smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(5020, [
                ['text' => 'Typed direct-envelope cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(5021, [
                ['text' => 'Second typed direct-envelope WordPress body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First typed direct-envelope WordPress heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['metadata' => ['document_page' => 5021], 'image' => 'typed-direct-envelope-layout-render'],
            ],
            'layout_results' => [[
                'metadata' => ['document_page' => 5021],
                'layout_result' => [
                    'pages' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                        ],
                        'raw_payload' => 'typed direct-envelope selected layout payload must stay hidden',
                    ],
                ],
                'raw_payload' => 'typed direct-envelope layout envelope payload must stay hidden',
            ]],
            'order_images' => [
                ['metadata' => ['document_page' => 5021], 'image' => 'typed-direct-envelope-order-render'],
            ],
            'order_results' => [[
                'metadata' => ['document_page' => 5021],
                'order_result' => [
                    'dictionary_output' => [
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                        ],
                        'raw_payload' => 'typed direct-envelope selected order payload must stay hidden',
                    ],
                ],
                'raw_payload' => 'typed direct-envelope order envelope payload must stay hidden',
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
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-typed-direct-envelope-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages, then zips layout/order model result objects with selected Marker pages; native supplied-boundary adapters may store typed result objects whose geometry is wrapped under pages or dictionary_output envelopes',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'typed_layout_direct_envelope_unwrapped' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'typed_order_direct_envelope_unwrapped' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'heading_promoted' => str_contains($text, '# First Typed Direct-Envelope Wordpress Heading.'),
    'body_preserved' => str_contains($text, 'Second typed direct-envelope WordPress body.'),
    'heading_before_body' => strpos($text, '# First Typed Direct-Envelope Wordpress Heading.') < strpos($text, 'Second typed direct-envelope WordPress body.'),
    'cover_excluded' => !str_contains($text, 'Typed direct-envelope cover should not import.'),
    'payload_excluded' => !str_contains($encoded, 'typed direct-envelope selected layout payload')
        && !str_contains($encoded, 'typed direct-envelope layout envelope payload')
        && !str_contains($encoded, 'typed direct-envelope selected order payload')
        && !str_contains($encoded, 'typed direct-envelope order envelope payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['typed_layout_direct_envelope_unwrapped']
    || !$flags['typed_order_direct_envelope_unwrapped']
    || !$flags['heading_promoted']
    || !$flags['body_preserved']
    || !$flags['heading_before_body']
    || !$flags['cover_excluded']
    || !$flags['payload_excluded']
) {
    throw new RuntimeException('Expected typed direct-envelope supplied layout/order payloads to align to the selected pdftext page: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $block) {
    $block = trim($block);
    if ($block === '') {
        continue;
    }

    if (str_starts_with($block, '# ')) {
        echo "<!-- wp:heading {\"level\":1} -->\n";
        echo '<h1>' . htmlspecialchars(substr($block, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h1>\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-typed-direct-envelope-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
