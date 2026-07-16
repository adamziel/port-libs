<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$charProc = "650 0 d0\nBT /Ft3 9 Tf <47484F5354> Tj ET\n";
$content = 'BT /Ft3 12 Tf 1 0 0 1 72 720 Tm <41424344> Tj ET';

$pdf = "%PDF-1.4\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3FallbackBoundary /FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] /Encoding /WinAnsiEncoding /CharProcs << /A 3 0 R /B 3 0 R /C 3 0 R /D 3 0 R /G 3 0 R /H 3 0 R /O 3 0 R /S 3 0 R /T 3 0 R >> >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);

echo '<!-- markerpdf:pdf-type3-charprocs-fallback-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-type3-charproc-stream-only-fallback-boundary',
    'font_text_sources' => [
        'Type3 /CharProcs glyph stream referenced by exact object generation',
        'stream-only fallback content stream without a page tree',
    ],
    'charproc_payload_visible_text_excluded' => !str_contains($plainText, 'GHOST'),
    'fallback_content_preserved' => $plainText === 'ABCD',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
