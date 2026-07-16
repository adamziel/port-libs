<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$arrayWrappedCharProc = "1000 0 d0\nBT /Fghost 9 Tf (array-wrapped glyph charproc text leak) Tj ET\n";
$validWideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (valid glyph-array boundary charproc text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <414243> Tj '
    . '1 0 0 1 118 720 Tm <44454647> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /B.array /a.array /d.array '
    . '/P.array /a.array /t.array /h.array] >>';
$charProcs = '<< /B.array [3 0 R] /a.array 4 0 R /d.array 4 0 R '
    . '/P.array 4 0 R /t.array 4 0 R /h.array 4 0 R >>';
$fallbackWidths = implode(' ', array_fill(0, 7, 250));

$pagePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcsGlyphArrayValue /BaseFont /T3CharProcsGlyphArrayValue "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 71 /Widths [{$fallbackWidths}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($arrayWrappedCharProc) . " >>\nstream\n{$arrayWrappedCharProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($validWideCharProc) . " >>\nstream\n{$validWideCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3CharProcsGlyphArrayValue /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$arrayWrappedGlyphProgram = "650 0 d0\nBT /Fghost 9 Tf (ARRAY WRAPPED GLYPH VALUE LEAK) Tj ET\n";
$validGlyphProgram = "650 0 d0\nBT /Fghost 9 Tf (VALID GLYPH VALUE LEAK) Tj ET\n";
$visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$fallbackCharProcs = '<< /A [3 0 R] /B 4 0 R /C 4 0 R /D 4 0 R '
    . '/G 4 0 R /H 4 0 R /I 4 0 R /L 4 0 R /N 4 0 R '
    . '/O 4 0 R /P 4 0 R /S 4 0 R /T 4 0 R /V 4 0 R >>';
$fallbackPdf = "%PDF-1.4\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3GlyphArrayValueFallback "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs {$fallbackCharProcs} >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($arrayWrappedGlyphProgram) . " >>\nstream\n{$arrayWrappedGlyphProgram}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($validGlyphProgram) . " >>\nstream\n{$validGlyphProgram}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pagePdf);
$plainText = implode("\n", $lines);
$fallbackPlainText = $extractor->extractPlainText($fallbackPdf);

$review = [
    'source' => 'native-pdf-type3-charprocs-glyph-array-value-boundary',
    'font_width_sources' => [
        'array-valued Type3 CharProc glyph entry is rejected as a glyph map entry',
        'Type3 /Widths fallback is used after invalid CharProcs glyph syntax',
        'stream references inside array-valued CharProc glyph entries remain font-private during fallback extraction',
    ],
    'glyph_array_value_rejected' => $plainText === 'Bad Path',
    'fallback_widths_preserve_word_gap' => str_contains($plainText, 'Bad Path') && !str_contains($plainText, 'BadPath'),
    'page_charproc_payload_excluded' => !str_contains($plainText, 'glyph charproc text leak'),
    'fallback_content_preserved' => $fallbackPlainText === 'Visible fallback content',
    'array_wrapped_glyph_stream_excluded' => !str_contains($fallbackPlainText, 'ARRAY WRAPPED GLYPH VALUE LEAK'),
    'valid_glyph_stream_excluded' => !str_contains($fallbackPlainText, 'VALID GLYPH VALUE LEAK'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'glyph_array_value_rejected',
    'fallback_widths_preserve_word_gap',
    'page_charproc_payload_excluded',
    'fallback_content_preserved',
    'array_wrapped_glyph_stream_excluded',
    'valid_glyph_stream_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs glyph array-value boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-glyph-array-value-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
