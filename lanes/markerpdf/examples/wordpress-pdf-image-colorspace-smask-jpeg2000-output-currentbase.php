<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$renderer = new PdfImageRenderer();

$jpxPayload = "\xff\x4fDeviceRGB JPX output bytes BT /F1 12 Tf (Hidden JPEG2000 Output Text) Tj\xff\xd9";
$maskBytes = "\x00\x80\xff";
$compressedMask = gzcompress($maskBytes);
if (!is_string($compressedMask)) {
    throw new RuntimeException('Unable to compress JPEG2000 output smoke soft-mask fixture.');
}

$objects = [
    'Resources' => '70 0 R',
    70 => '<< /ColorSpace << /CSrgb /DeviceRGB /CSstale /DeviceCMYK >> >>',
    82 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [1 0] /Length " . strlen($compressedMask) . " >>\nstream\n{$compressedMask}\nendstream",
];
$imageObject = "<< /Subtype /Image /Filter /JPXDecode /Width 3 /Height 1 /ColorSpace /CSrgb /BitsPerComponent 8 /Decode [0 1 1 0 0 1] /SMask 82 0 R /Length " . strlen($jpxPayload) . " >>\nstream\n{$jpxPayload}\nendstream";

$preview = $renderer->jpeg2000ColorSpaceSoftMaskOutputPreviewRows(
    $imageObject,
    [
        [0, 128, 240],
        [255, 64, 180],
        [32, 255, 0],
    ],
    $objects,
    3,
    [
        'pdfa' => [
            'has_output_intent' => true,
            'output_condition_identifiers' => ['WordPress JPEG2000 RGB OutputIntent'],
            'profile_sha256' => [hash('sha256', 'WordPress JPEG2000 output profile')],
        ],
    ]
);

$content = 'BT /F1 12 Tf 72 720 Td (JPEG2000 output review) Tj T* (WordPress media import) Tj ET';
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "2 0 obj\n{$imageObject}\nendobj\n%%EOF";
$visibleText = (new PdfTextExtractor())->extractPlainText($pdf);

$pixels = array_map(
    static function (array $pixel): array {
        return [
            'index' => $pixel['pixel_index'],
            'rgba' => $pixel['output_rgba'],
            'alpha' => round((float) $pixel['soft_mask_alpha'], 6),
        ];
    },
    $preview['pixels']
);

$metadata = [
    'source' => 'native-pdf-image-colorspace-smask-jpeg2000-output-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image and render_bbox_image RGB output',
    'visible_text_imported' => $visibleText === "JPEG2000 output review\nWordPress media import",
    'excluded_jpeg2000_payload_text' => !str_contains($visibleText, 'Hidden JPEG2000 Output Text'),
    'image_filter' => $preview['image_stream']['filters'],
    'review_only_image_stream' => $preview['review_only_image_stream'],
    'native_jpx_raster_decode' => $preview['native_jpx_raster_decode'],
    'uses_supplied_jpx_samples' => $preview['uses_supplied_jpx_samples'],
    'color_space_resource' => $preview['color_space_resource_name'],
    'soft_mask_source_object' => $preview['soft_mask_filter_boundary']['source_object'],
    'soft_mask_decoded_with_current_filters' => $preview['soft_mask_stream']['decoded_with_current_filters'],
    'pdfa_profile_source' => $preview['color_management']['profile_source'],
    'pixels' => $pixels,
    'output_color_mode' => $preview['output_color_mode'],
    'alpha_output_mode' => $preview['alpha_output_mode'],
    'image_payload_bytes_exposed' => false,
    'soft_mask_payload_bytes_exposed' => false,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

$swatch = $preview['pixels'][1]['output_rgba'];
$rgba = sprintf(
    'rgba(%d,%d,%d,%.6f)',
    $swatch['red'],
    $swatch['green'],
    $swatch['blue'],
    (float) $swatch['alpha']
);

echo '<!-- markerpdf:image-colorspace-smask-jpeg2000-output-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-image-filter="JPXDecode"';
echo ' data-marker-preview-only="true"';
echo ' data-marker-native-jpx-raster-decode="false"';
echo ' data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-color-space-resource="' . htmlspecialchars((string) $preview['color_space_resource_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-source="' . htmlspecialchars((string) $preview['soft_mask_filter_boundary']['source_object'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-alpha-mode="' . htmlspecialchars($preview['alpha_output_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-rgba="' . htmlspecialchars(json_encode($pixels, JSON_UNESCAPED_SLASHES) ?: '[]', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="JPEG2000 color-space soft-mask output preview swatch" style="background: ' . htmlspecialchars($rgba, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '; width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $visibleText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
