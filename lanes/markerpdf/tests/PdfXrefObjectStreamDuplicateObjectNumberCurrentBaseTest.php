<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamDuplicateObjectNumberCurrentBasePdf = static function (): string {
    $guardContent = 'BT /F1 12 Tf 72 720 Td (Current duplicate-object guard page) Tj ET';
    $firstDuplicateContent = 'BT /F1 12 Tf 72 700 Td (Duplicate object first member leak) Tj ET';
    $secondDuplicateContent = 'BT /F1 12 Tf 72 680 Td (Duplicate object selected member leak) Tj ET';

    $firstDuplicatePage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>';
    $secondDuplicatePage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R >>';
    $objectData = $firstDuplicatePage . "\n" . $secondDuplicatePage . "\n";
    $secondOffset = strlen($firstDuplicatePage . "\n");
    $header = '4 0 4 ' . $secondOffset;
    $objectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress duplicate-object-number object stream fixture.');
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
    $addObject(5, 0, "<< /Length " . strlen($firstDuplicateContent) . " >>\nstream\n{$firstDuplicateContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($guardContent) . " >>\nstream\n{$guardContent}\nendstream");
    $addObject(10, 0, "<< /Length " . strlen($secondDuplicateContent) . " >>\nstream\n{$secondDuplicateContent}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(2, 6, 1)
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(1, $offsets['8:0'])
        . $xrefRow(1, $offsets['9:0'])
        . $xrefRow(1, $offsets['10:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress duplicate-object-number xref stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 3] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects duplicate object numbers in xref-selected object stream headers' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamDuplicateObjectNumberCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamDuplicateObjectNumberCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];

        $t->same(['Current duplicate-object guard page'], $extractor->extractTextLines($pdf));
        $t->same(['Current duplicate-object guard page'], $extractor->extractTextRuns($pdf));
        $t->same('Current duplicate-object guard page', $text);
        $t->same("Current duplicate-object guard page\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Duplicate object first member leak'));
        $t->true(!str_contains($text, 'Duplicate object selected member leak'));
        $t->true(!str_contains($text, "\0"));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(1, $review['duplicate_header_object_number_rejection_count'] ?? null);
        $t->same(2, $entry['matching_header_object_number_count'] ?? null);
        $t->same(true, $entry['duplicate_header_object_number'] ?? null);
        $t->same(true, $entry['duplicate_header_object_number_rejected'] ?? null);
        $t->same(1, $entry['xref_member_index'] ?? null);
        $t->true(array_key_exists('actual_member_index', $entry));
        $t->same(null, $entry['actual_member_index']);
        $t->same('duplicate_header_object_number', $entry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
