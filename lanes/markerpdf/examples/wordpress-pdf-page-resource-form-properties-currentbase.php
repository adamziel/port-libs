<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = '/Span /SharedActual BDC BT /F1 12 Tf 72 720 Td (Page glyph noise) Tj ET EMC '
    . 'q /ActualForm Do Q '
    . 'BT /F1 12 Tf 72 650 Td (After form glyph) Tj ET';
$formContent = '/Span /SharedActual BDC BT /F1 12 Tf 12 24 Td (Form glyph noise) Tj ET EMC '
    . '/Span /FormOnly BDC BT /F1 12 Tf 12 12 Td (Alt glyph noise) Tj ET EMC';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 7 0 R >> /XObject << /ActualForm 5 0 R >> /Properties << /SharedActual << /ActualText (Page resource ActualText) >> >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 200 80] /Resources << /Font << /F1 7 0 R >> /Properties << /SharedActual << /ActualText (Form local ActualText) >> /FormOnly << /Alt (Form local Alt text) >> >> >> /Length " . strlen($formContent) . " >>\nstream\n{$formContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$expected = [
    'Page resource ActualText',
    'Form local ActualText',
    'Form local Alt text',
    'After form glyph',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected form-local marked-content Properties to remain scoped before WordPress import.');
}

echo '<!-- markerpdf-page-resource-form-properties-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'Form XObject /Resources /Properties ActualText is scoped to expanded form content before Gutenberg paragraph rendering',
    'page_property_preserved' => in_array('Page resource ActualText', $lines, true),
    'form_local_actual_text_imported' => in_array('Form local ActualText', $lines, true),
    'form_local_alt_text_imported' => in_array('Form local Alt text', $lines, true),
    'shared_property_name_scoped' => substr_count($plainText, 'Page resource ActualText') === 1
        && substr_count($plainText, 'Form local ActualText') === 1,
    'raw_glyph_noise_excluded' => !str_contains($plainText, 'glyph noise'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
