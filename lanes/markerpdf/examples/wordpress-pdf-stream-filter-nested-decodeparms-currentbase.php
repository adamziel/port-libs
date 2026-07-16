<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$directNestedLeak = 'BT /F1 12 Tf 72 720 Td (Direct Nested DecodeParms Leak) Tj ET';
$directNestedCompressed = gzcompress($directNestedLeak);
$indirectNestedLeak = 'BT /F1 12 Tf 72 704 Td (Indirect Nested DecodeParms Leak) Tj ET';
$indirectNestedCompressed = gzcompress($indirectNestedLeak);
if (!is_string($directNestedCompressed) || !is_string($indirectNestedCompressed)) {
    throw new RuntimeException('Unable to compress nested DecodeParms smoke streams.');
}

$visibleAfter = 'BT /F1 12 Tf 72 688 Td (Visible After Nested DecodeParms) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R 8 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter /FlateDecode /DecodeParms [ [ << /Predictor 1 >> ] ] /Length " . strlen($directNestedCompressed) . " >>\nstream\n{$directNestedCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Filter /FlateDecode /DecodeParms [ 10 0 R ] /Length " . strlen($indirectNestedCompressed) . " >>\nstream\n{$indirectNestedCompressed}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "10 0 obj\n[ << /Predictor 1 >> ]\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

$metadata = [
    'native_boundary' => 'WordPress PDF stream-filter stack nested DecodeParms array fail-closed import',
    'line_count' => count($lines),
    'direct_nested_decodeparms_rejected' => !str_contains($plainText, 'Direct Nested DecodeParms Leak'),
    'indirect_nested_decodeparms_rejected' => !str_contains($plainText, 'Indirect Nested DecodeParms Leak'),
    'visible_fallback_preserved' => in_array('Visible After Nested DecodeParms', $lines, true),
    'decodeparms_array_object_excluded' => !str_contains($plainText, '10 0 obj') && !str_contains($plainText, 'Predictor'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    $metadata['direct_nested_decodeparms_rejected'] !== true
    || $metadata['indirect_nested_decodeparms_rejected'] !== true
    || $metadata['visible_fallback_preserved'] !== true
    || $metadata['decodeparms_array_object_excluded'] !== true
) {
    throw new RuntimeException('Nested DecodeParms stream-filter boundary smoke failed.');
}

echo '<!-- markerpdf:stream-filter-nested-decodeparms-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
