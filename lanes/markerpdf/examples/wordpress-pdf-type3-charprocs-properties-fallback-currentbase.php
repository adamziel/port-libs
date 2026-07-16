<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$charProc = "650 0 d0\n/Glyph /FontGlyphProps BDC EMC /Point /StreamGlyphProps DP\n"
    . "BT /Ft3 9 Tf <47484F5354> Tj ET\n";
$visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$fontPropertyPayload = 'BT /Fghost 8 Tf 0 0 Td (Type3 font property stream text leak) Tj ET';
$streamPropertyPayload = 'BT /Fghost 8 Tf 0 0 Td (Type3 stream property text leak) Tj ET';
$nestedPropertyPayload = 'BT /Fghost 8 Tf 0 0 Td (nested Type3 property text leak) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3PropertiesFallback "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
    . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
    . "/Resources << /Properties << /FontGlyphProps 30 0 R >> /Font << /Fghost 1 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Resources << /Properties << /StreamGlyphProps 31 0 R >> "
    . "/Font << /Fghost 1 0 R >> >> /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($fontPropertyPayload) . " >>\nstream\n{$fontPropertyPayload}\nendstream\nendobj\n"
    . "31 0 obj\n<< /PrivateNested 32 0 R /Length " . strlen($streamPropertyPayload) . " >>\nstream\n{$streamPropertyPayload}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($nestedPropertyPayload) . " >>\nstream\n{$nestedPropertyPayload}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-properties-fallback-boundary',
    'font_text_sources' => [
        'Type3 /CharProcs glyph stream with marked-content property resources',
        'Type3 font /Resources /Properties property-list stream',
        'Type3 CharProc stream /Resources /Properties property-list stream',
        'nested property-list helper stream',
        'stream-only fallback content stream without a page tree',
    ],
    'fallback_content_preserved' => $plainText === 'Visible fallback content',
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'GHOST'),
    'font_property_payload_excluded' => !str_contains($plainText, 'Type3 font property stream text leak'),
    'stream_property_payload_excluded' => !str_contains($plainText, 'Type3 stream property text leak'),
    'nested_property_payload_excluded' => !str_contains($plainText, 'nested Type3 property text leak'),
    'property_resource_names_excluded' => !str_contains($plainText, 'FontGlyphProps')
        && !str_contains($plainText, 'StreamGlyphProps'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'fallback_content_preserved',
    'charproc_payload_visible_text_excluded',
    'font_property_payload_excluded',
    'stream_property_payload_excluded',
    'nested_property_payload_excluded',
    'property_resource_names_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs Properties fallback boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-properties-fallback-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
