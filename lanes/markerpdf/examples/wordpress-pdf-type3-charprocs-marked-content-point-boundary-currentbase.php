<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$mpCharProc = "/GlyphPoint MP\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (wide marked-content point charproc text leak) Tj ET\n";
$dpCharProc = "/GlyphPoint << /ActualText (1000 0 d0 point property decoy) /Private << /Fake 1000 0 d0 >> >> DP\n"
    . "250 0 0 0 250 700 d1\n"
    . "BT /Fghost 9 Tf (thin marked-content point charproc text leak) Tj ET\n";
$malformedDpCharProc = "999 /GlyphPoint << /ActualText (bad DP property decoy) >> DP\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (malformed marked-content point charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 96 704 Tm <58595A5B> Tj '
    . 'T* 1 0 0 1 72 688 Tm <616263> Tj '
    . '1 0 0 1 109 688 Tm <64656667> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /W.mp /i.mp /d.mp /e.mp '
    . '/B.mp /l.mp /o.mp /c.mp /k.mp '
    . '84 /T.dp /h.dp /i.dp /n.dp /T.dp /e.dp /x.dp /t.dp '
    . '97 /B.baddp /a.baddp /d.baddp /J.baddp /o.baddp /i.baddp /n.baddp] >>';
$charProcs = '<< /W.mp 3 0 R /i.mp 3 0 R /d.mp 3 0 R /e.mp 3 0 R '
    . '/B.mp 3 0 R /l.mp 3 0 R /o.mp 3 0 R /c.mp 3 0 R /k.mp 3 0 R '
    . '/T.dp 4 0 R /h.dp 4 0 R /i.dp 4 0 R /n.dp 4 0 R '
    . '/e.dp 4 0 R /x.dp 4 0 R /t.dp 4 0 R '
    . '/B.baddp 5 0 R /a.baddp 5 0 R /d.baddp 5 0 R '
    . '/J.baddp 5 0 R /o.baddp 5 0 R /i.baddp 5 0 R /n.baddp 5 0 R >>';
$staleWidths = array_fill(0, 39, 250);
foreach (range(19, 26) as $index) {
    $staleWidths[$index] = 1000;
}
$staleWidthsText = implode(' ', $staleWidths);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3MarkedContentPointBoundary /BaseFont /T3MarkedContentPointBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 103 /Widths [{$staleWidthsText}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($mpCharProc) . " >>\nstream\n{$mpCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($dpCharProc) . " >>\nstream\n{$dpCharProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($malformedDpCharProc) . " >>\nstream\n{$malformedDpCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3MarkedContentPointBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charprocs-marked-content-point-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charprocs-marked-content-point-boundary',
    'font_width_sources' => [
        'Type3 CharProc MP point marker before d0 width',
        'Type3 CharProc DP property marker before d1 width',
        'malformed DP operands rejected before late metric fallback',
    ],
    'marked_content_point_widths_preserved' => $lines === ['WideBlock', 'Thin Text', 'Bad Join'],
    'mp_width_overrides_stale_widths' => str_contains($plainText, 'WideBlock') && !str_contains($plainText, 'Wide Block'),
    'dp_width_overrides_stale_widths' => str_contains($plainText, 'Thin Text') && !str_contains($plainText, 'ThinText'),
    'malformed_dp_metric_rejected' => str_contains($plainText, 'Bad Join') && !str_contains($plainText, 'BadJoin'),
    'marked_content_property_decoys_excluded' => !str_contains($plainText, 'point property decoy') && !str_contains($plainText, 'Private'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'marked-content point charproc text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
