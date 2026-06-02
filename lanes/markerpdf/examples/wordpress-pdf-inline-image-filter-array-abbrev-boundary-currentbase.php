<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$imageRow = 'raw EI BT /F1 12 Tf 72 690 Td (Inline Null Filter Noise) Tj ET';
$compressedImage = gzcompress("\0" . $imageRow, 0);
if (!is_string($compressedImage)) {
    throw new RuntimeException('Unable to build inline image null-filter fixture.');
}

$content = "BT /F1 12 Tf 72 720 Td (Before Null Filter Image) Tj ET\n"
    . 'BI /W ' . strlen($imageRow) . ' /H 1 /CS /G /BPC 8 /F [ null /Fl ] '
    . '/DP [ null << /Predictor 12 /Columns ' . strlen($imageRow) . " /Colors 1 /BitsPerComponent 8 >> ] ID "
    . $compressedImage . "\nEI\n"
    . 'BT /F1 12 Tf 72 704 Td (After Null Filter Image) Tj ET';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);

echo '<!-- markerpdf-inline-image-filter-array-abbrev-boundary-smoke ' . htmlspecialchars(json_encode([
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'page /Contents BI /F filter-array null entry plus /Fl abbreviation aligns /DP before Gutenberg paragraph rendering',
    'uses_inline_image_abbreviations' => true,
    'uses_filter_array_null_entry' => true,
    'fake_ei_inside_compressed_payload' => str_contains($compressedImage, ' EI '),
    'visible_text_imported' => $lines === ['Before Null Filter Image', 'After Null Filter Image'],
    'excluded_inline_image_text' => !str_contains($plainText, 'Inline Null Filter Noise')
        && !str_contains($plainText, 'raw EI'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
