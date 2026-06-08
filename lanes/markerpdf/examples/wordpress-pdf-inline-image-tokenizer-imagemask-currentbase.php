<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$content = "BT /F1 12 Tf 72 720 Td (Before Tight ImageMask Boundary) Tj ET\n"
    . "BI /W 8 /H 1 /IM true ID\n"
    . "\x80EI\n"
    . "BT /F1 12 Tf 72 704 Td (Visible Tight ImageMask Boundary) Tj ET\n"
    . "BT /F1 12 Tf 72 688 Td (Before Premature ImageMask Boundary) Tj ET\n"
    . "BI /W 9 /H 1 /IM true ID\n"
    . "\x80EI BT /F1 12 Tf 72 660 Td (Premature ImageMask Payload Noise) Tj ET rawtail\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 672 Td (After Premature ImageMask Boundary) Tj ET";

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
    'Before Tight ImageMask Boundary',
    'Visible Tight ImageMask Boundary',
    'Before Premature ImageMask Boundary',
    'After Premature ImageMask Boundary',
];

$metadata = [
    'source' => 'native-pdf-inline-image-tokenizer-imagemask-currentbase',
    'upstream_boundary' => 'markerPDF searchable PDF text extraction keeps inline ImageMask raster bytes out of text import',
    'visible_text_imported' => $lines === $expected,
    'tight_imagemask_sample_floor_text_preserved' => in_array('Visible Tight ImageMask Boundary', $lines, true),
    'premature_tight_imagemask_ei_payload_excluded_until_floor' => in_array('After Premature ImageMask Boundary', $lines, true)
        && !str_contains($plainText, 'Premature ImageMask Payload Noise')
        && !str_contains($plainText, 'rawtail')
        && !str_contains($plainText, "\x80EI"),
    'imagemask_dictionary_omits_colorspace_and_bpc' => !str_contains($plainText, 'BitsPerComponent')
        && !str_contains($plainText, 'ColorSpace'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
];

if (
    ($metadata['visible_text_imported'] ?? false) !== true
    || ($metadata['tight_imagemask_sample_floor_text_preserved'] ?? false) !== true
    || ($metadata['premature_tight_imagemask_ei_payload_excluded_until_floor'] ?? false) !== true
) {
    throw new RuntimeException('Expected inline ImageMask tokenizer boundary to preserve visible WordPress text and exclude image payload bytes.');
}

echo '<!-- markerpdf:inline-image-tokenizer-imagemask-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($lines as $line) {
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
}
