<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$actualTextUtf16 = '<FEFF005200650073006F0075007200630065006400200057005000200042006C006F0063006B0073>';
$pageContent = 'BT /F1 12 Tf 72 720 Td (Visible Intro) Tj T* '
    . '/Span << /ActualText (Accessible WordPress Import) /Alt (Ignored Alt Text) >> BDC (Glyph Noise) Tj EMC T* '
    . '/Span /PActual BDC (Resource Glyph Noise) Tj EMC T* ET '
    . '/Figure << /Alt (Figure: migration workflow screenshot) >> BDC q /Im1 Do Q EMC '
    . 'BT /F1 12 Tf 72 672 Td /Span << /Alt (Inline Alt Summary) >> BDC (Inline Alt Glyphs) Tj EMC ET';
$imageNoise = 'BT /F1 12 Tf 72 704 Td (Raster Alt Noise) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> /Properties << /PActual 6 0 R >> /XObject << /Im1 5 0 R >> >> /Contents 7 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Length " . strlen($imageNoise) . " >>\nstream\n{$imageNoise}\nendstream\nendobj\n"
    . "6 0 obj\n<< /ActualText {$actualTextUtf16} >>\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf:pdf-marked-content-actualtext ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-marked-content',
    'operators' => ['BDC', 'EMC'],
    'uses_actual_text' => str_contains($plainText, 'Accessible WordPress Import')
        && str_contains($plainText, 'Resourced WP Blocks'),
    'uses_alt_text_fallback' => str_contains($plainText, 'Figure: migration workflow screenshot')
        && str_contains($plainText, 'Inline Alt Summary'),
    'suppressed_original_glyph_noise' => !str_contains($plainText, 'Glyph Noise')
        && !str_contains($plainText, 'Resource Glyph Noise')
        && !str_contains($plainText, 'Inline Alt Glyphs')
        && !str_contains($plainText, 'Raster Alt Noise'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
