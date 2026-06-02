<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$maskBytes = "\x00\x80\xff";
$compressedMask = gzcompress($maskBytes);
if (!is_string($compressedMask)) {
    throw new RuntimeException('Unable to compress calibrated JBIG2 soft-mask fixture.');
}

$imagePayload = "\x97JB2\r\n\x1a\nJBIG2-CALIBRATED-IMAGE";
$maskHex = strtoupper(bin2hex($compressedMask)) . '>';
$globalsBytes = 'GBLS-JB2';
$objects = [
    81 => '<< /WhitePoint [0.9505 1 1.089] /BlackPoint [0.01 0.02 0.03] /Gamma [2.2 2.1 2.0] /Matrix [0.4 0.3 0.2 0.1 0.8 0.1 0.2 0.1 0.7] >>',
    82 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /DecodeParms [null << /Predictor 1 /Columns 3 /Colors 1 /BitsPerComponent 8 >>] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
    90 => "<< /Length " . strlen($globalsBytes) . " >>\nstream\n{$globalsBytes}\nendstream",
];
$imageObject = "<< /Subtype /Image /Filter /JBIG2Decode /DecodeParms << /JBIG2Globals 90 0 R >> /Width 3 /Height 1 /ColorSpace [/CalRGB 81 0 R] /BitsPerComponent 8 /SMask 82 0 R /Length " . strlen($imagePayload) . " >>\nstream\n{$imagePayload}\nendstream";

$renderer = new PdfImageRenderer();
$preview = $renderer->calibratedImageStreamPreviewRows($imageObject, $objects, 3);
$jbig2Parms = $preview['image_filter_details'][0]['decode_parms'] ?? [];
$softMaskAlpha = $renderer->softMaskSampleOpacity(128, $preview['soft_mask']);

$metadata = [
    'source' => 'native-pdf-calibrated-jbig2-softmask-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $preview['source_color_space'],
    'image_filters' => $preview['image_stream']['filters'],
    'preview_only_filters' => $preview['image_filter_boundary']['preview_only_filters'],
    'review_only_image_stream' => $preview['review_only_image_stream'],
    'jbig2_globals_present' => $preview['image_filter_boundary']['jbig2_globals_present'],
    'jbig2_globals_object' => $jbig2Parms['jbig2_globals_object'] ?? null,
    'jbig2_globals_length' => $jbig2Parms['jbig2_globals_length'] ?? null,
    'jbig2_globals_sha256' => $jbig2Parms['jbig2_globals_sha256'] ?? null,
    'soft_mask_decoded_with_current_filters' => $preview['soft_mask_stream']['decoded_with_current_filters'] ?? false,
    'soft_mask_decoded_preview_hex' => $preview['soft_mask_stream']['decoded_preview_hex'] ?? null,
    'soft_mask_alpha_for_mid_sample' => round($softMaskAlpha, 6),
    'decode_source' => $preview['image_decode']['source'] ?? null,
    'output_color_mode' => $preview['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-calibrated-jbig2-softmask-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-image-filter="' . htmlspecialchars(implode(',', $preview['image_stream']['filters']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-preview-only="' . ($preview['review_only_image_stream'] ? 'true' : 'false') . '"';
echo ' data-marker-jbig2-globals="' . ($preview['image_filter_boundary']['jbig2_globals_present'] ? 'true' : 'false') . '"';
echo ' data-marker-soft-mask-alpha="' . htmlspecialchars((string) round($softMaskAlpha, 6), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-color-mode="' . htmlspecialchars($preview['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Calibrated JBIG2 image with decoded soft-mask review swatch" style="background: linear-gradient(90deg, rgba(0,128,255,0.5), rgba(255,255,255,1)); width: 96px; height: 48px;"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
