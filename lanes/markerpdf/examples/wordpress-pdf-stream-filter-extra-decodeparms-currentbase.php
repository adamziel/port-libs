<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$ambiguousContent = 'BT /F1 12 Tf 72 720 Td (Extra DecodeParms Leak) Tj ET';
$ambiguousCompressed = gzcompress($ambiguousContent);
if ($ambiguousCompressed === false) {
    throw new RuntimeException('Unable to compress ambiguous DecodeParms smoke stream.');
}

$visibleAfter = 'BT /F1 12 Tf 72 700 Td (Visible After Extra DecodeParms) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents [4 0 R 6 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Filter /FlateDecode /DecodeParms [ null << /Predictor 1 >> ] /Length " . strlen($ambiguousCompressed) . " >>\nstream\n{$ambiguousCompressed}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "6 0 obj\n<< /Length " . strlen($visibleAfter) . " >>\nstream\n{$visibleAfter}\nendstream\nendobj\n"
    . "%%EOF\n";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

$metadata = [
    'native_boundary' => 'WordPress PDF stream-filter stack extra DecodeParms fail-closed import',
    'line_count' => count($lines),
    'extra_decodeparms_rejected' => !str_contains($plainText, 'Extra DecodeParms Leak'),
    'visible_fallback_preserved' => in_array('Visible After Extra DecodeParms', $lines, true),
    'predictor_dictionary_excluded' => !str_contains($plainText, 'Predictor'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

echo '<!-- markerpdf:stream-filter-extra-decodeparms-boundary ' . htmlspecialchars(
    json_encode($metadata, JSON_UNESCAPED_SLASHES) ?: '{}',
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
) . " -->\n";
foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n";
}
