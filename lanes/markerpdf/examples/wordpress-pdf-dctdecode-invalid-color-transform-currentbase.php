<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$jpegSegment = static fn (int $marker, string $payload): string => "\xff" . chr($marker) . pack('n', strlen($payload) + 2) . $payload;
$sofPayload = "\x08" . pack('n', 1) . pack('n', 1) . "\x04"
    . "\x01\x11\x00"
    . "\x02\x11\x00"
    . "\x03\x11\x00"
    . "\x04\x11\x00";
$jpegBytes = "\xff\xd8" . $jpegSegment(0xc0, $sofPayload) . "\xff\xd9";
$imageDictionary = '<< /Filter /DCTDecode /ColorSpace /DeviceCMYK /BitsPerComponent 8 /DecodeParms << /ColorTransform 9 >> >>';

$renderer = new PdfImageRenderer();
$plan = $renderer->dctDecodeImageColorPlan($imageDictionary, $jpegBytes);
$rgb = $renderer->dctDecodeSampleToRgb([0, 255, 255, 0], $plan);

if (
    ($plan['decode_parms_color_transform'] ?? null) !== 9
    || ($plan['decode_parms_color_transform_valid'] ?? true) !== false
    || ($plan['decode_parms_color_transform_ignored'] ?? false) !== true
    || ($plan['effective_color_transform'] ?? null) !== 0
    || ($plan['uses_ycck_transform'] ?? true) !== false
    || $rgb !== ['red' => 255, 'green' => 0, 'blue' => 0]
) {
    throw new RuntimeException('Invalid DCTDecode ColorTransform was not ignored before RGB preview.');
}

$metadata = [
    'source' => 'native-pdf-dctdecode-invalid-color-transform',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'decode_parms_color_transform' => $plan['decode_parms_color_transform'],
    'decode_parms_color_transform_valid' => $plan['decode_parms_color_transform_valid'],
    'decode_parms_color_transform_ignored' => $plan['decode_parms_color_transform_ignored'],
    'effective_color_transform' => $plan['effective_color_transform'],
    'uses_ycck_transform' => $plan['uses_ycck_transform'],
    'rgb_preview_sample' => $rgb,
    'notes' => $plan['notes'],
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

echo '<!-- markerpdf:pdf-dctdecode-invalid-color-transform ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-dct-invalid-color-transform="ignored"';
echo ' data-marker-dct-effective-transform="' . htmlspecialchars((string) $plan['effective_color_transform'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($plan['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Invalid DCTDecode ColorTransform review swatch" style="' . htmlspecialchars($style, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
