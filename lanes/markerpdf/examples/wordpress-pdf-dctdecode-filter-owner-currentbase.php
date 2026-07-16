<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$imageBytes = "\x00\xff\xff\x00";
$compressedImage = gzcompress($imageBytes);
if (!is_string($compressedImage)) {
    throw new RuntimeException('Unable to build non-DCT image fixture.');
}

$before = 'BT /F1 12 Tf 72 720 Td (Before Non DCT DecodeParms Owner) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Non DCT DecodeParms Owner) Tj ET';
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Filter /FlateDecode /DecodeParms << /ColorTransform 1 >> /Length ' . strlen($compressedImage) . ' >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$compressedImage}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$imagePlan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary);
$colorPlan = $renderer->dctDecodeImageColorPlan($imageDictionary, "\xff\xd8\xff\xd9");
$sampleRgb = $renderer->dctDecodeSampleToRgb([0, 255, 255, 0], $colorPlan);

$dctDecodeParmsNotAttributed = ($colorPlan['filter'] ?? null) === 'FlateDecode'
    && ($colorPlan['decode_parms_color_transform'] ?? null) === null
    && ($colorPlan['effective_color_transform'] ?? null) === 0
    && ($colorPlan['uses_ycck_transform'] ?? true) === false
    && $sampleRgb === ['red' => 255, 'green' => 0, 'blue' => 0];
$paragraphsPreserved = $lines === ['Before Non DCT DecodeParms Owner', 'After Non DCT DecodeParms Owner']
    && !str_contains($plainText, 'ColorTransform');

if (!$paragraphsPreserved || !$dctDecodeParmsNotAttributed) {
    throw new RuntimeException('Non-DCT image DecodeParms were incorrectly attributed to DCTDecode before WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-filter-owner-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-decodeparms-filter-owner',
    'upstream_boundary' => 'DecodeParms belong to their declared image filter; DCT ColorTransform is applied only at a DCTDecode filter boundary',
    'image_filters' => $imagePlan['image_filters'] ?? [],
    'dct_color_plan_filter' => $colorPlan['filter'] ?? null,
    'dct_decodeparms_color_transform' => $colorPlan['decode_parms_color_transform'] ?? null,
    'dct_effective_color_transform' => $colorPlan['effective_color_transform'] ?? null,
    'dct_uses_ycck_transform' => $colorPlan['uses_ycck_transform'] ?? null,
    'sample_rgb_after_owner_guard' => $sampleRgb,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
