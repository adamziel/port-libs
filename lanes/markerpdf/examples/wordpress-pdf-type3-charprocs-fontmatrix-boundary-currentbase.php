<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "500 0 d0\nBT /Fghost 9 Tf (wide fontmatrix charproc text leak) Tj ET\n";
$thinCharProc = "125 0 0 0 125 700 d1\nBT /Fghost 9 Tf (thin fontmatrix charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /W.wide /i.wide /d.wide /e.wide '
    . '/B.wide /l.wide /o.wide /c.wide /k.wide 84 /T.thin /h.thin /i.thin '
    . '/n.thin /T.thin /e.thin /x.thin /t.thin] >>';
$charProcs = '<< /W.wide 3 0 R /i.wide 3 0 R /d.wide 3 0 R /e.wide 3 0 R '
    . '/B.wide 3 0 R /l.wide 3 0 R /o.wide 3 0 R /c.wide 3 0 R /k.wide 3 0 R '
    . '/T.thin 4 0 R /h.thin 4 0 R /i.thin 4 0 R /n.thin 4 0 R '
    . '/e.thin 4 0 R /x.thin 4 0 R /t.thin 4 0 R >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 19 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3FontMatrixCharProcs /BaseFont /T3FontMatrixCharProcs "
    . "/Resources << /Font << /NestedT3 << /Subtype /Type3 /FontMatrix [0.001 0 0 0.001 0 0] >> >> >> "
    . "/FontBBox [0 0 500 350] /FontMatrix [0.002 0 0 0.002 0 0] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
    . "19 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charprocs-fontmatrix-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charprocs-fontmatrix-boundary',
    'font_width_sources' => [
        'Type3 /FontMatrix [0.002 0 0 0.002 0 0]',
        'nested resource FontMatrix decoy ignored',
        'Type3 CharProc d0 width 500 normalized to 1000 text-space units',
        'Type3 CharProc d1 width 125 normalized to 250 text-space units',
    ],
    'fontmatrix_charproc_widths_normalized' => $lines === ['WideBlock', 'Thin Text'],
    'wide_block_spacing_preserved' => str_contains($plainText, 'WideBlock') && !str_contains($plainText, 'Wide Block'),
    'thin_text_spacing_preserved' => str_contains($plainText, 'Thin Text') && !str_contains($plainText, 'ThinText'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'fontmatrix charproc text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
