<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefStreamNestedHelperObjectStreamCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current nested xref helper page) Tj T* (Nested compressed helper references selected) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale nested xref helper leak) Tj T* (Unresolved helper fallback) Tj ET';

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
            throw new RuntimeException('Unable to compress nested helper object stream fixture.');
        }

        return [$header, $compressed];
    };

    $currentMemberIndexes = [];
    [$currentHeader, $currentCompressedObjectStream] = $objectStream([
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
    ], $currentMemberIndexes);

    $xrefOperandReferenceIndexes = [];
    [$xrefOperandReferenceHeader, $xrefOperandReferenceStream] = $objectStream([
        30 => "33 % nested /W helper reference split by a PDF comment\n0 R",
        31 => "34 % nested /Index helper reference split by a PDF comment\n0 R",
    ], $xrefOperandReferenceIndexes);

    $xrefOperandValueIndexes = [];
    [$xrefOperandValueHeader, $xrefOperandValueStream] = $objectStream([
        33 => '[1 4 1]',
        34 => '[1 6 9 1 30 2]',
    ], $xrefOperandValueIndexes);

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
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R /Note (stale direct page fallback) >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($currentHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($currentCompressedObjectStream) . " >>\nstream\n{$currentCompressedObjectStream}\nendstream");
    $addObject(7, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($xrefOperandReferenceHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($xrefOperandReferenceStream) . " >>\nstream\n{$xrefOperandReferenceStream}\nendstream");
    $addObject(8, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($xrefOperandValueHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($xrefOperandValueStream) . " >>\nstream\n{$xrefOperandValueStream}\nendstream");
    $addObject(9, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(2, 6, $currentMemberIndexes[4])
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(1, $offsets['9:0'])
        . $xrefRow(2, 7, $xrefOperandReferenceIndexes[30])
        . $xrefRow(2, 7, $xrefOperandReferenceIndexes[31]);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress nested helper xref stream.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 35 /Root 1 0 R /Index 31 0 R /W 30 0 R /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'resolves xref-stream W and Index arrays through nested compressed helper references' => static function (
        TestRunner $t
    ) use ($parserXrefStreamNestedHelperObjectStreamCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserXrefStreamNestedHelperObjectStreamCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];

        $expected = ['Current nested xref helper page', 'Nested compressed helper references selected'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale nested xref helper leak'));
        $t->true(!str_contains($text, 'Unresolved helper fallback'));
        $t->true(!str_contains($text, 'stale direct page fallback'));
        $t->true(!str_contains($text, "\0"));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(3, $review['compressed_entry_count']);
        $t->same(4, $entry['object_number'] ?? null);
        $t->same(6, $entry['object_stream'] ?? null);
        $t->same('xref_selected_object_stream_carrier', $entry['object_stream_owner_policy'] ?? null);
        $t->same('explicit_member_index', $entry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
