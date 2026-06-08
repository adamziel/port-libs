<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validCharProc = "1000 0 d0\nBT /Fghost 9 Tf (valid text-object metric boundary charproc text leak) Tj ET\n";
$hiddenD0CharProc = "1000 0 d0\nBT 250 0 d0 ET\n"
    . "BT /Fghost 9 Tf (hidden d0 text-object metric charproc text leak) Tj ET\n";
$hiddenD1CharProc = "1000 0 0 0 1000 700 d1\nBT 250 0 0 0 250 700 d1 ET\n"
    . "BT /Fghost 9 Tf (hidden d1 text-object metric charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <45464748> Tj '
    . 'T* 1 0 0 1 72 704 Tm <545556575859> Tj '
    . '1 0 0 1 118 704 Tm <5A5B5C> Tj '
    . 'T* 1 0 0 1 72 688 Tm <616263646566> Tj '
    . '1 0 0 1 118 688 Tm <676869> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /G.valid /o.valid /o.valid /d.valid '
    . '/W.valid /i.valid /d.valid /e.valid '
    . '84 /H.hiddend0 /i.hiddend0 /d.hiddend0 /d.hiddend0 /e.hiddend0 /n.hiddend0 '
    . '/G.hiddend0 /a.hiddend0 /p.hiddend0 '
    . '97 /M.hiddend1 /e.hiddend1 /t.hiddend1 /r.hiddend1 /i.hiddend1 /c.hiddend1 '
    . '/G.hiddend1 /a.hiddend1 /p.hiddend1] >>';
$charProcs = '<< /G.valid 3 0 R /o.valid 3 0 R /d.valid 3 0 R '
    . '/W.valid 3 0 R /i.valid 3 0 R /e.valid 3 0 R '
    . '/H.hiddend0 4 0 R /i.hiddend0 4 0 R /d.hiddend0 4 0 R '
    . '/e.hiddend0 4 0 R /n.hiddend0 4 0 R /G.hiddend0 4 0 R '
    . '/a.hiddend0 4 0 R /p.hiddend0 4 0 R '
    . '/M.hiddend1 5 0 R /e.hiddend1 5 0 R /t.hiddend1 5 0 R '
    . '/r.hiddend1 5 0 R /i.hiddend1 5 0 R /c.hiddend1 5 0 R '
    . '/G.hiddend1 5 0 R /a.hiddend1 5 0 R /p.hiddend1 5 0 R >>';
$fallbackWidths = implode(' ', array_fill(0, 41, 250));

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3TextObjectMetricBoundary /BaseFont /T3TextObjectMetricBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 105 /Widths [{$fallbackWidths}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($validCharProc) . " >>\nstream\n{$validCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($hiddenD0CharProc) . " >>\nstream\n{$hiddenD0CharProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($hiddenD1CharProc) . " >>\nstream\n{$hiddenD1CharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3TextObjectMetricBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-text-object-metric-boundary',
    'font_width_sources' => [
        'valid initial Type3 CharProc d0 width',
        'duplicate d0 hidden inside post-metric BT/ET text object rejected',
        'duplicate d1 hidden inside post-metric BT/ET text object rejected',
        'font /Widths fallback after malformed hidden duplicate metrics',
    ],
    'valid_charproc_width_preserved' => str_contains($plainText, 'GoodWide') && !str_contains($plainText, 'Good Wide'),
    'hidden_d0_text_object_metric_rejected' => str_contains($plainText, 'Hidden Gap') && !str_contains($plainText, 'HiddenGap'),
    'hidden_d1_text_object_metric_rejected' => str_contains($plainText, 'Metric Gap') && !str_contains($plainText, 'MetricGap'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'text-object metric charproc text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'valid_charproc_width_preserved',
    'hidden_d0_text_object_metric_rejected',
    'hidden_d1_text_object_metric_rejected',
    'charproc_payload_visible_text_excluded',
] as $flag) {
    if ($review[$flag] !== true) {
        throw new RuntimeException("Type3 text-object metric boundary failed: {$flag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-text-object-metric-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
