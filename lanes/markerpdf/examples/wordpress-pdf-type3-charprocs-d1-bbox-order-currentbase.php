<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validThinCharProc = "250 0 0 0 250 700 d1\nBT /Fghost 9 Tf (valid d1 bbox order charproc text leak) Tj ET\n";
$invertedXCharProc = "1000 0 300 0 100 700 d1\nBT /Fghost 9 Tf (inverted x bbox charproc text leak) Tj ET\n";
$invertedYCharProc = "1000 0 0 700 1000 100 d1\nBT /Fghost 9 Tf (inverted y bbox charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 96 720 Tm <45464748> Tj '
    . 'T* 1 0 0 1 72 704 Tm <54555657> Tj '
    . '1 0 0 1 118 704 Tm <58595A> Tj '
    . 'T* 1 0 0 1 72 688 Tm <6162636465> Tj '
    . '1 0 0 1 118 688 Tm <666768> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /T.valid /h.valid /i.valid /n.valid '
    . '/T.valid /e.valid /x.valid /t.valid '
    . '84 /F.xflip /l.xflip /i.xflip /p.xflip /G.xflip /a.xflip /p.xflip '
    . '97 /Y.yflip /f.yflip /l.yflip /i.yflip /p.yflip /G.yflip /a.yflip /p.yflip] >>';
$charProcs = '<< /T.valid 3 0 R /h.valid 3 0 R /i.valid 3 0 R /n.valid 3 0 R '
    . '/e.valid 3 0 R /x.valid 3 0 R /t.valid 3 0 R '
    . '/F.xflip 4 0 R /l.xflip 4 0 R /i.xflip 4 0 R /p.xflip 4 0 R '
    . '/G.xflip 4 0 R /a.xflip 4 0 R '
    . '/Y.yflip 5 0 R /f.yflip 5 0 R /l.yflip 5 0 R /i.yflip 5 0 R '
    . '/p.yflip 5 0 R /G.yflip 5 0 R /a.yflip 5 0 R >>';
$fallbackWidths = implode(' ', array_fill(0, 40, 250));

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3D1BBoxOrderBoundary /BaseFont /T3D1BBoxOrderBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 104 /Widths [{$fallbackWidths}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($validThinCharProc) . " >>\nstream\n{$validThinCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($invertedXCharProc) . " >>\nstream\n{$invertedXCharProc}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($invertedYCharProc) . " >>\nstream\n{$invertedYCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3D1BBoxOrderBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$review = [
    'source' => 'native-pdf-type3-charprocs-d1-bbox-order-boundary',
    'font_width_sources' => [
        'valid Type3 CharProc d1 bbox order preserves thin glyph spacing',
        'inverted d1 x bbox order rejected before WordPress text grouping',
        'inverted d1 y bbox order rejected before WordPress text grouping',
        'font /Widths fallback after malformed Type3 glyph bbox order',
    ],
    'valid_d1_bbox_width_preserved' => str_contains($plainText, 'Thin Text') && !str_contains($plainText, 'ThinText'),
    'inverted_x_bbox_order_rejected' => str_contains($plainText, 'Flip Gap') && !str_contains($plainText, 'FlipGap'),
    'inverted_y_bbox_order_rejected' => str_contains($plainText, 'Yflip Gap') && !str_contains($plainText, 'YflipGap'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'bbox order charproc text leak')
        && !str_contains($plainText, 'inverted x bbox charproc text leak')
        && !str_contains($plainText, 'inverted y bbox charproc text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'valid_d1_bbox_width_preserved',
    'inverted_x_bbox_order_rejected',
    'inverted_y_bbox_order_rejected',
    'charproc_payload_visible_text_excluded',
] as $flag) {
    if ($review[$flag] !== true) {
        throw new RuntimeException("Type3 d1 bbox order boundary failed: {$flag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-d1-bbox-order-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
