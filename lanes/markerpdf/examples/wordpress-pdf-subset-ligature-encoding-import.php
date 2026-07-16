<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = 'BT /Fsubset 12 Tf 72 720 Td <202122232425262728292A2B2C2D2E2F30> Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fsubset 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+SubsetSerif /Encoding << /Type /Encoding /Differences [32 /O /f_f_i.alt /c /e /space /f_i /l /e /space /endash /space /C /a /f /eacute /space /Euro] >> >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo "<!-- markerpdf-subset-ligature-encoding-smoke " . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'subset simple-font Encoding Differences glyph names decoded before Gutenberg paragraph rendering',
    'subset_font_prefix_ignored' => true,
    'ligature_components_decoded' => str_contains($plainText, 'Office file'),
    'adobe_glyph_names_decoded' => str_contains($plainText, "\u{2013}")
        && str_contains($plainText, "\u{00E9}")
        && str_contains($plainText, "\u{20AC}"),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
