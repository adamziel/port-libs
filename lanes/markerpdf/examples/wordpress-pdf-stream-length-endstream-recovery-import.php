<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$shortContent = 'BT /F1 12 Tf 72 720 Td (Recovered Length Stream) Tj T* (Endstream Fallback) Tj ET';
$shortCompressed = gzcompress($shortContent);
if (!is_string($shortCompressed)) {
    throw new RuntimeException('Unable to compress stale short length smoke fixture.');
}

$rawContent = 'BT /F1 12 Tf 72 688 Td (Raw Length Recovery) Tj ET';
$missingLengthContent = 'BT /F1 12 Tf 72 656 Td (Literal endstream Word) Tj T* (Missing Length Tail) Tj ET';
$validLengthContent = 'BT /F1 12 Tf 72 624 Td (Visible endstream Word) Tj T* (Length Still Wins) Tj ET';
$unsupportedNoise = 'BT /F1 12 Tf 72 592 Td (Unsupported stale Length leak) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 5 0 R 6 0 R 7 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter /FlateDecode /Length " . (strlen($shortCompressed) - 5) . " >>\nstream\n{$shortCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . (strlen($rawContent) - 4) . " >>\nstream\n{$rawContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< >>\nstream\n{$missingLengthContent}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($validLengthContent) . " >>\nstream\n{$validLengthContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Filter /Crypt /Length " . (strlen($unsupportedNoise) - 5) . " >>\nstream\n{$unsupportedNoise}\nendstream\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$joined = implode("\n", $lines);

echo '<!-- markerpdf:pdf-stream-length-endstream-recovery ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-payload-recovery',
    'recovered_stale_flate_length' => in_array('Recovered Length Stream', $lines, true)
        && in_array('Endstream Fallback', $lines, true),
    'recovered_stale_raw_length' => in_array('Raw Length Recovery', $lines, true),
    'missing_length_endstream_word_preserved' => in_array('Literal endstream Word', $lines, true)
        && in_array('Missing Length Tail', $lines, true),
    'declared_length_endstream_word_preserved' => in_array('Visible endstream Word', $lines, true)
        && in_array('Length Still Wins', $lines, true),
    'unsupported_filter_excluded' => !str_contains($joined, 'Unsupported stale Length leak'),
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
