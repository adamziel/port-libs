<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pdfWithContent = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

$objects = [
    91 => '<000000FF000000FF000000FF>',
    101 => '0',
    102 => '3',
    103 => '1',
];

$renderer = new PdfImageRenderer();
$indexedDictionary = '/W 3 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D [101 0 R 102 0 R]';
$grayDictionary = '/W 2 /H 1 /CS /G /BPC 8 /D [103 0 R 101 0 R]';
$maskDictionary = '/W 4 /H 1 /IM true /BPC 1 /D [103 0 R 101 0 R]';

$indexedPreview = $renderer->inlineIndexedImageStreamPreviewRows($indexedDictionary, "\x00\x80\xff", $objects, 3);
$grayPreview = $renderer->inlineImageColorSpaceMaskOutputPreviewRows($grayDictionary, "\x00\xff", $objects, 2);
$maskPreview = $renderer->inlineImageMaskPreviewRows($maskDictionary, "\xa0", $objects, 4);
$cycleReview = $renderer->inlineImageReviewPlan(
    '/W 1 /H 1 /IM true /D [101 0 R 102 0 R]',
    "\x80",
    [
        101 => '102 0 R',
        102 => '101 0 R',
    ]
);

$content = "BT /F1 12 Tf 72 720 Td (Before Indirect Decode Array Inline) Tj ET\n"
    . "BI /W 32 /H 1 /CS /G /BPC 8 /D [103 0 R 101 0 R] ID\n"
    . "abc EI BT /F1 12 Tf 72 690 Td (Indirect Decode Array Payload Noise) Tj ET rawtail\nEI\n"
    . "BT /F1 12 Tf 72 704 Td (After Indirect Decode Array Inline) Tj ET";
$text = (new PdfTextExtractor())->extractPlainText($pdfWithContent($content));

$metadata = [
    'source' => 'native-pdf-inline-image-decode-array-operand-currentbase',
    'upstream_boundary' => 'marker PDFium/PIL image Decode handoff plus pdftext inline image payload exclusion',
    'visible_text_imported' => $text === "Before Indirect Decode Array Inline\nAfter Indirect Decode Array Inline",
    'inline_payload_excluded_from_text' => !str_contains($text, 'Indirect Decode Array Payload Noise') && !str_contains($text, 'rawtail'),
    'direct_decode_array_member_references_resolved' => ($indexedPreview['image_decode']['valid_for_components'] ?? false) === true,
    'generation_operands_not_counted_as_decode_values' => ($indexedPreview['image_decode']['component_count'] ?? null) === 1,
    'indexed_palette_indexes' => array_column($indexedPreview['pixels'], 'palette_index'),
    'gray_decode_inverted' => ($grayPreview['image_decode']['inverted_components'] ?? []) === [0],
    'gray_output_rgba' => array_column($grayPreview['pixels'], 'output_rgba'),
    'mask_decode_inverted' => ($maskPreview['image_mask']['decode']['inverted_components'] ?? []) === [0],
    'mask_opacity' => array_column($maskPreview['pixels'], 'opacity'),
    'cyclic_decode_member_failed_closed' => ($cycleReview['image_mask']['decode']['valid_for_components'] ?? true) === false
        && ($cycleReview['inline_image_review_only'] ?? false) === true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:inline-image-decode-array-operand-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-inline-image="true"';
echo ' data-marker-decode-member-references="resolved"';
echo ' data-marker-palette-indexes="' . htmlspecialchars(implode(',', array_column($indexedPreview['pixels'], 'palette_index')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-gray-decode="' . (($grayPreview['image_decode']['inverted_components'] ?? []) === [0] ? 'inverted' : 'identity') . '"';
echo ' data-marker-mask-decode="' . (($maskPreview['image_mask']['decode']['inverted_components'] ?? []) === [0] ? 'inverted' : 'identity') . '">';
echo '<div role="img" aria-label="Inline image Decode array operand review swatch"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
