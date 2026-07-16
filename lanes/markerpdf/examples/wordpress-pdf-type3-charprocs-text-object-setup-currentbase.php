<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "BT /Fghost 9 Tf 0 0 Td ET\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (wide text-object setup charproc text leak) Tj ET\n";
$thinCharProc = "BT 12 TL 1 0 0 1 0 0 Tm T* ET\n250 0 0 0 250 700 d1\n"
    . "BT /Fghost 9 Tf (thin text-object setup charproc text leak) Tj ET\n";
$paintBeforeMetricCharProc = "BT /Fghost 9 Tf (pre metric paint text leak) Tj ET\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (painted text-object setup charproc text leak) Tj ET\n";
$openTextObjectCharProc = "BT /Fghost 9 Tf\n1000 0 d0\nET\n"
    . "BT /Fghost 9 Tf (open text-object setup charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 96 704 Tm <58595A5B> Tj '
    . 'T* 1 0 0 1 72 688 Tm <6162636465> Tj '
    . '1 0 0 1 118 688 Tm <666768> Tj '
    . 'T* 1 0 0 1 72 672 Tm <696A6B6C> Tj '
    . '1 0 0 1 118 672 Tm <6D6E6F> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /W.textobj /i.textobj /d.textobj /e.textobj '
    . '/B.textobj /l.textobj /o.textobj /c.textobj /k.textobj '
    . '84 /T.thinobj /h.thinobj /i.thinobj /n.thinobj /T.thinobj /e.thinobj /x.thinobj /t.thinobj '
    . '97 /P.paint /a.paint /i.paint /n.paint /t.paint '
    . '102 /G.paint /a.paint /p.paint '
    . '105 /O.open /p.open /e.open /n.open 109 /G.open /a.open /p.open] >>';
$charProcs = '<< /W.textobj 3 0 R /i.textobj 3 0 R /d.textobj 3 0 R /e.textobj 3 0 R '
    . '/B.textobj 3 0 R /l.textobj 3 0 R /o.textobj 3 0 R /c.textobj 3 0 R /k.textobj 3 0 R '
    . '/T.thinobj 4 0 R /h.thinobj 4 0 R /i.thinobj 4 0 R /n.thinobj 4 0 R '
    . '/e.thinobj 4 0 R /x.thinobj 4 0 R /t.thinobj 4 0 R '
    . '/P.paint 5 0 R /a.paint 5 0 R /i.paint 5 0 R /n.paint 5 0 R '
    . '/t.paint 5 0 R /G.paint 5 0 R /p.paint 5 0 R '
    . '/O.open 6 0 R /p.open 6 0 R /e.open 6 0 R /n.open 6 0 R '
    . '/G.open 6 0 R /a.open 6 0 R >>';
$fallbackWidths = array_fill(0, 47, 250);
foreach (range(19, 26) as $index) {
    $fallbackWidths[$index] = 1000;
}
$fallbackWidthsText = implode(' ', $fallbackWidths);

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3TextObjectSetupBoundary /BaseFont /T3TextObjectSetupBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 111 /Widths [{$fallbackWidthsText}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 7 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($paintBeforeMetricCharProc) . " >>\nstream\n{$paintBeforeMetricCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($openTextObjectCharProc) . " >>\nstream\n{$openTextObjectCharProc}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /FontDescriptor /FontName /T3TextObjectSetupBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-text-object-setup-boundary',
    'font_width_sources' => [
        'balanced no-paint Type3 CharProc text object before d0',
        'balanced no-paint Type3 CharProc text object before d1',
        'pre-metric text painting rejected',
        'open pre-metric text object rejected',
    ],
    'text_object_setup_charproc_widths_preserved' => $lines === ['WideBlock', 'Thin Text', 'Paint Gap', 'Open Gap'],
    'pre_metric_text_paint_rejected' => str_contains($plainText, 'Paint Gap') && !str_contains($plainText, 'PaintGap'),
    'open_text_object_metric_rejected' => str_contains($plainText, 'Open Gap') && !str_contains($plainText, 'OpenGap'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'text-object setup charproc text leak')
        && !str_contains($plainText, 'pre metric paint text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'text_object_setup_charproc_widths_preserved',
    'pre_metric_text_paint_rejected',
    'open_text_object_metric_rejected',
    'charproc_payload_visible_text_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs text-object setup boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-text-object-setup-currentbase '
    . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
