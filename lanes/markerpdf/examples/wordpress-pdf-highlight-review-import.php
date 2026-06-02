<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Needs plugin review) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [5 0 R << /Type /Annot /Subtype /Text /Rect [72 660 180 678] /Contents (sticky note only) >>] /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [72 700 250 718] /QuadPoints [72 718 160 718 72 700 160 700 170 718 250 718 170 700 250 700] /Contents (Confirm plugin compatibility before publishing) /T (Editorial QA) /Subj (Highlight) /M (D:20260602033700Z) /C [1 0.92 0] /CA 0.45 >>\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 700.0, 250.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 250.0, 718.0],
            'spans' => [
                ['text' => 'Needs plugin', 'bbox' => [72.0, 700.0, 160.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' review', 'bbox' => [170.0, 700.0, 250.0, 718.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfMarkupAnnotationExtractor();
$markedPages = $extractor->applyMarkupsToPages($pages, $pdf);
$markups = $markedPages[0]['markup_annotations'] ?? [];
$annotatedSpans = 0;
foreach ($markedPages[0]['blocks'][0]['lines'][0]['spans'] as $span) {
    if (($span['review_annotations'] ?? []) !== []) {
        $annotatedSpans++;
    }
}

echo '<!-- markerpdf-pdf-highlight-review-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'PDF /Annots text-markup /QuadPoints mapped to supplied pdftext spans for WordPress review metadata',
    'markup_count' => count($markups),
    'annotated_spans' => $annotatedSpans,
    'review_contents' => $markups[0]['contents'] ?? null,
    'quad_rects' => $markups[0]['quad_rects'] ?? [],
    'excluded_text_annotation' => count($markups) === 1,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n<p>";
foreach ($markedPages[0]['blocks'][0]['lines'][0]['spans'] as $span) {
    $text = htmlspecialchars((string) ($span['text'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $reviews = $span['review_annotations'] ?? [];
    if (is_array($reviews) && $reviews !== []) {
        $review = $reviews[0];
        $label = htmlspecialchars((string) ($review['contents'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $author = htmlspecialchars((string) ($review['author'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<mark data-markerpdf-review="' . $label . '" data-markerpdf-review-author="' . $author . '">' . $text . '</mark>';
        continue;
    }

    echo $text;
}
echo "</p>\n<!-- /wp:paragraph -->\n";
