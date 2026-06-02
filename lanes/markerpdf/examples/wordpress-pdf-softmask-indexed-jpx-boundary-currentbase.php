<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$maskBytes = "\x00\x80\xff";
$compressedMask = gzcompress($maskBytes);
if (!is_string($compressedMask)) {
    throw new RuntimeException('Unable to compress Indexed JPX soft-mask fixture.');
}

$maskHex = strtoupper(bin2hex($compressedMask)) . '>';
$jpxPayload = "\xff\x4fJPX indexed image payload kept review-only before RGB preview\xff\xd9";
$objects = [
    41 => '<000000FF000000FF000000FF>',
    42 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
];

$imageObject = "<< /Subtype /Image /Filter /JPXDecode /Width 3 /Height 1 /ColorSpace [/Indexed /DeviceRGB 3 41 0 R] /BitsPerComponent 2 /Decode [0 3] /SMask 42 0 R /Length " . strlen($jpxPayload) . " >>\nstream\n{$jpxPayload}\nendstream";

$renderer = new PdfImageRenderer();
$preview = $renderer->indexedImageStreamPreviewRows($imageObject, $objects, 3);
$softMask = $preview['soft_mask_stream'];
$metadata = [
    'source' => 'native-pdf-softmask-indexed-jpx-boundary-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb',
    'source_color_space' => $preview['source_color_space'],
    'image_filters' => $preview['image_stream']['filters'],
    'preview_only_filters' => $preview['image_stream']['preview_only_filters'],
    'review_only_image_stream' => $preview['review_only_image_stream'],
    'indexed_high_value' => $preview['indexed_color_space']['high_value'],
    'indexed_lookup_length_matches' => $preview['indexed_color_space']['lookup_length_matches'],
    'image_decode_source' => $preview['image_decode']['source'],
    'preview_pixel_count' => $preview['preview_pixel_count'],
    'soft_mask_filters' => is_array($softMask) ? $softMask['filters'] : [],
    'soft_mask_decoded_with_current_filters' => is_array($softMask) && $softMask['decoded_with_current_filters'],
    'soft_mask_decoded_preview_hex' => is_array($softMask) ? $softMask['decoded_preview_hex'] : null,
    'complete_soft_mask_sample_data' => $preview['complete_soft_mask_sample_data'],
    'stream_notes' => $preview['stream_notes'],
    'output_color_mode' => $preview['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-softmask-indexed-jpx-boundary-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-image-filter="' . htmlspecialchars(implode(',', $preview['image_stream']['filters']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-preview-only="' . ($preview['review_only_image_stream'] ? 'true' : 'false') . '"';
echo ' data-marker-indexed-high-value="' . htmlspecialchars((string) $preview['indexed_color_space']['high_value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-decoded="' . ($metadata['soft_mask_decoded_with_current_filters'] ? 'true' : 'false') . '"';
echo ' data-marker-alpha-mode="soft-mask-review">';
echo '<div role="img" aria-label="Indexed JPX image with decoded soft mask review swatch"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
