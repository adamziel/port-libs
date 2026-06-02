<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();
$jpxPayload = "\xff\x4fDeviceN JPX colorants and alpha stay inside JPEG2000 bytes\xff\xd9";
$groupPayload = "q /ImSpot Do Q\n";
$objects = [
    60 => '<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>',
    91 => "<< /Type /XObject /Subtype /Form /BBox [0 0 36 18] /Group << /S /Transparency /CS /DeviceGray /I true /K false >> /Length " . strlen($groupPayload) . " >>\nstream\n{$groupPayload}endstream",
    95 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [0.2] /C1 [0.8] /N 2 >>',
    96 => '<< /Type /Mask /S /Luminosity /G 91 0 R /BC [0.25] /TR 95 0 R >>',
];

$imageObject = "<< /Subtype /Image /Filter /JPXDecode /Width 2 /Height 1 /ColorSpace [/DeviceN [/Spot#20Blue /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Decode [1 0 0 1] /SMask 96 0 R /Length " . strlen($jpxPayload) . " >>\nstream\n{$jpxPayload}\nendstream";
$preview = $renderer->alternateColorantStreamPreviewRows($imageObject, $objects, 2);

if (($preview['review_only_image_stream'] ?? false) !== true || ($preview['image_stream']['preview_only_filters'] ?? []) !== ['JPXDecode']) {
    throw new RuntimeException('DeviceN JPX image stream was not kept review-only.');
}
if (($preview['soft_mask_transfer_function_applied_before_rgb'] ?? false) !== true || ($preview['soft_mask_transfer_function']['object'] ?? null) !== 95) {
    throw new RuntimeException('DeviceN soft-mask transfer metadata was not preserved.');
}
if (($preview['preview_pixel_count'] ?? null) !== 0 || ($preview['pixels'] ?? null) !== []) {
    throw new RuntimeException('JPX stream payload leaked into native pixel rows.');
}

$metadata = [
    'source' => 'native-pdf-image-devicen-transfer-jpx-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $preview['source_color_space'],
    'image_filters' => $preview['image_stream']['filters'],
    'preview_only_filters' => $preview['image_stream']['preview_only_filters'],
    'review_only_image_stream' => $preview['review_only_image_stream'],
    'native_raster_decode' => $preview['image_filter_boundary']['native_raster_decode'],
    'decoded_image_bytes' => $preview['image_stream']['decoded_length'],
    'complete_image_sample_data' => $preview['complete_image_sample_data'],
    'alternate_color_space' => $preview['alternate_color_space'],
    'tint_transform_object' => $preview['tint_transform_object'],
    'tint_transform_preview_mode' => $preview['tint_transform_preview_mode'],
    'soft_mask_subtype' => $preview['soft_mask_group']['subtype'],
    'soft_mask_group_color_space' => $preview['soft_mask_group']['group_color_space'],
    'soft_mask_transfer_function' => $preview['soft_mask_transfer_function'],
    'soft_mask_transfer_applied_before_rgb' => $preview['soft_mask_transfer_function_applied_before_rgb'],
    'soft_mask_stream' => $preview['soft_mask_stream'],
    'stream_notes' => $preview['stream_notes'],
    'alpha_output_mode' => $preview['alpha_output_mode'],
    'output_color_mode' => $preview['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
    'executes_jpx_raster_decode' => false,
];

echo '<!-- markerpdf:pdf-image-devicen-transfer-jpx-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-preview-only-filters="' . htmlspecialchars(implode(',', $preview['image_stream']['preview_only_filters']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-subtype="' . htmlspecialchars((string) $preview['soft_mask_group']['subtype'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-transfer-object="' . htmlspecialchars((string) $preview['soft_mask_transfer_function']['object'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-alpha-output-mode="' . htmlspecialchars($preview['alpha_output_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($preview['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="DeviceN JPX image with transfer-mask review boundary" style="background: linear-gradient(90deg, rgba(50,128,210,0.2), rgba(255,255,255,0.8)); width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
