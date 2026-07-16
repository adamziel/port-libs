<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (Stencil image import start) Tj ET\n"
    . "q 0.2 0.4 0.8 rg 18 0 0 9 72 690 cm /Blue#20Stencil Do Q\n"
    . "0.35 g q 10 0 0 10 112 690 cm /Gray#20Stencil Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (Stencil image import end) Tj ET';
$bluePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Blue Stencil Payload Noise) Tj ET';
$grayPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Gray Stencil Payload Noise) Tj ET';
$blueCompressed = gzcompress($bluePayload);
$grayCompressed = gzcompress($grayPayload);
if (!is_string($blueCompressed) || !is_string($grayCompressed)) {
    throw new RuntimeException('Unable to compress image mask stencil smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Blue#20Stencil 5 0 R /Gray#20Stencil 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($blueCompressed) . " >>\nstream\n{$blueCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /FlateDecode /Decode [0 1] /Length " . strlen($grayCompressed) . " >>\nstream\n{$grayCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$blue = $entriesByName['Blue Stencil'] ?? [];
$gray = $entriesByName['Gray Stencil'] ?? [];
if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || ($blue['image_mask_uses_current_nonstroking_color'] ?? false) !== true
    || ($blue['image_mask_paint_colors'][0]['color_space'] ?? null) !== 'DeviceRGB'
    || ($blue['image_mask_paint_colors'][0]['components'] ?? null) !== [0.2, 0.4, 0.8]
    || ($gray['image_mask_paint_colors'][0]['color_space'] ?? null) !== 'DeviceGray'
    || ($gray['image_mask_paint_colors'][0]['components'] ?? null) !== [0.35]
    || str_contains($plainText, 'WordPress Blue Stencil Payload Noise')
    || str_contains($plainText, 'WordPress Gray Stencil Payload Noise')
) {
    throw new RuntimeException('Image mask stencil paint-color boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-imagemask-color-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text text pages plus marker.pdf.images.render_image RGB handoff; ImageMask stencils use the current nonstroking color before raster review',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'blue_stencil_color_space' => $blue['image_mask_paint_colors'][0]['color_space'] ?? null,
    'blue_stencil_components' => $blue['image_mask_paint_colors'][0]['components'] ?? [],
    'blue_stencil_operator' => $blue['image_mask_paint_colors'][0]['operator'] ?? null,
    'gray_stencil_color_space' => $gray['image_mask_paint_colors'][0]['color_space'] ?? null,
    'gray_stencil_components' => $gray['image_mask_paint_colors'][0]['components'] ?? [],
    'gray_stencil_operator' => $gray['image_mask_paint_colors'][0]['operator'] ?? null,
    'payload_in_visible_text' => false,
    'image_mask_paint_color_review_only' => ($blue['image_mask_paint_color_review_only'] ?? false) === true
        && ($gray['image_mask_paint_color_review_only'] ?? false) === true,
];

echo '<!-- markerpdf:pdf-image-xobject-imagemask-color-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
