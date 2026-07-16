<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = 'q 72 650 240 50 re W n q /FmStruct Do Q Q '
    . 'BT /F1 12 Tf 72 620 Td (Unmarked page tail ignored by StructTree replay) Tj ET';
$formContent = 'BT /F1 12 Tf '
    . '/P << /MCID 0 >> BDC 0 24 Td (Visible form body) Tj EMC '
    . '/P << /MCID 1 /ActualText (Hidden replacement leak) >> BDC 0 100 Td (Hidden clipped form body) Tj EMC '
    . 'ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> /XObject << /FmStruct 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 200 140] /Matrix [1 0 0 1 72 650] /Resources << /Font << /F1 6 0 R >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /K [22 0 R 21 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Type /StructElem /S /P /Pg 3 0 R /K 0 >>\nendobj\n"
    . "22 0 obj\n<< /Type /StructElem /S /P /Pg 3 0 R /K 1 >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$tagged = $extractor->extractTaggedContent($pdf);

echo '<!-- markerpdf-page-form-xobject-structtree-clip-currentbase ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'page clipping path remains active after Form XObject expansion and StructTree MCID ordering before Gutenberg paragraph rendering',
    'visible_form_mcid_imported' => $lines === ['Visible form body'],
    'clipped_form_actualtext_excluded' => !str_contains($plainText, 'Hidden replacement leak'),
    'clipped_form_glyphs_excluded' => !str_contains($plainText, 'Hidden clipped form body'),
    'tagged_mcid_rows' => array_column($tagged, 'mcid'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
