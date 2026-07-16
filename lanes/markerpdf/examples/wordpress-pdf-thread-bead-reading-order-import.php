<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (Article part one) Tj ET '
    . 'BT /F1 12 Tf 320 720 Td (Article part three) Tj ET '
    . 'BT /F1 12 Tf 72 640 Td (Article part two) Tj ET '
    . 'BT /F1 12 Tf 320 640 Td (Article part four) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Threads [20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (WordPress Article Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 620 250 740] /N 22 0 R /V 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [300 620 520 740] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$expected = [
    'Article part one',
    'Article part two',
    'Article part three',
    'Article part four',
];

echo '<!-- markerpdf:pdf-thread-bead-reading-order ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-catalog-threads',
    'native_boundary' => 'catalog /Threads /F bead linked list and /R page rectangles before Gutenberg paragraph rendering',
    'used_thread_bead_order' => $lines === $expected,
    'suppressed_raw_column_order' => !str_contains($plainText, "Article part three\nArticle part two"),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
