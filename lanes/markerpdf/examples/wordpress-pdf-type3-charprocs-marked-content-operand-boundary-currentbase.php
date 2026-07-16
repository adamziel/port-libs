<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$malformedBmcCharProc = "999 /Glyph BMC\n1000 0 d0\nEMC\n"
    . "BT /Fghost 9 Tf (malformed BMC charproc text leak) Tj ET\n";
$malformedBdcCharProc = "999 /Glyph << /ActualText (250 0 d0 marked-content decoy) >> BDC\n1000 0 d0\nEMC\n"
    . "BT /Fghost 9 Tf (malformed BDC charproc text leak) Tj ET\n";
$validBmcCharProc = "/Glyph BMC\nEMC\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (valid BMC charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <414243> Tj '
    . '1 0 0 1 109 720 Tm <444546> Tj '
    . 'T* 1 0 0 1 72 704 Tm <4B4C4D> Tj '
    . '1 0 0 1 109 704 Tm <4E4F50> Tj '
    . 'T* 1 0 0 1 72 688 Tm <55565758> Tj '
    . '1 0 0 1 121 688 Tm <595A> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /B.badbmc /m.badbmc /c.badbmc '
    . '/G.badbmc /a.badbmc /p.badbmc '
    . '75 /B.badbdc /d.badbdc /c.badbdc /G.badbdc /a.badbdc /p.badbdc '
    . '85 /W.goodbmc /i.goodbmc /d.goodbmc /e.goodbmc /O.goodbmc /k.goodbmc] >>';
$charProcs = '<< /B.badbmc 3 0 R /m.badbmc 3 0 R /c.badbmc 3 0 R '
    . '/G.badbmc 3 0 R /a.badbmc 3 0 R /p.badbmc 3 0 R '
    . '/B.badbdc 4 0 R /d.badbdc 4 0 R /c.badbdc 4 0 R /G.badbdc 4 0 R '
    . '/a.badbdc 4 0 R /p.badbdc 4 0 R '
    . '/W.goodbmc 5 0 R /i.goodbmc 5 0 R /d.goodbmc 5 0 R '
    . '/e.goodbmc 5 0 R /O.goodbmc 5 0 R /k.goodbmc 5 0 R >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3MarkedContentOperandBoundary /BaseFont /T3MarkedContentOperandBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($malformedBmcCharProc) . " >>\nstream\n{$malformedBmcCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($malformedBdcCharProc) . " >>\nstream\n{$malformedBdcCharProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($validBmcCharProc) . " >>\nstream\n{$validBmcCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3MarkedContentOperandBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charprocs-marked-content-operand-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charprocs-marked-content-operand-boundary',
    'font_width_sources' => [
        'Malformed Type3 CharProc BMC operands rejected before d0 width',
        'Malformed Type3 CharProc BDC operands rejected before d0 width',
        'Valid Type3 CharProc BMC/EMC wrapper still preserves d0 width',
    ],
    'malformed_marked_content_metrics_rejected' => str_contains($plainText, 'Bmc Gap') && str_contains($plainText, 'Bdc Gap'),
    'valid_marked_content_metric_preserved' => str_contains($plainText, 'WideOk') && !str_contains($plainText, 'Wide Ok'),
    'malformed_metric_payloads_not_grouped' => !str_contains($plainText, 'BmcGap') && !str_contains($plainText, 'BdcGap'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'charproc text leak'),
    'marked_content_property_decoy_excluded' => !str_contains($plainText, 'marked-content decoy'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
