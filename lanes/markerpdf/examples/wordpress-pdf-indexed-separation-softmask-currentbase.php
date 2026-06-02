<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$objects = [
    51 => '<< /FunctionType 2 /Domain [0 1] /Range [0 1 0 1 0 1 0 1] /C0 [0 0 0 0] /C1 [1 0.25 0 0] /N 1 >>',
    52 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [1 0] /Length 3 >>\nstream\nMSK\nendstream",
];
$imageDictionary = '<< /Subtype /Image /Width 3 /Height 1 /ColorSpace [/Indexed [/Separation /Spot#20Orange /DeviceCMYK 51 0 R] 2 <0040C0>] /BitsPerComponent 2 /Decode [0 2] /SMask 52 0 R >>';

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);
$preview = $renderer->indexedAlternateColorantSamplePreview(3, $plan, 64);

$roundedTints = array_map(static fn (float $value): float => round($value, 6), $preview['colorant_tints']);
$metadata = [
    'source' => 'native-pdf-indexed-separation-softmask-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $preview['source_color_space'],
    'base_color_space' => $preview['base_color_space'],
    'decoded_index' => $preview['decoded_index'],
    'palette_index' => $preview['palette_index'],
    'colorant_tints' => $roundedTints,
    'alternate_color_space' => $preview['alternate_color_space'],
    'tint_transform_object' => $preview['tint_transform_object'],
    'tint_transform_function_type' => $preview['tint_transform_function_type'],
    'soft_mask_alpha' => round((float) $preview['soft_mask_alpha'], 6),
    'output_color_mode' => $preview['output_color_mode'],
    'notes' => $plan['notes'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

$alpha = $preview['soft_mask_alpha'] ?? 1.0;
$tint = $preview['colorant_tints']['Spot Orange'] ?? 0.0;
$swatch = sprintf('rgba(%d,%d,%d,%.3f)', 255, (int) round(128 * (1.0 - $tint)), 0, $alpha);

echo '<!-- markerpdf:pdf-indexed-separation-softmask-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-base-color-space="' . htmlspecialchars((string) $preview['base_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-colorant="' . htmlspecialchars(implode(',', array_keys($preview['colorant_tints'])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-palette-index="' . htmlspecialchars((string) $preview['palette_index'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-alpha="' . htmlspecialchars((string) round((float) $preview['soft_mask_alpha'], 6), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Indexed Separation spot-color image review swatch" style="background: ' . htmlspecialchars($swatch, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '; width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
