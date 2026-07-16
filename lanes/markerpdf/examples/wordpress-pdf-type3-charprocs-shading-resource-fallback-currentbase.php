<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$charProc = "650 0 d0\nq /GlyphShade sh /FunctionShade sh Q\n"
    . "BT /Ft3 9 Tf <47484F5354> Tj ET\n";
$visibleFallback = 'BT /Ft3 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$shadingPayload = "BT /Fghost 7 Tf 0 0 Td (Type3 shading resource text leak) Tj ET\n";
$functionPayload = "BT /Fghost 7 Tf 0 0 Td (Type3 shading function text leak) Tj ET\n";

$pdf = "%PDF-1.4\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3ShadingFallback "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
    . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
    . "/Resources << /Shading << /GlyphShade 30 0 R /FunctionShade 32 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
    . "30 0 obj\n<< /ShadingType 4 /ColorSpace /DeviceRGB /BitsPerCoordinate 8 "
    . "/BitsPerComponent 8 /BitsPerFlag 8 /Length " . strlen($shadingPayload) . " >>\n"
    . "stream\n{$shadingPayload}\nendstream\nendobj\n"
    . "31 0 obj\n<< /FunctionType 4 /Domain [0 1] /Range [0 1] "
    . "/Length " . strlen($functionPayload) . " >>\n"
    . "stream\n{$functionPayload}\nendstream\nendobj\n"
    . "32 0 obj\n<< /ShadingType 1 /ColorSpace /DeviceRGB /Function 31 0 R >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-shading-resource-fallback-boundary',
    'font_text_sources' => [
        'Type3 /CharProcs glyph stream with Shading paint resources',
        'Type3 /Resources /Shading stream and function-stream resources',
        'stream-only fallback content stream without a page tree',
    ],
    'fallback_content_preserved' => $plainText === 'Visible fallback content',
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'GHOST'),
    'shading_resource_payload_excluded' => !str_contains($plainText, 'Type3 shading resource text leak'),
    'shading_function_payload_excluded' => !str_contains($plainText, 'Type3 shading function text leak'),
    'shading_resource_names_excluded' => !str_contains($plainText, 'GlyphShade') && !str_contains($plainText, 'FunctionShade'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'fallback_content_preserved',
    'charproc_payload_visible_text_excluded',
    'shading_resource_payload_excluded',
    'shading_function_payload_excluded',
    'shading_resource_names_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs shading resource fallback boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-shading-resource-fallback-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
