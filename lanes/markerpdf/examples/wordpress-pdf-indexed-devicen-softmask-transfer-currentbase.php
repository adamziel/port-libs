<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$objects = [
    60 => '<< /FunctionType 4 /Domain [0 1 0 1] /Range [0 1 0 1 0 1 0 1] /Length 24 >>',
    91 => "<< /Type /XObject /Subtype /Form /BBox [0 0 42 21] /Group << /S /Transparency /CS /DeviceGray /I true /K false >> /Length 14 >>\nstream\nq /ImSpot Do Q\nendstream",
    95 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [1] /C1 [0] /N 1 >>',
    96 => '<< /Type /Mask /S /Luminosity /G 91 0 R /BC [0.25] /TR 95 0 R >>',
];
$imageDictionary = '<< /Subtype /Image /Width 3 /Height 1 /ColorSpace [/Indexed [/DeviceN [/Spot#20Blue /Spot#20Varnish] /DeviceCMYK 60 0 R << /Subtype /NChannel >>] 2 <0040FF0000FF>] /BitsPerComponent 2 /Decode [0 2] /SMask 96 0 R >>';

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);
$preview = $renderer->indexedAlternateColorantSamplePreview(3, $plan, 0.25);

$roundedTints = array_map(static fn (float $value): float => round($value, 6), $preview['colorant_tints']);
$metadata = [
    'source' => 'native-pdf-indexed-devicen-softmask-transfer-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $preview['source_color_space'],
    'base_color_space' => $preview['base_color_space'],
    'palette_index' => $preview['palette_index'],
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
    'notes' => $plan['notes'],
    'output_color_mode' => $preview['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

$alpha = (float) $preview['soft_mask_alpha'];
$blueTint = $preview['colorant_tints']['Spot Blue'] ?? 0.0;
$varnishTint = $preview['colorant_tints']['Spot Varnish'] ?? 0.0;
$swatch = sprintf(
    'rgba(%d,%d,%d,%.3f)',
    (int) round(255 * (1.0 - $blueTint)),
    (int) round(190 * (1.0 - $varnishTint)),
    255,
    $alpha
);

echo '<!-- markerpdf:pdf-indexed-devicen-softmask-transfer-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-base-color-space="' . htmlspecialchars((string) $preview['base_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-colorants="' . htmlspecialchars(implode(',', array_keys($roundedTints)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-palette-index="' . htmlspecialchars((string) $preview['palette_index'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-subtype="' . htmlspecialchars((string) $plan['soft_mask_group']['subtype'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-transfer-applied="' . ($preview['soft_mask_transfer_applied'] ? 'true' : 'false') . '"';
echo ' data-marker-soft-mask-alpha="' . htmlspecialchars((string) round($alpha, 6), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($preview['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Indexed DeviceN image with soft-mask transfer review swatch" style="background: ' . htmlspecialchars($swatch, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '; width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
