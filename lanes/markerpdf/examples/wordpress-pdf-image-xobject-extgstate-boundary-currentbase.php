<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

require_once __DIR__ . '/../src/PdfTextExtractor.php';

$pageContent = "BT /F1 12 Tf 72 720 Td (WordPress ExtGState Image Intro) Tj ET\n"
    . "q /Alpha#20State gs 20 0 0 10 72 690 cm /Alpha#20Image Do Q\n"
    . "q /Soft#20Mask#20State gs 12 0 0 12 110 690 cm /Soft#20Image Do Q\n"
    . "q /Private#20State gs 8 0 0 8 140 690 cm /PlainImage Do Q\n"
    . 'BT /F1 12 Tf 72 660 Td (WordPress ExtGState Image Outro) Tj ET';
$alphaPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Alpha ExtGState Image Noise) Tj ET';
$softPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Soft Mask ExtGState Image Noise) Tj ET';
$plainPayload = 'BT /F1 12 Tf 72 720 Td (WordPress Plain ExtGState Image Noise) Tj ET';
$alphaCompressed = gzcompress($alphaPayload);
$softCompressed = gzcompress($softPayload);
$plainCompressed = gzcompress($plainPayload);
if (!is_string($alphaCompressed) || !is_string($softCompressed) || !is_string($plainCompressed)) {
    throw new RuntimeException('Unable to compress ExtGState image smoke payloads.');
}

$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /ExtGState << /Alpha#20State 20 0 R /Soft#20Mask#20State 21 0 R /Private << /Private#20State 24 0 R >> >> /XObject << /Alpha#20Image 5 0 R /Soft#20Image 6 0 R /PlainImage 7 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($alphaCompressed) . " >>\nstream\n{$alphaCompressed}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($softCompressed) . " >>\nstream\n{$softCompressed}\nendstream\nendobj\n"
    . "7 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($plainCompressed) . " >>\nstream\n{$plainCompressed}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Type /ExtGState /CA 0.75 /ca 0.42 /BM /Multiply /AIS true >>\nendobj\n"
    . "21 0 obj\n<< /Type /ExtGState /ca 0.5 /BM [/Screen /Normal] /SMask 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /Mask /S /Luminosity /G 23 0 R /TR /Identity >>\nendobj\n"
    . "23 0 obj\n<< /Type /XObject /Subtype /Form /BBox [0 0 1 1] /Length 0 >>\nstream\n\nendstream\nendobj\n"
    . "24 0 obj\n<< /Type /ExtGState /ca 0.1 /BM /Difference >>\nendobj\n%%EOF";

$extractor = new PdfTextExtractor();
$review = $extractor->extractImageXObjectBoundaryReview($pdf);
$plainText = $extractor->extractPlainText($pdf);

$entriesByName = [];
foreach ($review['entries'] as $entry) {
    $entriesByName[$entry['resource_name']] = $entry;
}

$alphaState = $entriesByName['Alpha Image']['invocation_graphics_states'][0] ?? [];
$softState = $entriesByName['Soft Image']['invocation_graphics_states'][0] ?? [];
$plainState = $entriesByName['PlainImage']['invocation_graphics_states'] ?? null;

$metadata = [
    'source' => 'native-pdf-image-xobject-extgstate-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image RGB handoff receives rendered page graphics state while native PHP records review metadata only',
    'image_xobject_count' => $review['image_xobject_count'],
    'invoked_image_xobject_count' => $review['invoked_image_xobject_count'],
    'alpha_extgstate_resources' => $alphaState['ext_gstate_resources'] ?? [],
    'alpha_nonstroking_alpha' => $alphaState['nonstroking_alpha'] ?? null,
    'alpha_blend_modes' => $alphaState['blend_modes'] ?? [],
    'alpha_source_flag' => $alphaState['alpha_source'] ?? null,
    'soft_extgstate_resources' => $softState['ext_gstate_resources'] ?? [],
    'soft_nonstroking_alpha' => $softState['nonstroking_alpha'] ?? null,
    'soft_blend_modes' => $softState['blend_modes'] ?? [],
    'soft_mask_type' => $softState['soft_mask']['type'] ?? null,
    'soft_mask_subtype' => $softState['soft_mask']['subtype'] ?? null,
    'soft_mask_group_object' => $softState['soft_mask']['group_object'] ?? null,
    'private_extgstate_rejected' => $plainState === [],
    'payload_in_visible_text' => str_contains($plainText, 'ExtGState Image Noise'),
    'executes_python_or_models' => $review['executes_python_or_models'],
    'executes_external_pdf_tools' => $review['executes_external_pdf_tools'],
];

if (
    $metadata['image_xobject_count'] !== 3
    || $metadata['invoked_image_xobject_count'] !== 3
    || $metadata['alpha_extgstate_resources'] !== ['Alpha State']
    || $metadata['alpha_nonstroking_alpha'] !== 0.42
    || $metadata['alpha_blend_modes'] !== ['Multiply']
    || $metadata['alpha_source_flag'] !== true
    || $metadata['soft_extgstate_resources'] !== ['Soft Mask State']
    || $metadata['soft_nonstroking_alpha'] !== 0.5
    || $metadata['soft_blend_modes'] !== ['Screen', 'Normal']
    || $metadata['soft_mask_type'] !== 'graphics_state_soft_mask'
    || $metadata['soft_mask_subtype'] !== 'Luminosity'
    || $metadata['soft_mask_group_object'] !== 23
    || $metadata['private_extgstate_rejected'] !== true
    || $metadata['payload_in_visible_text'] !== false
    || $metadata['executes_python_or_models'] !== false
    || $metadata['executes_external_pdf_tools'] !== false
    || str_contains($plainText, 'WordPress Alpha ExtGState Image Noise')
    || str_contains($plainText, 'WordPress Soft Mask ExtGState Image Noise')
    || str_contains($plainText, 'WordPress Plain ExtGState Image Noise')
) {
    throw new RuntimeException('Image XObject ExtGState boundary smoke failed.');
}

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";
echo '<!-- markerpdf-image-xobject-extgstate-boundary ' . json_encode($metadata, JSON_UNESCAPED_SLASHES) . " -->\n";
