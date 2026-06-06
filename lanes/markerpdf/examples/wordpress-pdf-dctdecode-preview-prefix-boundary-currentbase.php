<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before Preview Prefix DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Preview Prefix DCT Import) Tj ET';
$payload = "\x00\x00\x00\x0cjP  \r\n\x87\nJPX bytes before unreachable DCT "
    . 'BT /F1 12 Tf 72 700 Td (WordPress Preview Prefix DCT Leak) Tj ET'
    . "\xff\xd9";
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
$imageDictionary = '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/JPXDecode /DCTDecode] /DecodeParms [null << /ColorTransform 1 >>] /Length ' . strlen($payload) . ' >>';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n{$imageDictionary}\nstream\n{$payload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$photoReview = $review['entries'][0] ?? [];
$rendererPlan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary);
$xobjectBoundary = is_array($photoReview) ? ($photoReview['dctdecode_filter_boundary'] ?? null) : null;
$rendererBoundary = $rendererPlan['dctdecode_filter_boundary'] ?? null;
$expectedLines = ['Before Preview Prefix DCT Import', 'After Preview Prefix DCT Import'];
$payloadExcluded = !str_contains($plainText, 'WordPress Preview Prefix DCT Leak')
    && !str_contains($plainText, 'JPX bytes before unreachable DCT');
$previewPrefixBlocked = ($xobjectBoundary['pre_dctdecode_preview_filters_block_native_prefix_decode'] ?? false) === true
    && ($rendererBoundary['pre_dctdecode_preview_filters_block_native_prefix_decode'] ?? false) === true;
$boundariesMatch = $xobjectBoundary === $rendererBoundary;

if ($lines !== $expectedLines || !$payloadExcluded || !$previewPrefixBlocked || !$boundariesMatch) {
    throw new RuntimeException('DCTDecode preview-prefix boundary leaked payload bytes or lost review metadata.');
}

echo '<!-- markerpdf:pdf-dctdecode-preview-prefix-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-preview-prefix-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text.get_text_blocks + marker.pdf.images.render_image',
    'stream_filters' => ['JPXDecode', 'DCTDecode'],
    'preview_only_filters_before_dctdecode' => $xobjectBoundary['preview_only_filters_before_dctdecode'] ?? [],
    'pre_dctdecode_preview_filters_block_native_prefix_decode' => $previewPrefixBlocked,
    'xobject_boundary_matches_renderer' => $boundariesMatch,
    'dctdecode_image_payload_excluded_from_text' => $payloadExcluded,
    'xobject_preview_only_filters' => $photoReview['preview_only_filters'] ?? [],
    'xobject_native_raster_decode' => $photoReview['native_raster_decode'] ?? null,
    'xobject_decoded_with_current_filters' => $photoReview['decoded_with_current_filters'] ?? null,
    'renderer_preview_only_filters' => $rendererPlan['image_filter_boundary']['preview_only_filters'] ?? [],
    'renderer_native_raster_decode' => $rendererPlan['image_filter_boundary']['native_raster_decode'] ?? null,
    'paragraphs' => $lines,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
