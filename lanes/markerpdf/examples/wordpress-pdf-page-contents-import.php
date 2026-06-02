<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageOneA = 'BT /F1 12 Tf 72 720 Td (Page One Intro) Tj T* ET';
$pageOneB = 'BT /F1 12 Tf 72 704 Td (Clean Blocks) Tj ET';
$pageTwo = 'BT /F1 12 Tf 72 720 Td (Second Page) Tj ET';
$phantom = 'BT /F1 12 Tf 72 720 Td (Phantom Form Text) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 8 0 R] /Count 2 >>\nendobj\n"
    . "8 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 9 0 R >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents [4 0 R 5 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageOneA) . " >>\nstream\n{$pageOneA}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($pageOneB) . " >>\nstream\n{$pageOneB}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Form /Length " . strlen($phantom) . " >>\nstream\n{$phantom}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($pageTwo) . " >>\nstream\n{$pageTwo}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);

echo '<!-- markerpdf-page-contents-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog page-tree ordered /Contents streams before Gutenberg paragraph rendering',
    'excluded_unreferenced_stream_text' => !str_contains($extractor->extractPlainText($pdf), 'Phantom Form Text'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
