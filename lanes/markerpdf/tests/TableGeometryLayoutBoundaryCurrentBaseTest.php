<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableFormatter;

$layoutBoundaryPdftextPage = static function (array $lines): array {
    return [
        'page' => 0,
        'bbox' => [0.0, 0.0, 612.0, 792.0],
        'rotation' => 0,
        'blocks' => [[
            'lines' => array_map(
                static fn (array $line): array => [
                    'bbox' => $line['bbox'],
                    'spans' => [[
                        'text' => $line['text'],
                        'bbox' => $line['bbox'],
                        'font' => [
                            'name' => $line['font'] ?? 'Times-Roman',
                            'flags' => 0,
                            'weight' => $line['weight'] ?? 400,
                            'size' => $line['size'] ?? 12,
                        ],
                    ]],
                ],
                $lines
            ),
        ]],
    ];
};

return [
    'normalizes serialized table layout geometry before crop planning' => static function (TestRunner $t): void {
        $page = [
            'pnum' => 3,
            'bbox' => [0.0, 0.0, 612.0, 792.0],
            'layout' => [
                'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                'bboxes' => [
                    ['label' => 'Table', 'bbox' => [312.0, 230.0, 72.0, 150.0]],
                    ['label' => 'Table', 'left' => '350', 'top' => '150', 'right' => '470', 'bottom' => '230'],
                    ['label' => 'Table', 'polygon' => [[72.0, 300.0], [192.0, 300.0], [192.0, 380.0], [72.0, 380.0]]],
                    ['label' => 'Text', 'bbox' => [72.0, 420.0, 220.0, 450.0]],
                ],
            ],
            'blocks' => [],
        ];

        $planned = (new TableFormatter())->getTableBoxes(
            [$page],
            [['blocks' => []]],
            [['width' => 612, 'height' => 792]]
        );

        $t->same([3], $planned['table_counts']);
        $t->same([[72.0, 150.0, 312.0, 230.0], [350.0, 150.0, 470.0, 230.0], [72.0, 300.0, 192.0, 380.0]], $planned['table_bboxes']);
        $t->same([72.0, 150.0, 312.0, 230.0], $planned['table_images'][0]['source_bbox']);
        $t->same([350.0, 150.0, 470.0, 230.0], $planned['table_images'][1]['source_bbox']);
        $t->same([72.0, 300.0, 192.0, 380.0], $planned['table_images'][2]['source_bbox']);
        $t->same([240.0, 120.0, 120.0], array_map(static fn (array $image): float => $image['crop_width'], $planned['table_images']));
        $t->same([80.0, 80.0, 80.0], array_map(static fn (array $image): float => $image['crop_height'], $planned['table_images']));
        $t->same([['blocks' => []], ['blocks' => []], ['blocks' => []]], $planned['text_lines']);
    },
    'surfaces reversed named layout crop geometry through supplied WordPress conversion' => static function (TestRunner $t) use ($layoutBoundaryPdftextPage): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-layout-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table layout geometry boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $layoutBoundaryPdftextPage([
                        ['text' => 'Layout table boundary review', 'bbox' => [72.0, 48.0, 450.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale reversed layout table text should be replaced.', 'bbox' => [72.0, 176.0, 300.0, 196.0]],
                        ['text' => 'After layout geometry review.', 'bbox' => [72.0, 276.0, 450.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 450.0, 68.0]],
                            ['label' => 'Table', 'left' => '312', 'top' => '230', 'right' => '72', 'bottom' => '150'],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 450.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [[
                        'rows' => [
                            ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 32.0]],
                            ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
                        ],
                        'cols' => [
                            ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
                            ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
                        ],
                        'cells' => [
                            ['bbox' => [10.0, 5.0, 90.0, 20.0], 'text' => 'Block'],
                            ['bbox' => [130.0, 5.0, 230.0, 20.0], 'text' => 'Status'],
                            ['bbox' => [10.0, 45.0, 90.0, 65.0], 'text' => 'Images'],
                            ['bbox' => [130.0, 45.0, 230.0, 65.0], 'text' => 'Ready'],
                        ],
                    ]],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $t->contains('# Layout Table Boundary Review', $result['text']);
            $t->contains('| Block  | Status |', $result['text']);
            $t->contains('| Images | Ready  |', $result['text']);
            $t->contains('After layout geometry review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale reversed layout table text should be replaced.'));
            $t->same([[72.0, 150.0, 312.0, 230.0]], $result['metadata']['table_plan']['table_bboxes'] ?? null);
            $t->same(['width' => 240, 'height' => 80], $result['metadata']['table_spanning_grid_review'][0]['geometry_boundary_review']['image_size'] ?? null);
            $t->same(1, $result['metadata']['inserted_tables'] ?? null);
        } finally {
            unlink($path);
        }
    },
];
