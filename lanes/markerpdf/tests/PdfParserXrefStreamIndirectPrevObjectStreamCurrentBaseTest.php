<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefStreamIndirectPrevObjectStreamCurrentBasePdf = static function (): string {
    $content = 'BT /F1 12 Tf 72 720 Td (Prev indirect compressed object stream page) Tj T* (Object stream prev helper selected) Tj ET';

    $objectStream = static function (array $members, array &$memberIndexes): array {
        $headerPairs = [];
        $memberIndexes = [];
        $objectData = '';
        foreach ($members as $objectNumber => $body) {
            $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
            $memberIndexes[$objectNumber] = count($memberIndexes);
            $objectData .= $body . "\n";
        }

        $header = implode(' ', $headerPairs);
        $plain = $header . "\n" . $objectData;
        $compressed = gzcompress($plain);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress indirect Prev object-stream fixture.');
        }

        return [$header, $compressed];
    };

    $prevMemberIndexes = [];
    [$prevHeader, $prevCompressedObjectStream] = $objectStream([
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    ], $prevMemberIndexes);

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($prevMemberIndexes) . ' /First ' . (strlen($prevHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($prevCompressedObjectStream) . " >>\nstream\n{$prevCompressedObjectStream}\nendstream");
    $addObject(10, 0, '<< /Type /Font /Subtype /Type3 /CharProcs << /A 5 0 R >> /Encoding << /Type /Encoding /Differences [65 /A] >> /FirstChar 65 /LastChar 65 /Widths [500] >>');

    $prevXrefRows = ''
        . $xrefRow(2, 6, $prevMemberIndexes[1])
        . $xrefRow(2, 6, $prevMemberIndexes[2])
        . $xrefRow(2, 6, $prevMemberIndexes[3])
        . $xrefRow(2, 6, $prevMemberIndexes[4])
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(1, $offsets['10:0']);
    $prevCompressedXref = gzcompress($prevXrefRows);
    if (!is_string($prevCompressedXref)) {
        throw new RuntimeException('Unable to compress previous xref-stream fixture.');
    }

    $prevXrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 10 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($prevCompressedXref) . " >>\n"
        . "stream\n{$prevCompressedXref}\nendstream\nendobj\n";

    $helperMemberIndexes = [];
    [$helperHeader, $helperCompressedObjectStream] = $objectStream([
        30 => (string) $prevXrefOffset,
    ], $helperMemberIndexes);
    $addObject(7, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($helperHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($helperCompressedObjectStream) . " >>\nstream\n{$helperCompressedObjectStream}\nendstream");

    $currentXrefRows = ''
        . $xrefRow(1, $offsets['7:0'])
        . $xrefRow(2, 7, $helperMemberIndexes[30]);
    $currentCompressedXref = gzcompress($currentXrefRows);
    if (!is_string($currentCompressedXref)) {
        throw new RuntimeException('Unable to compress current xref-stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "40 0 obj\n"
        . '<< /Type /XRef /Size 41 /Root 1 0 R /Index [7 1 30 1] /W [1 4 1] /Prev 30 0 R /Filter /FlateDecode /Length ' . strlen($currentCompressedXref) . " >>\n"
        . "stream\n{$currentCompressedXref}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'resolves xref-stream indirect Prev offsets from compressed helper object streams' => static function (TestRunner $t) use ($parserXrefStreamIndirectPrevObjectStreamCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserXrefStreamIndirectPrevObjectStreamCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');

        $expected = [
            'Prev indirect compressed object stream page',
            'Object stream prev helper selected',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'CharProcs'));
        $t->true(!str_contains($text, 'startxref'));
        $t->true(!str_contains($text, '30 0 R'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(5, $review['compressed_entry_count']);
        foreach ([1, 2, 3, 4] as $objectNumber) {
            $entry = $entries[$objectNumber] ?? [];
            $t->same($objectNumber, $entry['object_number'] ?? null);
            $t->same(6, $entry['object_stream'] ?? null);
            $t->same('xref_selected_object_stream_carrier', $entry['object_stream_owner_policy'] ?? null);
            $t->same('explicit_member_index', $entry['selection_policy'] ?? null);
        }

        $helperEntry = $entries[30] ?? [];
        $t->same(30, $helperEntry['object_number'] ?? null);
        $t->same(7, $helperEntry['object_stream'] ?? null);
        $t->same('xref_selected_object_stream_carrier', $helperEntry['object_stream_owner_policy'] ?? null);
        $t->same('explicit_member_index', $helperEntry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
