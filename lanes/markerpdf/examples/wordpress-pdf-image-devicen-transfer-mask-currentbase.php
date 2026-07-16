<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$groupPayload = "q /ImSpot Do Q\n";
$objects = [
    60 => '<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>',
    91 => "<< /Type /XObject /Subtype /Form /BBox [0 0 36 18] /Group << /S /Transparency /CS /DeviceGray /I true /K false >> /Length " . strlen($groupPayload) . " >>\nstream\n{$groupPayload}endstream",
    95 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [1] /C1 [0] /N 1 >>',
    96 => '<< /Type /Mask /S /Luminosity /G 91 0 R /BC [0.25] /TR 95 0 R >>',
];

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace [/DeviceN [/Spot#20Blue /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] /BitsPerComponent 8 /Decode [0 1 1 0] /SMask 96 0 R >>',
    $objects
);
$preview = $renderer->alternateColorantSamplePreview([128, 64], $plan, 0.25);

$roundedTints = array_map(static fn (float $value): float => round($value, 6), $preview['colorant_tints']);
$metadata = [
    'source' => 'native-pdf-image-devicen-transfer-mask-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $preview['source_color_space'],
    'colorant_tints' => $roundedTints,
    'alternate_color_space' => $preview['alternate_color_space'],
    'tint_transform_object' => $preview['tint_transform_object'],
    'tint_transform_preview_mode' => $preview['tint_transform_preview_mode'],
    'soft_mask_subtype' => $plan['soft_mask_group']['subtype'],
    'soft_mask_group_color_space' => $plan['soft_mask_group']['group_color_space'],
    'soft_mask_transfer_function' => $preview['soft_mask_transfer_function'],
    'soft_mask_alpha_before_transfer' => round((float) $preview['soft_mask_alpha_before_transfer'], 6),
    'soft_mask_alpha' => round((float) $preview['soft_mask_alpha'], 6),
    'soft_mask_transfer_applied' => $preview['soft_mask_transfer_applied'],
    'alpha_output_mode' => $plan['alpha_output_mode'],
    'output_color_mode' => $preview['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-image-devicen-transfer-mask-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-colorants="' . htmlspecialchars(implode(',', array_keys($roundedTints)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-subtype="' . htmlspecialchars((string) $plan['soft_mask_group']['subtype'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-transfer-alpha="' . htmlspecialchars((string) round((float) $preview['soft_mask_alpha'], 6), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($preview['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="DeviceN image with soft-mask transfer review swatch" style="background: linear-gradient(90deg, rgba(128,191,255,0.75), rgba(255,255,255,1)); width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
