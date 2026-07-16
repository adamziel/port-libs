<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefStreamIndirectPrevOffsetRepairCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale indirect Prev offset repair page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current indirect Prev offset repair page) Tj T* (Compressed Prev helper repaired current rows) Tj ET';

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
            throw new RuntimeException('Unable to compress indirect Prev offset-repair object stream fixture.');
        }

        return [$header, $compressed];
    };

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $classicRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $previousXrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 6\n"
        . $classicRow(0, 65535, 'f')
        . $classicRow($offsets['1:0'])
        . $classicRow($offsets['2:0'])
        . $classicRow($offsets['3:0'])
        . $classicRow($offsets['4:0'])
        . $classicRow($offsets['5:0'])
        . "trailer\n<< /Size 6 /Root 1 0 R >>\n"
        . "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R /Note (current catalog after Prev) >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $helperMemberIndexes = [];
    [$helperHeader, $helperCompressed] = $objectStream([
        9000 => (string) $previousXrefOffset,
    ], $helperMemberIndexes);
    $addObject(7, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($helperHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($helperCompressed) . " >>\nstream\n{$helperCompressed}\nendstream");
    $addObject(10, 0, '<< /Type /Font /Subtype /Type3 /CharProcs << /A 5 0 R >> /Encoding << /Type /Encoding /Differences [65 /A] >> /FirstChar 65 /LastChar 65 /Widths [500] >>');

    $currentXrefRows = ''
        . $xrefRow(1, 0, 0)
        . $xrefRow(1, 0, 0)
        . $xrefRow(1, 0, 0)
        . $xrefRow(1, $offsets['4:0'], 0)
        . $xrefRow(1, 0, 0)
        . $xrefRow(1, $offsets['7:0'], 0)
        . $xrefRow(1, $offsets['10:0'], 0)
        . $xrefRow(2, 7, $helperMemberIndexes[9000]);
    $compressedCurrentXref = gzcompress($currentXrefRows);
    if (!is_string($compressedCurrentXref)) {
        throw new RuntimeException('Unable to compress current indirect Prev offset-repair xref stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "40 0 obj\n"
        . '<< /Type /XRef /Size 9001 /Root 1 0 R /Index [1 7 9000 1] /W [1 4 1] /Prev 9000 0 R /Filter /FlateDecode /Length ' . strlen($compressedCurrentXref) . " >>\n"
        . "stream\n{$compressedCurrentXref}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'repairs damaged current xref-stream offsets after resolving indirect Prev from compressed helper objects' => static function (
        TestRunner $t
    ) use ($parserXrefStreamIndirectPrevOffsetRepairCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserXrefStreamIndirectPrevOffsetRepairCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');

        $expected = [
            'Current indirect Prev offset repair page',
            'Compressed Prev helper repaired current rows',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale indirect Prev offset repair page'));
        $t->true(!str_contains($text, 'CharProcs'));
        $t->true(!str_contains($text, 'current catalog after Prev'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $helperEntry = $entries[9000] ?? [];
        $t->same(9000, $helperEntry['object_number'] ?? null);
        $t->same(7, $helperEntry['object_stream'] ?? null);
        $t->same('explicit_member_index', $helperEntry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
