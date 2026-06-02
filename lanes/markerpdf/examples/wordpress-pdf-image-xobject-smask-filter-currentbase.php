<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$maskBytes = "\x00\x7f\xff";
$compressed = gzcompress($maskBytes);
if (!is_string($compressed)) {
    throw new RuntimeException('Unable to compress soft-mask smoke fixture.');
}

$encodedMask = strtoupper(bin2hex($compressed)) . '>';
$imageDictionary = '<< /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /SMask 31 0 R >>';
$objects = [
    31 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /DecodeParms [null 32 0 R] /Decode [0 1] /Length " . strlen($encodedMask) . " >>\nstream\n{$encodedMask}\nendstream",
    32 => '<< /Predictor 1 /Columns 3 /Colors 1 /BitsPerComponent 8 >>',
    99 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length 8 >>\nstream\nSTALEMSK\nendstream",
];

$renderer = new PdfImageRenderer();
$plan = $renderer->imageColorSpaceSoftMaskPlan($imageDictionary, $objects);
$boundary = $plan['soft_mask_filter_boundary'];

$metadata = [
    'source' => 'native-pdf-image-xobject-smask-filter-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'soft_mask_source_object' => $boundary['source_object'],
    'soft_mask_filters' => $boundary['filters'],
    'soft_mask_raw_length' => $boundary['raw_length'],
    'soft_mask_decoded_length' => $boundary['decoded_length'],
    'soft_mask_decoded_sha256' => $boundary['decoded_sha256'],
    'soft_mask_decoded_sample_bytes' => $boundary['decoded_sample_bytes'],
    'soft_mask_decoded_with_current_filters' => $boundary['decoded_with_current_filters'],
    'uses_current_object_map' => $boundary['uses_current_object_map'],
    'stale_mask_object_excluded' => $boundary['source_object'] === 31 && $boundary['decoded_sha256'] === hash('sha256', $maskBytes),
    'soft_mask_opacity_zero' => $renderer->softMaskSampleOpacity(0, $plan['soft_mask']),
    'soft_mask_opacity_max' => $renderer->softMaskSampleOpacity(255, $plan['soft_mask']),
    'output_color_mode' => $plan['output_color_mode'],
    'alpha_output_mode' => $plan['alpha_output_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-image-xobject-smask-filter-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-soft-mask-filter="' . htmlspecialchars(implode(',', $boundary['filters']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-decoded="' . ($boundary['decoded_with_current_filters'] ? 'true' : 'false') . '"';
echo ' data-marker-soft-mask-source-object="' . htmlspecialchars((string) $boundary['source_object'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($plan['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Image XObject soft-mask filter review swatch" style="background: linear-gradient(90deg, rgba(40,120,220,0), rgba(40,120,220,0.5), rgba(40,120,220,1)); width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
