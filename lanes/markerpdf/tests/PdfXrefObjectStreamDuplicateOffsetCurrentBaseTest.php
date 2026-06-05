<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamDuplicateOffsetCurrentBasePdf = static function (): string {
    $directContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate-offset guard page) Tj ET';
    $duplicateContent = 'BT /F1 12 Tf 72 700 Td (Duplicate offset compressed page leak) Tj T* (Shared member body rejected) Tj ET';

    $compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (duplicate-offset member page) >>';
    $offsetDecoy = '<< /Type /Page /Parent 2 0 R /Note (later offset delimiter only) >>';
    $objectData = $compressedPage . "\n" . $offsetDecoy . "\n";
    $header = '4 0 12 0 13 ' . strlen($compressedPage . "\n");
    $objectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress duplicate-offset object-stream fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [8 0 R 4 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($duplicateContent) . " >>\nstream\n{$duplicateContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 3 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($directContent) . " >>\nstream\n{$directContent}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(2, 6, 0)
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(1, $offsets['8:0'])
        . $xrefRow(1, $offsets['9:0'])
        . $xrefRow(2, 6, 1);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress duplicate-offset xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 2 12 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects duplicate object-stream member offsets before WordPress text extraction' => static function (TestRunner $t) use ($xrefObjectStreamDuplicateOffsetCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamDuplicateOffsetCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];

        $t->same(['Current duplicate-offset guard page'], $extractor->extractTextLines($pdf));
        $t->same(['Current duplicate-offset guard page'], $extractor->extractTextRuns($pdf));
        $t->same('Current duplicate-offset guard page', $text);
        $t->same("Current duplicate-offset guard page\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Duplicate offset compressed page leak'));
        $t->true(!str_contains($text, 'Shared member body rejected'));
        $t->true(!str_contains($text, 'duplicate-offset member page'));
        $t->true(!str_contains($text, 'later offset delimiter only'));
        $t->true(!str_contains($text, "\0"));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(2, $review['compressed_entry_count']);
        $t->same(2, $review['duplicate_member_offset_rejection_count'] ?? null);
        $t->same('duplicate_object_stream_member_offset', $entry['selection_policy'] ?? null);
        $t->same(2, $entry['matching_header_offset_count'] ?? null);
        $t->same(true, $entry['duplicate_member_offset'] ?? null);
        $t->same(true, $entry['duplicate_member_offset_rejected'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
