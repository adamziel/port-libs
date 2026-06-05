<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamZeroWidthCarrierCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current zero-width carrier guard page) Tj T* (Invalid carrier row stays review-only) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 700 Td (Stale zero-width carrier page leak) Tj T* (Default object stream zero must fail closed) Tj ET';

    $compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>';
    $header = '4 0';
    $objectStream = gzcompress($header . "\n" . $compressedPage . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress zero-width carrier object-stream fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefStreamRow = static fn (int $type, int $fieldThree = 0): string => chr($type) . chr($fieldThree);
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (stale direct page must be suppressed) >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 10\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow($offsets['3:0'])
        . $xrefTableRow($offsets['4:0'])
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow($offsets['6:0'])
        . $xrefTableRow(0, 0, 'f')
        . $xrefTableRow($offsets['8:0'])
        . $xrefTableRow($offsets['9:0'])
        . "trailer\n<< /Size 10 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $xrefRows = $xrefStreamRow(2);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress zero-width carrier xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Prev ' . $previousXrefOffset . ' /Index [4 1] /W [1 0 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'fails closed on zero-width xref object-stream carrier fields before WordPress text extraction' => static function (TestRunner $t) use ($xrefObjectStreamZeroWidthCarrierCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamZeroWidthCarrierCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];

        $expected = [
            'Current zero-width carrier guard page',
            'Invalid carrier row stays review-only',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale zero-width carrier page leak'));
        $t->true(!str_contains($text, 'Default object stream zero must fail closed'));
        $t->true(!str_contains($text, 'stale direct page must be suppressed'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(1, $review['zero_width_object_stream_entry_count'] ?? null);
        $t->same(1, $review['unresolved_object_stream_carrier_count'] ?? null);
        $t->same(4, $entry['object_number'] ?? null);
        $t->same(0, $entry['object_stream'] ?? null);
        $t->same(false, $entry['object_stream_field_is_explicit'] ?? null);
        $t->same(true, $entry['object_stream_field_is_zero_width'] ?? null);
        $t->same(false, $entry['object_stream_carrier_resolved'] ?? null);
        $t->same(true, $entry['invalid_object_stream_carrier_rejected'] ?? null);
        $t->same('missing_object_stream_carrier', $entry['object_stream_owner_policy'] ?? null);
        $t->same('missing_object_stream_member', $entry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
