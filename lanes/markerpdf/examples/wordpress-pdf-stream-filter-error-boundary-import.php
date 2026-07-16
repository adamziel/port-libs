<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$visibleBefore = 'BT /F1 12 Tf 72 720 Td (Filter Boundary Visible) Tj ET';
$visibleAfter = 'BT /F1 12 Tf 72 688 Td (Filter Boundary Tail) Tj ET';
$cryptNoise = 'BT /F1 12 Tf 72 704 Td (Unsupported Crypt Leak) Tj ET';
$corruptFlateNoise = 'BT /F1 12 Tf 72 672 Td (Corrupt Flate Leak) Tj ET';
$stackedNoise = 'BT /F1 12 Tf 72 656 Td (Stacked Unknown Leak) Tj ET';
$stackedEncodedNoise = strtoupper(bin2hex($stackedNoise)) . '>';
$missingIndirectNoise = 'BT /F1 12 Tf 72 640 Td (Missing Indirect Filter Leak) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 5 0 R 6 0 R 7 0 R 8 0 R 9 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($visibleBefore) . " >>\nstream\n{$visibleBefore}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /Crypt /Length " . strlen($cryptNoise) . " >>\nstream\n{$cryptNoise}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Filter /FlateDecode /Length " . strlen($corruptFlateNoise) . " >>\nstream\n{$corruptFlateNoise}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Filter [ /ASCIIHexDecode /NoSuchDecode ] /Length " . strlen($stackedEncodedNoise) . " >>\nstream\n{$stackedEncodedNoise}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Filter 99 0 R /Length " . strlen($missingIndirectNoise) . " >>\nstream\n{$missingIndirectNoise}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "%%EOF";

$lines = (new PdfTextExtractor())->extractTextLines($pdf);
$joined = implode("\n", $lines);

echo '<!-- markerpdf:pdf-stream-filter-error-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter',
    'unsupported_filter_excluded' => !str_contains($joined, 'Unsupported Crypt Leak'),
    'corrupt_filter_excluded' => !str_contains($joined, 'Corrupt Flate Leak'),
    'stacked_unknown_filter_excluded' => !str_contains($joined, 'Stacked Unknown Leak'),
    'missing_indirect_filter_excluded' => !str_contains($joined, 'Missing Indirect Filter Leak'),
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
