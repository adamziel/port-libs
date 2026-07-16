<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$encodingCMap = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "/CMapName /WPType3CharProcUnicode-H def\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "16 begincidchar\n"
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
    . "<18> 116\n"
    . "<19> 101\n"
    . "<1A> 120\n"
    . "endcidchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (charproc text payload must stay hidden) Tj ET\n";
$thinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (thin charproc payload must stay hidden) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <01020304> Tj '
    . '1 0 0 1 118 720 Tm <0506070809> Tj '
    . 'T* 1 0 0 1 72 704 Tm <14151617> Tj '
    . '1 0 0 1 96 704 Tm <18191A18> Tj ET';

$widths = array_fill(0, 55, 500.0);
foreach ([66, 67, 68, 69, 73, 75, 76, 79, 87] as $code) {
    $widths[$code - 66] = 1000.0;
}
foreach ([101, 104, 105, 110, 116, 120] as $code) {
    $widths[$code - 66] = 250.0;
}
$widthArray = implode(' ', array_map(static fn (float $width): string => rtrim(rtrim(sprintf('%.1F', $width), '0'), '.'), $widths));

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 25 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcUnicode /BaseFont /T3CharProcUnicode /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] /FirstChar 66 /LastChar 118 /Widths 22 0 R /Encoding 19 0 R /CharProcs 21 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
    . "19 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
    . "21 0 obj\n<< /W.wide 3 0 R /I.wide 3 0 R /D.wide 3 0 R /E.wide 3 0 R /B.wide 3 0 R /L.wide 3 0 R /O.wide 3 0 R /C.wide 3 0 R /K.wide 3 0 R /t.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R /e.thin 4 0 R /x.thin 4 0 R >>\nendobj\n"
    . "22 0 obj\n[{$widthArray}]\nendobj\n"
    . "25 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charproc-to-unicode-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charproc-glyph-name-unicode-boundary',
    'font_text_sources' => [
        'Type3 /Encoding CMap source-to-CID rows',
        'standard Adobe glyph names in /CharProcs',
        'no /ToUnicode CMap',
    ],
    'charproc_glyph_names_decode_text' => str_contains($plainText, 'WIDEBLOCK') && str_contains($plainText, 'thin text'),
    'raw_source_controls_excluded' => !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $plainText),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'charproc text payload') && !str_contains($plainText, 'thin charproc payload'),
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
