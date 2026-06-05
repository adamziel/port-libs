<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$charProc = "1000 0 d0\n/GlyphICC CS 0.25 SCN /GlyphSpot cs 0.75 scn\n"
    . "BT /Fghost 9 Tf (direct color-space charproc text leak) Tj ET\n";
$visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$iccProfilePayload = 'BT /Fghost 9 Tf 10 10 Td (Type3 ICC profile stream text leak) Tj ET';
$tintFunctionPayload = 'BT /Fghost 9 Tf 10 10 Td (Type3 tint transform stream text leak) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3ColorSpaceFallback "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
    . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
    . "/Resources << /ColorSpace << /GlyphICC [/ICCBased 30 0 R] "
    . "/GlyphSpot [/Separation /Spot#20Glyph /DeviceRGB 31 0 R] >> "
    . "/Font << /Fghost 1 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
    . "30 0 obj\n<< /N 3 /Alternate /DeviceRGB /Length " . strlen($iccProfilePayload) . " >>\nstream\n{$iccProfilePayload}\nendstream\nendobj\n"
    . "31 0 obj\n<< /FunctionType 4 /Domain [0 1] /Range [0 1 0 1 0 1] /Length " . strlen($tintFunctionPayload) . " >>\n"
    . "stream\n{$tintFunctionPayload}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-colorspace-fallback-boundary',
    'font_text_sources' => [
        'Type3 /CharProcs glyph stream with ICCBased and Separation ColorSpace resources',
        'Type3 /Resources /ColorSpace ICC profile stream',
        'Type3 /Resources /ColorSpace tint-transform function stream',
        'stream-only fallback content stream without a page tree',
    ],
    'fallback_content_preserved' => $plainText === 'Visible fallback content',
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'direct color-space charproc text leak'),
    'icc_profile_payload_excluded' => !str_contains($plainText, 'Type3 ICC profile stream text leak'),
    'tint_transform_payload_excluded' => !str_contains($plainText, 'Type3 tint transform stream text leak'),
    'colorspace_resource_names_excluded' => !str_contains($plainText, 'GlyphICC') && !str_contains($plainText, 'GlyphSpot'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'fallback_content_preserved',
    'charproc_payload_visible_text_excluded',
    'icc_profile_payload_excluded',
    'tint_transform_payload_excluded',
    'colorspace_resource_names_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs ColorSpace fallback boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-colorspace-fallback-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
