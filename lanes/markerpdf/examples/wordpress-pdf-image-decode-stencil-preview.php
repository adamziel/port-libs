<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();

$rgbPlan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Decode [1 0 0 1 0 1] >>'
);
$maskPlan = $renderer->imageColorSpaceSoftMaskPlan(
    '<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ImageMask true /Decode [1 0] >>'
);

$decodedSample = $renderer->imageSampleDecodeValues([0, 128, 255], $rgbPlan['image_decode'], $rgbPlan['bits_per_component']);
$metadata = [
    'source' => 'native-pdf-image-decode-stencil-preview',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'rgb_image_decode' => $rgbPlan['image_decode'],
    'decoded_sample' => $decodedSample,
    'image_mask' => $maskPlan['image_mask'],
    'image_mask_opacity_zero' => $renderer->imageMaskSampleOpacity(0, $maskPlan['image_mask']),
    'image_mask_opacity_one' => $renderer->imageMaskSampleOpacity(1, $maskPlan['image_mask']),
    'output_color_mode' => $maskPlan['output_color_mode'],
    'alpha_output_mode' => $maskPlan['alpha_output_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-image-decode-stencil-preview ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-image-decode="' . ($rgbPlan['image_decode_applied_before_rgb'] ? 'true' : 'false') . '"';
echo ' data-marker-image-mask="' . ($maskPlan['image_mask_applied_before_rgb'] ? 'true' : 'false') . '"';
echo ' data-marker-image-mask-decode="' . ($maskPlan['image_mask']['inverted'] ? 'inverted' : 'identity') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($maskPlan['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Decoded image-mask preview swatch" style="background: linear-gradient(90deg, rgb(255,255,255), rgb(0,96,192)); width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
