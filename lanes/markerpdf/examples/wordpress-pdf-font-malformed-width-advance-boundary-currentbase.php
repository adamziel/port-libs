<?php
declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$widths = array_fill(0, 36, '1000');
$widths[35] = '/BadWidth';
$widthArray = implode(' ', $widths);
$content = 'BT /Fhelv 12 Tf 1 0 0 1 72 720 Tm (Ill) Tj 1 0 0 1 93 720 Tm (Word) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fhelv 2 0 R >> >> /Contents 3 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /FirstChar 73 /LastChar 108 /Widths [{$widthArray}] >>\nendobj\n"
    . "3 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
if ($lines !== ['Ill Word'] || $plainText !== 'Ill Word') {
    throw new RuntimeException('Expected malformed simple-font Widths to fall back to Base14 word-gap advances.');
}

echo '<!-- markerpdf:pdf-font-width-advance-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-simple-font-width-advance-boundary',
    'malformed_width_array_fell_back_to_base14' => true,
    'word_gap_preserved_for_wordpress_paragraph' => true,
    'width_payload_visible_text_leaked' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
