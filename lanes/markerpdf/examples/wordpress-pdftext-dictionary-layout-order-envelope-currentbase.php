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

$coverPage = $page(930, [
    ['text' => 'Envelope layout cover should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);
$selectedPage = $page(931, [
    ['text' => 'Second envelope layout column is selected.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First envelope layout column starts the import.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);
$appendixPage = $page(932, [
    ['text' => 'Envelope layout appendix should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);

$dictionaryEnvelope = [
    'metadata' => [
        'adapter' => 'cached pdftext.dictionary_output',
        'raw_payload' => 'layout order envelope metadata must stay hidden',
    ],
    'pages' => [$coverPage, $selectedPage, $appendixPage],
];

$layoutResults = [
    [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
            ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ],
    ],
    [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
        ],
    ],
    [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
            ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ],
    ],
];
$orderResults = [
    [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
        ],
    ],
    [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
            ['position' => 2, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
        ],
    ],
    [
        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
        'bboxes' => [
            ['position' => 1, 'bbox' => [318.0, 96.0, 570.0, 144.0]],
            ['position' => 2, 'bbox' => [60.0, 96.0, 290.0, 144.0]],
        ],
    ],
];

$path = sys_get_temp_dir() . '/markerpdf-layout-order-envelope-' . bin2hex(random_bytes(4)) . '.pdf';
file_put_contents($path, "%PDF-1.4\n% pdftext dictionary envelope layout order boundary\n%%EOF");

try {
    $result = (new SuppliedDocumentConverter())->convert(
        $path,
        $dictionaryEnvelope,
        [
            'metadata' => ['languages' => ['English']],
            'max_pages' => 1,
            'start_page' => 1,
            'lowres_images' => ['cover-layout-render', 'selected-layout-render', 'appendix-layout-render'],
            'layout_results' => $layoutResults,
            'order_images' => ['cover-order-render', 'selected-order-render', 'appendix-order-render'],
            'order_results' => $orderResults,
        ],
        new MarkerSettings(['EXTRACT_IMAGES' => false])
    );
} finally {
    unlink($path);
}

$text = $result['text'];
$encoded = json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$flags = [
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-envelope-currentbase',
    'source_truth' => 'markerPDF receives pdftext.dictionary_output pages before trimming PDFium documents for layout/order model zipping; native cached envelopes must use the unwrapped page count when aligning supplied full-document artifacts',
    'support_component' => 'pdf-text-dictionary-layout-order-boundary',
    'page_range' => $result['metadata']['page_range'] ?? [],
    'source_pages' => $result['metadata']['pdftext']['source_pages'] ?? null,
    'document_page_count' => $result['context']['document_page_count'] ?? null,
    'trimmed_document_page_count' => $result['context']['trimmed_document_page_count'] ?? null,
    'layout_artifacts_trimmed' => ($result['metadata']['layout_plan']['image_count'] ?? null) === 1
        && ($result['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'order_artifacts_trimmed' => ($result['metadata']['order_plan']['image_count'] ?? null) === 1
        && ($result['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'layout_order_assigned' => ($result['metadata']['layout_plan']['assigned_pages'] ?? null) === 1
        && ($result['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'first_before_second' => strpos($text, 'First envelope layout column starts the import.') < strpos($text, 'Second envelope layout column is selected.'),
    'cover_excluded' => !str_contains($text, 'Envelope layout cover should not import.'),
    'appendix_excluded' => !str_contains($text, 'Envelope layout appendix should not import.'),
    'payload_excluded' => !str_contains($encoded, 'layout order envelope metadata must stay hidden'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    !$flags['layout_artifacts_trimmed']
    || !$flags['order_artifacts_trimmed']
    || !$flags['layout_order_assigned']
    || !$flags['first_before_second']
    || !$flags['cover_excluded']
    || !$flags['appendix_excluded']
    || !$flags['payload_excluded']
) {
    throw new RuntimeException('Expected pdftext dictionary envelope layout/order artifacts to align to the selected page: ' . json_encode($flags, JSON_UNESCAPED_SLASHES));
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

echo '<!-- markerpdf-pdftext-dictionary-layout-order-envelope-currentbase ' . htmlspecialchars(json_encode($flags, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
