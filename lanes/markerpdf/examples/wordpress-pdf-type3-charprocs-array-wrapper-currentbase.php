<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide array-wrapped CharProcs text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <414243> Tj '
    . '1 0 0 1 118 720 Tm <44454647> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /B.array /a.array /d.array '
    . '/P.array /a.array /t.array /h.array] >>';
$charProcs = '<< /B.array 3 0 R /a.array 3 0 R /d.array 3 0 R '
    . '/P.array 3 0 R /t.array 3 0 R /h.array 3 0 R >>';
$fallbackWidths = implode(' ', array_fill(0, 7, 250));

$pagePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcsArrayWrapper /BaseFont /T3CharProcsArrayWrapper "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 71 /Widths [{$fallbackWidths}] "
    . "/Encoding {$encoding} /CharProcs [21 0 R] /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3CharProcsArrayWrapper /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "21 0 obj\n{$charProcs}\nendobj\n%%EOF";

$glyphProgram = "650 0 d0\nBT /Fghost 9 Tf (ARRAY WRAPPED CHARPROCS GLYPH LEAK) Tj ET\n";
$charProcsPayload = "BT /Fghost 9 Tf (ARRAY WRAPPED CHARPROCS STREAM PAYLOAD LEAK) Tj ET\n";
$visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$streamCharProcsDictionary = '<< /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R '
    . '/G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R '
    . '/Length ' . strlen($charProcsPayload) . ' >>';

$fallbackPdf = "%PDF-1.4\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3ArrayWrapperFallback "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs [21 0 R] >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($glyphProgram) . " >>\nstream\n{$glyphProgram}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n"
    . "21 0 obj\n{$streamCharProcsDictionary}\nstream\n{$charProcsPayload}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pagePdf);
$plainText = implode("\n", $lines);
$fallbackPlainText = $extractor->extractPlainText($fallbackPdf);

$review = [
    'source' => 'native-pdf-type3-charprocs-array-wrapper-boundary',
    'font_text_sources' => [
        'array-wrapped /CharProcs value rejected as a Type3 glyph dictionary',
        'array-wrapped /CharProcs dictionary references still marked font-private in stream fallback',
        'array-wrapped /CharProcs stream object payload excluded from WordPress paragraphs',
    ],
    'array_wrapped_charprocs_rejected' => $plainText === 'Bad Path',
    'fallback_widths_preserve_word_gap' => str_contains($plainText, 'Bad Path') && !str_contains($plainText, 'BadPath'),
    'page_charproc_payload_excluded' => !str_contains($plainText, 'array-wrapped CharProcs text leak'),
    'fallback_content_preserved' => $fallbackPlainText === 'Visible fallback content',
    'fallback_glyph_payload_excluded' => !str_contains($fallbackPlainText, 'ARRAY WRAPPED CHARPROCS GLYPH LEAK'),
    'fallback_stream_payload_excluded' => !str_contains($fallbackPlainText, 'ARRAY WRAPPED CHARPROCS STREAM PAYLOAD LEAK'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'array_wrapped_charprocs_rejected',
    'fallback_widths_preserve_word_gap',
    'page_charproc_payload_excluded',
    'fallback_content_preserved',
    'fallback_glyph_payload_excluded',
    'fallback_stream_payload_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs array-wrapper boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-array-wrapper-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
