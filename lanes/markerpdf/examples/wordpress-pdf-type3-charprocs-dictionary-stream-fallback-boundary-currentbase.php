<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$glyphProgram = "650 0 d0\nBT /Fghost 9 Tf (GHOST GLYPH LEAK) Tj ET\n";
$charProcsPayload = "BT /Fghost 9 Tf (CHARPROCS STREAM PAYLOAD LEAK) Tj ET\n";
$visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$charProcsStreamDictionary = '<< /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R '
    . '/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R '
    . '/Length ' . strlen($charProcsPayload) . ' >>';

$pdf = "%PDF-1.4\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3InvalidStreamFallback "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs 21 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($glyphProgram) . " >>\nstream\n{$glyphProgram}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
    . "21 0 obj\n{$charProcsStreamDictionary}\nstream\n{$charProcsPayload}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

$review = [
    'source' => 'native-pdf-type3-charprocs-dictionary-stream-fallback-boundary',
    'font_text_sources' => [
        'invalid indirect /CharProcs stream object excluded from stream-only fallback',
        'glyph streams named in invalid /CharProcs stream dictionary excluded as font-private payloads',
        'stream-only fallback content stream preserved for WordPress import',
    ],
    'fallback_content_preserved' => $plainText === 'Visible fallback content',
    'charprocs_stream_payload_excluded' => !str_contains($plainText, 'CHARPROCS STREAM PAYLOAD LEAK'),
    'charproc_glyph_payload_excluded' => !str_contains($plainText, 'GHOST GLYPH LEAK'),
    'font_program_name_excluded' => !str_contains($plainText, 'T3InvalidStreamFallback'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'fallback_content_preserved',
    'charprocs_stream_payload_excluded',
    'charproc_glyph_payload_excluded',
    'font_program_name_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs stream fallback boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-dictionary-stream-fallback-boundary-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
