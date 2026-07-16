<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcCMapImageXObjectBoundaryPdf = static function (): array {
    $encodingCMap = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "/CMapName /Type3CharProcImageCMap-H def\n"
        . "1 begincodespacerange\n"
        . "<AB00> <ABFF>\n"
        . "endcodespacerange\n"
        . "1 begincidchar\n"
        . "<AB01> 65\n"
        . "endcidchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $charProc = "1000 0 d0\n"
        . "q 12 0 0 8 1 2 cm /Glyph#20Image Do Q\n"
        . "BT /Fghost 9 Tf (Type3 CMap CharProc visible text leak) Tj ET\n";
    $pageContent = 'BT /Ft3 24 Tf 72 720 Td <AB01> Tj ET';
    $glyphPayload = 'BT /Fghost 9 Tf 0 0 Td (Type3 CMap Glyph Image Payload Noise) Tj ET';
    $unusedPayload = 'BT /Fghost 9 Tf 0 0 Td (Unused Type3 CMap Image Payload Noise) Tj ET';
    $glyphCompressed = gzcompress($glyphPayload);
    $unusedCompressed = gzcompress($unusedPayload);
    if (!is_string($glyphCompressed) || !is_string($unusedCompressed)) {
        throw new RuntimeException('Unable to compress Type3 CMap CharProc image fixture payloads.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 10 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Kids [11 0 R] /Count 1 >>\nendobj\n"
        . "11 0 obj\n<< /Type /Page /Parent 10 0 R /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3GlyphImageCMapBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding 19 0 R /CharProcs << /A 3 0 R >> "
        . "/Resources << /XObject << /Glyph#20Image 5 0 R /Unused#20Glyph#20Image 6 0 R >> /Font << /Fghost 7 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($charProc) . " >>\nstream\n{$charProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 2 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($glyphCompressed) . " >>\nstream\n{$glyphCompressed}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($unusedCompressed) . " >>\nstream\n{$unusedCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "19 0 obj\n<< /Length " . strlen($encodingCMap) . " >>\nstream\n{$encodingCMap}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n%%EOF";

    return [$pdf, $glyphPayload, $unusedPayload];
};

return [
    'records CMap-encoded Type3 CharProc Image XObject paints as review-only metadata' => static function (
        TestRunner $t
    ) use ($type3CharProcCMapImageXObjectBoundaryPdf): void {
        [$pdf, $glyphPayload, $unusedPayload] = $type3CharProcCMapImageXObjectBoundaryPdf();
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
        $t->true(is_array($entry), 'Type3 CMap CharProc image review row should be present.');
        if (!is_array($entry)) {
            return;
        }

        $t->same('Glyph Image', $entry['resource_name']);
        $t->same(['Type3 Ft3', 'A', 'Glyph Image'], $entry['resource_path']);
        $t->same(5, $entry['object_number']);
        $t->same(0, $entry['object_generation']);
        $t->same(true, $entry['invoked']);
        $t->same(1, $entry['invocation_count']);
        $t->same([[12.0, 0.0, 0.0, 8.0, 1.0, 2.0]], $entry['invocation_matrices']);
        $t->same([1.0, 2.0, 13.0, 10.0], $entry['image_unit_bbox']);
        $t->same('Image', $entry['subtype']);
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
        $t->same(3, $entry['type3_charproc_object']);
        $t->same(0, $entry['type3_charproc_generation']);
        $t->same(true, $entry['type3_charproc_image_review']);

        $t->same(['A'], $extractor->extractTextLines($pdf));
        $t->same('A', $plainText);
        $t->true(!str_contains($plainText, 'Type3 CMap CharProc visible text leak'));
        $t->true(!str_contains($plainText, 'Type3 CMap Glyph Image Payload Noise'));
        $t->true(!str_contains($plainText, 'Unused Type3 CMap Image Payload Noise'));
        $t->true(!str_contains($plainText, 'AB01'));

        $encoded = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';
        $t->true(!str_contains($encoded, $glyphPayload));
        $t->true(!str_contains($encoded, $unusedPayload));
    },
];
