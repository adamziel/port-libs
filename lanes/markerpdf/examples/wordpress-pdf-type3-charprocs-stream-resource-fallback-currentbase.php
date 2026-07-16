<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$glyphProgram = "650 0 d0\nBT /Fghost 9 Tf (INVALID STREAM GLYPH PAYLOAD LEAK) Tj ET\n";
$charProcsPayload = "BT /Fghost 9 Tf (INVALID CHARPROCS STREAM PAYLOAD LEAK) Tj ET\n";
$visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$privateFormPayload = 'BT /Fghost 9 Tf 0 0 Td (INVALID CHARPROCS RESOURCE FORM LEAK) Tj ET';
$privateNestedPayload = 'BT /Fghost 9 Tf 0 0 Td (INVALID CHARPROCS NESTED RESOURCE LEAK) Tj ET';
$charProcsStreamDictionary = '<< /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R '
    . '/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R '
    . '/Resources << /XObject << /InvalidGlyphResource 6 0 R >> /Font << /Fghost 1 0 R >> >> '
    . '/Length ' . strlen($charProcsPayload) . ' >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3InvalidStreamResourceFallback "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs 21 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($glyphProgram) . " >>\nstream\n{$glyphProgram}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] "
    . "/Resources << /XObject << /NestedInvalidGlyphResource 7 0 R >> /Font << /Fghost 1 0 R >> >> "
    . "/Length " . strlen($privateFormPayload) . " >>\nstream\n{$privateFormPayload}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 20 20] "
    . "/Resources << /Font << /Fghost 1 0 R >> >> /Length " . strlen($privateNestedPayload)
    . " >>\nstream\n{$privateNestedPayload}\nendstream\nendobj\n"
    . "21 0 obj\n{$charProcsStreamDictionary}\nstream\n{$charProcsPayload}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-stream-resource-fallback-boundary',
    'font_private_sources' => [
        'malformed indirect /CharProcs stream object payload',
        'glyph streams named in malformed /CharProcs stream dictionary',
        'Form XObject resource stream reached only from malformed /CharProcs stream /Resources',
        'nested Form XObject resource stream reached only from malformed /CharProcs stream resources',
    ],
    'fallback_content_preserved' => $lines === ['Visible fallback content'],
    'charproc_glyph_payload_excluded' => !str_contains($plainText, 'INVALID STREAM GLYPH PAYLOAD LEAK'),
    'charprocs_stream_payload_excluded' => !str_contains($plainText, 'INVALID CHARPROCS STREAM PAYLOAD LEAK'),
    'charprocs_resource_payload_excluded' => !str_contains($plainText, 'INVALID CHARPROCS RESOURCE FORM LEAK'),
    'nested_resource_payload_excluded' => !str_contains($plainText, 'INVALID CHARPROCS NESTED RESOURCE LEAK'),
    'resource_names_excluded' => !str_contains($plainText, 'InvalidGlyphResource')
        && !str_contains($plainText, 'NestedInvalidGlyphResource'),
    'font_program_name_excluded' => !str_contains($plainText, 'T3InvalidStreamResourceFallback'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'fallback_content_preserved',
    'charproc_glyph_payload_excluded',
    'charprocs_stream_payload_excluded',
    'charprocs_resource_payload_excluded',
    'nested_resource_payload_excluded',
    'resource_names_excluded',
    'font_program_name_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs stream resource fallback boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-stream-resource-fallback-currentbase '
    . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
