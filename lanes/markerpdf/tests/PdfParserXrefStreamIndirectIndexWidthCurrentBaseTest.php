<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefStreamIndirectIndexWidthCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current indirect xref array page) Tj T* (Indirect W Index selected) Tj ET';
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale indirect xref array leak) Tj T* (Fallback object stream expanded) Tj ET';

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
            throw new RuntimeException('Unable to compress indirect xref array object-stream fixture.');
        }

        return [$header, $compressed];
    };

    $currentMemberIndexes = [];
    $staleMemberIndexes = [];
    [$currentHeader, $currentCompressedObjectStream] = $objectStream([
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
    ], $currentMemberIndexes);
    [$staleHeader, $staleCompressedObjectStream] = $objectStream([
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R /Note (stale fallback object stream page) >>',
    ], $staleMemberIndexes);

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $row = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($currentHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($currentCompressedObjectStream) . " >>\nstream\n{$currentCompressedObjectStream}\nendstream");
    $addObject(7, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($staleHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($staleCompressedObjectStream) . " >>\nstream\n{$staleCompressedObjectStream}\nendstream");
    $addObject(9, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");

    $wOffset = $addObject(30, 0, '[1 4 1]');
    $indexOffset = $addObject(31, 0, '[1 6 9 1 30 3]');
    $sizeOffset = $addObject(32, 0, '33');

    $xrefRows = ''
        . $row(1, $offsets['1:0'])
        . $row(1, $offsets['2:0'])
        . $row(1, $offsets['3:0'])
        . $row(2, 6, $currentMemberIndexes[4])
        . $row(1, $offsets['5:0'])
        . $row(1, $offsets['6:0'])
        . $row(1, $offsets['9:0'])
        . $row(1, $wOffset)
        . $row(1, $indexOffset)
        . $row(1, $sizeOffset);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress indirect W/Index xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 32 0 R /Root 1 0 R /Index 31 0 R /W 30 0 R /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'resolves indirect xref-stream W and Index arrays before object-stream current-base selection' => static function (TestRunner $t) use ($parserXrefStreamIndirectIndexWidthCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserXrefStreamIndirectIndexWidthCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];

        $expected = ['Current indirect xref array page', 'Indirect W Index selected'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale indirect xref array leak'));
        $t->true(!str_contains($text, 'Fallback object stream expanded'));
        $t->true(!str_contains($text, 'stale fallback object stream page'));
        $t->true(!str_contains($text, "\0"));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(4, $entry['object_number'] ?? null);
        $t->same(6, $entry['object_stream'] ?? null);
        $t->same('xref_selected_object_stream_carrier', $entry['object_stream_owner_policy'] ?? null);
        $t->same('explicit_member_index', $entry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
