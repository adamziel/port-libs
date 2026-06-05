<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "4 Tc 0 Tw 100 Tz /Fghost 9 Tf 3 Tr 1 Ts\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (wide text-state charproc text leak) Tj ET\n";
$thinCharProc = "12 TL /Fghost 9 Tf 0 Tr\n250 0 0 0 250 700 d1\n"
    . "BT /Fghost 9 Tf (thin text-state charproc text leak) Tj ET\n";
$malformedTextStateCharProc = "(bad text-state operand) /Fghost 9 Tf\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (malformed text-state charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 96 704 Tm <58595A5B> Tj '
    . 'T* 1 0 0 1 72 688 Tm <6162636465> Tj '
    . '1 0 0 1 118 688 Tm <666768> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /W.widestate /i.widestate /d.widestate /e.widestate '
    . '/B.widestate /l.widestate /o.widestate /c.widestate /k.widestate '
    . '84 /T.thinstate /h.thinstate /i.thinstate /n.thinstate /T.thinstate /e.thinstate /x.thinstate /t.thinstate '
    . '97 /G.guard /u.guard /a.guard /r.guard /d.guard /G.guard /a.guard /p.guard] >>';
$charProcs = '<< /W.widestate 3 0 R /i.widestate 3 0 R /d.widestate 3 0 R /e.widestate 3 0 R '
    . '/B.widestate 3 0 R /l.widestate 3 0 R /o.widestate 3 0 R /c.widestate 3 0 R /k.widestate 3 0 R '
    . '/T.thinstate 4 0 R /h.thinstate 4 0 R /i.thinstate 4 0 R /n.thinstate 4 0 R '
    . '/e.thinstate 4 0 R /x.thinstate 4 0 R /t.thinstate 4 0 R '
    . '/G.guard 5 0 R /u.guard 5 0 R /a.guard 5 0 R /r.guard 5 0 R '
    . '/d.guard 5 0 R /p.guard 5 0 R >>';
$staleWidths = array_fill(0, 40, 250);
foreach (range(19, 26) as $index) {
    $staleWidths[$index] = 1000;
}
$staleWidthsText = implode(' ', $staleWidths);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3TextStateBoundary /BaseFont /T3TextStateBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 104 /Widths [{$staleWidthsText}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($malformedTextStateCharProc) . " >>\nstream\n{$malformedTextStateCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3TextStateBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charprocs-text-state-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charprocs-text-state-boundary',
    'font_width_sources' => [
        'Type3 CharProc text-state setup before d0 width',
        'Type3 CharProc text-state setup before d1 width',
        'malformed text-state operands before metrics fail closed to Widths/MissingWidth',
    ],
    'text_state_charproc_widths_preserved' => $lines === ['WideBlock', 'Thin Text', 'Guard Gap'],
    'wide_block_spacing_preserved' => str_contains($plainText, 'WideBlock') && !str_contains($plainText, 'Wide Block'),
    'thin_text_spacing_preserved' => str_contains($plainText, 'Thin Text') && !str_contains($plainText, 'ThinText'),
    'malformed_text_state_operand_rejected' => str_contains($plainText, 'Guard Gap') && !str_contains($plainText, 'GuardGap'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'text-state charproc text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
