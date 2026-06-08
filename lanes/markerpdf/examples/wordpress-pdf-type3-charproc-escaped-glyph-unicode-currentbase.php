<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide escaped glyph charproc payload leak) Tj ET\n";
$thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin escaped glyph charproc payload leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 '
    . '/uni#30#30#35#37.wide /uni#30#30#36#39.wide /uni#30#30#36#34.wide '
    . '/uni#30#30#36#35.wide /uni#30#30#34#32.wide /uni#30#30#36#43.wide '
    . '/uni#30#30#36#46.wide /uni#30#30#36#33.wide /uni#30#30#36#42.wide '
    . '84 /u#30#30#37#34.thin /u#30#30#36#38.thin /u#30#30#36#39.thin '
    . '/u#30#30#36#45.thin /u#30#30#37#34.thin /u#30#30#36#35.thin '
    . '/u#30#30#37#38.thin /u#30#30#37#34.thin] >>';
$charProcs = '<< '
    . '/uni#30#30#35#37.wide 3 0 R /uni#30#30#36#39.wide 3 0 R '
    . '/uni#30#30#36#34.wide 3 0 R /uni#30#30#36#35.wide 3 0 R '
    . '/uni#30#30#34#32.wide 3 0 R /uni#30#30#36#43.wide 3 0 R '
    . '/uni#30#30#36#46.wide 3 0 R /uni#30#30#36#33.wide 3 0 R '
    . '/uni#30#30#36#42.wide 3 0 R /u#30#30#37#34.thin 4 0 R '
    . '/u#30#30#36#38.thin 4 0 R /u#30#30#36#39.thin 4 0 R '
    . '/u#30#30#36#45.thin 4 0 R /u#30#30#36#35.thin 4 0 R '
    . '/u#30#30#37#38.thin 4 0 R >>';
$widthValues = array_fill(0, 27, 250);
foreach (range(19, 26) as $index) {
    $widthValues[$index] = 1000;
}
$staleWidths = implode(' ', $widthValues);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3EscapedGlyphs /BaseFont /T3EscapedGlyphs "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 91 /Widths [{$staleWidths}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3EscapedGlyphs /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charproc-escaped-glyph-unicode-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-escaped-charproc-glyph-name-boundary',
    'font_text_sources' => [
        'escaped PDF names in Type3 /Encoding Differences',
        'escaped PDF names in Type3 /CharProcs keys',
        'CharProc d0/d1 widths before stale /Widths',
        'no /ToUnicode CMap',
    ],
    'escaped_type3_glyph_names_decode_text' => $lines === ['WideBlock', 'thin text'],
    'escaped_charproc_keys_select_widths' => !str_contains($plainText, 'Wide Block') && !str_contains($plainText, 'thintext'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'escaped glyph charproc payload'),
    'raw_escaped_glyph_names_excluded' => !str_contains($plainText, 'uni#30') && !str_contains($plainText, 'u#30'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
