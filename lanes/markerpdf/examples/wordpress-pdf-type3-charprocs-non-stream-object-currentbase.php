<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validWideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (valid non-stream boundary charproc text leak) Tj ET\n";
$malformedPlainCharProc = "1000 0 d0\nBT /Fghost 9 Tf (plain object charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <45464748> Tj '
    . 'T* 1 0 0 1 72 704 Tm <5455565758> Tj '
    . '1 0 0 1 118 704 Tm <595A5B> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /G.stream /o.stream /o.stream /d.stream '
    . '/W.stream /i.stream /d.stream /e.stream '
    . '84 /P.plain /l.plain /a.plain /i.plain /n.plain /G.plain /a.plain /p.plain] >>';
$charProcs = '<< /G.stream 3 0 R /o.stream 3 0 R /d.stream 3 0 R '
    . '/W.stream 3 0 R /i.stream 3 0 R /e.stream 3 0 R '
    . '/P.plain 4 0 R /l.plain 4 0 R /a.plain 4 0 R /i.plain 4 0 R '
    . '/n.plain 4 0 R /G.plain 4 0 R /p.plain 4 0 R >>';
$fallbackWidths = implode(' ', array_fill(0, 32, 250));

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3NonStreamBoundary /BaseFont /T3NonStreamBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 91 /Widths [{$fallbackWidths}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($validWideCharProc) . " >>\nstream\n{$validWideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n{$malformedPlainCharProc}\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3NonStreamBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-non-stream-object-boundary',
    'font_width_sources' => [
        'valid Type3 CharProc stream d0 width keeps GoodWide joined',
        'malformed non-stream CharProc object is rejected before width grouping',
        'Type3 Widths/FontDescriptor fallback preserves Plain Gap',
    ],
    'valid_stream_charproc_width_preserved' => ($lines[0] ?? null) === 'GoodWide',
    'non_stream_charproc_width_rejected' => str_contains($plainText, 'Plain Gap') && !str_contains($plainText, 'PlainGap'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'charproc text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'valid_stream_charproc_width_preserved',
    'non_stream_charproc_width_rejected',
    'charproc_payload_visible_text_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs non-stream object boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-non-stream-object-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
