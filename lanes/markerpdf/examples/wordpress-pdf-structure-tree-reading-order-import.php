<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = 'BT /F1 12 Tf '
    . '/P << /MCID 1 >> BDC 72 704 Td (Body paragraph second) Tj EMC '
    . '/H1 << /MCID 0 >> BDC 72 720 Td (Tagged heading first) Tj EMC '
    . '/Artifact << /MCID 9 >> BDC 72 688 Td (Artifact footer noise) Tj EMC ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /StructTreeRoot 20 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /StructTreeRoot /K [21 0 R 22 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /Type /StructElem /S /H1 /Pg 3 0 R /K 0 >>\nendobj\n"
    . "22 0 obj\n<< /Type /StructElem /S /P /K << /Type /MCR /Pg 3 0 R /MCID 1 >> >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-structure-tree-reading-order-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /StructTreeRoot /K MCID reading order before Gutenberg paragraph rendering',
    'used_structure_tree_order' => $lines === ['Tagged heading first', 'Body paragraph second'],
    'excluded_artifact_mcid' => !str_contains($plainText, 'Artifact footer noise'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
