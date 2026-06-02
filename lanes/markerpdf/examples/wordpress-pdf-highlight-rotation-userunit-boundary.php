<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /MediaBox [0 0 400 600] /CropBox [10 20 170 220] /Rotate 90 /UserUnit 5 /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /UserUnit 9 0 R /Annots [7 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [30 150 110 170] /QuadPoints [30 170 110 170 30 150 110 150] /Contents (UserUnit review) /T (Geometry QA) /C [0.2 0.6 1] >>\nendobj\n"
    . "9 0 obj\n2\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'bbox' => [0.0, 0.0, 400.0, 320.0],
    'rotation' => 90,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [260.0, 40.0, 300.0, 200.0],
        'lines' => [[
            'bbox' => [260.0, 40.0, 300.0, 200.0],
            'spans' => [
                ['text' => 'Scaled review', 'bbox' => [260.0, 40.0, 300.0, 200.0], 'font' => 'Helvetica'],
                ['text' => ' unscaled decoy', 'bbox' => [130.0, 20.0, 150.0, 100.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfMarkupAnnotationExtractor();
$markedPages = $extractor->applyMarkupsToPages($pages, $pdf);
$markup = $markedPages[0]['markup_annotations'][0] ?? [];
$review = $markedPages[0]['blocks'][0]['lines'][0]['spans'][0]['review_annotations'][0] ?? [];
$decoyAnnotated = isset($markedPages[0]['blocks'][0]['lines'][0]['spans'][1]['review_annotations']);

echo '<!-- markerpdf-pdf-highlight-rotation-userunit-boundary ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF text-markup /QuadPoints transformed through /CropBox, /Rotate, and page-local /UserUnit before marker/pdftext span review metadata',
    'page_rotation' => $markup['page_rotation'] ?? null,
    'page_user_unit' => $markup['page_user_unit'] ?? null,
    'display_page_bbox' => $markup['display_page_bbox'] ?? null,
    'pdftext_quad_rect' => $markup['pdftext_quad_rects'][0] ?? null,
    'annotated_span_text' => $markedPages[0]['blocks'][0]['lines'][0]['spans'][0]['text'] ?? null,
    'quad_rect_coordinate_space' => $review['quad_rect_coordinate_space'] ?? null,
    'unscaled_decoy_annotated' => $decoyAnnotated,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($markedPages[0]['blocks'][0]['lines'][0]['spans'] as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $reviews = $span['review_annotations'] ?? [];
    if (is_array($reviews) && $reviews !== []) {
        $label = htmlspecialchars((string) ($reviews[0]['contents'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<mark data-markerpdf-review="' . $label . '">' . $text . '</mark>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
