<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPType3PrivateUnusedGlyph-H def\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "15 begincidchar\n"
    . "<01> 87\n"
    . "<02> 73\n"
    . "<03> 68\n"
    . "<04> 69\n"
    . "<05> 66\n"
    . "<06> 76\n"
    . "<07> 79\n"
    . "<08> 67\n"
    . "<09> 75\n"
    . "<14> 116\n"
    . "<15> 104\n"
    . "<16> 105\n"
    . "<17> 110\n"
    . "<18> 101\n"
    . "<19> 120\n"
    . "endcidchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide private boundary charproc text leak) Tj ET\n";
$thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin private boundary charproc text leak) Tj ET\n";
$privateUnusedCharProc = "900 0 d0\nBT /Fghost 9 Tf (unused private glyph text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <01020304> Tj '
    . '1 0 0 1 118 720 Tm <0506070809> Tj '
    . 'T* 1 0 0 1 72 704 Tm <14151617> Tj '
    . '1 0 0 1 96 704 Tm <14181914> Tj ET';
$charProcs = '<< /W 3 0 R /I 3 0 R /D 3 0 R /E 3 0 R '
    . '/B 3 0 R /L 3 0 R /O 3 0 R /C 3 0 R /K 3 0 R '
    . '/t 4 0 R /h 4 0 R /i 4 0 R /n 4 0 R /e 4 0 R /x 4 0 R '
    . '/Private.UnusedGlyph 5 0 R >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 25 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3PrivateUnusedGlyph /BaseFont /T3PrivateUnusedGlyph "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding 19 0 R /CharProcs 21 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($privateUnusedCharProc) . " >>\nstream\n{$privateUnusedCharProc}\nendstream\nendobj\n"
    . "19 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "21 0 obj\n{$charProcs}\nendobj\n"
    . "25 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charproc-private-glyph-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charproc-private-glyph-boundary',
    'font_text_sources' => [
        'Type3 /Encoding CMap source-to-CID rows',
        'standard Adobe glyph names selected by /CharProcs',
        'unused private /CharProcs name ignored for no-ToUnicode fallback',
    ],
    'standard_charprocs_decode_text' => str_contains($plainText, 'WIDEBLOCK') && str_contains($plainText, 'thin text'),
    'unused_private_charproc_ignored' => !str_contains($plainText, 'Private.UnusedGlyph'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'private boundary charproc text leak') && !str_contains($plainText, 'unused private glyph text leak'),
    'wide_width_boundary_preserved' => !str_contains($plainText, 'WIDE BLOCK'),
    'thin_width_boundary_preserved' => !str_contains($plainText, 'thintext'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
