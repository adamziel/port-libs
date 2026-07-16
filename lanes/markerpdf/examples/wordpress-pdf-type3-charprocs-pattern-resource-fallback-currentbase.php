<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$charProc = "650 0 d0\nq /Pattern cs /GlyphPattern scn 0 0 12 12 re f Q\n"
    . "BT /Ft3 9 Tf <47484F5354> Tj ET\n";
$visibleFallback = 'BT /Ft3 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$patternPaint = "q 0 0 12 12 re f /NestedGlyphPaint Do "
    . "BT /Fghost 7 Tf 0 0 Td (Type3 pattern resource text leak) Tj ET Q\n";
$nestedGlyphPaint = "BT /Fghost 7 Tf 0 0 Td (nested Type3 pattern XObject text leak) Tj ET\n";

$pdf = "%PDF-1.4\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3PatternFallback "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
    . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
    . "/Resources << /Pattern << /GlyphPattern 30 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
    . "30 0 obj\n<< /PatternType 1 /PaintType 1 /TilingType 1 /BBox [0 0 12 12] "
    . "/XStep 12 /YStep 12 /Resources << /Font << /Fghost 31 0 R >> "
    . "/XObject << /NestedGlyphPaint 32 0 R >> >> /Length " . strlen($patternPaint) . " >>\n"
    . "stream\n{$patternPaint}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "32 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] "
    . "/Resources << /Font << /Fghost 31 0 R >> >> /Length " . strlen($nestedGlyphPaint) . " >>\n"
    . "stream\n{$nestedGlyphPaint}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-pattern-resource-fallback-boundary',
    'font_text_sources' => [
        'Type3 /CharProcs glyph stream with tiling Pattern paint resource',
        'Type3 /Resources /Pattern stream with nested Form XObject paint resource',
        'stream-only fallback content stream without a page tree',
    ],
    'fallback_content_preserved' => $plainText === 'Visible fallback content',
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'GHOST'),
    'pattern_resource_payload_excluded' => !str_contains($plainText, 'Type3 pattern resource text leak'),
    'nested_pattern_xobject_payload_excluded' => !str_contains($plainText, 'nested Type3 pattern XObject text leak'),
    'pattern_resource_names_excluded' => !str_contains($plainText, 'GlyphPattern') && !str_contains($plainText, 'NestedGlyphPaint'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'fallback_content_preserved',
    'charproc_payload_visible_text_excluded',
    'pattern_resource_payload_excluded',
    'nested_pattern_xobject_payload_excluded',
    'pattern_resource_names_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs pattern resource fallback boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-pattern-resource-fallback-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
