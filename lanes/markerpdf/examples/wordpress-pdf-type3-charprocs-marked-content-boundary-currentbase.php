<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "/Glyph << /ActualText (250 0 d0 marked content decoy) /Private << /Fake 250 0 d0 >> >> BDC\n"
    . "1000 0 d0\nEMC\nBT /Fghost 9 Tf (wide marked content charproc text leak) Tj ET\n";
$thinCharProc = "/Glyph BMC\nEMC\n250 0 0 0 250 700 d1\n"
    . "BT /Fghost 9 Tf (thin marked content charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 96 704 Tm <58595A5B> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /W.marked /i.marked /d.marked /e.marked '
    . '/B.marked /l.marked /o.marked /c.marked /k.marked '
    . '84 /T.markedthin /h.markedthin /i.markedthin /n.markedthin '
    . '/T.markedthin /e.markedthin /x.markedthin /t.markedthin] >>';
$charProcs = '<< /W.marked 3 0 R /i.marked 3 0 R /d.marked 3 0 R /e.marked 3 0 R '
    . '/B.marked 3 0 R /l.marked 3 0 R /o.marked 3 0 R /c.marked 3 0 R /k.marked 3 0 R '
    . '/T.markedthin 4 0 R /h.markedthin 4 0 R /i.markedthin 4 0 R /n.markedthin 4 0 R '
    . '/e.markedthin 4 0 R /x.markedthin 4 0 R /t.markedthin 4 0 R >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3MarkedContentBoundary /BaseFont /T3MarkedContentBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3MarkedContentBoundary /Flags 4 /MissingWidth 500 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charprocs-marked-content-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charprocs-marked-content-boundary',
    'font_width_sources' => [
        'Type3 CharProc BDC marked-content wrapper before d0 width',
        'Type3 CharProc BMC/EMC marked-content wrapper before d1 width',
        'FontDescriptor MissingWidth fallback excluded when CharProc metrics are valid',
    ],
    'marked_content_metric_widths_preserved' => str_contains($plainText, 'WideBlock') && str_contains($plainText, 'Thin Text'),
    'missing_width_fallback_excluded_from_grouping' => !str_contains($plainText, 'Wide Block') && !str_contains($plainText, 'ThinText'),
    'marked_content_property_decoy_excluded' => !str_contains($plainText, 'marked content decoy') && !str_contains($plainText, 'Private'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'marked content charproc text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
