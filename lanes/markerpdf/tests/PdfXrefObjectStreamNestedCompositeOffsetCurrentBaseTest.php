<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamNestedCompositeOffsetCurrentBasePdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current nested-composite guard page) Tj ET';
    $leakContent = 'BT /F1 12 Tf 72 700 Td (Nested object-stream dictionary leak) Tj T* (Composite member offset ignored) Tj ET';

    $memberWithNestedPage = '<< /Type /Metadata /Private << /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (nested object-stream page decoy) >> /Tail (still inside metadata) >>';
    $nestedPageOffset = strpos($memberWithNestedPage, '<< /Type /Page');
    if ($nestedPageOffset === false) {
        throw new RuntimeException('Unable to locate nested object-stream page dictionary.');
    }

    $header = '12 0 4 ' . $nestedPageOffset;
    $objectStreamPlain = $header . "\n" . $memberWithNestedPage . "\n";
    $objectStream = gzcompress($objectStreamPlain);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress nested-composite object stream fixture.');
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
    $addObject(5, 0, "<< /Length " . strlen($leakContent) . " >>\nstream\n{$leakContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(2, 6, 1)
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(1, $offsets['8:0'])
        . $xrefRow(1, $offsets['9:0']);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress nested-composite xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects object-stream member offsets inside nested dictionaries before WordPress text extraction' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamNestedCompositeOffsetCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamNestedCompositeOffsetCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];

        $t->same(['Current nested-composite guard page'], $extractor->extractTextLines($pdf));
        $t->same(['Current nested-composite guard page'], $extractor->extractTextRuns($pdf));
        $t->same('Current nested-composite guard page', $text);
        $t->same("Current nested-composite guard page\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Nested object-stream dictionary leak'));
        $t->true(!str_contains($text, 'Composite member offset ignored'));
        $t->true(!str_contains($text, 'nested object-stream page decoy'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(1, $review['invalid_member_offset_rejection_count']);
        $t->same(0, $review['strict_dependency_rejection_count']);
        $t->same(4, $entry['object_number'] ?? null);
        $t->same(6, $entry['object_stream'] ?? null);
        $t->same(1, $entry['xref_member_index'] ?? null);
        $t->same(2, $entry['object_stream_member_count'] ?? null);
        $t->same(1, $entry['matching_header_object_number_count'] ?? null);
        $t->same(false, $entry['member_offset_token_boundary'] ?? null);
        $t->true($entry['invalid_member_offset_rejected'] ?? false);
        $t->same(false, $entry['strict_dependency_would_reject'] ?? null);
        $t->same('invalid_object_stream_member_offset', $entry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
