<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\MarkerSettings;
use PortLibs\MarkerPDF\SuppliedDocumentConverter;
use PortLibs\MarkerPDF\TableRecognizer;

$detectorCropBoundaryPdftextPage = static function (array $lines): array {
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

$detectorCropBoundaryRows = static fn (): array => [
    ['row_id' => 0, 'bbox' => [0.0, 0.0, 240.0, 32.0]],
    ['row_id' => 1, 'bbox' => [0.0, 40.0, 240.0, 70.0]],
];

$detectorCropBoundaryCols = static fn (): array => [
    ['col_id' => 0, 'bbox' => [0.0, 0.0, 100.0, 80.0]],
    ['col_id' => 1, 'bbox' => [120.0, 0.0, 240.0, 80.0]],
];

$detectorCropBoundaryCells = static fn (): array => [
    ['bbox' => [260.0, 4.0, 290.0, 20.0], 'text' => null],
    ['bbox' => [10.0, 4.0, 90.0, 20.0], 'text' => null],
    ['bbox' => [130.0, 4.0, 230.0, 20.0], 'text' => null],
    ['bbox' => [10.0, 44.0, 90.0, 60.0], 'text' => null],
    ['bbox' => [130.0, 44.0, 230.0, 60.0], 'text' => null],
];

return [
    'filters off crop detector cells before source order OCR text assignment' => static function (
        TestRunner $t
    ) use ($detectorCropBoundaryRows, $detectorCropBoundaryCols, $detectorCropBoundaryCells): void {
        $recognizer = new TableRecognizer();
        $cells = $recognizer->getCells(
            [[72.0, 150.0, 312.0, 230.0]],
            [['width' => 240, 'height' => 80]],
            [null],
            [0 => $detectorCropBoundaryCells()],
            true
        );
        $recognized = $recognizer->recognizeTables(
            $cells['table_cells'],
            $cells['needs_ocr'],
            [[
                'rows' => $detectorCropBoundaryRows(),
                'cols' => $detectorCropBoundaryCols(),
            ]],
            [0 => ['Feature', 'Status', 'Images', 'Ready']]
        );
        $formatted = $recognizer->formatRecognizedTables($recognized, [['width' => 240, 'height' => 80]]);
        $detectorReview = $cells['table_detector_cell_boundary_reviews'][0] ?? [];

        $t->same([true], $cells['needs_ocr']);
        $t->same(4, count($cells['table_cells'][0] ?? []));
        $t->same('table_detector_cell_crop_boundary', $detectorReview['review_target'] ?? null);
        $t->same('tabled.inference.recognition.get_cells.table_image_detector_cells', $detectorReview['upstream_boundary'] ?? null);
        $t->same(5, $detectorReview['cell_count'] ?? null);
        $t->same(4, $detectorReview['active_cell_count'] ?? null);
        $t->same(1, $detectorReview['excluded_cell_count'] ?? null);
        $t->same('excluded_outside_table_image', $detectorReview['cells'][0]['status'] ?? null);
        $t->same(true, $detectorReview['cells'][0]['detector_cell_excluded_before_ocr'] ?? null);
        $t->same(false, $detectorReview['cells'][0]['ocr_source_order_retained_after_crop_boundary'] ?? null);
        $t->same('within_table_image', $detectorReview['cells'][1]['status'] ?? null);
        $t->same(true, $detectorReview['cells'][1]['ocr_source_order_retained_after_crop_boundary'] ?? null);
        $t->same(['Feature', 'Status', 'Images', 'Ready'], array_column($formatted['assigned_cells'][0] ?? [], 'text'));
        $t->contains('| Feature | Status |', $formatted['markdown_tables'][0] ?? '');
        $t->contains('| Images  | Ready  |', $formatted['markdown_tables'][0] ?? '');
        $t->true(!str_contains($formatted['markdown_tables'][0] ?? '', 'Offcrop'));
    },
    'surfaces detector crop boundary through supplied WordPress conversion' => static function (
        TestRunner $t
    ) use ($detectorCropBoundaryPdftextPage, $detectorCropBoundaryRows, $detectorCropBoundaryCols, $detectorCropBoundaryCells): void {
        $path = sys_get_temp_dir() . '/markerpdf-table-detector-crop-boundary-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, "%PDF-1.4\n% table detector crop boundary current-base fixture\n%%EOF");
        try {
            $document = (new SuppliedDocumentConverter())->convert(
                $path,
                [
                    $detectorCropBoundaryPdftextPage([
                        ['text' => 'Detector crop table boundary', 'bbox' => [72.0, 48.0, 460.0, 68.0], 'font' => 'Heading-Bold', 'weight' => 700, 'size' => 18],
                        ['text' => 'Stale detector table line should be replaced.', 'bbox' => [72.0, 176.0, 330.0, 196.0]],
                        ['text' => 'After detector crop review.', 'bbox' => [72.0, 276.0, 460.0, 294.0]],
                    ]),
                ],
                [
                    'metadata' => ['languages' => ['English']],
                    'layout_results' => [[
                        'image_bbox' => [0.0, 0.0, 612.0, 792.0],
                        'bboxes' => [
                            ['label' => 'Title', 'bbox' => [72.0, 48.0, 460.0, 68.0]],
                            ['label' => 'Table', 'bbox' => [72.0, 150.0, 312.0, 230.0]],
                            ['label' => 'Text', 'bbox' => [72.0, 276.0, 460.0, 294.0]],
                        ],
                    ]],
                    'recognized_tables' => [[
                        'rows' => $detectorCropBoundaryRows(),
                        'cols' => $detectorCropBoundaryCols(),
                    ]],
                    'table_detect_boxes' => true,
                    'table_detector_cells' => [$detectorCropBoundaryCells()],
                    'table_ocr_text_lines' => [['Feature', 'Status', 'Images', 'Ready']],
                    'table_rendered_image_sizes' => [['width' => 612, 'height' => 792]],
                ],
                new MarkerSettings(['EXTRACT_IMAGES' => false])
            );

            $detectorReview = $document['metadata']['table_detector_cell_boundary_reviews'][0] ?? [];
            $assignedTexts = array_column($document['metadata']['table_assigned_cells'][0] ?? [], 'text');

            $t->contains('# Detector Crop Table Boundary', $document['text']);
            $t->contains('| Feature | Status |', $document['text']);
            $t->contains('| Images  | Ready  |', $document['text']);
            $t->contains('After detector crop review.', $document['text']);
            $t->true(!str_contains($document['text'], 'Stale detector table line should be replaced.'));
            $t->same(['Feature', 'Status', 'Images', 'Ready'], $assignedTexts);
            $t->same(['layout', 'table-cell-routing', 'table-recognition', 'table-formatting'], $document['metadata']['supplied_boundaries'] ?? null);
            $t->same([true], $document['metadata']['table_needs_ocr'] ?? null);
            $t->same([4], $document['metadata']['table_cell_counts'] ?? null);
            $t->same('table_detector_cell_crop_boundary', $detectorReview['review_target'] ?? null);
            $t->same(1, $detectorReview['excluded_cell_count'] ?? null);
            $t->same(false, $document['metadata']['context']['filetype'] !== 'pdf');
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    },
];
