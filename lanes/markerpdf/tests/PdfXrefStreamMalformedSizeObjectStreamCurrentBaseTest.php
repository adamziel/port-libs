<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefStreamMalformedSizeObjectStreamCurrentBasePdf = static function (?string $sizeOperand): string {
    $directLeak = 'BT /F1 12 Tf 72 720 Td (Malformed Size direct fallback leak) Tj ET';
    $compressedLeak = 'BT /F1 12 Tf 72 700 Td (Malformed Size object-stream leak) Tj T* (Bad Size row range imported) Tj ET';

    $compressedPage = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>';
    $header = '7 0';
    $objectStream = gzcompress($header . "\n" . $compressedPage . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress malformed xref-size object-stream fixture.');
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
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R 7 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(4, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>');
    $addObject(5, 0, "<< /Length " . strlen($directLeak) . " >>\nstream\n{$directLeak}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(9, 0, "<< /Length " . strlen($compressedLeak) . " >>\nstream\n{$compressedLeak}\nendstream");

    $xrefRows = ''
        . $xrefRow(0, 0, 255)
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(1, $offsets['4:0'])
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(2, 6, 0)
        . $xrefRow(0, 0, 0)
        . $xrefRow(1, $offsets['9:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress malformed xref-size stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $sizeClause = $sizeOperand === null ? '' : ' /Size ' . $sizeOperand;
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Root 1 0 R' . $sizeClause . ' /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects malformed xref-stream Size without Index before object-stream fallback text' => static function (
        TestRunner $t
    ) use ($xrefStreamMalformedSizeObjectStreamCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $cases = [
            [
                'size_operand' => '/BadSize',
                'size_value' => '/BadSize',
                'resolved_size' => null,
                'owner_policy' => 'non_integer_xref_stream_size_without_index',
            ],
            [
                'size_operand' => null,
                'size_value' => null,
                'resolved_size' => null,
                'owner_policy' => 'missing_xref_stream_size_without_index',
            ],
            [
                'size_operand' => '-1',
                'size_value' => '-1',
                'resolved_size' => -1,
                'owner_policy' => 'negative_xref_stream_size_without_index',
            ],
        ];

        foreach ($cases as $case) {
            $pdf = $xrefStreamMalformedSizeObjectStreamCurrentBasePdf($case['size_operand']);
            $text = $extractor->extractPlainText($pdf);
            $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
            $sizeEntry = $review['malformed_xref_stream_size_entries'][0] ?? [];

            $t->same([], $extractor->extractTextLines($pdf));
            $t->same([], $extractor->extractTextRuns($pdf));
            $t->same('', $text);
            $t->same('', $extractor->naiveGetText($pdf));
            $t->same(0, $extractor->extractOutlineMetadata($pdf)['pages']);
            $t->same([], $extractor->extractPageLabels($pdf));
            $t->true(!str_contains($text, 'Malformed Size direct fallback leak'));
            $t->true(!str_contains($text, 'Malformed Size object-stream leak'));
            $t->true(!str_contains($text, 'Bad Size row range imported'));
            $t->true(!str_contains($text, "\0"));

            $t->same('pdf_xref_object_stream_index_review', $review['source']);
            $t->same(0, $review['compressed_entry_count']);
            $t->same(0, $review['malformed_xref_stream_width_count']);
            $t->same(0, $review['malformed_xref_stream_index_count']);
            $t->same(1, $review['malformed_xref_stream_size_count'] ?? null);
            $t->same($case['size_value'], $sizeEntry['size_value'] ?? null);
            $t->same($case['resolved_size'], $sizeEntry['resolved_size'] ?? null);
            $t->same($case['owner_policy'], $sizeEntry['owner_policy'] ?? null);
            $t->same(true, $sizeEntry['index_array_absent'] ?? null);
            $t->same(true, $sizeEntry['rejected_before_row_decode'] ?? null);
            $t->same(false, $review['executes_python_or_models']);
            $t->same(false, $review['executes_external_pdf_tools']);
        }
    },
];
