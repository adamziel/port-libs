<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$imageDictionary = '<< /Type /XObject /Subtype /Image /Width 2 /Height 2 /ColorSpace [/ICCBased 10 0 R] /BitsPerComponent 8 /SMask 11 0 R >>';
$objects = [
    10 => "<< /N 3 /Alternate /DeviceRGB /Range [0 1 0 1 0 1] /Length 12 >>\nstream\nICC-PROFILE\nendstream",
    11 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 2 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode 12 0 R /Matte [1 0.5 0] /Interpolate true /Length 4 >>\nstream\nMASK\nendstream",
    12 => '[1 0]',
];

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);

$metadata = [
    'source' => 'native-pdf-icc-softmask-image-review',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $plan['source_color_space'],
    'icc_profile' => $plan['icc_profile'],
    'soft_mask' => $plan['soft_mask'],
    'soft_mask_applied_before_rgb' => $plan['soft_mask_applied_before_rgb'],
    'soft_mask_decode_applied_before_rgb' => $plan['soft_mask_decode_applied_before_rgb'],
    'soft_mask_decode_component_mismatch' => $plan['soft_mask_decode_component_mismatch'],
    'soft_mask_opacity_zero' => $renderer->softMaskSampleOpacity(0, $plan['soft_mask']),
    'soft_mask_opacity_max' => $renderer->softMaskSampleOpacity(255, $plan['soft_mask']),
    'matte_unblending_required' => $plan['matte_unblending_required'],
    'output_color_mode' => $plan['output_color_mode'],
    'alpha_output_mode' => $plan['alpha_output_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-icc-softmask-image-review ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($plan['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($plan['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask="' . ($plan['soft_mask_applied_before_rgb'] ? 'true' : 'false') . '"';
echo ' data-marker-soft-mask-decode="' . ($plan['soft_mask']['decode_inverted'] ? 'inverted' : 'identity') . '"';
echo ' data-marker-matte-unblend="' . ($plan['matte_unblending_required'] ? 'true' : 'false') . '">';
echo '<div role="img" aria-label="ICCBased image with soft mask review swatch" style="background: linear-gradient(90deg, rgb(255,128,0), rgb(255,255,255)); width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
