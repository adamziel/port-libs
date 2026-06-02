<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$textContent = 'BT /F1 12 Tf 72 720 Td (JPEG2000 mask preview) Tj T* (WordPress import text) Tj ET';
$jpxPayload = "\xff\x4fJPX image mask bytes BT /F1 12 Tf 72 690 Td (JPX Mask Noise) Tj ET\xff\xd9";
$imageDictionary = '<< /Type /XObject /Subtype /Image /Width 4 /Height 1 /ImageMask true /BitsPerComponent 1 /Filter /JPXDecode /Decode [1 0] /Length ' . strlen($jpxPayload) . ' >>';
$imageObject = $imageDictionary . "\nstream\n{$jpxPayload}\nendstream";
$pdf = "%PDF-1.4\n"
    . "1 0 obj\n<< /Length " . strlen($textContent) . " >>\nstream\n{$textContent}\nendstream\nendobj\n"
    . "2 0 obj\n{$imageObject}\nendobj\n%%EOF";

$renderer = new PdfImageRenderer();
$preview = $renderer->jpeg2000ImageMaskPreviewRows($imageObject, "\x50", [], 4);
$text = (new PdfTextExtractor())->extractPlainText($pdf);
$opacities = array_map(
    static fn (array $pixel): float => (float) $pixel['opacity'],
    $preview['pixels']
);

$metadata = [
    'source' => 'native-pdf-jpeg2000-mask-decode-preview-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb plus PDF JPX ImageMask Decode review boundary',
    'visible_text_imported' => $text === "JPEG2000 mask preview\nWordPress import text",
    'excluded_jpx_mask_payload_text' => !str_contains($text, 'JPX Mask Noise'),
    'image_filter' => $preview['image_stream']['filters'],
    'review_only_image_stream' => $preview['review_only_image_stream'],
    'native_jpeg2000_decode' => $preview['native_jpeg2000_decode'],
    'uses_supplied_jpeg2000_mask_samples' => $preview['uses_supplied_jpeg2000_mask_samples'],
    'image_mask_decode' => $preview['image_mask']['decode']['source'],
    'image_mask_decode_inverted' => $preview['image_mask']['inverted'],
    'mask_opacity_preview' => $opacities,
    'complete_mask_sample_data' => $preview['complete_mask_sample_data'],
    'alpha_output_mode' => $preview['alpha_output_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:jpeg2000-mask-decode-preview-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-image-filter="JPXDecode"';
echo ' data-marker-preview-only="true"';
echo ' data-marker-jpeg2000-native-decode="false"';
echo ' data-marker-image-mask="true"';
echo ' data-marker-mask-decode="' . ($preview['image_mask']['inverted'] ? 'inverted' : 'identity') . '"';
echo ' data-marker-mask-opacity="' . htmlspecialchars(implode(',', $opacities), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="JPEG2000 image mask decode preview swatch"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
