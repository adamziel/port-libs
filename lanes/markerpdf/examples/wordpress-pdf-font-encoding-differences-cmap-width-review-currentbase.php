<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "1000 0 d0\n";
$thinCharProc = "250 0 0 0 250 700 d1\n";
$toUnicode = "/CIDInit /ProcSet findresource begin\n"
    . "12 dict begin\n"
    . "begincmap\n"
    . "1 begincodespacerange\n"
    . "<00> <FF>\n"
    . "endcodespacerange\n"
    . "17 beginbfchar\n"
    . "<41> <0057>\n"
    . "<42> <0069>\n"
    . "<43> <0064>\n"
    . "<44> <0065>\n"
    . "<45> <0042>\n"
    . "<46> <006C>\n"
    . "<47> <006F>\n"
    . "<48> <0063>\n"
    . "<49> <006B>\n"
    . "<54> <0054>\n"
    . "<55> <0068>\n"
    . "<56> <0069>\n"
    . "<57> <006E>\n"
    . "<58> <0054>\n"
    . "<59> <0065>\n"
    . "<5A> <0078>\n"
    . "<5B> <0074>\n"
    . "endbfchar\n"
    . "endcmap\n"
    . "CMapName currentdict /CMap defineresource pop\n"
    . "end\n"
    . "end\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 21 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3IndirectImport /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] /Encoding 19 0 R /CharProcs << /W.wide 3 0 R /i.wide 4 0 R /d.wide 5 0 R /e.wide 6 0 R /B.wide 7 0 R /l.wide 8 0 R /o.wide 9 0 R /c.wide 10 0 R /k.wide 11 0 R /T.thin 12 0 R /h.thin 13 0 R /i.thin 14 0 R /n.thin 15 0 R /e.thin 16 0 R /x.thin 17 0 R /t.thin 18 0 R >> /ToUnicode 20 0 R >>\nendobj\n";

for ($objectNumber = 3; $objectNumber <= 18; $objectNumber++) {
    $charProc = $objectNumber <= 11 ? $wideCharProc : $thinCharProc;
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n";
}

$pdf .= "19 0 obj\n<< /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences 22 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($toUnicode) . " >>\nstream\n{$toUnicode}\nendstream\nendobj\n"
    . "21 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "22 0 obj\n[65 /W.wide /i.wide /d.wide /e.wide /B.wide /l.wide /o.wide /c.wide /k.wide 84 /T.thin /h.thin /i.thin /n.thin /T.thin /e.thin /x.thin /t.thin]\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-font-encoding-differences-cmap-width-review-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-indirect-encoding-differences-cmap-width-boundary',
    'font_width_sources' => [
        'ToUnicode CMap source codes',
        'indirect Type3 /Encoding /Differences',
        'Type3 /CharProcs d0/d1 widths',
    ],
    'to_unicode_cmap_text_decoded' => str_contains($plainText, 'WideBlock') && str_contains($plainText, 'Thin Text'),
    'indirect_type3_encoding_differences_resolved' => !str_contains($plainText, 'Wide Block'),
    'charproc_width_gap_preserved' => !str_contains($plainText, 'ThinText'),
    'raw_source_codes_excluded' => !str_contains($plainText, 'ABCD') && !str_contains($plainText, 'TUVW'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
