<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$jpxPayload = "\xff\x4f\x00\x00 EI BT /F1 12 Tf 72 690 Td (Inline JPX Noise) Tj ET \xff\xd9";
$inlineDictionary = '/W 3 /H 1 /CS /RGB /BPC 8 /F /JPXDecode /D [0 1 1 0 0 1] /SMask 38 0 R';
$content = "BT /F1 12 Tf 72 720 Td (Before Inline JPX) Tj ET\n"
    . 'BI ' . $inlineDictionary . " ID\n" . $jpxPayload . "\nEI\n"
    . 'BT /F1 12 Tf 72 704 Td (After Inline JPX) Tj ET';

$maskBytes = "\x00\x80\xff";
$compressedMask = gzcompress($maskBytes);
if (!is_string($compressedMask)) {
    throw new RuntimeException('Unable to compress inline JPX soft-mask fixture.');
}

$maskHex = strtoupper(bin2hex($compressedMask)) . '>';
$objects = [
    38 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /Length " . strlen($maskHex) . " >>\nstream\n{$maskHex}\nendstream",
    39 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Decode [0 1] /Length 5 >>\nstream\nSTALE\nendstream",
];

$renderer = new PdfImageRenderer();
$plan = $renderer->inlineImageReviewPlan($inlineDictionary, $jpxPayload, $objects);
$text = (new PdfTextExtractor())->extractPlainText($pdfWithContent($content));
$metadata = [
    'source' => 'native-pdf-inline-jpx-smask-decode-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb plus pdftext inline image payload boundary',
    'visible_text_imported' => $text === "Before Inline JPX\nAfter Inline JPX",
    'excluded_inline_jpx_payload_text' => !str_contains($text, 'Inline JPX Noise'),
    'inline_jpx_review_only' => in_array('JPXDecode', $plan['inline_image']['review_only_filters'], true),
    'inline_native_raster_decode' => $plan['inline_image']['native_raster_decode'],
    'inline_soft_mask_source_object' => $plan['inline_image']['soft_mask_source_object'],
    'inline_soft_mask_uses_current_object_map' => $plan['inline_image']['soft_mask_uses_current_object_map'],
    'soft_mask_decoded_with_current_filters' => $plan['inline_image']['soft_mask_decoded_with_current_filters'],
    'soft_mask_decode_applied_before_rgb' => $plan['inline_image']['soft_mask_decode_applied_before_rgb'],
    'soft_mask_decoded_preview_hex' => $plan['soft_mask_filter_boundary']['decoded_preview_hex'],
    'soft_mask_opacity_zero' => $renderer->softMaskSampleOpacity(0, $plan['soft_mask']),
    'soft_mask_opacity_mid' => $renderer->softMaskSampleOpacity(128, $plan['soft_mask']),
    'soft_mask_opacity_max' => $renderer->softMaskSampleOpacity(255, $plan['soft_mask']),
    'alpha_output_mode' => $plan['alpha_output_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:inline-jpx-smask-decode-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-inline-image="true"';
echo ' data-marker-image-filter="' . htmlspecialchars(implode(',', $plan['image_filters']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-preview-only="' . ($plan['inline_image_review_only'] ? 'true' : 'false') . '"';
echo ' data-marker-soft-mask-source="' . htmlspecialchars((string) $plan['inline_image']['soft_mask_source_object'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-soft-mask-current-object="' . ($plan['inline_image']['soft_mask_uses_current_object_map'] ? 'true' : 'false') . '"';
echo ' data-marker-soft-mask-decode="' . ($plan['soft_mask']['decode_inverted'] ? 'inverted' : 'identity') . '"';
echo ' data-marker-alpha-mode="' . htmlspecialchars($plan['alpha_output_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Inline JPX image with decoded soft mask review swatch"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
