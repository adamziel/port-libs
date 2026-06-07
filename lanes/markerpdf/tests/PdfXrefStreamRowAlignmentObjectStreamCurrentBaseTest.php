<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefStreamRowAlignmentObjectStreamCurrentBasePdf = static function (): string {
    $leakingContent = 'BT /F1 12 Tf 72 720 Td (Malformed row-alignment object-stream page leak) Tj T* (Trailing xref byte ignored) Tj ET';

    $members = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [4 0 R] /Count 1 >>',
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
    ];
    $memberIndexes = [];
    $headerPairs = [];
    $objectData = '';
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }
    $header = implode(' ', $headerPairs);
    $objectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress row-alignment object stream fixture.');
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

    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($leakingContent) . " >>\nstream\n{$leakingContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $xrefRows = ''
        . $xrefRow(2, 6, $memberIndexes[1])
        . $xrefRow(2, 6, $memberIndexes[2])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(2, 6, $memberIndexes[4])
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . 'X';
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress unaligned xref stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'fails closed on unaligned xref-stream rows before object-stream expansion' => static function (
        TestRunner $t
    ) use ($xrefStreamRowAlignmentObjectStreamCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefStreamRowAlignmentObjectStreamCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $alignmentEntry = $review['malformed_xref_stream_row_alignment_entries'][0] ?? [];

        $t->same([], $extractor->extractTextLines($pdf));
        $t->same([], $extractor->extractTextRuns($pdf));
        $t->same('', $text);
        $t->same('', $extractor->naiveGetText($pdf));
        $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same([], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Malformed row-alignment object-stream page leak'));
        $t->true(!str_contains($text, 'Trailing xref byte ignored'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(0, $review['compressed_entry_count']);
        $t->same(1, $review['malformed_xref_stream_row_alignment_count'] ?? null);
        $t->same('unaligned_xref_stream_row_width', $alignmentEntry['owner_policy'] ?? null);
        $t->same(37, $alignmentEntry['decoded_length'] ?? null);
        $t->same(6, $alignmentEntry['entry_width'] ?? null);
        $t->same(6, $alignmentEntry['complete_row_count'] ?? null);
        $t->same(1, $alignmentEntry['trailing_byte_count'] ?? null);
        $t->same(true, $alignmentEntry['rejected_before_row_decode'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
