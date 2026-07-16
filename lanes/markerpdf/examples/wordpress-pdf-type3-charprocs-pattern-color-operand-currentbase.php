<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$validPatternColorCharProc = "/Pattern cs 0.25 0.75 /GlyphPattern scn\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (valid pattern color charproc text leak) Tj ET\n";
$malformedPatternColorCharProc = "/Pattern cs /GlyphPattern 0.25 scn\n1000 0 d0\n"
    . "BT /Fghost 9 Tf (malformed pattern color charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <45464748> Tj '
    . 'T* 1 0 0 1 72 704 Tm <545556> Tj '
    . '1 0 0 1 109 704 Tm <5758595A> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /G.validpattern /o.validpattern /o.validpattern /d.validpattern '
    . '/P.validpattern /a.validpattern /t.validpattern /h.validpattern '
    . '84 /B.badpattern /a.badpattern /d.badpattern /J.badpattern /o.badpattern /i.badpattern /n.badpattern] >>';
$charProcs = '<< /G.validpattern 3 0 R /o.validpattern 3 0 R /d.validpattern 3 0 R '
    . '/P.validpattern 3 0 R /a.validpattern 3 0 R /t.validpattern 3 0 R /h.validpattern 3 0 R '
    . '/B.badpattern 4 0 R /a.badpattern 4 0 R /d.badpattern 4 0 R '
    . '/J.badpattern 4 0 R /o.badpattern 4 0 R /i.badpattern 4 0 R /n.badpattern 4 0 R >>';
$fallbackWidths = implode(' ', array_fill(0, 26, 250));

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3PatternColorOperandBoundary /BaseFont /T3PatternColorOperandBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 90 /Widths [{$fallbackWidths}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R "
    . "/Resources << /Pattern << /GlyphPattern 30 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($validPatternColorCharProc) . " >>\nstream\n{$validPatternColorCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($malformedPatternColorCharProc) . " >>\nstream\n{$malformedPatternColorCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3PatternColorOperandBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "30 0 obj\n<< /PatternType 1 /PaintType 2 /TilingType 1 /BBox [0 0 8 8] /XStep 8 /YStep 8 /Length 0 >>\nstream\n\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-pattern-color-operand-boundary',
    'font_text_sources' => [
        'valid Type3 pattern color components followed by pattern name',
        'malformed Type3 pattern name before numeric color component',
        'fallback Type3 Widths preserve WordPress word gap after rejected glyph metrics',
    ],
    'valid_pattern_color_widths_resolved' => str_contains($plainText, 'GoodPath') && !str_contains($plainText, 'Good Path'),
    'malformed_pattern_color_widths_rejected' => str_contains($plainText, 'Bad Join') && !str_contains($plainText, 'BadJoin'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'pattern color charproc text leak'),
    'pattern_resource_name_excluded' => !str_contains($plainText, 'GlyphPattern'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'valid_pattern_color_widths_resolved',
    'malformed_pattern_color_widths_rejected',
    'charproc_payload_visible_text_excluded',
    'pattern_resource_name_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs pattern color operand boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-pattern-color-operand-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
