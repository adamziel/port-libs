<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n1 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

$renderer = new PdfImageRenderer();

$indexedImageBytes = "\x1c";
$indexedMaskBytes = "\x00\x80\xff";
$compressedIndexedImage = gzcompress($indexedImageBytes);
$compressedIndexedMask = gzcompress($indexedMaskBytes);
if (!is_string($compressedIndexedImage) || !is_string($compressedIndexedMask)) {
    throw new RuntimeException('Unable to compress inline Indexed smoke bytes.');
}

$indexedImageHex = strtoupper(bin2hex($compressedIndexedImage)) . '>';
$indexedMaskHex = strtoupper(bin2hex($compressedIndexedMask)) . '>';
$indexedObjects = [
    91 => '<000000FF000000FF000000FF>',
    92 => "<< /Type /XObject /Subtype /Image /Width 3 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /Length " . strlen($indexedMaskHex) . " >>\nstream\n{$indexedMaskHex}\nendstream",
];

$indexedPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
    '/W 3 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 2 /F [/AHx /Fl] /D [0 3] /Mask [1 1] /SMask 92 0 R',
    $indexedImageHex,
    $indexedObjects,
    3
);

$jpxPayload = "\xff\x4fJPX SMask bytes EI BT /F1 12 Tf 72 690 Td (Hidden Inline Output Noise) Tj ET\xff\xd9";
$jpxMaskBytes = "\x00\xff";
$compressedJpxMask = gzcompress($jpxMaskBytes);
if (!is_string($compressedJpxMask)) {
    throw new RuntimeException('Unable to compress inline JPX soft-mask smoke bytes.');
}

$jpxMaskHex = strtoupper(bin2hex($compressedJpxMask)) . '>';
$jpxDictionary = '/W 2 /H 1 /CS /RGB /BPC 8 /F /JPXDecode /D [0 1 1 0 0 1] /SMask 38 0 R';
$jpxObjects = [
    38 => "<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter [/ASCIIHexDecode /FlateDecode] /Decode [1 0] /Length " . strlen($jpxMaskHex) . " >>\nstream\n{$jpxMaskHex}\nendstream",
];

$jpxPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows(
    $jpxDictionary,
    $jpxPayload,
    $jpxObjects,
    2,
    [
        [0, 128, 240],
        [40, 64, 180],
    ]
);

$content = "BT /F1 12 Tf 72 720 Td (Before Inline Output Preview) Tj ET\n"
    . 'BI ' . $jpxDictionary . " ID\n" . $jpxPayload . "\nEI\n"
    . 'BT /F1 12 Tf 72 704 Td (After Inline Output Preview) Tj ET';
$text = (new PdfTextExtractor())->extractPlainText($pdfWithContent($content));

$indexedRed = $indexedPreview['pixels'][1] ?? null;
$jpxVisible = $jpxPreview['pixels'][0] ?? null;
$jpxTransparent = $jpxPreview['pixels'][1] ?? null;
if (!is_array($indexedRed) || ($indexedRed['output_rgba']['red'] ?? null) !== 255 || ($indexedRed['alpha_source'] ?? null) !== 'soft_mask') {
    throw new RuntimeException('Inline Indexed output preview smoke failed.');
}
if (!is_array($jpxVisible) || ($jpxVisible['output_rgba']['alpha'] ?? null) !== 1.0 || ($jpxVisible['output_rgba']['green'] ?? null) !== 127) {
    throw new RuntimeException('Inline JPX visible output preview smoke failed.');
}
if (!is_array($jpxTransparent) || ($jpxTransparent['output_rgba']['alpha'] ?? null) !== 0.0 || ($jpxTransparent['output_rgba']['green'] ?? null) !== 191) {
    throw new RuntimeException('Inline JPX transparent output preview smoke failed.');
}

$rgbaCss = static function (array $rgba): string {
    return sprintf(
        'rgba(%d,%d,%d,%s)',
        (int) $rgba['red'],
        (int) $rgba['green'],
        (int) $rgba['blue'],
        rtrim(rtrim(sprintf('%.6F', (float) $rgba['alpha']), '0'), '.')
    );
};

$metadata = [
    'source' => 'native-pdf-image-colorspace-mask-inline-output-preview-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb and marker.images.extract image Markdown insertion',
    'visible_text_imported' => $text === "Before Inline Output Preview\nAfter Inline Output Preview",
    'excluded_inline_image_payload_text' => !str_contains($text, 'Hidden Inline Output Noise'),
    'indexed_source_color_space' => $indexedPreview['source_color_space'],
    'indexed_output_rgba' => $indexedPreview['pixels'][1]['output_rgba'],
    'indexed_color_key_suppressed_by_soft_mask' => $indexedPreview['color_key_mask_suppressed_by_soft_mask'],
    'jpx_review_only_image_stream' => $jpxPreview['review_only_image_stream'],
    'jpx_uses_supplied_samples' => $jpxPreview['uses_supplied_samples'],
    'jpx_visible_output_rgba' => $jpxVisible['output_rgba'],
    'jpx_transparent_output_rgba' => $jpxTransparent['output_rgba'],
    'inline_payload_excluded_from_text' => $jpxPreview['inline_image_payload_excluded_from_text'],
    'alpha_output_mode' => $jpxPreview['alpha_output_mode'],
    'output_color_mode' => $jpxPreview['output_color_mode'],
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:pdf-image-colorspace-mask-inline-output-preview-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-inline-output-preview="true"';
echo ' data-marker-indexed-alpha-source="' . htmlspecialchars($indexedRed['alpha_source'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-jpx-preview-only="' . ($jpxPreview['review_only_image_stream'] ? 'true' : 'false') . '"';
echo ' data-marker-jpx-supplied-samples="' . ($jpxPreview['uses_supplied_samples'] ? 'true' : 'false') . '"';
echo ' data-marker-inline-payload-excluded="' . ($jpxPreview['inline_image_payload_excluded_from_text'] ? 'true' : 'false') . '"';
echo ' data-marker-alpha-mode="' . htmlspecialchars($jpxPreview['alpha_output_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Inline PDF image color-space mask output preview swatch" style="display:flex;width:144px;height:48px;border:1px solid #777;">';
echo '<span style="flex:1;background:' . htmlspecialchars($rgbaCss($indexedRed['output_rgba']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ';"></span>';
echo '<span style="flex:1;background:' . htmlspecialchars($rgbaCss($jpxVisible['output_rgba']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ';"></span>';
echo '<span style="flex:1;background:' . htmlspecialchars($rgbaCss($jpxTransparent['output_rgba']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ';"></span>';
echo '</div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
