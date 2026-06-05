<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$before = 'BT /F1 12 Tf 72 720 Td (Before Post EOI DCT Import) Tj ET';
$after = 'BT /F1 12 Tf 72 680 Td (After Post EOI DCT Import) Tj ET';
$jpegPayload = "\xff\xd8\xff\xe0JFIF\0wordpress jpeg\xff\xd9";
$postEoiSurplus = "\nBT /F1 12 Tf 72 700 Td (WordPress Post EOI DCT Leak) Tj ET\n";
$declaredPayload = $jpegPayload . $postEoiSurplus;
$pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($declaredPayload) . " >>\nstream\n{$declaredPayload}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$rendererObjects = [
    30 => '[ /ICCBased 31 0 R ]',
    31 => "<< /N 3 /Alternate /DeviceRGB /Length 7 >>\nstream\nPROFILE\nendstream",
];
$rendererImage = "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace 30 0 R /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($declaredPayload) . " >>\nstream\n{$declaredPayload}\nendstream";

$extractor = new PdfTextExtractor();
$renderer = new PdfImageRenderer();
$lines = $extractor->extractTextLines($pdf);
$plainText = $extractor->extractPlainText($pdf);
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$entry = $review['entries'][0] ?? [];
$rendererPreview = $renderer->iccBasedImageStreamPreviewRows($rendererImage, $rendererObjects);

$expected = ['Before Post EOI DCT Import', 'After Post EOI DCT Import'];
$surplusExcluded = !str_contains($plainText, 'WordPress Post EOI DCT Leak')
    && !str_contains($plainText, 'JFIF');
$xobjectClipped = ($entry['raw_length'] ?? null) === strlen($jpegPayload)
    && (($entry['raw_length'] ?? 0) < strlen($declaredPayload));
$rendererClipped = ($rendererPreview['image_stream']['raw_length'] ?? null) === strlen($jpegPayload)
    && (($rendererPreview['image_stream']['raw_length'] ?? 0) < strlen($declaredPayload));

if (
    $lines !== $expected
    || !$surplusExcluded
    || !$xobjectClipped
    || !$rendererClipped
    || ($entry['preview_only_filters'] ?? []) !== ['DCTDecode']
    || ($entry['native_raster_decode'] ?? true) !== false
    || ($rendererPreview['image_stream']['preview_only_filters'] ?? []) !== ['DCTDecode']
    || ($rendererPreview['image_stream']['decoded_with_current_filters'] ?? true) !== false
) {
    throw new RuntimeException('DCTDecode post-EOI boundary smoke failed before WordPress import.');
}

echo '<!-- markerpdf:pdf-dctdecode-post-eoi-boundary-currentbase ' . htmlspecialchars(json_encode([
    'source' => 'native-pdf-dctdecode-post-eoi-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image review-only JPEG payload',
    'stream_filters' => ['DCTDecode'],
    'paragraphs' => $lines,
    'declared_payload_length' => strlen($declaredPayload),
    'jpeg_eoi_payload_length' => strlen($jpegPayload),
    'xobject_post_eoi_surplus_clipped' => $xobjectClipped,
    'renderer_post_eoi_surplus_clipped' => $rendererClipped,
    'post_eoi_surplus_excluded_from_text' => $surplusExcluded,
    'native_raster_decode' => $entry['native_raster_decode'] ?? null,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
