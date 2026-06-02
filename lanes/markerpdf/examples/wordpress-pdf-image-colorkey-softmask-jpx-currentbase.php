<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$objects = [
    91 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [1 0] /Length 2 >>\nstream\nMS\nendstream",
];
$imageDictionary = '<< /Subtype /Image /Filter /JPXDecode /Width 2 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Decode [0 1 1 0 0 1] /Mask [0 0 120 140 200 255] /SMask 91 0 R /SMaskInData 2 >>';

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);
$colorKeyPreviewBlocked = false;
try {
    $renderer->colorKeyMaskSamplePreview([0, 128, 240], $plan);
} catch (InvalidArgumentException) {
    $colorKeyPreviewBlocked = true;
}

$invalidPlan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Filter /JPXDecode /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Mask [0 0 120 140 200 255] /SMaskInData 9 >>'
);
$invalidColorKeyPreview = $renderer->colorKeyMaskSamplePreview([0, 128, 240], $invalidPlan);

if (!$colorKeyPreviewBlocked || ($plan['jpx_soft_mask_in_data']['uses_embedded_soft_mask'] ?? false) !== true) {
    throw new RuntimeException('Valid JPX SMaskInData did not stay authoritative over ColorKey.');
}
if (($invalidPlan['jpx_soft_mask_in_data']['valid_value'] ?? true) !== false || ($invalidPlan['color_key_mask_applied_before_rgb'] ?? false) !== true) {
    throw new RuntimeException('Invalid JPX SMaskInData incorrectly suppressed ColorKey.');
}

$metadata = [
    'source' => 'native-pdf-image-colorkey-softmask-jpx-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'image_filters' => $plan['image_filters'],
    'preview_only_filters' => $plan['image_filter_boundary']['preview_only_filters'],
    'native_raster_decode' => $plan['image_filter_boundary']['native_raster_decode'],
    'smask_in_data' => $plan['jpx_soft_mask_in_data'],
    'external_soft_mask_ignored' => $plan['jpx_soft_mask_in_data']['external_soft_mask_ignored'],
    'color_key_mask' => $plan['color_key_mask'],
    'color_key_mask_applied_before_rgb' => $plan['color_key_mask_applied_before_rgb'],
    'color_key_mask_suppressed_by_soft_mask' => $plan['color_key_mask_suppressed_by_soft_mask'],
    'color_key_preview_blocked' => $colorKeyPreviewBlocked,
    'external_soft_mask_plan' => $plan['soft_mask'],
    'alpha_output_mode' => $plan['alpha_output_mode'],
    'invalid_smask_in_data' => $invalidPlan['jpx_soft_mask_in_data'],
    'invalid_smask_in_data_color_key_applied' => $invalidPlan['color_key_mask_applied_before_rgb'],
    'invalid_smask_in_data_alpha_mode' => $invalidPlan['alpha_output_mode'],
    'invalid_smask_in_data_color_key_alpha' => $invalidColorKeyPreview['alpha'],
    'output_color_mode' => $plan['output_color_mode'],
    'notes' => $plan['notes'],
    'invalid_notes' => $invalidPlan['notes'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-image-colorkey-softmask-jpx-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-image-filter="' . htmlspecialchars(implode(',', $plan['image_filters']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-preview-only="' . ($plan['image_filter_boundary']['native_raster_decode'] ? 'false' : 'true') . '"';
echo ' data-marker-smask-in-data="' . htmlspecialchars((string) $plan['jpx_soft_mask_in_data']['value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-external-smask-ignored="' . ($plan['jpx_soft_mask_in_data']['external_soft_mask_ignored'] ? 'true' : 'false') . '"';
echo ' data-marker-color-key-suppressed="' . ($plan['color_key_mask_suppressed_by_soft_mask'] ? 'true' : 'false') . '"';
echo ' data-marker-color-key-preview-blocked="' . ($colorKeyPreviewBlocked ? 'true' : 'false') . '"';
echo ' data-marker-invalid-smask-in-data-color-key-applied="' . ($invalidPlan['color_key_mask_applied_before_rgb'] ? 'true' : 'false') . '"';
echo ' data-marker-alpha-mode="' . htmlspecialchars($plan['alpha_output_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($plan['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="JPX image with embedded opacity channel review"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
