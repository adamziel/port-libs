<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$charProc = "650 0 d0\n/GlyphStreamFont 9 Tf\n"
    . "BT /GlyphStreamFont 9 Tf (direct Type3 font-resource charproc text leak) Tj ET\n";
$visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$fontProgramPayload = 'BT /Fghost 7 Tf 0 0 Td (Type3 font resource FontFile text leak) Tj ET';
$streamFontProgramPayload = 'BT /Fghost 7 Tf 0 0 Td (Type3 stream font resource FontFile text leak) Tj ET';
$streamFontCidSetPayload = 'BT /Fghost 7 Tf 0 0 Td (Type3 stream font resource CIDSet text leak) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3FontResourceFallback "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R "
    . "/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> "
    . "/Resources << /Font << /GlyphFont 30 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Resources << /Font << /GlyphStreamFont 40 0 R >> >> "
    . "/Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /GlyphPrivate /FontDescriptor 31 0 R >>\nendobj\n"
    . "31 0 obj\n<< /Type /FontDescriptor /FontName /GlyphPrivate /Flags 4 /FontFile2 32 0 R >>\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($fontProgramPayload) . " >>\nstream\n{$fontProgramPayload}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /GlyphStreamPrivate "
    . "/CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /FontDescriptor 41 0 R >>\nendobj\n"
    . "41 0 obj\n<< /Type /FontDescriptor /FontName /GlyphStreamPrivate /Flags 4 "
    . "/FontFile2 42 0 R /CIDSet 43 0 R >>\nendobj\n"
    . "42 0 obj\n<< /Length " . strlen($streamFontProgramPayload) . " >>\nstream\n{$streamFontProgramPayload}\nendstream\nendobj\n"
    . "43 0 obj\n<< /Length " . strlen($streamFontCidSetPayload) . " >>\nstream\n{$streamFontCidSetPayload}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-font-resource-fallback-boundary',
    'font_private_sources' => [
        'Type3 font /Resources /Font FontDescriptor /FontFile2',
        'Type3 CharProc stream /Resources /Font FontDescriptor /FontFile2',
        'Type3 CharProc stream /Resources /Font FontDescriptor /CIDSet',
    ],
    'fallback_content_preserved' => $lines === ['Visible fallback content'],
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'direct Type3 font-resource charproc text leak'),
    'font_program_payload_excluded' => !str_contains($plainText, 'FontFile text leak'),
    'font_descriptor_private_stream_excluded' => !str_contains($plainText, 'CIDSet text leak'),
    'font_resource_names_excluded' => !str_contains($plainText, 'GlyphFont') && !str_contains($plainText, 'GlyphStreamFont'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'fallback_content_preserved',
    'charproc_payload_visible_text_excluded',
    'font_program_payload_excluded',
    'font_descriptor_private_stream_excluded',
    'font_resource_names_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs font resource fallback boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-font-resource-fallback-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
