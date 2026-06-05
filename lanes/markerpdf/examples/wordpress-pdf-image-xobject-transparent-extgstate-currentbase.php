<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress Transparent Image Intro) Tj ET\n"
    . "q /Invisible#20State gs 20 0 0 10 72 690 cm /Transparent#20Image Do Q\n"
    . "q /Visible#20State gs 12 0 0 8 108 690 cm /Visible#20Image Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress Transparent Image Outro) Tj ET';
$transparentPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Transparent ExtGState Image Noise) Tj ET';
$visiblePayload = 'BT /F1 12 Tf 72 720 Td (WordPress Visible ExtGState Image Noise) Tj ET';
$transparentCompressed = gzcompress($transparentPayload);
$visibleCompressed = gzcompress($visiblePayload);
if (!is_string($transparentCompressed) || !is_string($visibleCompressed)) {
    throw new RuntimeException('Unable to compress transparent ExtGState image smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /ExtGState << /Invisible#20State 20 0 R /Visible#20State 21 0 R >> /XObject << /Transparent#20Image 5 0 R /Visible#20Image 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($transparentCompressed) . " >>\nstream\n{$transparentCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($visibleCompressed) . " >>\nstream\n{$visibleCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Type /ExtGState /ca 0 /CA 1 /BM /Normal >>\nendobj\n"
    . "21 0 obj\n<< /Type /ExtGState /ca 0.65 /BM /Normal >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$transparent = $entriesByName['Transparent Image'] ?? [];
$visible = $entriesByName['Visible Image'] ?? [];
$metadata = [
    'source' => 'native-pdf-image-xobject-transparent-extgstate-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image receives rendered page graphics state; native PHP keeps zero-alpha Image XObjects as review-only metadata without painted media geometry',
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'transparent_invoked' => $transparent['invoked'] ?? null,
    'transparent_invocation_count' => $transparent['invocation_count'] ?? null,
    'transparent_painted_invocation_count' => $transparent['painted_invocation_count'] ?? null,
    'transparent_image_visible_bbox' => $transparent['image_visible_bbox'] ?? null,
    'transparent_paint_suppressed' => $transparent['graphics_state_paint_suppressed'] ?? null,
    'transparent_paint_suppression_reasons' => $transparent['graphics_state_paint_suppression_reasons'] ?? [],
    'transparent_nonstroking_alpha' => $transparent['invocation_graphics_states'][0]['nonstroking_alpha'] ?? null,
    'visible_painted_invocation_count' => $visible['painted_invocation_count'] ?? null,
    'visible_image_visible_bbox' => $visible['image_visible_bbox'] ?? null,
    'payload_in_visible_text' => str_contains($plainText, 'ExtGState Image Noise'),
    'executes_python_or_models' => $review['executes_python_or_models'],
    'executes_external_pdf_tools' => $review['executes_external_pdf_tools'],
];

if (
    $metadata['image_xobject_count'] !== 2
    || $metadata['invoked_image_xobject_count'] !== 2
    || $metadata['transparent_invoked'] !== true
    || $metadata['transparent_invocation_count'] !== 1
    || $metadata['transparent_painted_invocation_count'] !== 0
    || $metadata['transparent_image_visible_bbox'] !== null
    || $metadata['transparent_paint_suppressed'] !== true
    || $metadata['transparent_paint_suppression_reasons'] !== ['nonstroking_alpha_zero']
    || $metadata['transparent_nonstroking_alpha'] !== 0.0
    || $metadata['visible_painted_invocation_count'] !== 1
    || $metadata['visible_image_visible_bbox'] !== [108.0, 690.0, 120.0, 698.0]
    || $metadata['payload_in_visible_text'] !== false
    || $metadata['executes_python_or_models'] !== false
    || $metadata['executes_external_pdf_tools'] !== false
    || str_contains($plainText, 'WordPress Transparent ExtGState Image Noise')
    || str_contains($plainText, 'WordPress Visible ExtGState Image Noise')
) {
    throw new RuntimeException('Transparent image XObject ExtGState smoke failed.');
}

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
echo '<!-- markerpdf-image-xobject-transparent-extgstate-currentbase '
    . json_encode($metadata, JSON_UNESCAPED_SLASHES)
    . " -->\n";
