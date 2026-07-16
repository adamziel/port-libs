<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validCharProc = "1000 0 d0\nBT /Fghost 9 Tf (valid initial charproc text leak) Tj ET\n";
$lateMetricCharProc = "BT /Fghost 9 Tf (pre metric paint text leak) Tj ET\n1000 0 d0\nBT /Fghost 9 Tf (late metric charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 118 704 Tm <58595A> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /W.good /i.good /d.good /e.good '
    . '/B.good /l.good /o.good /c.good /k.good '
    . '84 /L.late /a.late /t.late /e.late /G.late /a.late /p.late] >>';
$charProcs = '<< /W.good 3 0 R /i.good 3 0 R /d.good 3 0 R /e.good 3 0 R '
    . '/B.good 3 0 R /l.good 3 0 R /o.good 3 0 R /c.good 3 0 R /k.good 3 0 R '
    . '/L.late 4 0 R /a.late 4 0 R /t.late 4 0 R /e.late 4 0 R '
    . '/G.late 4 0 R /p.late 4 0 R >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3InitialMetricBoundary /BaseFont /T3InitialMetricBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($validCharProc) . " >>\nstream\n{$validCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($lateMetricCharProc) . " >>\nstream\n{$lateMetricCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3InitialMetricBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charprocs-initial-operator-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charprocs-pre-metric-paint-boundary',
    'font_width_sources' => [
        'initial Type3 CharProc d0 width',
        'post-paint Type3 CharProc d0 rejected',
        'FontDescriptor MissingWidth fallback for rejected glyph metrics',
    ],
    'valid_initial_metric_width_preserved' => str_contains($plainText, 'WideBlock') && !str_contains($plainText, 'Wide Block'),
    'post_paint_metric_rejected' => str_contains($plainText, 'Late Gap') && !str_contains($plainText, 'LateGap'),
    'missing_width_fallback_used' => str_contains($plainText, 'Late Gap'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'charproc text leak') && !str_contains($plainText, 'pre metric paint text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
