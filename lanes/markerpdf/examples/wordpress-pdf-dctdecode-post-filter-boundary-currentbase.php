<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$content = "BT /F1 12 Tf 72 720 Td (DCT Post Filter Import) Tj ET\n"
    . "q 24 0 0 24 72 680 cm /Photo Do Q\n"
    . 'BT /F1 12 Tf 72 650 Td (Clean Post Filter Paragraph) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0BT /F1 12 Tf 72 700 Td (DCT Post Filter Raster Noise) Tj ET\xff\xd9";
$imageDictionary = '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/DCTDecode /ASCIIHexDecode /JPXDecode] /DecodeParms [<< /ColorTransform 1 >> null null] >>';
$expectedBoundary = [
    'declared_filter' => 'DCTDecode',
    'canonical_filter' => 'DCTDecode',
    'alias_used' => false,
    'non_null_filter_index' => 0,
    'filters_before_dctdecode' => [],
    'native_prefix_filters' => [],
    'preview_only_filters_before_dctdecode' => [],
    'filters_after_dctdecode' => ['ASCIIHexDecode', 'JPXDecode'],
    'native_filters_after_dctdecode' => ['ASCIIHexDecode'],
    'preview_only_filters_after_dctdecode' => ['JPXDecode'],
    'dctdecode_is_terminal_filter' => false,
    'post_dctdecode_filters_present' => true,
    'post_dctdecode_filters_block_native_decode' => true,
    'source_filter_preserved' => true,
    'review_only' => true,
    'native_raster_decode' => false,
];

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> /XObject << /Photo 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/DCTDecode /ASCIIHexDecode /JPXDecode] /DecodeParms [<< /ColorTransform 1 >> null null] /Length " . strlen($jpegPayload) . " >>\nstream\n{$jpegPayload}\nendstream\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$imagePlan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary);
$imageReview = $extractor->extractImageXObjectBoundaryReview($pdf);
$photoReview = $imageReview['entries'][0] ?? [];
$rendererBoundary = $imagePlan['dctdecode_filter_boundary'] ?? null;
$xobjectBoundary = $photoReview['dctdecode_filter_boundary'] ?? null;
$postDctFiltersBlocked = ($rendererBoundary['post_dctdecode_filters_block_native_decode'] ?? false) === true
    && ($xobjectBoundary['post_dctdecode_filters_block_native_decode'] ?? false) === true;
$boundariesAligned = $rendererBoundary === $expectedBoundary && $xobjectBoundary === $expectedBoundary;
$noiseExcluded = !str_contains($plainText, 'DCT Post Filter Raster Noise') && !str_contains($plainText, 'JFIF');

if (
    $lines !== ['DCT Post Filter Import', 'Clean Post Filter Paragraph']
    || !$noiseExcluded
    || !$postDctFiltersBlocked
    || !$boundariesAligned
    || ($photoReview['native_raster_decode'] ?? true) !== false
    || ($photoReview['decoded_with_current_filters'] ?? true) !== false
) {
    throw new RuntimeException('DCTDecode post-filter boundary did not stay review-only before WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-post-filter-boundary ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-stream-filter',
    'upstream_boundary' => 'marker.pdf.images.render_image',
    'stream_filters' => ['DCTDecode', 'ASCIIHexDecode', 'JPXDecode'],
    'dctdecode_filters_after' => $rendererBoundary['filters_after_dctdecode'] ?? [],
    'dctdecode_native_filters_after' => $rendererBoundary['native_filters_after_dctdecode'] ?? [],
    'dctdecode_preview_only_filters_after' => $rendererBoundary['preview_only_filters_after_dctdecode'] ?? [],
    'post_dctdecode_filters_block_native_decode' => $postDctFiltersBlocked,
    'xobject_boundary_matches_renderer' => $boundariesAligned,
    'dctdecode_image_payload_excluded_from_text' => $noiseExcluded,
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
