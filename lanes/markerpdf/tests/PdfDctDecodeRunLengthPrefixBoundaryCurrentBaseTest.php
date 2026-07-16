<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDctDecodeRunLengthPrefixBoundaryCurrentBaseEncode = static function (string $bytes): string {
    $encoded = '';
    for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += 128) {
        $chunk = substr($bytes, $offset, 128);
        $encoded .= chr(strlen($chunk) - 1) . $chunk;
    }

    return $encoded . chr(128);
};

$pdfDctDecodeRunLengthPrefixBoundaryCurrentBaseFixture = static function () use ($pdfDctDecodeRunLengthPrefixBoundaryCurrentBaseEncode): array {
    $before = 'BT /F1 12 Tf 72 720 Td (Before RunLength DCT stream) Tj ET';
    $after = 'BT /F1 12 Tf 72 680 Td (After RunLength DCT stream) Tj ET';
    $fakeObject = 'BT /F1 12 Tf 72 700 Td (RunLength DCT early EOD leak) Tj ET';
    $incompleteJpeg = "\xff\xd8\xff\xe0\x00\x10JFIF\0incomplete";
    $completeJpeg = "\xff\xd8\xff\xe0\x00\x10JFIF\0complete!\xff\xd9";
    $encodedPayload = $pdfDctDecodeRunLengthPrefixBoundaryCurrentBaseEncode($incompleteJpeg)
        . "\nendstream\nendobj\n"
        . "9 0 obj\n<< /Length " . strlen($fakeObject) . " >>\nstream\n{$fakeObject}\nendstream\nendobj\n"
        . $pdfDctDecodeRunLengthPrefixBoundaryCurrentBaseEncode($completeJpeg);
    $fakeTerminatorOffset = strpos($encodedPayload, "\nendstream\n");
    if ($fakeTerminatorOffset === false) {
        throw new RuntimeException('Focused RunLength DCT fixture must expose a fake early EOD endstream marker.');
    }

    $buildStreamOnlyPdf = static function (?int $declaredLength) use ($before, $after, $encodedPayload): string {
        $lengthOperand = $declaredLength === null ? '' : " /Length {$declaredLength}";

        return "%PDF-1.4\n"
            . "1 0 obj\n<< /Length " . strlen($before) . " >>\nstream\n{$before}\nendstream\nendobj\n"
            . "2 0 obj\n<< /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/RunLengthDecode /DCTDecode]{$lengthOperand} >>\nstream\n{$encodedPayload}\nendstream\nendobj\n"
            . "3 0 obj\n<< /Length " . strlen($after) . " >>\nstream\n{$after}\nendstream\nendobj\n%%EOF";
    };

    $pageContent = $before . "\nq 24 0 0 24 72 680 cm /Photo Do Q\n" . $after;
    $pagePdf = "%PDF-1.4\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 10 0 R >> /XObject << /Photo 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /XObject /Subtype /Image /Width 1 /Height 1 /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/RunLengthDecode /DCTDecode] /Length {$fakeTerminatorOffset} >>\nstream\n{$encodedPayload}\nendstream\nendobj\n"
        . "10 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n%%EOF";

    return [
        'before' => $before,
        'after' => $after,
        'fake_terminator_offset' => $fakeTerminatorOffset,
        'encoded_payload' => $encodedPayload,
        'stream_without_length' => $buildStreamOnlyPdf(null),
        'stream_with_stale_length' => $buildStreamOnlyPdf($fakeTerminatorOffset),
        'page_pdf' => $pagePdf,
    ];
};

return [
    'keeps RunLengthDecode prefix DCTDecode EOD decoys inside image payload boundaries' => static function (TestRunner $t) use ($pdfDctDecodeRunLengthPrefixBoundaryCurrentBaseFixture): void {
        $fixture = $pdfDctDecodeRunLengthPrefixBoundaryCurrentBaseFixture();
        $extractor = new PdfTextExtractor();
        $expected = [
            'Before RunLength DCT stream',
            'After RunLength DCT stream',
        ];

        foreach ([$fixture['stream_without_length'], $fixture['stream_with_stale_length'], $fixture['page_pdf']] as $pdf) {
            $plainText = $extractor->extractPlainText($pdf);

            $t->same($expected, $extractor->extractTextLines($pdf));
            $t->same($expected, $extractor->extractTextRuns($pdf));
            $t->same("Before RunLength DCT stream\nAfter RunLength DCT stream", $plainText);
            $t->same("Before RunLength DCT stream\nAfter RunLength DCT stream\n", $extractor->naiveGetText($pdf));
            $t->true(!str_contains($plainText, 'RunLength DCT early EOD leak'));
            $t->true(!str_contains($plainText, 'JFIF'));
            $t->true(!str_contains($plainText, 'endstream'));
        }

        $review = $extractor->extractImageXObjectBoundaryReview($fixture['page_pdf']);
        $entry = $review['entries'][0] ?? null;
        $pageText = $extractor->extractPlainText($fixture['page_pdf']);

        $t->same(['Before RunLength DCT stream', 'After RunLength DCT stream'], $extractor->extractTextLines($fixture['page_pdf']));
        $t->same("Before RunLength DCT stream\nAfter RunLength DCT stream", $pageText);
        $t->true(!str_contains($pageText, 'RunLength DCT early EOD leak'));
        $t->true(is_array($entry), 'Image XObject review row should be present.');
        $t->same(strlen($fixture['encoded_payload']), $entry['raw_length'] ?? null);
        $t->true(($entry['raw_length'] ?? 0) > $fixture['fake_terminator_offset']);
        $t->same(['RunLengthDecode', 'DCTDecode'], $entry['filters'] ?? null);
        $t->same(['DCTDecode'], $entry['preview_only_filters'] ?? null);
        $t->same(false, $entry['native_raster_decode'] ?? null);
        $t->same(false, $entry['decoded_with_current_filters'] ?? null);
        $t->same(false, $entry['payload_in_visible_text'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
