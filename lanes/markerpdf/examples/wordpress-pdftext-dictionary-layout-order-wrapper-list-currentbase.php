<?php

declare(strict_types=1);

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
                        'font' => ['name' => 'Times-Roman', 'flags' => null, 'weight' => 400, 'size' => 11.0],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

$convert = static function (array $pages, array $options): array {
    $path = sys_get_temp_dir() . '/markerpdf-wrapper-list-layout-order-' . bin2hex(random_bytes(4)) . '.pdf';
    file_put_contents($path, "%PDF-1.4\n% wrapper-list pdftext layout order boundary\n%%EOF");

    try {
        return (new SuppliedDocumentConverter())->convert(
            $path,
            $pages,
            $options + [
                'metadata' => ['languages' => ['English']],
                'max_pages' => 1,
                'start_page' => 1,
            ],
            new MarkerSettings(['EXTRACT_IMAGES' => false])
        );
    } finally {
        unlink($path);
    }
};

$coverPage = $page(840, [
    ['text' => 'Wrapper-list cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);
$selectedPage = $page(841, [
    ['text' => 'Second wrapper-list marker column.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First wrapper-list marker column.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);
$appendixPage = $page(842, [
    ['text' => 'Wrapper-list appendix should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]],
]);

$selectedLayout = [
    'page_metadata' => [['page' => 841]],
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];
$selectedOrder = [
    'page_metadata' => [['page' => 841]],
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];

$matched = $convert(
    [$coverPage, $selectedPage, $appendixPage],
    [
        'lowres_images' => [
            ['metadata' => [['page' => 840]], 'image' => 'wrapper-list-cover-layout-render'],
            ['page_metadata' => [['page' => 841]], 'image' => 'wrapper-list-selected-layout-render'],
        ],
        'layout_results' => [
            [
                'metadata' => [['page' => 840]],
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Picture', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                    ['label' => 'Picture', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                ],
            ],
            $selectedLayout,
        ],
        'order_images' => [
            ['metadata' => [['page' => 840]], 'image' => 'wrapper-list-cover-order-render'],
            ['page_metadata' => [['page' => 841]], 'image' => 'wrapper-list-selected-order-render'],
        ],
        'order_results' => [
            [
                'metadata' => [['page' => 840]],
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['position' => 1, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
                    ['position' => 2, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                ],
            ],
            $selectedOrder,
        ],
    ]
);

$ambiguousPage = $page(861, [
    ['text' => 'Second ambiguous wrapper-list column remains source ordered.', 'bbox' => [330.0, 112.0, 560.0, 128.0]],
    ['text' => 'First ambiguous wrapper-list column has no trusted order.', 'bbox' => [72.0, 112.0, 280.0, 128.0]],
]);
$ambiguousArtifact = [
    'metadata' => [['page' => 860], ['page' => 861]],
    'image_bbox' => [0.0, 0.0, 612.0, 792.0],
    'bboxes' => [
        ['position' => 1, 'bbox' => [60.0, 92.0, 290.0, 150.0]],
        ['position' => 2, 'bbox' => [318.0, 92.0, 570.0, 150.0]],
    ],
];
$ambiguous = $convert(
    [$page(860, [['text' => 'Ambiguous wrapper-list cover page should not import.', 'bbox' => [72.0, 80.0, 330.0, 94.0]]]), $ambiguousPage],
    [
        'lowres_images' => [['metadata' => [['page' => 860], ['page' => 861]], 'image' => 'ambiguous-wrapper-list-layout-render']],
        'layout_results' => [[
            'metadata' => [['page' => 860], ['page' => 861]],
            'image_bbox' => [0.0, 0.0, 612.0, 792.0],
            'bboxes' => [
                ['label' => 'Text', 'bbox' => [60.0, 92.0, 290.0, 150.0]],
                ['label' => 'Text', 'bbox' => [318.0, 92.0, 570.0, 150.0]],
            ],
        ]],
        'order_images' => [['metadata' => [['page' => 860], ['page' => 861]], 'image' => 'ambiguous-wrapper-list-order-render']],
        'order_results' => [$ambiguousArtifact],
    ]
);

foreach (preg_split('/\R{2,}/', trim($matched['text'])) ?: [] as $paragraph) {
    $paragraph = trim($paragraph);
    if ($paragraph === '') {
        continue;
    }

    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . nl2br(htmlspecialchars($paragraph, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

$matchedText = $matched['text'];
$ambiguousText = $ambiguous['text'];
echo '<!-- markerpdf-pdftext-dictionary-layout-order-wrapper-list-currentbase ' . htmlspecialchars(json_encode([
    'scenario' => 'wordpress-pdftext-dictionary-layout-order-wrapper-list-currentbase',
    'source_truth' => 'markerPDF trims pdftext dictionary pages before layout/order assignment; native adapters may serialize page metadata as singleton wrapper lists, while multi-entry wrapper lists are ambiguous and must fail closed',
    'page_range' => $matched['metadata']['page_range'] ?? [],
    'wrapper_list_layout_artifacts_trimmed' => ($matched['metadata']['layout_plan']['layout_result_count'] ?? null) === 1,
    'wrapper_list_order_artifacts_trimmed' => ($matched['metadata']['order_plan']['order_result_count'] ?? null) === 1,
    'singleton_wrapper_list_matched' => ($matched['metadata']['layout_plan']['assigned_pages'] ?? null) === 1
        && ($matched['metadata']['order_plan']['assigned_pages'] ?? null) === 1,
    'ambiguous_wrapper_list_rejected' => ($ambiguous['metadata']['supplied_boundaries'] ?? null) === [],
    'ordered_text' => [
        'first_before_second' => strpos($matchedText, 'First wrapper-list marker column.') < strpos($matchedText, 'Second wrapper-list marker column.'),
        'ambiguous_source_order_preserved' => strpos($ambiguousText, 'Second ambiguous wrapper-list column remains source ordered.') < strpos($ambiguousText, 'First ambiguous wrapper-list column has no trusted order.'),
        'cover_excluded' => !str_contains($matchedText, 'Wrapper-list cover page should not import.'),
        'appendix_excluded' => !str_contains($matchedText, 'Wrapper-list appendix should not import.'),
    ],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
