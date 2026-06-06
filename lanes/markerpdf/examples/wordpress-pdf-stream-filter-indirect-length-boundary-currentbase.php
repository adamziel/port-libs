<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$leakingContent = 'BT /F1 12 Tf 72 720 Td (Malformed Indirect Length Leak) Tj ET';
$compressed = gzcompress($leakingContent, 0);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to build malformed indirect Length smoke fixture.');
}

$visibleAfter = 'BT /F1 12 Tf 72 700 Td (Visible After Malformed Length) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "10 0 obj\n" . strlen($compressed) . " << /LengthDecoy true >>\nendobj\n"
    . "4 0 obj\n<< /Length 10 0 R /Filter /FlateDecode >>\nstream\n{$compressed}\nendstream\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

$metadata = [
    'native_boundary' => 'WordPress PDF stream-filter indirect Length helpers must be standalone integers before filtered page import',
    'line_count' => count($lines),
    'malformed_indirect_length_rejected' => !str_contains($plainText, 'Malformed Indirect Length Leak'),
    'visible_fallback_preserved' => in_array('Visible After Malformed Length', $lines, true),
    'length_decoy_excluded' => !str_contains($plainText, 'LengthDecoy'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if ($metadata['malformed_indirect_length_rejected'] !== true || $metadata['visible_fallback_preserved'] !== true) {
    throw new RuntimeException('Malformed indirect Length boundary smoke failed.');
}

echo '<!-- markerpdf:stream-filter-indirect-length-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
