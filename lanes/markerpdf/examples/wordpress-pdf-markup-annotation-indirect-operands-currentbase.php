<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMarkupAnnotationExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Indirect highlight Exact underline Wrong generation) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R /Annots [7 0 R 8 0 R 9 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /Annot /Subtype 70 0 R /Rect [20 0 R 21 0 R 22 0 R 23 0 R] /QuadPoints [30 0 R 31 0 R 32 0 R 33 0 R 34 0 R 35 0 R 36 0 R 37 0 R] /Contents 80 0 R /T 81 0 R /Subj 82 0 R /M 83 0 R /NM 84 0 R /C [90 0 R 91 0 R 92 0 R] /CA 93 0 R /F 94 0 R /Border [95 0 R 96 0 R 97 0 R [98 0 R 99 0 R]] /BS << /W 100 0 R /S 101 0 R /D [102 0 R 103 0 R] >> >>\nendobj\n"
    . "8 0 obj\n<< /Type /Annot /Subtype /Underline /Rect [40 0 R 41 0 R 42 0 R 43 0 R] /QuadPoints [44 0 R 45 0 R 46 0 R 47 0 R 48 0 R 49 0 R 50 0 R 51 0 R] /Contents (Exact underline review) /T (QA) /C [0 0 1] /CA 0.4 /F 4 >>\nendobj\n"
    . "9 0 obj\n<< /Type /Annot /Subtype /Highlight /Rect [60 1 R 61 1 R 62 1 R 63 1 R] /QuadPoints [64 1 R 65 1 R 66 1 R 67 1 R 68 1 R 69 1 R 72 1 R 73 1 R] /Contents (Wrong generation highlight decoy) /T (Stale QA) /C [0 1 0] >>\nendobj\n"
    . "20 0 obj\n72\nendobj\n21 0 obj\n700\nendobj\n22 0 obj\n174\nendobj\n23 0 obj\n718\nendobj\n"
    . "30 0 obj\n72\nendobj\n31 0 obj\n718\nendobj\n32 0 obj\n174\nendobj\n33 0 obj\n718\nendobj\n34 0 obj\n72\nendobj\n35 0 obj\n700\nendobj\n36 0 obj\n174\nendobj\n37 0 obj\n700\nendobj\n"
    . "40 0 obj\n184\nendobj\n41 0 obj\n700\nendobj\n42 0 obj\n286\nendobj\n43 0 obj\n718\nendobj\n"
    . "44 0 obj\n184\nendobj\n45 0 obj\n718\nendobj\n46 0 obj\n286\nendobj\n47 0 obj\n718\nendobj\n48 0 obj\n184\nendobj\n49 0 obj\n700\nendobj\n50 0 obj\n286\nendobj\n51 0 obj\n700\nendobj\n"
    . "60 0 obj\n72\nendobj\n61 0 obj\n660\nendobj\n62 0 obj\n174\nendobj\n63 0 obj\n678\nendobj\n"
    . "64 0 obj\n72\nendobj\n65 0 obj\n678\nendobj\n66 0 obj\n174\nendobj\n67 0 obj\n678\nendobj\n68 0 obj\n72\nendobj\n69 0 obj\n660\nendobj\n72 0 obj\n174\nendobj\n73 0 obj\n660\nendobj\n"
    . "70 0 obj\n/Highlight\nendobj\n"
    . "80 0 obj\n(Indirect highlight review)\nendobj\n"
    . "81 0 obj\n(Review QA)\nendobj\n"
    . "82 0 obj\n(Highlight note)\nendobj\n"
    . "83 0 obj\n(D:20260606223147Z)\nendobj\n"
    . "84 0 obj\n<686967682d696e646972656374>\nendobj\n"
    . "90 0 obj\n1\nendobj\n91 0 obj\n0.85\nendobj\n92 0 obj\n0\nendobj\n"
    . "93 0 obj\n0.55\nendobj\n94 0 obj\n4\nendobj\n"
    . "95 0 obj\n0\nendobj\n96 0 obj\n0\nendobj\n97 0 obj\n2\nendobj\n98 0 obj\n3\nendobj\n99 0 obj\n1\nendobj\n"
    . "100 0 obj\n1.5\nendobj\n101 0 obj\n/D\nendobj\n102 0 obj\n4\nendobj\n103 0 obj\n2\nendobj\n"
    . "%%EOF";

$pages = [[
    'pnum' => 0,
    'blocks' => [[
        'type' => 'Text',
        'bbox' => [72.0, 660.0, 286.0, 718.0],
        'lines' => [[
            'bbox' => [72.0, 700.0, 286.0, 718.0],
            'spans' => [
                ['text' => 'Indirect highlight', 'bbox' => [72.0, 700.0, 174.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Exact underline', 'bbox' => [184.0, 700.0, 286.0, 718.0], 'font' => 'Helvetica'],
                ['text' => ' Wrong generation', 'bbox' => [72.0, 660.0, 174.0, 678.0], 'font' => 'Helvetica'],
            ],
        ]],
    ]],
]];

$extractor = new PdfMarkupAnnotationExtractor();
$markups = $extractor->extractPageMarkups($pdf);
$reviewPages = $extractor->applyMarkupsToPages($pages, $pdf);
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);
$encodedReview = json_encode([$markups, $reviewPages], JSON_UNESCAPED_SLASHES) ?: '';
$spans = $reviewPages[0]['blocks'][0]['lines'][0]['spans'] ?? [];
$wordpressText = implode('', array_map(static fn (array $span): string => (string) ($span['text'] ?? ''), $spans));

$summary = [
    'support_component' => 'native-pdf-markup-annotation-indirect-operands',
    'native_boundary' => 'Text-markup annotation /Subtype, /Rect, /QuadPoints, style, color, flag, and text operands resolve exact-generation indirect objects before WordPress review spans',
    'markup_annotation_objects' => array_column($markups[0]['markups'] ?? [], 'annotation_object'),
    'markup_subtypes' => array_column($markups[0]['markups'] ?? [], 'subtype'),
    'highlight_review_attached' => str_contains($encodedReview, 'Indirect highlight review'),
    'underline_review_attached' => str_contains($encodedReview, 'Exact underline review'),
    'wrong_generation_attached' => str_contains($encodedReview, 'Wrong generation highlight decoy'),
    'wordpress_text' => $wordpressText,
    'annotation_payload_text_visible' => str_contains($visibleText, 'Indirect highlight review')
        || str_contains($visibleText, 'Wrong generation highlight decoy')
        || str_contains($visibleText, 'Exact underline review'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
];

if (($summary['highlight_review_attached'] ?? false) !== true || ($summary['underline_review_attached'] ?? false) !== true) {
    throw new RuntimeException('Expected exact-generation indirect markup operands to attach review metadata.');
}
if (($summary['wrong_generation_attached'] ?? true) !== false) {
    throw new RuntimeException('Wrong-generation markup operands must not attach stale review metadata.');
}
if (($summary['annotation_payload_text_visible'] ?? true) !== false) {
    throw new RuntimeException('Annotation review text must stay out of visible WordPress content.');
}

echo '<!-- markerpdf-pdf-markup-annotation-indirect-operands-currentbase ' . htmlspecialchars(json_encode($summary, JSON_UNESCAPED_SLASHES) ?: '{}', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($wordpressText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
