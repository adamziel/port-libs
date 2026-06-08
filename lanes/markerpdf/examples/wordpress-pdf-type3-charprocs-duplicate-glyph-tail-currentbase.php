<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentWideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (current duplicate glyph-tail charproc text leak) Tj ET\n";
$staleThinCharProc = "250 0 d0\nBT /Fghost 9 Tf (stale duplicate glyph-tail charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <4546474849> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /W.dup /i.dup /d.dup /e.dup '
    . '/B.dup /l.dup /o.dup /c.dup /k.dup] >>';
$charProcs = '<< /W.dup 4 0 R 99 0 R /W.dup 3 0 R '
    . '/i.dup 3 0 R /d.dup 3 0 R /e.dup 3 0 R '
    . '/B.dup 3 0 R /l.dup 3 0 R /o.dup 3 0 R /c.dup 3 0 R /k.dup 3 0 R >>';
$fallbackWidths = implode(' ', array_fill(0, 9, 250));

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3DuplicateGlyphTailBoundary /BaseFont /T3DuplicateGlyphTailBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 73 /Widths [{$fallbackWidths}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($currentWideCharProc) . " >>\nstream\n{$currentWideCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($staleThinCharProc) . " >>\nstream\n{$staleThinCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3DuplicateGlyphTailBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-duplicate-glyph-tail-boundary',
    'font_width_sources' => [
        'stale duplicate glyph entry with malformed reference tail is ignored',
        'later valid duplicate glyph entry supplies selected Type3 d0 width',
    ],
    'later_duplicate_glyph_width_selected' => str_contains($plainText, 'WideBlock') && !str_contains($plainText, 'Wide Block'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'duplicate glyph-tail charproc text leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach (['later_duplicate_glyph_width_selected', 'charproc_payload_visible_text_excluded'] as $flag) {
    if ($review[$flag] !== true) {
        throw new RuntimeException("Type3 duplicate glyph-tail boundary failed: {$flag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-duplicate-glyph-tail-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
