<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$asciiHex = static fn (string $bytes): string => strtoupper(bin2hex($bytes));

$cleanWideCharProc = "1000 0 d0\nBT /Fghost 9 Tf (clean filtered type3 charproc text leak) Tj ET\n";
$tailedCharProc = "1000 0 d0\nBT /Fghost 9 Tf (tailed filtered type3 charproc text leak) Tj ET\n";
$cleanEncoded = $asciiHex($cleanWideCharProc) . ">\n \t";
$tailedEncoded = $asciiHex($tailedCharProc)
    . "> 250 0 d0\nBT /Fghost 9 Tf (post-eod type3 charproc tail leak) Tj ET";

$content = 'BT /Ft3 12 Tf '
    . '1 0 0 1 72 720 Tm <41424344> Tj '
    . '1 0 0 1 118 720 Tm <45464748> Tj '
    . 'T* 1 0 0 1 72 704 Tm <545556> Tj '
    . '1 0 0 1 118 704 Tm <575859> Tj ET';
$encoding = '<< /Type /Encoding /Differences [65 /G.clean /o.clean /o.clean /d.clean '
    . '/W.clean /i.clean /d.clean /e.clean '
    . '84 /B.tailed /a.tailed /d.tailed /G.tailed /a.tailed /p.tailed] >>';
$charProcs = '<< /G.clean 3 0 R /o.clean 3 0 R /d.clean 3 0 R '
    . '/W.clean 3 0 R /i.clean 3 0 R /e.clean 3 0 R '
    . '/B.tailed 4 0 R /a.tailed 4 0 R /d.tailed 4 0 R '
    . '/G.tailed 4 0 R /p.tailed 4 0 R >>';
$fallbackWidths = implode(' ', array_fill(0, 25, 250));

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /T3FilterStackBoundary /BaseFont /T3FilterStackBoundary "
    . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
    . "/FirstChar 65 /LastChar 89 /Widths [{$fallbackWidths}] "
    . "/Encoding {$encoding} /CharProcs {$charProcs} /FontDescriptor 6 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Filter /ASCIIHexDecode /Length " . strlen($cleanEncoded) . " >>\nstream\n{$cleanEncoded}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Filter /ASCIIHexDecode /Length " . strlen($tailedEncoded) . " >>\nstream\n{$tailedEncoded}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /FontDescriptor /FontName /T3FilterStackBoundary /Flags 4 /MissingWidth 250 >>\nendobj\n"
    . "20 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

$summary = [
    'source' => 'native-pdf-stream-filter-stack-type3-charproc-currentbase',
    'native_boundary' => 'WordPress PDF Type3 CharProc stream-filter stack EOD boundary',
    'text_lines' => $lines,
    'clean_type3_width_preserved' => in_array('GoodWide', $lines, true),
    'tailed_type3_filter_stack_rejected' => in_array('Bad Gap', $lines, true),
    'post_eod_charproc_tail_excluded' => !str_contains($plainText, 'post-eod type3 charproc tail leak'),
    'charproc_payload_text_excluded' => !str_contains($plainText, 'filtered type3 charproc text leak'),
    'executes_pdf_actions' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $summary['text_lines'] !== ['GoodWide', 'Bad Gap']
    || !$summary['post_eod_charproc_tail_excluded']
    || !$summary['charproc_payload_text_excluded']
) {
    throw new RuntimeException('Type3 CharProc stream-filter stack boundary smoke failed.');
}

echo '<!-- markerpdf:stream-filter-stack-type3-charproc-boundary ' . htmlspecialchars(
    json_encode($summary, JSON_UNESCAPED_SLASHES),
    ENT_QUOTES
) . " -->\n";
echo "<p>GoodWide</p>\n";
echo "<p>Bad Gap</p>\n";
