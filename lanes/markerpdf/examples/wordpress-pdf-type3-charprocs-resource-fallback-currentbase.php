<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$charProc = "1000 0 d0\n/TopGlyphPaint Do /StreamGlyphPaint Do\n"
    . "BT /Fghost 9 Tf (direct charproc resource fallback text leak) Tj ET\n";
$visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$topLevelGlyphResource = 'BT /Fghost 9 Tf 10 10 Td (top Type3 resource form leak) Tj ET';
$streamGlyphResource = 'BT /Fghost 9 Tf 10 10 Td (stream Type3 resource form leak) Tj ET';
$nestedGlyphResource = 'BT /Fghost 9 Tf 10 10 Td (nested Type3 resource form leak) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3ResourceFallback "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R /G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
    . "/Resources << /XObject << /TopGlyphPaint 5 0 R >> /Font << /Fghost 1 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Resources << /XObject << /StreamGlyphPaint 6 0 R >> /Font << /Fghost 1 0 R >> >> /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] /Resources << /Font << /Fghost 1 0 R >> >> /Length " . strlen($topLevelGlyphResource) . " >>\nstream\n{$topLevelGlyphResource}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] /Resources << /XObject << /NestedGlyphPaint 7 0 R >> /Font << /Fghost 1 0 R >> >> /Length " . strlen($streamGlyphResource) . " >>\nstream\n{$streamGlyphResource}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] /Resources << /Font << /Fghost 1 0 R >> >> /Length " . strlen($nestedGlyphResource) . " >>\nstream\n{$nestedGlyphResource}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charprocs-resource-fallback-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charprocs-resource-fallback-boundary',
    'visible_fallback_content_preserved' => $plainText === 'Visible fallback content',
    'direct_charproc_payload_excluded' => !str_contains($plainText, 'direct charproc resource fallback text leak'),
    'top_level_type3_resource_form_excluded' => !str_contains($plainText, 'top Type3 resource form leak'),
    'stream_type3_resource_form_excluded' => !str_contains($plainText, 'stream Type3 resource form leak'),
    'nested_type3_resource_form_excluded' => !str_contains($plainText, 'nested Type3 resource form leak'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
