<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$maskBytes = "\x20\x80\xe0";
$compressedMask = gzcompress($maskBytes);
if (!is_string($compressedMask)) {
    throw new RuntimeException('Unable to compress named ColorSpace soft-mask smoke fixture.');
}

$maskHex = strtoupper(bin2hex($compressedMask)) . '>';
$objects = [
    'Resources' => '70 0 R',
    70 => '<< /ColorSpace << /CSspot [/Separation /Spot#20Gold /DeviceCMYK 81 0 R] /CSstale /DeviceRGB >> >>',
    71 => '<< /ColorSpace << /CSspot /DeviceRGB >> >>',
    81 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1 0 1 0 1 0 1] /C0 [0 0 0 0] /C1 [0 0.12 0.8 0] /N 1 >>',
    82 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /DecodeParms [null << /Predictor 1 /Columns 3 /Colors 1 /BitsPerComponent 8 >>] /Decode [1 0] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
];

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 3 /Height 1 /ColorSpace /CSspot /BitsPerComponent 8 /Decode [1 0] /SMask 82 0 R >>',
    $objects
);
$preview = $renderer->alternateColorantSamplePreview([64], $plan, 128);
$boundary = $plan['soft_mask_filter_boundary'];

$roundedTints = array_map(static fn (float $value): float => round($value, 6), $preview['colorant_tints']);
$alpha = round((float) $preview['soft_mask_alpha'], 6);
$tint = $roundedTints['Spot Gold'] ?? 0.0;
$swatch = sprintf('rgba(%d,%d,%d,%.3f)', 255, (int) round(216 * (1.0 - $tint)), 40, $alpha);

$metadata = [
    'source' => 'native-pdf-image-named-colorspace-smask-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $plan['source_color_space'],
    'color_space_resource_name' => $plan['color_space_resource_name'],
    'color_space_resource_value' => $plan['color_space_resource_value'],
    'color_space_resource_source' => $plan['color_space_resource_source'],
    'color_space_resolved_from_resources' => $plan['color_space_resolved_from_resources'],
    'stale_resource_map_excluded' => $plan['color_space_resource_source'] === 'Resources.ColorSpace',
    'alternate_color_space' => $preview['alternate_color_space'],
    'colorant_tints' => $roundedTints,
    'soft_mask_source_object' => $boundary['source_object'],
    'soft_mask_filters' => $boundary['filters'],
    'soft_mask_decoded_with_current_filters' => $boundary['decoded_with_current_filters'],
    'soft_mask_decoded_sample_bytes' => $boundary['decoded_sample_bytes'],
    'soft_mask_alpha' => $alpha,
    'output_color_mode' => $preview['output_color_mode'],
    'alpha_output_mode' => $plan['alpha_output_mode'],
    'notes' => $plan['notes'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-image-named-colorspace-smask-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($plan['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-color-space-resource="' . htmlspecialchars((string) $plan['color_space_resource_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-colorant="' . htmlspecialchars(implode(',', array_keys($preview['colorant_tints'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-source="' . htmlspecialchars((string) $boundary['source_object'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-decoded="' . ($boundary['decoded_with_current_filters'] ? 'true' : 'false') . '"';
echo ' data-marker-alpha-mode="' . htmlspecialchars($plan['alpha_output_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Named PDF ColorSpace soft-mask review swatch" style="background: ' . htmlspecialchars($swatch, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '; width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
