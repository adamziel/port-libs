<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$imageDictionary = '<< /Subtype /Image /Width 2 /Height 1 /ColorSpace [/Indexed /DeviceRGB 2 41 0 R] /BitsPerComponent 2 /SMask 42 0 R >>';
$objects = [
    41 => '<0000002040F0F08010>',
    42 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [1 0] /Length 2 >>\nstream\nMS\nendstream",
];

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);
$first = $renderer->indexedSamplePreview(0, $plan, 255);
$last = $renderer->indexedSamplePreview(3, $plan, 0);
$clamped = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Subtype /Image /Width 1 /Height 1 /ColorSpace [/Indexed /DeviceRGB 2 <000000FFFFFF010203>] /BitsPerComponent 2 /Decode [-1 5] >>'
);
$clampedHigh = $renderer->indexedSamplePreview(3, $clamped);

$rgba = sprintf(
    'rgba(%d,%d,%d,%.3f)',
    (int) round($last['base_components'][0] * 255),
    (int) round($last['base_components'][1] * 255),
    (int) round($last['base_components'][2] * 255),
    $last['soft_mask_alpha'] ?? 1.0
);

$metadata = [
    'source' => 'native-pdf-indexed-smask-decode-colorspace-boundary',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $plan['source_color_space'],
    'indexed_lookup_length_matches' => $plan['indexed_color_space']['lookup_length_matches'],
    'default_decode_source' => $plan['image_decode']['source'],
    'first_decoded_index' => $first['decoded_index'],
    'first_palette_index' => $first['palette_index'],
    'first_soft_mask_alpha' => $first['soft_mask_alpha'],
    'last_decoded_index' => $last['decoded_index'],
    'last_palette_index' => $last['palette_index'],
    'last_palette_rgb' => $last['base_components'],
    'last_soft_mask_alpha' => $last['soft_mask_alpha'],
    'explicit_decode_clamped_to_hival' => $clampedHigh['clamped_to_hival'],
    'explicit_decode_palette_index' => $clampedHigh['palette_index'],
    'output_color_mode' => $plan['output_color_mode'],
    'alpha_output_mode' => $plan['alpha_output_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-indexed-smask-decode-colorspace-boundary ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($plan['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-indexed-decode="' . htmlspecialchars($plan['image_decode']['source'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-palette-index="' . htmlspecialchars((string) $last['palette_index'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-alpha="' . htmlspecialchars((string) $last['soft_mask_alpha'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-clamped-index="' . ($clampedHigh['clamped_to_hival'] ? 'true' : 'false') . '">';
echo '<div role="img" aria-label="Indexed image default Decode and soft-mask alpha review swatch" style="background: ' . htmlspecialchars($rgba, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '; width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
