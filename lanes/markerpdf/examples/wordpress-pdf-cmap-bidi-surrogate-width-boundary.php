<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Fsur 12 Tf 1 0 0 1 72 720 Tm <01> Tj 1 0 0 1 92 720 Tm <02> Tj ET';
$cmap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "2 beginbfchar\n"
    . "<01> <2067D83DDE002069>\n"
    . "<02> <0057006F00720064>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fsur 2 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /CustomBidiSurrogate /ToUnicode 3 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($cmap) . " >>\nstream\n{$cmap}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$expected = "\u{2067}\u{1F600}\u{2069} Word";

echo '<!-- markerpdf:pdf-cmap-bidi-surrogate-width-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-tounicode-bidi-surrogate-source-glyph-advance-boundary',
    'font_width_sources' => ['ToUnicode CMap source codes', 'default 500-unit glyph advance fallback'],
    'bidi_surrogate_space_preserved' => $plainText === $expected,
    'surrogate_scalar_decoded' => str_contains($plainText, "\u{1F600}"),
    'bidi_isolate_controls_preserved' => str_contains($plainText, "\u{2067}") && str_contains($plainText, "\u{2069}"),
    'nul_bytes_removed' => !str_contains($plainText, "\0"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
