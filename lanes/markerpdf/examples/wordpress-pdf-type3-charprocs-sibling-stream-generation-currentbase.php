<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentGlyphProgram = "650 0 d0\nBT /Fghost 9 Tf (CURRENT STREAM SIBLING GLYPH LEAK) Tj ET\n";
$staleGlyphProgram = "650 0 d0\nBT /Fghost 9 Tf (STALE STREAM SIBLING GLYPH LEAK) Tj ET\n";
$visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$staleCharProcsPayload = "BT /Fghost 9 Tf (STALE CHARPROCS STREAM GENERATION PAYLOAD LEAK) Tj ET\n";
$staleResourcePayload = 'BT /Fghost 9 Tf 0 0 Td (STALE CHARPROCS STREAM RESOURCE LEAK) Tj ET';
$currentCharProcs = '<< /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R '
    . '/G 3 0 R /H 3 0 R /I 3 0 R /L 3 0 R /N 3 0 R '
    . '/O 3 0 R /S 3 0 R /T 3 0 R /V 3 0 R >>';
$staleCharProcsStreamDictionary = '<< /A 5 0 R /B 5 0 R /C 5 0 R /D 5 0 R '
    . '/G 5 0 R /H 5 0 R /I 5 0 R /L 5 0 R /N 5 0 R '
    . '/O 5 0 R /S 5 0 R /T 5 0 R /V 5 0 R '
    . '/Resources << /XObject << /StaleSiblingPaint 7 0 R >> /Font << /Fghost 1 0 R >> >> '
    . '/Length ' . strlen($staleCharProcsPayload) . ' >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3CharProcsSiblingStreamGeneration "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs 21 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($currentGlyphProgram) . " >>\nstream\n{$currentGlyphProgram}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($staleGlyphProgram) . " >>\nstream\n{$staleGlyphProgram}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 12 12] "
    . "/Resources << /Font << /Fghost 1 0 R >> >> /Length " . strlen($staleResourcePayload)
    . " >>\nstream\n{$staleResourcePayload}\nendstream\nendobj\n"
    . "21 0 obj\n{$currentCharProcs}\nendobj\n"
    . "21 1 obj\n{$staleCharProcsStreamDictionary}\nstream\n{$staleCharProcsPayload}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-sibling-stream-generation-boundary',
    'visible_fallback_content_preserved' => $lines === ['Visible fallback content'],
    'current_generation_glyph_payload_excluded' => !str_contains($plainText, 'CURRENT STREAM SIBLING GLYPH LEAK'),
    'stale_sibling_generation_glyph_payload_excluded' => !str_contains($plainText, 'STALE STREAM SIBLING GLYPH LEAK'),
    'stale_sibling_charprocs_stream_payload_excluded' => !str_contains($plainText, 'STALE CHARPROCS STREAM GENERATION PAYLOAD LEAK'),
    'stale_sibling_resource_payload_excluded' => !str_contains($plainText, 'STALE CHARPROCS STREAM RESOURCE LEAK')
        && !str_contains($plainText, 'StaleSiblingPaint'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'visible_fallback_content_preserved',
    'current_generation_glyph_payload_excluded',
    'stale_sibling_generation_glyph_payload_excluded',
    'stale_sibling_charprocs_stream_payload_excluded',
    'stale_sibling_resource_payload_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs sibling stream-generation boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-sibling-stream-generation-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
