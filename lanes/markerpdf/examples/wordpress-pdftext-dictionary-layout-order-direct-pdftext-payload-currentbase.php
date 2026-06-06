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

$path = sys_get_temp_dir() . '/markerpdf-direct-pdftext-payload-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% direct pdftext payload layout order current-base smoke\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        [
            $page(5330, [
                ['text' => 'Direct pdftext payload cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
            $page(5331, [
                ['text' => 'Second direct pdftext payload WordPress body.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
                ['text' => 'First direct pdftext payload heading.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
            ]),
            $page(5332, [
                ['text' => 'Direct pdftext payload appendix should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
            ]),
        ],
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => [
                ['metadata' => ['document_page' => 5331], 'image' => 'direct-pdftext-selected-layout-render'],
            ],
            'layout_results' => [[
                'metadata' => ['document_page' => 5331],
                'pdftext' => [
                    'page' => 6331,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['label' => 'Title', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ],
                    'raw_payload' => 'direct pdftext selected layout payload must stay hidden',
                ],
                'raw_payload' => 'direct pdftext layout envelope payload must stay hidden',
            ]],
            'order_images' => [
                ['metadata' => ['document_page' => 5331], 'image' => 'direct-pdftext-selected-order-render'],
            ],
            'order_results' => [[
                'metadata' => ['document_page' => 5331],
                'pdftext' => [
                    'page' => 6331,
                    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                    'bboxes' => [
                        ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
                        ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
                    ],
                    'raw_payload' => 'direct pdftext selected order payload must stay hidden',
                ],
                'raw_payload' => 'direct pdftext order envelope payload must stay hidden',
            ]],
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$heading = '# First Direct Pdftext Payload Heading.';
$body = 'Second direct pdftext payload WordPress body.';
$headingPosition = strpos($text, $heading);
$bodyPosition = strpos($text, $body);
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-direct-pdftext-payload-currentbase',
    'source_truth' => 'markerPDF trims searchable-PDF pdftext dictionaries before assigning supplied layout/order artifacts; native adapters may keep a single selected layout/order payload directly under pdftext',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'layout_direct_pdftext_payload_unwrapped' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1,
    'order_direct_pdftext_payload_unwrapped' => ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'heading_promoted' => $headingPosition !== false,
    'body_preserved' => $bodyPosition !== false,
    'heading_before_body' => $headingPosition !== false && $bodyPosition !== false && $headingPosition < $bodyPosition,
    'cover_excluded' => !str_contains($text, 'Direct pdftext payload cover should not import.'),
    'appendix_excluded' => !str_contains($text, 'Direct pdftext payload appendix should not import.'),
    'payload_excluded' => !str_contains($encoded, 'direct pdftext selected layout payload')
        && !str_contains($encoded, 'direct pdftext layout envelope payload')
        && !str_contains($encoded, 'direct pdftext selected order payload')
        && !str_contains($encoded, 'direct pdftext order envelope payload'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['layout_direct_pdftext_payload_unwrapped']
    || !$flags['order_direct_pdftext_payload_unwrapped']
    || !$flags['heading_promoted']
    || !$flags['body_preserved']
    || !$flags['heading_before_body']
    || !$flags['cover_excluded']
    || !$flags['appendix_excluded']
    || !$flags['payload_excluded']
) {
    throw new RuntimeException('Expected direct pdftext supplied payloads to drive selected WordPress layout/order import: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
}

foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $block) {
    $block = trim($block);
    if ($block === '') {
        continue;
    }

    if (str_starts_with($block, '# ')) {
        echo "<!-- wp:heading {\"level\":2} -->\n";
        echo '<h2>' . htmlspecialchars(substr($block, 2), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</h2>\n";
        echo "<!-- /wp:heading -->\n\n";
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf-pdftext-dictionary-layout-order-direct-pdftext-payload-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
