<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = '/OC/Hidden#4Cayer BDC BT /F1 12 Tf 72 720 Td (Hidden comment-array leak) Tj ET EMC '
    . '/OC/Visible#4Cayer BDC BT /F1 12 Tf 72 700 Td (Visible comment-array layer) Tj ET EMC';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n"
    . "<< /Type /Catalog /Pages 2 0 R /OCProperties<</OCGs[20 0 R 21 0 R]/D<</BaseState/OFF/ON[ % 20 0 R /Hidden#4Cayer is only parser-comment review text\n 21 0 R]/Order[20 0 R % /Hidden#4Cayer comment-only name\n 21 0 R]>>>> >>\n"
    . "endobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources<</Font<</F1 4 0 R>>/Properties<</Hidden#4Cayer 20 0 R/Visible#4Cayer 21 0 R>>>> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /OCG /Name (Hidden migration review layer) >>\nendobj\n"
    . "21 0 obj\n<< /Type /OCG /Name (Visible import layer) >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
if ($lines !== ['Visible comment-array layer']) {
    throw new RuntimeException('Expected PDF comments inside optional-content arrays to stay out of OCG visibility references.');
}

echo '<!-- markerpdf-parser-name-array-comment-boundary-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-array-reference-tokenizer',
    'native_boundary' => 'PDF comments inside arrays do not contribute object references or names',
    'commented_hidden_reference_ignored' => !str_contains($plainText, 'Hidden comment-array leak'),
    'visible_reference_preserved' => str_contains($plainText, 'Visible comment-array layer'),
    'comment_name_text_excluded' => !str_contains($plainText, 'Hidden#4Cayer'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
