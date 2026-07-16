<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jpegSegment = static fn (int $marker, string $payload): string => "\xff" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
$componentCount = 4;
$sofPayload = "\x08" . pack('n', 1) . pack('n', 1) . chr($componentCount);
for ($component = 1; $component <= $componentCount; $component++) {
    $sofPayload .= chr($component) . "\x11\x00";
}

$adobeApp14 = 'Adobe' . pack('n', 100) . pack('n', 0) . pack('n', 0) . "\x02";
$jpegBytes = "\xff\xd8" . $jpegSegment(0xee, $adobeApp14) . $jpegSegment(0xc0, $sofPayload) . "\xff\xd9";
$objects = [
    71 => '[1 0 0 1 0 1 0 1]',
];
$imageDictionary = '<< /Filter /DCTDecode /ColorSpace /DeviceCMYK /BitsPerComponent 8 /Decode 71 0 R /DecodeParms << /ColorTransform 0 >> >>';

$renderer = new PdfImageRenderer();
$plan = $renderer->dctDecodeImageColorPlan($imageDictionary, $jpegBytes, $objects);
$rgb = $renderer->dctDecodeSampleToRgb([76, 85, 255, 0], $plan);

$metadata = [
    'source' => 'native-pdf-dctdecode-cmyk-decode-review',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $plan['source_color_space'],
    'adobe_app14_transform' => $plan['adobe_app14_transform'],
    'decode_parms_color_transform' => $plan['decode_parms_color_transform'],
    'effective_color_transform' => $plan['effective_color_transform'],
    'image_decode_applied_before_rgb' => $plan['image_decode_applied_before_rgb'],
    'image_decode_component_mismatch' => $plan['image_decode_component_mismatch'],
    'image_decode_inverted_components' => $plan['image_decode']['inverted_components'] ?? [],
    'rgb_preview_sample' => $rgb,
    'output_color_mode' => $plan['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

$style = sprintf(
    'background-color: rgb(%d,%d,%d); width: 96px; height: 48px;',
    $rgb['red'],
    $rgb['green'],
    $rgb['blue']
);

echo '<!-- markerpdf:pdf-dctdecode-cmyk-decode-review ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-dct-decode="' . ($plan['image_decode_applied_before_rgb'] ? 'applied' : 'not-applied') . '"';
echo ' data-marker-dct-transform="' . htmlspecialchars((string) $plan['effective_color_transform'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($plan['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="DCTDecode CMYK Decode review swatch" style="' . htmlspecialchars($style, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
