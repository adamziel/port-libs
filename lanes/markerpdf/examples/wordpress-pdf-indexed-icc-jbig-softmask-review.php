<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$imageDictionary = '<< /Subtype /Image /Filter /JBIG2Decode /DecodeParms << /JBIG2Globals 24 0 R >> /Width 3 /Height 1 /ColorSpace [/Indexed 21 0 R 2 22 0 R] /BitsPerComponent 2 /Decode [0 2] /SMask 23 0 R >>';
$objects = [
    20 => "<< /N 3 /Alternate /DeviceRGB /Range [0 1 0 1 0 1] /Length 11 >>\nstream\nICC-PROFILE\nendstream",
    21 => '[/ICCBased 20 0 R]',
    22 => '<00000080FF40010203>',
    23 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [1 0] /Length 3 >>\nstream\nMSK\nendstream",
    24 => "<< /Length 4 >>\nstream\nGBLS\nendstream",
];

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);
$decodedIndex = $renderer->imageSampleDecodeValues([3], $plan['image_decode'], $plan['bits_per_component'])[0];
$paletteRgb = $renderer->indexedSampleToBaseComponents((int) $decodedIndex, $plan['indexed_color_space']);
$softMaskZero = $renderer->softMaskSampleOpacity(0, $plan['soft_mask']);
$softMaskMax = $renderer->softMaskSampleOpacity(255, $plan['soft_mask']);
$rgbCss = sprintf(
    'rgb(%d,%d,%d)',
    (int) round($paletteRgb[0] * 255),
    (int) round($paletteRgb[1] * 255),
    (int) round($paletteRgb[2] * 255)
);

$metadata = [
    'source' => 'native-pdf-indexed-icc-jbig-softmask-review',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $plan['source_color_space'],
    'image_filters' => $plan['image_filters'],
    'preview_only_filters' => $plan['image_filter_boundary']['preview_only_filters'],
    'jbig2_globals_present' => $plan['image_filter_boundary']['jbig2_globals_present'],
    'uses_indexed_color_space' => $plan['uses_indexed_color_space'],
    'indexed_base_color_space' => $plan['indexed_color_space']['base_color_space'],
    'indexed_base_uses_icc_profile' => $plan['indexed_color_space']['base_uses_icc_profile'],
    'indexed_lookup_length_matches' => $plan['indexed_color_space']['lookup_length_matches'],
    'decoded_index' => $decodedIndex,
    'indexed_palette_rgb' => $paletteRgb,
    'soft_mask_decode_applied_before_rgb' => $plan['soft_mask_decode_applied_before_rgb'],
    'soft_mask_opacity_zero' => $softMaskZero,
    'soft_mask_opacity_max' => $softMaskMax,
    'output_color_mode' => $plan['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-indexed-icc-jbig-softmask-review ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($plan['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-indexed-base="' . htmlspecialchars((string) $plan['indexed_color_space']['base_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-image-filter="' . htmlspecialchars(implode(',', $plan['image_filters']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-preview-only="' . ($plan['image_filter_boundary']['native_raster_decode'] ? 'false' : 'true') . '"';
echo ' data-marker-soft-mask-decode="' . ($plan['soft_mask']['decode_inverted'] ? 'inverted' : 'identity') . '">';
echo '<div role="img" aria-label="Indexed ICCBased JBIG2 image review swatch" style="background: ' . htmlspecialchars($rgbCss, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '; width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
