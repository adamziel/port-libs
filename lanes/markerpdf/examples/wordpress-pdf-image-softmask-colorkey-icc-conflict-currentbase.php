<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$imageDictionary = '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace [/ICCBased 10 0 R] /BitsPerComponent 8 /Decode [0 1 0 1 0 1] /Mask [0 10 20 30 40 50] /SMask 11 0 R >>';
$objects = [
    10 => "<< /N 3 /Alternate /DeviceRGB /Range [0 1 0 1 0 1] /Length 11 >>\nstream\nICC-PROFILE\nendstream",
    11 => "<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [1 0] /Length 1 >>\nstream\nM\nendstream",
];

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);
$decoded = $renderer->imageSampleDecodeValues([5, 25, 45], $plan['image_decode'], $plan['bits_per_component']);
$softMaskAlpha = $renderer->softMaskSampleOpacity(64, $plan['soft_mask']);
$colorKeyPreviewBlocked = false;
try {
    $renderer->colorKeyMaskSamplePreview([5, 25, 45], $plan);
} catch (InvalidArgumentException) {
    $colorKeyPreviewBlocked = true;
}

$metadata = [
    'source' => 'native-pdf-image-softmask-colorkey-icc-conflict-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $plan['source_color_space'],
    'icc_profile' => $plan['icc_profile'],
    'image_decode' => $plan['image_decode'],
    'decoded_components' => array_map(static fn (float $value): float => round($value, 6), $decoded),
    'color_key_mask' => $plan['color_key_mask'],
    'color_key_mask_applied_before_rgb' => $plan['color_key_mask_applied_before_rgb'],
    'color_key_mask_suppressed_by_soft_mask' => $plan['color_key_mask_suppressed_by_soft_mask'],
    'color_key_preview_blocked' => $colorKeyPreviewBlocked,
    'soft_mask' => $plan['soft_mask'],
    'soft_mask_alpha' => round($softMaskAlpha, 6),
    'soft_mask_applied_before_rgb' => $plan['soft_mask_applied_before_rgb'],
    'alpha_output_mode' => $plan['alpha_output_mode'],
    'output_color_mode' => $plan['output_color_mode'],
    'notes' => $plan['notes'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-image-softmask-colorkey-icc-conflict-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($plan['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-icc-profile="' . ($plan['uses_icc_profile'] ? 'true' : 'false') . '"';
echo ' data-marker-color-key-suppressed="' . ($plan['color_key_mask_suppressed_by_soft_mask'] ? 'true' : 'false') . '"';
echo ' data-marker-color-key-preview-blocked="' . ($colorKeyPreviewBlocked ? 'true' : 'false') . '"';
echo ' data-marker-alpha-source="soft-mask"';
echo ' data-marker-soft-mask-alpha="' . htmlspecialchars((string) round($softMaskAlpha, 6), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($plan['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="ICCBased image with soft-mask ColorKey precedence review" style="background: linear-gradient(90deg, rgba(5,25,45,' . htmlspecialchars((string) round($softMaskAlpha, 6), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '), rgb(255,255,255)); width: 96px; height: 48px; border: 1px solid #777;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
