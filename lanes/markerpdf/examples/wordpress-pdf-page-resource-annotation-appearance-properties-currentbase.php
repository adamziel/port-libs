<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$emptyContent = '';
$inheritedAppearance = '/Span /SharedActual BDC BT /F1 10 Tf 0 18 Td (Inherited appearance glyph noise) Tj ET EMC';
$localAppearance = '/Span /SharedActual BDC BT /F1 10 Tf 0 18 Td (Local appearance glyph noise) Tj ET EMC';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources 10 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Annots [20 0 R 21 0 R] /Contents 5 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($emptyContent) . " >>\nstream\n{$emptyContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "10 0 obj\n<< /Font << /F1 7 0 R >> /Properties << /SharedActual << /ActualText (Inherited page ActualText) >> >> >>\nendobj\n"
    . "20 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [72 660 240 700] /AP << /N 30 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Annot /Subtype /FreeText /Rect [72 620 240 650] /AP << /N 31 0 R >> >>\nendobj\n"
    . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 48] /Length " . strlen($inheritedAppearance) . " >>\nstream\n{$inheritedAppearance}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 180 48] /Resources << /Font << /F1 7 0 R >> /Properties << /SharedActual << /ActualText (Appearance local ActualText) >> >> >> /Length " . strlen($localAppearance) . " >>\nstream\n{$localAppearance}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$expected = [
    'Inherited page ActualText',
    'Appearance local ActualText',
];

if ($lines !== $expected) {
    throw new RuntimeException('Expected annotation appearance /Resources /Properties to stay scoped before WordPress import.');
}

echo '<!-- markerpdf-page-resource-annotation-appearance-properties-currentbase-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'annotation appearance Form /Resources /Properties ActualText is scoped before Gutenberg paragraph rendering',
    'inherited_page_actual_text_imported' => in_array('Inherited page ActualText', $lines, true),
    'appearance_local_actual_text_imported' => in_array('Appearance local ActualText', $lines, true),
    'shared_property_name_scoped' => substr_count($plainText, 'Inherited page ActualText') === 1
        && substr_count($plainText, 'Appearance local ActualText') === 1,
    'raw_glyph_noise_excluded' => !str_contains($plainText, 'glyph noise'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
