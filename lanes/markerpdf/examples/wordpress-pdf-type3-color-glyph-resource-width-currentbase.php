<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "q 1 0 0 rg 1000 0 d0 /GlyphPaint Do Q\n";
$thinCharProc = "q /CS1 cs 0.2 0.8 0.1 scn 250 0 0 0 250 700 d1 /GlyphPaint Do Q\n";
$glyphPaint = "q 0 0 1 rg 0 0 12 12 re f BT /Fghost 7 Tf 0 0 Td (Color glyph resource text leak) Tj ET Q\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <57494445> Tj '
    . '1 0 0 1 118 720 Tm <424C4F434B> Tj '
    . 'T* 1 0 0 1 72 704 Tm <7468696E> Tj '
    . '1 0 0 1 96 704 Tm <74657874> Tj ET';
$flags = (1 << 2) | (1 << 5);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 25 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3ColorGlyph /BaseFont /T3ColorGlyph /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] /Encoding /WinAnsiEncoding /CharProcs 21 0 R /Resources 24 0 R /FontDescriptor 23 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
    . "21 0 obj\n<< /W 3 0 R /I 3 0 R /D 3 0 R /E 3 0 R /B 3 0 R /L 3 0 R /O 3 0 R /C 3 0 R /K 3 0 R /t 4 0 R /h 4 0 R /i 4 0 R /n 4 0 R /e 4 0 R /x 4 0 R >>\nendobj\n"
    . "23 0 obj\n<< /Type /FontDescriptor /FontName /T3ColorGlyphSerif /Flags {$flags} /FontWeight 400 >>\nendobj\n"
    . "24 0 obj\n<< /XObject << /GlyphPaint 30 0 R >> /ColorSpace << /CS1 /DeviceRGB >> >>\nendobj\n"
    . "25 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] /Resources << /Font << /Fghost 31 0 R >> >> /Length " . strlen($glyphPaint) . " >>\nstream\n{$glyphPaint}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$pages = $extractor->extractStyledTextPages($pdf);
$firstSpan = $pages[0]['blocks'][0]['lines'][0]['spans'][0] ?? [];

echo '<!-- markerpdf:pdf-type3-color-glyph-resource-width-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-color-glyph-resource-width-currentbase',
    'font_width_sources' => [
        'Type3 named /Encoding glyph names',
        'colored CharProc d0/d1 widths',
        'CharProc /Resources XObject painting boundary',
    ],
    'named_encoding_charproc_widths_resolved' => str_contains($plainText, 'WIDEBLOCK') && !str_contains($plainText, 'WIDE BLOCK'),
    'thin_color_glyph_width_gap_preserved' => str_contains($plainText, 'thin text') && !str_contains($plainText, 'thintext'),
    'color_glyph_resource_payload_excluded' => !str_contains($plainText, 'Color glyph resource text leak'),
    'descriptor_flags_preserved' => ($firstSpan['font_flags'] ?? null) === $flags,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
