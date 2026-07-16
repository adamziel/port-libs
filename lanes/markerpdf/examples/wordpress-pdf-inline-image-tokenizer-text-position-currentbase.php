<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = "BT /F1 12 Tf 72 720 Td (Before Text Position Boundary) Tj ET\n"
    . "BI /W 8 /H 1 /IM true /F /JBIG2Decode ID\n"
    . "\x80 EI BT /F1 12 Tf 72 660 Td (Text Position Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 704 Td (Visible Td Text) Tj ET\n"
    . "BT /F1 12 Tf 1 0 0 1 72 688 Tm (Visible Tm Text) Tj ET\n"
    . "BT /F1 12 Tf 14 TL 72 672 Td (Visible T Star First) Tj T* (Visible T Star Second) Tj ET\n"
    . "BT /F1 12 Tf 72 656 Td (Visible Quote First) Tj T* (Visible Single Quote) ' 2 3 (Visible Double Quote) \" ET\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 620 Td (After Text Position Boundary) Tj ET";

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
    'Before Text Position Boundary',
    'Visible Td Text',
    'Visible Tm Text',
    'Visible T Star First',
    'Visible T Star Second',
    'Visible Quote First',
    'Visible Single Quote',
    'Visible Double Quote',
    'After Text Position Boundary',
];

$metadata = [
    'source' => 'native-pdf-inline-image-tokenizer-text-position-currentbase',
    'upstream_boundary' => 'markerPDF searchable PDF text extraction before image/OCR/model fallback',
    'visible_text_imported' => $lines === $expected,
    'preview_only_text_position_text_preserved_after_safe_boundary' => in_array('Visible Tm Text', $lines, true)
        && in_array('Visible T Star Second', $lines, true)
        && in_array('Visible Double Quote', $lines, true),
    'inline_payload_text_excluded' => !str_contains($plainText, 'Text Position Payload Noise')
        && !str_contains($plainText, 'rawtail'),
    'inline_payload_filter_name_excluded' => !str_contains($plainText, 'JBIG2Decode'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    ($metadata['visible_text_imported'] ?? false) !== true
    || ($metadata['preview_only_text_position_text_preserved_after_safe_boundary'] ?? false) !== true
    || ($metadata['inline_payload_text_excluded'] ?? false) !== true
) {
    throw new RuntimeException('Expected inline image tokenizer text-position boundary to preserve visible WordPress text and exclude image payload bytes.');
}

echo '<!-- markerpdf:inline-image-tokenizer-text-position-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
}
