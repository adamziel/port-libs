<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "1000 0 d0\n";
$thinCharProc = "250 0 0 0 250 700 d1\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj 1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj 1 0 0 1 96 704 Tm <58595A5B> Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3Import /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding << /Type /Encoding /Differences [65 /W.wide /i.wide /d.wide /e.wide /B.wide /l.wide /o.wide /c.wide /k.wide 84 /T.thin /h.thin /i.thin /n.thin /T.thin /e.thin /x.thin /t.thin] >> "
    . "/CharProcs << /W.wide 3 0 R /i.wide 4 0 R /d.wide 5 0 R /e.wide 6 0 R /B.wide 7 0 R /l.wide 8 0 R /o.wide 9 0 R /c.wide 10 0 R /k.wide 11 0 R /T.thin 12 0 R /h.thin 13 0 R /i.thin 14 0 R /n.thin 15 0 R /e.thin 16 0 R /x.thin 17 0 R /t.thin 18 0 R >> >>\nendobj\n";
for ($objectNumber = 3; $objectNumber <= 18; $objectNumber++) {
    $charProc = $objectNumber <= 11 ? $wideCharProc : $thinCharProc;
    $pdf .= "{$objectNumber} 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n";
}
$pdf .= "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);

echo '<!-- markerpdf:pdf-type3-charproc-widths ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charprocs-d0-d1-widths-advance-boundary',
    'font_width_sources' => ['/CharProcs d0', '/CharProcs d1'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
