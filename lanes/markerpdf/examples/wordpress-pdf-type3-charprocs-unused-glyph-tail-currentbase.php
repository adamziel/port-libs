<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide unused-tail charproc text leak) Tj ET\n";
$unusedCharProc = "250 0 d0\nBT /Fghost 9 Tf (UNUSED GLYPH TAIL LEAK) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /W.used /i.used /d.used /e.used '
    . '/B.used /l.used /o.used /c.used /k.used] >>';
$charProcs = '<< /Unused.tail 4 0 R 99 0 R /W.used 3 0 R /i.used 3 0 R '
    . '/d.used 3 0 R /e.used 3 0 R /B.used 3 0 R /l.used 3 0 R '
    . '/o.used 3 0 R /c.used 3 0 R /k.used 3 0 R >>';
$fallbackWidths = implode(' ', array_fill(0, 9, 250));

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3UnusedGlyphTailBoundary "
    . "/BaseFont /T3UnusedGlyphTailBoundary /FontBBox [0 0 1000 700] "
    . "/FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 65 /LastChar 73 "
    . "/Widths [{$fallbackWidths}] /Encoding {$encoding} /CharProcs {$charProcs} "
    . "/FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($unusedCharProc) . " >>\nstream\n{$unusedCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3UnusedGlyphTailBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charprocs-unused-glyph-tail-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charprocs-unused-glyph-tail-boundary',
    'font_width_sources' => [
        'selected Type3 CharProc d0 widths',
        'unused malformed glyph-entry tail ignored for selected width map',
        'FontDescriptor MissingWidth fallback remains available for selected malformed glyphs',
    ],
    'used_charproc_widths_preserved' => str_contains($plainText, 'WideBlock') && !str_contains($plainText, 'Wide Block'),
    'unused_glyph_tail_ignored_for_widths' => str_contains($plainText, 'WideBlock'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'charproc text leak') && !str_contains($plainText, 'UNUSED GLYPH TAIL LEAK'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
