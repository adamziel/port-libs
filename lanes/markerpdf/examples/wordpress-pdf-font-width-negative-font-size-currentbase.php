<?php
declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /Fnegfs 10 Tf 1 0 0 1 72 720 Tm <4142> Tj '
    . '/Fnegfs -12 Tf <4344> Tj '
    . '1 0 0 1 116 720 Tm <4546> Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fnegfs 2 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /ABCDEF+NegativeFontSizeAdvance /Encoding 6 0 R /FirstChar 65 /LastChar 70 /Widths [1000 1000 1000 1000 1000 1000] >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Encoding /Differences [65 /A /B /C /D /E /F] >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$pages = $extractor->extractStyledTextPages($pdf);
$line = $pages[0]['blocks'][0]['lines'][0] ?? [];
$spans = $line['spans'] ?? [];

$spanBboxes = array_column($spans, 'bbox');
$fontSizes = array_column($spans, 'font_size');

if ($lines !== ['ABCDEF'] || $plainText !== 'ABCDEF') {
    throw new RuntimeException('Expected negative Tf font-size operand to preserve contiguous WordPress import text.');
}

if ($spanBboxes !== [[0.0, 0.0, 20.0, 10.0], [20.0, 0.0, 44.0, 12.0], [44.0, 0.0, 68.0, 12.0]]) {
    throw new RuntimeException('Expected negative Tf font-size operand to preserve styled font advance bboxes.');
}

if ($fontSizes !== [10.0, 12.0, 12.0]) {
    throw new RuntimeException('Expected negative Tf font-size operand to normalize font-size magnitude.');
}

echo '<!-- markerpdf:pdf-font-width-negative-font-size-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-font-width-negative-font-size-currentbase',
    'negative_tf_font_size_magnitude_normalized' => true,
    'font_size_magnitude_preserved_for_wordpress_paragraph' => true,
    'false_word_gap_excluded' => !str_contains($plainText, 'ABCD EF'),
    'styled_bboxes_preserved' => true,
    'font_size_values' => $fontSizes,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $lineText) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($lineText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
