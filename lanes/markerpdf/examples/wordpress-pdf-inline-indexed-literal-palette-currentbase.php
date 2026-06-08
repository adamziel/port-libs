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

$dictionary = '/W 1 /H 1 /CS [/I /RGB 1 (/G/RGB)] /BPC 8';
$renderer = new PdfImageRenderer();
$preview = $renderer->inlineIndexedImageStreamPreviewRows($dictionary, "\xff", [], 1);
$pixel = $preview['pixels'][0] ?? null;
if (!is_array($pixel) || ($pixel['palette_index'] ?? null) !== 1) {
    throw new RuntimeException('Inline Indexed literal palette preview did not select the literal palette entry.');
}
if (($preview['indexed_color_space']['lookup_preview_hex'] ?? null) !== '2F472F524742') {
    throw new RuntimeException('Inline Indexed literal palette bytes were not preserved.');
}

$content = "BT /F1 12 Tf 72 720 Td (Before Literal Palette) Tj ET\n"
    . 'BI ' . $dictionary . " ID\n"
    . "\xffBT /F1 12 Tf 72 690 Td (Inline Literal Palette Noise) Tj ET\n"
    . "EI\n"
    . "BT /F1 12 Tf 72 704 Td (After Literal Palette) Tj ET";
$text = (new PdfTextExtractor())->extractPlainText($pdfWithContent($content));
if ($text !== "Before Literal Palette\nAfter Literal Palette") {
    throw new RuntimeException('Inline Indexed literal palette payload leaked into visible WordPress text.');
}

$components = array_map(static fn (float $value): int => (int) round($value * 255), $pixel['base_components']);
$metadata = [
    'source' => 'native-pdf-inline-indexed-literal-palette-currentbase',
    'upstream_boundary' => 'marker.pdf.images.render_image_rgb plus pdftext inline image payload boundary',
    'source_color_space' => $preview['source_color_space'],
    'inline_image_abbreviations_expanded' => $preview['inline_image_abbreviations_expanded'],
    'inline_payload_excluded_from_text' => $preview['inline_image_payload_excluded_from_text'],
    'canonical_dictionary' => $preview['inline_image']['canonical_dictionary'],
    'literal_palette_lookup_source' => $preview['indexed_color_space']['lookup_source'],
    'literal_palette_lookup_hex' => $preview['indexed_color_space']['lookup_preview_hex'],
    'palette_index' => $pixel['palette_index'],
    'palette_rgb' => $components,
    'visible_text_imported' => $text === "Before Literal Palette\nAfter Literal Palette",
    'excluded_inline_payload_text' => !str_contains($text, 'Inline Literal Palette Noise'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pypdfium_or_pil' => false,
];

echo '<!-- markerpdf:inline-indexed-literal-palette-currentbase ' . htmlspecialchars(json_encode($metadata, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
echo "<!-- wp:image -->\n";
echo '<figure class="wp-block-image" data-marker-inline-image="true"';
echo ' data-marker-color-space="' . htmlspecialchars($preview['source_color_space'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-palette-source="' . htmlspecialchars((string) $preview['indexed_color_space']['lookup_source'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-palette-index="' . htmlspecialchars((string) $pixel['palette_index'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
echo ' data-marker-output-mode="' . htmlspecialchars($preview['output_color_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
echo '<div role="img" aria-label="Inline Indexed literal palette preview swatch" style="width:96px;height:48px;border:1px solid #777;background:rgb(' . implode(',', $components) . ');"></div>';
echo "</figure>\n";
echo "<!-- /wp:image -->\n";
echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $text), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
