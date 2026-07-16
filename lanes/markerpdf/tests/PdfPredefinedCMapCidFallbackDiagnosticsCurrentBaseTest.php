<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$predefinedCMapCidFallbackDiagnosticsCurrentBasePdf = static function (): string {
    $identityToUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "2 beginbfchar\n"
        . "<0041> <0041>\n"
        . "<0042> <0042>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $unsupportedToUnicode = "/CIDInit /ProcSet findresource begin\n"
        . "12 dict begin\n"
        . "begincmap\n"
        . "1 begincodespacerange\n"
        . "<0000> <FFFF>\n"
        . "endcodespacerange\n"
        . "1 beginbfchar\n"
        . "<005A> <005A>\n"
        . "endbfchar\n"
        . "endcmap\n"
        . "CMapName currentdict /CMap defineresource pop\n"
        . "end\n"
        . "end\n";
    $content = 'BT '
        . '/Fid 12 Tf 1 0 0 1 72 720 Tm <00410042> Tj '
        . '/Fucs 12 Tf 1 0 0 1 72 700 Tm <00550043> Tj '
        . '/Fbad 12 Tf 1 0 0 1 72 680 Tm <005A> Tj '
        . 'ET';

    return "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Page /Resources << /Font << /Fid 2 0 R /Fucs 3 0 R /Fbad 4 0 R >> >> /Contents 11 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DiagIdentity /Encoding /Identity-H /DescendantFonts [5 0 R] /ToUnicode 8 0 R >>\nendobj\n"
        . "3 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DiagUCS2 /Encoding 9 0 R /DescendantFonts [6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Font /Subtype /Type0 /BaseFont /DiagUnsupported /Encoding /UniJIS-UTF16-H /DescendantFonts [7 0 R] /ToUnicode 10 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DiagIdentity /CIDSystemInfo << /Registry (Adobe) /Ordering (Identity) /Supplement 0 >> /DW 1000 /W [65 [250 250]] >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DiagUCS2 /CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 6 >> /DW 1000 /W [67 [250] 85 [250]] >>\nendobj\n"
        . "7 0 obj\n<< /Type /Font /Subtype /CIDFontType2 /BaseFont /DiagUnsupported /CIDSystemInfo << /Registry (Adobe) /Ordering (Japan1) /Supplement 6 >> /DW 1000 >>\nendobj\n"
        . "8 0 obj\n<< /Length " . strlen($identityToUnicode) . " >>\nstream\n{$identityToUnicode}\nendstream\nendobj\n"
        . "9 0 obj\n/UniJIS-UCS2-V\nendobj\n"
        . "10 0 obj\n<< /Length " . strlen($unsupportedToUnicode) . " >>\nstream\n{$unsupportedToUnicode}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n%%EOF";
};

return [
    'reviews predefined CMap and CID fallback diagnostics without mojibake leakage' => static function (
        TestRunner $t
    ) use ($predefinedCMapCidFallbackDiagnosticsCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $predefinedCMapCidFallbackDiagnosticsCurrentBasePdf();
        $plainText = $extractor->extractPlainText($pdf);
        $review = $extractor->extractPredefinedCMapCidFallbackReview($pdf);
        $entriesByFont = [];
        foreach ($review['entries'] as $entry) {
            $entriesByFont[$entry['font_object_number']] = $entry;
        }

        $t->same(['AB UC', 'Z'], $extractor->extractTextLines($pdf));
        $t->same(['AB', 'UC', 'Z'], $extractor->extractTextRuns($pdf));
        $t->same("AB UC\nZ", $plainText);
        $t->same("AB UC\nZ\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($plainText, "\0"));
        $t->true(!str_contains($plainText, "\xEF\xBF\xBD"));

        $t->same('pdf_predefined_cmap_cid_fallback_review', $review['source']);
        $t->same(3, $review['font_count']);
        $t->same(3, $review['predefined_cmap_count']);
        $t->same(2, $review['supported_predefined_cmap_count']);
        $t->same(1, $review['unsupported_predefined_cmap_count']);
        $t->same(2, $review['cid_fallback_count']);
        $t->same(1, $review['suppressed_cid_range_count']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);

        $identity = $entriesByFont[2] ?? [];
        $t->same('Identity-H', $identity['encoding_name'] ?? null);
        $t->same('direct_name', $identity['encoding_source'] ?? null);
        $t->same('Identity-H', $identity['selected_predefined_cmap'] ?? null);
        $t->same('supported', $identity['predefined_cmap_status'] ?? null);
        $t->same('identity', $identity['predefined_cmap_family'] ?? null);
        $t->same('explicit_tounicode_with_predefined_cid_fallback', $identity['fallback_path'] ?? null);
        $t->same('explicit_tounicode', $identity['unicode_mapping_policy'] ?? null);
        $t->same(0, $identity['writing_mode'] ?? null);
        $t->same(true, $identity['to_unicode_present'] ?? null);
        $t->same(2, $identity['to_unicode_map_count'] ?? null);
        $t->same([], $identity['suppressed_cid_ranges'] ?? null);

        $ucs2 = $entriesByFont[3] ?? [];
        $t->same('UniJIS-UCS2-V', $ucs2['encoding_name'] ?? null);
        $t->same('indirect_name', $ucs2['encoding_source'] ?? null);
        $t->same('UniJIS-UCS2-V', $ucs2['selected_predefined_cmap'] ?? null);
        $t->same('supported', $ucs2['predefined_cmap_status'] ?? null);
        $t->same('ucs2', $ucs2['predefined_cmap_family'] ?? null);
        $t->same('predefined_cid_codespace_unicode_suppressed', $ucs2['fallback_path'] ?? null);
        $t->same('suppressed_predefined_cid_unicode', $ucs2['unicode_mapping_policy'] ?? null);
        $t->same(1, $ucs2['writing_mode'] ?? null);
        $t->same(false, $ucs2['to_unicode_present'] ?? null);
        $t->same(1, count($ucs2['suppressed_cid_ranges'] ?? []));
        $t->same('predefined_cmap_has_no_embedded_unicode_map', $ucs2['suppressed_cid_ranges'][0]['reason'] ?? null);
        $t->same(true, $ucs2['mojibake_guard'] ?? null);

        $unsupported = $entriesByFont[4] ?? [];
        $t->same('UniJIS-UTF16-H', $unsupported['encoding_name'] ?? null);
        $t->same(null, $unsupported['selected_predefined_cmap'] ?? null);
        $t->same('unsupported', $unsupported['predefined_cmap_status'] ?? null);
        $t->same('known_predefined_unsupported', $unsupported['predefined_cmap_family'] ?? null);
        $t->same('explicit_tounicode_without_predefined_cid_fallback', $unsupported['fallback_path'] ?? null);
        $t->same('unsupported_predefined_cmap_not_used_for_unicode', $unsupported['unicode_mapping_policy'] ?? null);
        $t->same(true, $unsupported['unsupported_semantic_case'] ?? null);
        $t->same(true, $unsupported['to_unicode_present'] ?? null);
        $t->same(1, $unsupported['to_unicode_map_count'] ?? null);
        $t->same(true, $unsupported['mojibake_guard'] ?? null);

        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES);
        $t->true(is_string($encodedReview) && !str_contains($encodedReview, "\0"));
        $t->true(is_string($encodedReview) && !str_contains($encodedReview, "\xEF\xBF\xBD"));
    },
];
