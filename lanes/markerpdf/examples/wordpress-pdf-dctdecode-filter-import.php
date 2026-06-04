<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;
use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = 'BT /F1 12 Tf 72 720 Td (DCT PDF Import) Tj T* (Clean Paragraphs) Tj ET';
$jpegLikeNoise = "\xff\xd8\xff\xe0JFIF\0BT /F1 12 Tf 72 720 Td (Raster JPEG Noise) Tj ET\xff\xd9";
$imageDictionary = '<< /Filter /DCTDecode /ColorSpace /DeviceRGB /BitsPerComponent 8 /Width 1 /Height 1 >>';

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 6 0 R >> >> /Contents [4 0 R 5 0 R] >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Filter /DCTDecode /ColorSpace /DeviceRGB /BitsPerComponent 8 /Width 1 /Height 1 /Length " . strlen($jpegLikeNoise) . " >>\nstream\n{$jpegLikeNoise}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$imagePlan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary);
$inlinePlan = $renderer->inlineImageReviewPlan('/W 1 /H 1 /CS /RGB /BPC 8 /F /DCT', $jpegLikeNoise);
$dctReviewOnly = $imagePlan['image_filter_boundary']['preview_only_filters'] === ['DCTDecode']
    && $imagePlan['image_filter_boundary']['native_raster_decode'] === false
    && $inlinePlan['inline_image']['native_raster_decode'] === false
    && $inlinePlan['inline_image_review_only'] === true;
$noiseExcluded = !str_contains($plainText, 'Raster JPEG Noise');

if ($lines !== ['DCT PDF Import', 'Clean Paragraphs'] || !$noiseExcluded || !$dctReviewOnly) {
    throw new RuntimeException('DCTDecode filter boundary did not stay review-only before WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-filter ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter',
    'upstream_boundary' => 'marker.pdf.images.render_image',
    'stream_filters' => ['DCTDecode'],
    'preview_only_filters' => $imagePlan['image_filter_boundary']['preview_only_filters'],
    'native_raster_decode' => $imagePlan['image_filter_boundary']['native_raster_decode'],
    'inline_dct_review_only' => $inlinePlan['inline_image_review_only'],
    'paragraphs' => $lines,
    'excluded_dctdecode_image_noise' => $noiseExcluded,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
