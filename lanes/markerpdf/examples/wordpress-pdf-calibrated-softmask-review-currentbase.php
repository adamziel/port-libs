<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$imageDictionary = '<< /Subtype /Image /Width 3 /Height 1 /ColorSpace [/CalRGB 81 0 R] /BitsPerComponent 8 /SMask 82 0 R >>';
$objects = [
    81 => '<< /WhitePoint [0.9505 1 1.089] /BlackPoint [0.01 0.02 0.03] /Gamma [2.2 2.1 2.0] /Matrix [0.4 0.3 0.2 0.1 0.8 0.1 0.2 0.1 0.7] >>',
    82 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Decode [1 0] /Length 3 >>\nstream\nMSK\nendstream",
];

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);
$preview = $renderer->calibratedColorSamplePreview([0, 128, 255], $plan, 64);

$metadata = [
    'source' => 'native-pdf-calibrated-softmask-review-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $preview['source_color_space'],
    'calibrated_components' => array_map(static fn (float $value): float => round($value, 6), $preview['calibrated_components']),
    'white_point' => $preview['white_point'],
    'black_point' => $preview['black_point'],
    'gamma' => $preview['gamma'],
    'matrix' => $preview['matrix'],
    'decode_source' => $preview['decode_source'],
    'uses_default_decode' => $preview['uses_default_decode'],
    'soft_mask_alpha' => round((float) $preview['soft_mask_alpha'], 6),
    'output_color_mode' => $preview['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-calibrated-softmask-review-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-decode-source="' . htmlspecialchars((string) $preview['decode_source'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-alpha="' . htmlspecialchars((string) round((float) $preview['soft_mask_alpha'], 6), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($preview['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Calibrated PDF image with soft mask review swatch" style="background: linear-gradient(90deg, rgb(0,128,255), rgb(255,255,255)); width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
