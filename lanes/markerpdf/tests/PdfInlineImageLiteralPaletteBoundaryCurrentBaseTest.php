<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfImageRenderer;
use PortLibs\MarkerPDF\PdfTextExtractor;

$inlineImageLiteralPaletteBoundaryPdf = static function (string $content): string {
    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "%%EOF";
};

return [
    'preserves inline Indexed literal palette slash bytes while expanding real abbreviations before RGB preview' => static function (TestRunner $t) use ($inlineImageLiteralPaletteBoundaryPdf): void {
        $dictionary = '/W 1 /H 1 /CS [/I /RGB 1 (/G/RGB)] /BPC 8';
        $renderer = new PdfImageRenderer();

        $preview = $renderer->inlineIndexedImageStreamPreviewRows($dictionary, "\xff", [], 1);

        $t->same('Indexed', $preview['source_color_space']);
        $t->same(1, $preview['width']);
        $t->same(1, $preview['height']);
        $t->same(8, $preview['bits_per_component']);
        $t->same(1, $preview['expected_pixel_count']);
        $t->same(1, $preview['preview_pixel_count']);
        $t->same(true, $preview['inline_image_abbreviations_expanded']);
        $t->same(true, $preview['inline_image_payload_excluded_from_text']);
        $t->same(
            '<< /Width 1 /Height 1 /ColorSpace [/Indexed /DeviceRGB 1 (/G/RGB)] /BitsPerComponent 8 >>',
            $preview['inline_image']['canonical_dictionary']
        );
        $t->same('literal_string', $preview['indexed_color_space']['lookup_source']);
        $t->same(6, $preview['indexed_color_space']['lookup_length']);
        $t->same(6, $preview['indexed_color_space']['expected_lookup_length']);
        $t->same(true, $preview['indexed_color_space']['lookup_length_matches']);
        $t->same('2F472F524742', $preview['indexed_color_space']['lookup_preview_hex']);
        $t->same([47, 71, 47, 82, 71, 66], $preview['indexed_color_space']['lookup_bytes']);

        $pixel = $preview['pixels'][0];
        $t->same(255.0, $pixel['raw_sample']);
        $t->same(1.0, $pixel['decoded_index']);
        $t->same(1, $pixel['palette_index']);
        $t->same(false, $pixel['clamped_to_hival']);
        $t->same([82 / 255, 71 / 255, 66 / 255], $pixel['base_components']);

        $content = "BT /F1 12 Tf 72 720 Td (Before Literal Palette) Tj ET\n"
            . 'BI ' . $dictionary . " ID\n"
            . "\xffBT /F1 12 Tf 72 690 Td (Inline Literal Palette Noise) Tj ET\n"
            . "EI\n"
            . "BT /F1 12 Tf 72 704 Td (After Literal Palette) Tj ET";
        $extractor = new PdfTextExtractor();
        $text = $extractor->extractPlainText($inlineImageLiteralPaletteBoundaryPdf($content));

        $t->same("Before Literal Palette\nAfter Literal Palette", $text);
        $t->same(['Before Literal Palette', 'After Literal Palette'], $extractor->extractTextLines($inlineImageLiteralPaletteBoundaryPdf($content)));
        $t->true(!str_contains($text, 'Inline Literal Palette Noise'));
        $t->true(!str_contains($text, '/G/RGB'));
        $t->true(!str_contains($text, '/DeviceGray'));
        $t->true(!str_contains($text, '/DeviceRGB'));
    },
];
