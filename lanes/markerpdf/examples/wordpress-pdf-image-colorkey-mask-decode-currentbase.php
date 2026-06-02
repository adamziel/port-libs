<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$imageDictionary = '<< /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Decode [1 0 0 1 0 1] /Mask [0 0 120 140 200 255] >>';

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary);
$transparent = $renderer->colorKeyMaskSamplePreview([0, 128, 240], $plan);
$opaque = $renderer->colorKeyMaskSamplePreview([1, 128, 240], $plan);

$rgb = sprintf(
    'rgb(%d,%d,%d)',
    (int) round($opaque['decoded_components'][0] * 255),
    (int) round($opaque['decoded_components'][1] * 255),
    (int) round($opaque['decoded_components'][2] * 255)
);

$metadata = [
    'source' => 'native-pdf-image-colorkey-mask-decode-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $plan['source_color_space'],
    'image_decode' => $plan['image_decode'],
    'color_key_mask' => $plan['color_key_mask'],
    'transparent_sample' => $transparent,
    'opaque_sample' => $opaque,
    'alpha_output_mode' => $plan['alpha_output_mode'],
    'output_color_mode' => $plan['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-image-colorkey-mask-decode-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-key-mask="' . ($plan['color_key_mask_applied_before_rgb'] ? 'true' : 'false') . '"';
echo ' data-marker-mask-compares="' . ($plan['color_key_mask']['compares_before_decode'] ? 'raw-samples-before-decode' : 'unknown') . '"';
echo ' data-marker-transparent-alpha="' . htmlspecialchars((string) $transparent['alpha'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-opaque-alpha="' . htmlspecialchars((string) $opaque['alpha'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-decode-after-mask="' . ($opaque['decode_applied_after_color_key'] ? 'true' : 'false') . '">';
echo '<div role="img" aria-label="Color-key mask and Decode preview swatch" style="background: linear-gradient(90deg, transparent 0 48%, ' . htmlspecialchars($rgb, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ' 52% 100%); width: 96px; height: 48px; border: 1px solid #777;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
