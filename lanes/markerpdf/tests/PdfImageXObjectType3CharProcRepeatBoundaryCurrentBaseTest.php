<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcRepeatImageBoundaryPdf = static function (): array {
    $charProc = "1000 0 d0\n"
        . "q 12 0 0 8 1 2 cm /Glyph#20Image Do Q\n"
        . "BT /Fghost 9 Tf (Type3 repeated CharProc text leak) Tj ET\n";
    $pageContent = 'BT /Ft3 24 Tf 72 720 Td (AA) Tj [(A) -15 (A)] TJ ET';
    $glyphPayload = 'BT /Fghost 9 Tf 0 0 Td (Type3 repeated Glyph Image Payload Noise) Tj ET';
    $glyphCompressed = gzcompress($glyphPayload);
    if (!is_string($glyphCompressed)) {
        throw new RuntimeException('Unable to compress repeated Type3 CharProc image payload.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 10 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Kids [11 0 R] /Count 1 >>\nendobj\n"
        . "11 0 obj\n<< /Type /Page /Parent 10 0 R /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3GlyphImageRepeatBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding << /Type /Encoding /Differences [65 /A] >> "
        . "/CharProcs << /A 3 0 R >> "
        . "/Resources << /XObject << /Glyph#20Image 5 0 R >> /Font << /Fghost 7 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($glyphCompressed) . " >>\nstream\n{$glyphCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n%%EOF";

    return [$pdf, $glyphPayload];
};

return [
    'counts repeated Type3 CharProc Image XObject paints without exposing glyph image payloads' => static function (
        TestRunner $t
    ) use ($type3CharProcRepeatImageBoundaryPdf): void {
        [$pdf, $glyphPayload] = $type3CharProcRepeatImageBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(1, $review['page_count']);
        $t->same(1, $review['image_xobject_count']);
        $t->same(1, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $entry = $review['entries'][0] ?? null;
        $t->true(is_array($entry), 'repeated Type3 CharProc image review row should be present.');
        if (!is_array($entry)) {
            return;
        }

        $t->same('Glyph Image', $entry['resource_name']);
        $t->same(['Type3 Ft3', 'A', 'Glyph Image'], $entry['resource_path']);
        $t->same(5, $entry['object_number']);
        $t->same(0, $entry['object_generation']);
        $t->same(true, $entry['invoked']);
        $t->same(4, $entry['invocation_count']);
        $t->same(4, $entry['painted_invocation_count']);
        $t->same(4, $entry['type3_glyph_paint_count']);
        $t->same([
            [12.0, 0.0, 0.0, 8.0, 1.0, 2.0],
            [12.0, 0.0, 0.0, 8.0, 1.0, 2.0],
            [12.0, 0.0, 0.0, 8.0, 1.0, 2.0],
            [12.0, 0.0, 0.0, 8.0, 1.0, 2.0],
        ], $entry['invocation_matrices']);
        $t->same(2, $entry['width']);
        $t->same(1, $entry['height']);
        $t->same('DeviceGray', $entry['color_space']);
        $t->same(['FlateDecode'], $entry['filters']);
        $t->same(true, $entry['decoded_with_current_filters']);
        $t->same(strlen($glyphPayload), $entry['decoded_length']);
        $t->same(hash('sha256', $glyphPayload), $entry['decoded_sha256']);
        $t->same(false, $entry['payload_in_visible_text']);
        $t->same('Ft3', $entry['type3_font_resource']);
        $t->same(2, $entry['type3_font_object']);
        $t->same(0, $entry['type3_font_generation']);
        $t->same('A', $entry['type3_glyph_name']);
        $t->same(true, $entry['type3_charproc_image_review']);

        $t->same(['AAAA'], $extractor->extractTextLines($pdf));
        $t->same('AAAA', $plainText);
        $t->true(!str_contains($plainText, 'Type3 repeated CharProc text leak'));
        $t->true(!str_contains($plainText, 'Type3 repeated Glyph Image Payload Noise'));
        $t->true(!str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $glyphPayload));
    },
];
