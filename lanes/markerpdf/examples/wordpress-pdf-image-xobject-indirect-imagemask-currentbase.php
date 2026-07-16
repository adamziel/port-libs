<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress indirect ImageMask before import) Tj ET\n"
    . "0.3 g q 12 0 0 6 72 690 cm /Indirect#20Stencil Do Q\n"
    . "q 9 0 0 9 104 690 cm /Ordinary#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress indirect ImageMask after import) Tj ET';
$stencilPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Indirect ImageMask Stencil Payload Noise) Tj ET';
$ordinaryPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Ordinary ImageMask False Payload Noise) Tj ET';
$stencilCompressed = gzcompress($stencilPayload);
$ordinaryCompressed = gzcompress($ordinaryPayload);
if (!is_string($stencilCompressed) || !is_string($ordinaryCompressed)) {
    throw new RuntimeException('Unable to compress indirect ImageMask smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Indirect#20Stencil 5 0 R /Ordinary#20Image 8 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ImageMask 6 0 R /Filter /FlateDecode /Decode [1 0] /Length " . strlen($stencilCompressed) . " >>\nstream\n{$stencilCompressed}\nendstream\nendobj\n"
    . "6 0 obj\ntrue\nendobj\n"
    . "7 0 obj\nfalse\nendobj\n"
    . "8 0 obj\n<< /Type /XObject /Subtype /Image /Private << /ImageMask true >> /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /ImageMask 7 0 R /Filter /FlateDecode /Length " . strlen($ordinaryCompressed) . " >>\nstream\n{$ordinaryCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$stencil = $entriesByName['Indirect Stencil'] ?? [];
$ordinary = $entriesByName['Ordinary Image'] ?? [];
$stencilColor = is_array($stencil['image_mask_paint_colors'][0] ?? null)
    ? $stencil['image_mask_paint_colors'][0]
    : [];

if (
    ($review['image_xobject_count'] ?? 0) !== 2
    || ($review['invoked_image_xobject_count'] ?? 0) !== 2
    || ($stencil['image_mask'] ?? false) !== true
    || ($stencil['bits_per_component'] ?? null) !== 1
    || ($stencilColor['color_space'] ?? null) !== 'DeviceGray'
    || ($stencilColor['components'] ?? null) !== [0.3]
    || ($ordinary['image_mask'] ?? true) !== false
    || ($ordinary['bits_per_component'] ?? null) !== 8
    || ($ordinary['color_space'] ?? null) !== 'DeviceRGB'
    || str_contains($plainText, 'WordPress Indirect ImageMask Stencil Payload Noise')
    || str_contains($plainText, 'WordPress Ordinary ImageMask False Payload Noise')
) {
    throw new RuntimeException('Indirect ImageMask image XObject boundary smoke failed.');
}

$metadata = [
    'source' => 'native-pdf-image-xobject-indirect-imagemask-currentbase',
    'upstream_boundary' => 'marker.pdf.extract_text text pages plus marker.pdf.images.render_image RGB/stencil handoff; Image XObject ImageMask booleans may be indirect PDF scalar objects',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'indirect_stencil_image_mask' => $stencil['image_mask'] ?? null,
    'indirect_stencil_bits_per_component' => $stencil['bits_per_component'] ?? null,
    'indirect_stencil_paint_color' => $stencilColor,
    'ordinary_image_mask_from_indirect_false' => $ordinary['image_mask'] ?? null,
    'ordinary_image_ignored_private_imagemask_decoy' => ($ordinary['image_mask'] ?? true) === false,
    'payload_in_visible_text' => false,
];

echo '<!-- markerpdf:pdf-image-xobject-indirect-imagemask-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
foreach ($extractor->extractTextLines($pdf) as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}
