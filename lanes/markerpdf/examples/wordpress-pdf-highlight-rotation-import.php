<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [10 20 210 320] /CropBox [20 40 180 240] /Rotate 90 /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [7 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 300] /CropBox [0 0 200 300] /Rotate 270 /Annots [8 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [30 150 150 170] /QuadPoints [30 170 150 170 30 150 150 150] /Contents (Rotated crop highlight) /T (Rotation QA) /C [0 1 0] >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Underline /Rect [20 100 80 120] /QuadPoints [20 120 80 120 20 100 80 100] /Contents (Rotated 270 underline) /T (Rotation QA) /C [0 0 1] >>\nendobj\n"
    . "%%EOF";

$pages = [
    [
        'pnum' => 0,
        'bbox' => [0.0, 0.0, 200.0, 160.0],
        'rotation' => 90,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [110.0, 10.0, 130.0, 130.0],
            'lines' => [[
                'bbox' => [110.0, 10.0, 130.0, 130.0],
                'spans' => [
                    ['text' => 'Rotated crop', 'bbox' => [110.0, 10.0, 130.0, 130.0], 'font' => 'Helvetica'],
                    ['text' => ' raw decoy', 'bbox' => [30.0, 150.0, 150.0, 170.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ],
    [
        'pnum' => 1,
        'bbox' => [0.0, 0.0, 300.0, 200.0],
        'rotation' => 270,
        'blocks' => [[
            'type' => 'Text',
            'bbox' => [180.0, 120.0, 200.0, 180.0],
            'lines' => [[
                'bbox' => [180.0, 120.0, 200.0, 180.0],
                'spans' => [
                    ['text' => 'Rotated 270', 'bbox' => [180.0, 120.0, 200.0, 180.0], 'font' => 'Helvetica'],
                    ['text' => ' raw decoy', 'bbox' => [20.0, 100.0, 80.0, 120.0], 'font' => 'Helvetica'],
                ],
            ]],
        ]],
    ],
];

$extractor = new PdfMarkupAnnotationExtractor();
$markedPages = $extractor->applyMarkupsToPages($pages, $pdf);

$annotatedSpans = 0;
$rawDecoyAnnotated = false;
$coordinateSpaces = [];
foreach ($markedPages as $page) {
    foreach ($page['blocks'] as $block) {
        foreach ($block['lines'] as $line) {
            foreach ($line['spans'] as $span) {
                $reviews = $span['review_annotations'] ?? [];
                if (!is_array($reviews) || $reviews === []) {
                    continue;
                }

                $annotatedSpans++;
                $coordinateSpaces[] = $reviews[0]['quad_rect_coordinate_space'] ?? null;
                if (str_contains((string) ($span['text'] ?? ''), 'decoy')) {
                    $rawDecoyAnnotated = true;
                }
            }
        }
    }
}

echo '<!-- markerpdf-pdf-highlight-rotation-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF text-markup /QuadPoints transformed through page /CropBox and /Rotate before marker/pdftext span review metadata',
    'page_rotations' => [90, 270],
    'markup_count' => array_sum(array_map(static fn (array $page): int => count($page['markup_annotations'] ?? []), $markedPages)),
    'annotated_spans' => $annotatedSpans,
    'raw_decoy_annotated' => $rawDecoyAnnotated,
    'coordinate_spaces' => array_values(array_unique($coordinateSpaces)),
    'first_pdftext_quad_rect' => $markedPages[0]['markup_annotations'][0]['pdftext_quad_rects'][0] ?? null,
    'second_pdftext_quad_rect' => $markedPages[1]['markup_annotations'][0]['pdftext_quad_rects'][0] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($markedPages as $page) {
    echo "<!-- wp:paragraph -->\n<p>";
    foreach ($page['blocks'][0]['lines'][0]['spans'] as $span) {
        $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $reviews = $span['review_annotations'] ?? [];
        if (is_array($reviews) && $reviews !== []) {
            $review = $reviews[0];
            $label = htmlspecialchars((string) ($review['contents'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $space = htmlspecialchars((string) ($review['quad_rect_coordinate_space'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            echo '<mark data-markerpdf-review="' . $label . '" data-markerpdf-quad-space="' . $space . '">' . $text . '</mark>';
            continue;
        }

        echo $text;
    }
    echo "</p>\n<!-- /wp:paragraph -->\n";
}
