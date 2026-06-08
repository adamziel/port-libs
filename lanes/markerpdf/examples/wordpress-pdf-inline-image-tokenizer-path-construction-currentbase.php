<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = "BT /F1 12 Tf 72 720 Td (Before Curve Path Stray) Tj ET\n"
    . "BI /W 128 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x00\x01\x02 EI BT /F1 12 Tf 72 660 Td (Curve Path Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "10 10 m\n"
    . "20 20 30 20 40 10 c\n"
    . "50 20 60 10 v\n"
    . "80 20 100 10 y\n"
    . "BT /F1 12 Tf 72 704 Td (Visible Curve Path Before Stray) Tj ET\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 688 Td (Visible After Curve Path Stray) Tj ET";

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = implode("\n", $lines);
$expected = [
    'Before Curve Path Stray',
    'Visible Curve Path Before Stray',
    'Visible After Curve Path Stray',
];

$metadata = [
    'source' => 'native-pdf-inline-image-tokenizer-path-construction-currentbase',
    'upstream_boundary' => 'markerPDF searchable PDF text extraction keeps inline image raster bytes separate from following PDF content operators',
    'visible_text_imported' => $lines === $expected,
    'preview_only_curve_path_text_preserved_after_safe_boundary' => in_array('Visible Curve Path Before Stray', $lines, true)
        && in_array('Visible After Curve Path Stray', $lines, true),
    'inline_payload_text_excluded' => !str_contains($plainText, 'Curve Path Payload Noise')
        && !str_contains($plainText, 'rawtail'),
    'path_construction_operands_excluded_from_visible_text' => !str_contains($plainText, '20 20 30 20 40 10 c')
        && !str_contains($plainText, '50 20 60 10 v')
        && !str_contains($plainText, '80 20 100 10 y'),
    'inline_payload_filter_name_excluded' => !str_contains($plainText, 'JBIG2Decode'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    ($metadata['visible_text_imported'] ?? false) !== true
    || ($metadata['preview_only_curve_path_text_preserved_after_safe_boundary'] ?? false) !== true
    || ($metadata['inline_payload_text_excluded'] ?? false) !== true
    || ($metadata['path_construction_operands_excluded_from_visible_text'] ?? false) !== true
) {
    throw new RuntimeException('Expected inline image tokenizer path-construction boundary to preserve visible WordPress text and exclude image payload bytes.');
}

echo '<!-- markerpdf:inline-image-tokenizer-path-construction-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
}
