<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamHybridGenerationOwnerCurrentBasePdf = static function (): string {
    $staleContent = 'BT /F1 12 Tf 72 720 Td (Stale hybrid carrier generation zero page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current hybrid carrier generation page) Tj T* (Object stream owner generation one) Tj ET';

    $objectStream = static function (array $members, array &$memberIndexes): array {
        $objectData = '';
        $headerPairs = [];
        $memberIndexes = [];
        foreach ($members as $objectNumber => $body) {
            $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
            $memberIndexes[$objectNumber] = count($memberIndexes);
            $objectData .= $body . "\n";
        }

        $header = implode(' ', $headerPairs);
        $compressed = gzcompress($header . "\n" . $objectData);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress hybrid generation-owner object stream.');
        }

        return [$header, $compressed];
    };

    $staleMemberIndexes = [];
    $currentMemberIndexes = [];
    [$staleHeader, $staleObjectStream] = $objectStream([
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (stale generation zero carrier member) >>',
    ], $staleMemberIndexes);
    [$currentHeader, $currentObjectStream] = $objectStream([
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R /Note (current generation one carrier member) >>',
    ], $currentMemberIndexes);

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefTableRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
    $xrefStreamRow = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($staleContent) . " >>\nstream\n{$staleContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($staleHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($staleObjectStream) . " >>\nstream\n{$staleObjectStream}\nendstream");
    $addObject(6, 1, '<< /Type /ObjStm /N 1 /First ' . (strlen($currentHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($currentObjectStream) . " >>\nstream\n{$currentObjectStream}\nendstream");
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $hybridRows = $xrefStreamRow(2, 6, $currentMemberIndexes[4]);
    $hybridXrefStream = gzcompress($hybridRows);
    if (!is_string($hybridXrefStream)) {
        throw new RuntimeException('Unable to compress hybrid generation-owner xref stream.');
    }
    $hybridXrefOffset = $addObject(
        7,
        0,
        '<< /Type /XRef /Size 10 /Index [4 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($hybridXrefStream) . " >>\nstream\n{$hybridXrefStream}\nendstream"
    );

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n"
        . "0 4\n"
        . $xrefTableRow(0, 65535, 'f')
        . $xrefTableRow($offsets['1:0'])
        . $xrefTableRow($offsets['2:0'])
        . $xrefTableRow($offsets['3:0'])
        . "5 3\n"
        . $xrefTableRow($offsets['5:0'])
        . $xrefTableRow($offsets['6:1'], 1)
        . $xrefTableRow($hybridXrefOffset)
        . "9 1\n"
        . $xrefTableRow($offsets['9:0'])
        . "trailer\n<< /Size 10 /Root 1 0 R /XRefStm {$hybridXrefOffset} >>\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'uses the hybrid xref table carrier generation owner before expanding companion object-stream members' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamHybridGenerationOwnerCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamHybridGenerationOwnerCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entry = $review['entries'][0] ?? [];

        $expected = ['Current hybrid carrier generation page', 'Object stream owner generation one'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale hybrid carrier generation zero page'));
        $t->true(!str_contains($text, 'stale generation zero carrier member'));
        $t->true(!str_contains($text, 'current generation one carrier member'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(4, $entry['object_number'] ?? null);
        $t->same(6, $entry['object_stream'] ?? null);
        $t->same(0, $entry['xref_member_index'] ?? null);
        $t->same('explicit_member_index', $entry['selection_policy'] ?? null);
        $t->same(1, $entry['object_stream_selected_generation'] ?? null);
        $t->same(1, $entry['object_stream_xref_generation'] ?? null);
        $t->same(1, $entry['object_stream_xref_entry_type'] ?? null);
        $t->same('xref_selected_object_stream_carrier', $entry['object_stream_owner_policy'] ?? null);
        $t->same('compressed_generation_zero_member', $entry['generation_boundary_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
