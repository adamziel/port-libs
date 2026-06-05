<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

$bandOrderPdftextPage = static function (array $lines): array {
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

$bandOrderRecognition = static function (): array {
    return [
        'rows' => [
            ['row_id' => 20, 'bbox' => [0.0, 0.0, 200.0, 30.0]],
            ['row_id' => -5, 'bbox' => [0.0, 40.0, 200.0, 70.0]],
        ],
        'cols' => [
            ['col_id' => 100, 'bbox' => [0.0, 0.0, 96.0, 80.0]],
            ['col_id' => -10, 'bbox' => [108.0, 0.0, 200.0, 80.0]],
        ],
        'cells' => [
            ['bbox' => [6.0, 5.0, 84.0, 24.0], 'text' => 'Feature'],
            ['bbox' => [116.0, 5.0, 190.0, 24.0], 'text' => 'Status'],
            ['bbox' => [6.0, 45.0, 84.0, 64.0], 'text' => 'Images'],
            ['bbox' => [116.0, 45.0, 190.0, 64.0], 'text' => 'Ready'],
        ],
    ];
};

return [
    'preserves geometric row and column band order when supplied ids are arbitrary' => static function (
        TestRunner $t
    ) use ($bandOrderRecognition): void {
        $recognizer = new TableRecognizer();
        $result = $bandOrderRecognition();
        $assigned = $recognizer->assignRowsColumns($result, ['width' => 200, 'height' => 80]);
        $review = $recognizer->spanningGridReview($assigned, $result['rows'], $result['cols'], ['width' => 200, 'height' => 80]);
        $gridByPosition = [];
        foreach ($review['grid_cells'] as $gridCell) {
            $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
        }

        $t->same([[20], [20], [-5], [-5]], array_column($assigned, 'row_ids'));
        $t->same([[100], [-10], [100], [-10]], array_column($assigned, 'col_ids'));
        $t->same(
            "| Feature | Status |\n"
            . "|---------|--------|\n"
            . "| Images  | Ready  |",
            $recognizer->markdownFormat($assigned)
        );
        $t->same([20, -5], $review['rows']);
        $t->same([100, -10], $review['cols']);
        $t->same(['Feature', 'Status'], array_column($review['header_cells'], 'text'));
        $t->same(['Images', 'Ready'], array_column($review['data_cells'], 'text'));
        $t->same('anchor', $gridByPosition['20:100']['state'] ?? null);
        $t->same('anchor', $gridByPosition['20:-10']['state'] ?? null);
        $t->same('anchor', $gridByPosition['-5:100']['state'] ?? null);
        $t->same('anchor', $gridByPosition['-5:-10']['state'] ?? null);
        $t->same(['h-r20-c100'], $gridByPosition['-5:100']['headers'] ?? null);
        $t->same(['h-r20-c-10'], $gridByPosition['-5:-10']['headers'] ?? null);
        $t->same([0.0, 0.0, 96.0, 30.0], $gridByPosition['20:100']['grid_bbox'] ?? null);
        $t->same([108.0, 40.0, 200.0, 70.0], $gridByPosition['-5:-10']['grid_bbox'] ?? null);
    },
    'surfaces geometry-ordered arbitrary band ids through supplied WordPress conversion' => static function (
        TestRunner $t
    ) use ($bandOrderPdftextPage, $bandOrderRecognition): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-band-order-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table band order boundary current-base fixture\n%%EOF");
        try {
            $result = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $bandOrderPdftextPage([
                        ['text' => 'Band order table review', 'bbox' => [72.0, 48.0, 430.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale numeric-id table text should be replaced.', 'bbox' => [72.0, 176.0, 330.0, 196.0]],
                        ['text' => 'After band order review.', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 430.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 272.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 430.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [$bandOrderRecognition()],
                    'table_text_lines' => [['blocks' => []]],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $gridReview = $result['metadata']['table_spanning_grid_review'][0] ?? [];
            $gridByPosition = [];
            foreach (($gridReview['grid_cells'] ?? []) as $gridCell) {
                $gridByPosition[$gridCell['row_id'] . ':' . $gridCell['col_id']] = $gridCell;
            }

            $t->contains('# Band Order Table Review', $result['text']);
            $t->contains('| Feature | Status |', $result['text']);
            $t->contains('| Images  | Ready  |', $result['text']);
            $t->contains('After band order review.', $result['text']);
            $t->true(!str_contains($result['text'], 'Stale numeric-id table text should be replaced.'));
            $t->same([20, -5], $gridReview['rows'] ?? null);
            $t->same([100, -10], $gridReview['cols'] ?? null);
            $t->same(['Feature', 'Status'], array_column($gridReview['header_cells'] ?? [], 'text'));
            $t->same(['Images', 'Ready'], array_column($gridReview['data_cells'] ?? [], 'text'));
            $t->same(['h-r20-c100'], $gridByPosition['-5:100']['headers'] ?? null);
            $t->same(['h-r20-c-10'], $gridByPosition['-5:-10']['headers'] ?? null);
            $t->same(['layout', 'table-recognition', 'table-formatting'], $result['metadata']['supplied_boundaries']);
            $t->same(false, $result['metadata']['context']['filetype'] !== 'pdf');
        } finally {
            unlink($path);
        }
    },
];
