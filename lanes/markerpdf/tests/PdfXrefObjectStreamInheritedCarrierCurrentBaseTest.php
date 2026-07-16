<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamInheritedCarrierCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current inherited carrier page) Tj T* (Previous carrier row reused) Tj ET';
    $staleFallbackContent = 'BT /F1 12 Tf 72 720 Td (Stale direct fallback leak) Tj ET';

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
        $compressed = gzcompress($header . "\n" . $objectData);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress inherited-carrier object stream fixture.');
        }

        return [$header, $compressed];
    };

    $memberIndexes = [];
    [$header, $compressedObjectStream] = $objectStream([
        4 => '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R /Note (compressed page selected through inherited carrier row) >>',
    ], $memberIndexes);

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
    $addObject(2, 0, '<< /Type /Pages /Kids [8 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($staleFallbackContent) . " >>\nstream\n{$staleFallbackContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($compressedObjectStream) . " >>\nstream\n{$compressedObjectStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');

    $previousRows = ''
        . $row(1, $offsets['1:0'], 0)
        . $row(1, $offsets['2:0'], 0)
        . $row(1, $offsets['3:0'], 0)
        . $row(1, $offsets['5:0'], 0)
        . $row(1, $offsets['6:0'], 0)
        . $row(1, $offsets['8:0'], 0);
    $previousCompressedXref = gzcompress($previousRows);
    if (!is_string($previousCompressedXref)) {
        throw new RuntimeException('Unable to compress inherited-carrier previous xref stream fixture.');
    }
    $previousXrefOffset = $addObject(
        20,
        0,
        '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 3 5 2 8 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousCompressedXref) . " >>\nstream\n{$previousCompressedXref}\nendstream"
    );
    $pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentRows = ''
        . $row(1, $offsets['1:1'], 1)
        . $row(1, $offsets['2:1'], 1)
        . $row(2, 6, $memberIndexes[4])
        . $row(1, $offsets['9:0'], 0);
    $currentCompressedXref = gzcompress($currentRows);
    if (!is_string($currentCompressedXref)) {
        throw new RuntimeException('Unable to compress inherited-carrier current xref stream fixture.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 2 4 1 9 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentCompressedXref) . " >>\n"
        . "stream\n{$currentCompressedXref}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'reuses inherited direct object-stream carrier rows for current xref type-2 members' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamInheritedCarrierCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamInheritedCarrierCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];

        $expected = [
            'Current inherited carrier page',
            'Previous carrier row reused',
        ];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same(implode("\n", $expected), $text);
        $t->same(implode("\n", $expected) . "\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale direct fallback leak'));
        $t->true(!str_contains($text, 'compressed page selected through inherited carrier row'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(4, $entry['object_number'] ?? null);
        $t->same(6, $entry['object_stream'] ?? null);
        $t->same(1, $entry['object_stream_xref_entry_type'] ?? null);
        $t->true(($entry['object_stream_xref_offset'] ?? 0) > 0);
        $t->same(true, $entry['object_stream_entry_inherited_from_prev'] ?? null);
        $t->true(($entry['object_stream_inherited_xref_offset'] ?? 0) > 0);
        $t->same('xref_selected_object_stream_carrier', $entry['object_stream_owner_policy'] ?? null);
        $t->same('explicit_member_index', $entry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
