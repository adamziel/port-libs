<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$noLengthPostFilterBoundaryFixture = static function (): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before no length post CCITT) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After no length post CCITT) Tj ET';
    $fakeStream = 'BT /F1 12 Tf 72 700 Td (Fake no length post CCITT leak) Tj ET';
    $eofb = "\x00\x10\x01";
    $payload = "\x01\x02{$eofb}\n"
        . "endstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeStream) . " >>\nstream\n{$fakeStream}\nendstream\nendobj\n"
        . "\x03\x04";

    $pdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /NoLengthFax 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents [4 0 R 9 0 R 6 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 16 /Height 0 /ImageMask true /BitsPerComponent 1 /Filter [/CCITTFaxDecode /ASCIIHexDecode] /DecodeParms [<< /K -1 /Columns 16 /Rows 0 /EndOfBlock true >> null] >>\nstream\n{$payload}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [$pdf, $payload, $fakeStream];
};

return [
    'keeps no-Length post-CCITT native filters from owning stale stream markers' => static function (TestRunner $t) use ($noLengthPostFilterBoundaryFixture): void {
        [$pdf, $payload, $fakeStream] = $noLengthPostFilterBoundaryFixture();

        $extractor = new PdfTextExtractor();
        $review = $extractor->extractImageXObjectBoundaryReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $plainText = $extractor->extractPlainText($pdf);
        $runs = $extractor->extractTextRuns($pdf);
        $encodedReview = json_encode($review, JSON_UNESCAPED_SLASHES) ?: '';

        $t->same(['Before no length post CCITT', 'After no length post CCITT'], $extractor->extractTextLines($pdf));
        $t->same(['Before no length post CCITT', 'After no length post CCITT'], $runs);
        $t->same("Before no length post CCITT\nAfter no length post CCITT", $plainText);
        $t->true(!str_contains($plainText, 'Fake no length post CCITT leak'));
        $t->true(!str_contains(implode("\n", $runs), 'Fake no length post CCITT leak'));
        $t->true(!str_contains($plainText, 'endstream'));
        $t->same(1, $review['image_xobject_count'] ?? null);
        $t->same(1, count($review['entries'] ?? []));
        $t->same('NoLengthFax', $entry['resource_name'] ?? null);
        $t->same(['CCITTFaxDecode', 'ASCIIHexDecode'], $entry['filters'] ?? null);
        $t->same(['CCITTFaxDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(strlen($payload), $entry['raw_length'] ?? null);
        $t->same(null, $entry['decoded_length'] ?? null);
        $t->same(null, $entry['decoded_sha256'] ?? null);
        $t->same(null, $entry['decoded_preview_hex'] ?? null);
        $t->same(null, $entry['decoded_sample_bytes'] ?? null);
        $t->same(true, $entry['image_mask'] ?? null);
        $t->same(16, $entry['width'] ?? null);
        $t->same(0, $entry['height'] ?? null);
        $t->same(false, array_key_exists('native_prefix_decoded', $entry));
        $t->same('CCITTFaxDecode', $entry['ccitt_fax_filter_boundary']['declared_filter'] ?? null);
        $t->same('CCITTFaxDecode', $entry['ccitt_fax_filter_boundary']['canonical_filter'] ?? null);
        $t->same(false, $entry['ccitt_fax_filter_boundary']['alias_used'] ?? null);
        $t->same(0, $entry['ccitt_fax_filter_boundary']['non_null_filter_index'] ?? null);
        $t->same([], $entry['ccitt_fax_filter_boundary']['filters_before_ccitt'] ?? null);
        $t->same([], $entry['ccitt_fax_filter_boundary']['native_prefix_filters'] ?? null);
        $t->same(['ASCIIHexDecode'], $entry['ccitt_fax_filter_boundary']['filters_after_ccitt'] ?? null);
        $t->same(['ASCIIHexDecode'], $entry['ccitt_fax_filter_boundary']['native_filters_after_ccitt'] ?? null);
        $t->same([], $entry['ccitt_fax_filter_boundary']['preview_only_filters_after_ccitt'] ?? null);
        $t->same(false, $entry['ccitt_fax_filter_boundary']['ccitt_is_terminal_filter'] ?? null);
        $t->same(true, $entry['ccitt_fax_filter_boundary']['post_ccitt_filters_present'] ?? null);
        $t->same(true, $entry['ccitt_fax_filter_boundary']['post_ccitt_filters_block_native_decode'] ?? null);
        $t->same(true, $entry['ccitt_fax_filter_boundary']['source_filter_preserved'] ?? null);
        $t->same(true, $entry['ccitt_fax_filter_boundary']['review_only'] ?? null);
        $t->same(false, $entry['ccitt_fax_filter_boundary']['native_raster_decode'] ?? null);
        $t->same(-1, $entry['ccitt_fax_decode_boundary']['effective_decode_parms']['k'] ?? null);
        $t->same(16, $entry['ccitt_fax_decode_boundary']['effective_decode_parms']['columns'] ?? null);
        $t->same(0, $entry['ccitt_fax_decode_boundary']['effective_decode_parms']['rows'] ?? null);
        $t->same(true, $entry['ccitt_fax_decode_boundary']['effective_decode_parms']['end_of_block'] ?? null);
        $t->same('group4_two_dimensional', $entry['ccitt_fax_coding_boundary']['coding_mode'] ?? null);
        $t->true(!str_contains($encodedReview, $fakeStream));
        $t->true(!str_contains($encodedReview, 'Fake no length post CCITT leak'));
        $t->true(!str_contains($encodedReview, '9 0 obj'));
        $t->true(!str_contains($encodedReview, $payload));
    },
];
