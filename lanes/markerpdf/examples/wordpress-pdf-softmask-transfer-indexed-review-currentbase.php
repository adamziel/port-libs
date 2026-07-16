<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$groupPayload = "q /Im1 Do Q\n";
$objects = [
    91 => "<< /Type /XObject /Subtype /Form /BBox [0 0 48 24] /Group << /S /Transparency /CS [/Indexed /DeviceRGB 2 92 0 R] /I true /K false >> /Length " . strlen($groupPayload) . " >>\nstream\n{$groupPayload}endstream",
    92 => '<0000004080C0FFFFFF>',
    95 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1] /C0 [1] /C1 [0] /N 1 >>',
    96 => '<< /Type /Mask /S /Luminosity /G 91 0 R /BC [2] /TR 95 0 R >>',
];

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 2 /Height 1 /ColorSpace [/Indexed /DeviceRGB 2 92 0 R] /BitsPerComponent 2 /SMask 96 0 R >>',
    $objects
);
$palettePreview = $renderer->indexedSamplePreview(3, $plan);
$transferAlpha = $renderer->softMaskTransferSampleOpacity(0.25, $plan['soft_mask_transfer_function']);

$metadata = [
    'source' => 'native-pdf-softmask-transfer-indexed-review-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $plan['source_color_space'],
    'indexed_color_space' => $plan['indexed_color_space'],
    'palette_index' => $palettePreview['palette_index'],
    'palette_components' => array_map(static fn (float $value): float => round($value, 6), $palettePreview['base_components']),
    'soft_mask_group' => $plan['soft_mask_group'],
    'soft_mask_transfer_function' => $plan['soft_mask_transfer_function'],
    'transfer_alpha_for_quarter_sample' => round($transferAlpha, 6),
    'soft_mask_transfer_function_applied_before_rgb' => $plan['soft_mask_transfer_function_applied_before_rgb'],
    'alpha_output_mode' => $plan['alpha_output_mode'],
    'output_color_mode' => $plan['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-softmask-transfer-indexed-review-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($plan['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-subtype="' . htmlspecialchars((string) $plan['soft_mask_group']['subtype'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-group-color-space="' . htmlspecialchars((string) $plan['soft_mask_group']['group_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-transfer-function="' . htmlspecialchars((string) $plan['soft_mask_transfer_function']['preview_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-transfer-alpha="' . htmlspecialchars((string) round($transferAlpha, 6), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($plan['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Indexed PDF image with soft-mask transfer review swatch" style="background: linear-gradient(90deg, rgb(64,128,192), rgb(255,255,255)); width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
