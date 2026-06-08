<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$type3CharProcsSymbolEncodingBoundaryPdf = static function (): array {
    $wideCharProc = "1000 0 d0\n"
        . "q 2 0 0 2 0 0 cm /Glyph#20Image Do Q\n"
        . "BT /Fghost 9 Tf (wide symbol Type3 CharProc text leak) Tj ET\n";
    $thinCharProc = "250 0 d0\n"
        . "q 2 0 0 2 0 0 cm /Glyph#20Image Do Q\n"
        . "BT /Fghost 9 Tf (thin symbol Type3 CharProc text leak) Tj ET\n";
    $pageContent = 'BT /Ft3 12 Tf 72 720 Td <616267> Tj ET';
    $glyphPayload = 'BT /Fghost 9 Tf 0 0 Td (Symbol Type3 Glyph Image Payload Noise) Tj ET';
    $glyphCompressed = gzcompress($glyphPayload);
    if (!is_string($glyphCompressed)) {
        throw new RuntimeException('Unable to compress Symbol Type3 glyph image payload.');
    }

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 10 0 R >>\nendobj\n"
        . "10 0 obj\n<< /Type /Pages /Kids [11 0 R] /Count 1 >>\nendobj\n"
        . "11 0 obj\n<< /Type /Page /Parent 10 0 R /Resources << /Font << /Ft3 2 0 R >> >> /Contents 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type3 /Name /Ft3 /BaseFont /T3SymbolCharProcBoundary "
        . "/FontBBox [0 0 1000 700] /FontMatrix [0.001 0 0 0.001 0 0] "
        . "/Encoding /SymbolEncoding "
        . "/CharProcs << /alpha 3 0 R /beta 3 0 R /gamma 4 0 R >> "
        . "/Resources << /XObject << /Glyph#20Image 5 0 R >> /Font << /Fghost 7 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Length " . strlen($wideCharProc) . " >>\nstream\n{$wideCharProc}\nendstream\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($thinCharProc) . " >>\nstream\n{$thinCharProc}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceGray "
        . "/BitsPerComponent 8 /Filter /FlateDecode /Length " . strlen($glyphCompressed) . " >>\n"
        . "stream\n{$glyphCompressed}\nendstream\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "20 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n%%EOF";

    return [$pdf, $glyphPayload];
};

return [
    'maps SymbolEncoding bytes to Type3 CharProc glyph names before WordPress image review' => static function (
        TestRunner $t
    ) use ($type3CharProcsSymbolEncodingBoundaryPdf): void {
        [$pdf, $glyphPayload] = $type3CharProcsSymbolEncodingBoundaryPdf();
        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $plainText = $extractor->extractPlainText($pdf);
        $expectedText = "\u{03B1}\u{03B2}\u{03B3}";

        $t->same('pdf_image_xobject_boundary_review', $review['source']);
        $t->same(1, $review['page_count']);
        $t->same(3, $review['image_xobject_count']);
        $t->same(3, $review['invoked_image_xobject_count']);
        $t->same(0, $review['uninvoked_image_xobject_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $entriesByGlyph = [];
        foreach ($review['entries'] as $entry) {
            $entriesByGlyph[(string) ($entry['type3_glyph_name'] ?? '')] = $entry;
        }

        $t->same(['alpha', 'beta', 'gamma'], array_keys($entriesByGlyph));
        foreach (['alpha' => 3, 'beta' => 3, 'gamma' => 4] as $glyphName => $charProcObject) {
            $entry = $entriesByGlyph[$glyphName] ?? null;
            $t->true(is_array($entry), "Symbol Type3 glyph {$glyphName} should have an image review row.");
            if (!is_array($entry)) {
                continue;
            }

            $t->same('Glyph Image', $entry['resource_name']);
            $t->same(['Type3 Ft3', $glyphName, 'Glyph Image'], $entry['resource_path']);
            $t->same(5, $entry['object_number']);
            $t->same(0, $entry['object_generation']);
            $t->same(true, $entry['invoked']);
            $t->same(1, $entry['invocation_count']);
            $t->same(1, $entry['type3_glyph_paint_count']);
            $t->same(1, $entry['width']);
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
            $t->same($charProcObject, $entry['type3_charproc_object']);
            $t->same(0, $entry['type3_charproc_generation']);
            $t->same(true, $entry['type3_charproc_image_review']);
        }

        $t->same([$expectedText], $extractor->extractTextLines($pdf));
        $t->same([$expectedText], $extractor->extractTextRuns($pdf));
        $t->same($expectedText, $plainText);
        $t->same($expectedText . "\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, 'symbol Type3 CharProc text leak'));
        $t->true(!str_contains($plainText, 'Symbol Type3 Glyph Image Payload Noise'));
        $t->true(!str_contains(json_encode($review, JSON_UNESCAPED_SLASHES) ?: '', $glyphPayload));
    },
];
