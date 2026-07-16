<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamMemberTailBoundaryCurrentBasePdf = static function (): string {
    $guardContent = 'BT /F1 12 Tf 72 720 Td (Current object-stream member-tail guard page) Tj ET';
    $malformedContent = 'BT /F1 12 Tf 72 700 Td (Malformed member-tail page leak) Tj T* (Trailing object-stream operand accepted) Tj ET';

    $malformedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >> endobj (tail operand leak)';
    $reviewMember = '<< /Type /Review /Note (valid later member after malformed tail) >>';
    $header = '4 0 12 ' . strlen($malformedPage . "\n");
    $objectStreamPlain = $header . "\n" . $malformedPage . "\n" . $reviewMember . "\n";
    $objectStream = gzcompress($objectStreamPlain);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress object-stream member-tail fixture.');
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
    $addObject(5, 0, "<< /Length " . strlen($malformedContent) . " >>\nstream\n{$malformedContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($guardContent) . " >>\nstream\n{$guardContent}\nendstream");

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
        throw new RuntimeException('Unable to compress object-stream member-tail xref stream.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 2 12 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects object-stream members with trailing top-level operands before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamMemberTailBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamMemberTailBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $malformedEntry = $entries[4] ?? [];
        $laterEntry = $entries[12] ?? [];

        $t->same(['Current object-stream member-tail guard page'], $extractor->extractTextLines($pdf));
        $t->same(['Current object-stream member-tail guard page'], $extractor->extractTextRuns($pdf));
        $t->same('Current object-stream member-tail guard page', $text);
        $t->same("Current object-stream member-tail guard page\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Malformed member-tail page leak'));
        $t->true(!str_contains($text, 'Trailing object-stream operand accepted'));
        $t->true(!str_contains($text, 'tail operand leak'));
        $t->true(!str_contains($text, 'valid later member after malformed tail'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(2, $review['compressed_entry_count']);
        $t->same(1, $review['malformed_object_stream_member_tail_count'] ?? null);
        $t->same(4, $malformedEntry['object_number'] ?? null);
        $t->same(6, $malformedEntry['object_stream'] ?? null);
        $t->same(0, $malformedEntry['xref_member_index'] ?? null);
        $t->same(true, $malformedEntry['member_offset_token_boundary'] ?? null);
        $t->same(false, $malformedEntry['object_stream_member_has_single_value'] ?? null);
        $t->same(true, $malformedEntry['malformed_member_tail_rejected'] ?? null);
        $t->same('explicit_member_index', $malformedEntry['selection_policy'] ?? null);
        $t->same(12, $laterEntry['object_number'] ?? null);
        $t->same(true, $laterEntry['object_stream_member_has_single_value'] ?? null);
        $t->same(false, $laterEntry['malformed_member_tail_rejected'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
