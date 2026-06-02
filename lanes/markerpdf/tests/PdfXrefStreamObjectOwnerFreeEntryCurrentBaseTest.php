<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefStreamObjectOwnerFreeEntryCurrentBasePdf = static function (): string {
    $staleDirectContent = 'BT /F1 12 Tf 72 720 Td (Stale direct free-owner page) Tj ET';
    $staleCompressedContent = 'BT /F1 12 Tf 72 700 Td (Stale compressed free-owner page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current xref stream free owner page) Tj T* (Free row owns stale object) Tj ET';

    $members = [
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 10 0 R /Note (previous compressed free-owner page member) >>',
    ];
    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }
    $header = implode(' ', $headerPairs);
    $objectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress xref-stream free-owner object-stream fixture.');
    }

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
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (scanned direct free-owner page) >>');
    $addObject(5, 0, "<< /Length " . strlen($staleDirectContent) . " >>\nstream\n{$staleDirectContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(10, 0, "<< /Length " . strlen($staleCompressedContent) . " >>\nstream\n{$staleCompressedContent}\nendstream");

    $previousRows = ''
        . $row(1, $offsets['1:0'], 0)
        . $row(1, $offsets['2:0'], 0)
        . $row(1, $offsets['3:0'], 0)
        . $row(2, 6, $memberIndexes[4])
        . $row(1, $offsets['5:0'], 0)
        . $row(1, $offsets['6:0'], 0)
        . $row(1, $offsets['10:0'], 0);
    $previousCompressed = gzcompress($previousRows);
    if (!is_string($previousCompressed)) {
        throw new RuntimeException('Unable to compress previous xref-stream free-owner fixture.');
    }
    $previousXrefOffset = $addObject(
        20,
        0,
        '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 10 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressed) . " >>\nstream\n{$previousCompressed}\nendstream"
    );
    $pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [4 0 R 8 0 R] /Count 2 >>');
    $addObject(8, 0, '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentRows = ''
        . $row(1, $offsets['1:1'], 1)
        . $row(1, $offsets['2:1'], 1)
        . $row(0, 0, 2)
        . $row(1, $offsets['8:0'], 0)
        . $row(1, $offsets['9:0'], 0);
    $currentCompressed = gzcompress($currentRows);
    if (!is_string($currentCompressed)) {
        throw new RuntimeException('Unable to compress current xref-stream free-owner fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 2 4 1 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressed) . " >>\n"
        . "stream\n{$currentCompressed}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'keeps current xref-stream free entries authoritative over stale direct and previous object-stream owners' => static function (TestRunner $t) use ($xrefStreamObjectOwnerFreeEntryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefStreamObjectOwnerFreeEntryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $freeEntry = $review['free_entries'][0] ?? [];

        $t->same(['Current xref stream free owner page', 'Free row owns stale object'], $extractor->extractTextLines($pdf));
        $t->same(['Current xref stream free owner page', 'Free row owns stale object'], $extractor->extractTextRuns($pdf));
        $t->same("Current xref stream free owner page\nFree row owns stale object", $text);
        $t->same("Current xref stream free owner page\nFree row owns stale object\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale direct free-owner page'));
        $t->true(!str_contains($text, 'Stale compressed free-owner page'));
        $t->true(!str_contains($text, 'previous compressed free-owner page member'));
        $t->true(!str_contains($text, 'scanned direct free-owner page'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(0, $review['compressed_entry_count']);
        $t->same(1, $review['xref_stream_free_entry_count']);
        $t->same(1, $review['xref_stream_free_owner_count']);
        $t->same(4, $freeEntry['object_number'] ?? null);
        $t->same(2, $freeEntry['free_generation'] ?? null);
        $t->same(0, $freeEntry['next_free_object'] ?? null);
        $t->same(1, $freeEntry['direct_definition_count'] ?? null);
        $t->true($freeEntry['direct_object_suppressed'] ?? false);
        $t->true($freeEntry['previous_entry_suppressed'] ?? false);
        $t->same(2, $freeEntry['previous_entry_type'] ?? null);
        $t->same(6, $freeEntry['previous_object_stream'] ?? null);
        $t->same(0, $freeEntry['previous_member_index'] ?? null);
        $t->same('xref_stream_free_entry_suppressed_prev_compressed_object', $freeEntry['owner_policy'] ?? null);
        $t->true($freeEntry['suppressed_by_free_entry'] ?? false);
        $t->true($freeEntry['review_only'] ?? false);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
