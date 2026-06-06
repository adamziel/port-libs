<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (wide direct CharProcs tail text leak) Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <414243> Tj '
    . '1 0 0 1 118 720 Tm <44454647> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /B.tail /a.tail /d.tail '
    . '/P.tail /a.tail /t.tail /h.tail] >>';
$charProcs = '<< /B.tail 3 0 R /a.tail 3 0 R /d.tail 3 0 R '
    . '/P.tail 3 0 R /t.tail 3 0 R /h.tail 3 0 R >>';
$fallbackWidths = implode(' ', array_fill(0, 7, 250));

$pagePdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3CharProcsDirectTail /BaseFont /T3CharProcsDirectTail "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 71 /Widths [{$fallbackWidths}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} 99 0 R /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3CharProcsDirectTail /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$glyphProgram = "650 0 d0\nBT /Fghost 9 Tf (DIRECT TAIL GLYPH LEAK) Tj ET\n";
$visibleFallback = 'BT /F1 12 Tf 72 720 Td (Visible fallback content) Tj ET';
$fallbackCharProcs = '<< /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R '
    . '/G 3 0 R /H 3 0 R /I 3 0 R /L 3 0 R /O 3 0 R /P 3 0 R /S 3 0 R /T 3 0 R '
    . '/V 3 0 R /Y 3 0 R >>';
$fallbackPdf = "%PDF-1.4\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3DirectTailFallback "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/Encoding /WinAnsiEncoding /CharProcs {$fallbackCharProcs} 99 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($glyphProgram) . " >>\nstream\n{$glyphProgram}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visibleFallback) . " >>\nstream\n{$visibleFallback}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pagePdf);
$plainText = implode("\n", $lines);
$fallbackPlainText = $extractor->extractPlainText($fallbackPdf);

$review = [
    'source' => 'native-pdf-type3-charprocs-direct-dictionary-tail-boundary',
    'font_width_sources' => [
        'malformed direct /CharProcs dictionary with tail operand rejected as glyph map',
        'Type3 /Widths fallback preserved after invalid CharProcs dictionary syntax',
        'glyph streams named by the malformed dictionary remain font-private in stream fallback',
    ],
    'charprocs_direct_dictionary_tail_rejected' => $plainText === 'Bad Path',
    'fallback_widths_preserve_word_gap' => str_contains($plainText, 'Bad Path') && !str_contains($plainText, 'BadPath'),
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'direct CharProcs tail text leak'),
    'fallback_content_preserved' => $fallbackPlainText === 'Visible fallback content',
    'malformed_dictionary_glyph_payload_excluded' => !str_contains($fallbackPlainText, 'DIRECT TAIL GLYPH LEAK'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

foreach ([
    'charprocs_direct_dictionary_tail_rejected',
    'fallback_widths_preserve_word_gap',
    'charproc_payload_visible_text_excluded',
    'fallback_content_preserved',
    'malformed_dictionary_glyph_payload_excluded',
] as $requiredFlag) {
    if ($review[$requiredFlag] !== true) {
        throw new RuntimeException("Type3 CharProcs direct dictionary tail boundary failed: {$requiredFlag}");
    }
}

echo '<!-- markerpdf:pdf-type3-charprocs-direct-dictionary-tail-currentbase ' . htmlspecialchars(json_encode($review, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
