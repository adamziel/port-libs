<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamIndexZeroWidthMemberReviewPdf = static function (): string {
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current zero-width member page) Tj T* (Recovered by header object number) Tj ET';

    $members = [
        12 => '<< /Type /Page /Parent 2 0 R /Note (strict zero member decoy) >>',
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R >>',
    ];
    $objectData = '';
    $headerPairs = [];
    foreach ($members as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $objectData .= $body . "\n";
    }
    $header = implode(' ', $headerPairs);
    $objectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress zero-width member object-stream fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($members) . ' /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $xrefRows = ''
        . chr(1) . pack('N', $offsets['1:0'])
        . chr(1) . pack('N', $offsets['2:0'])
        . chr(1) . pack('N', $offsets['3:0'])
        . chr(2) . pack('N', 6)
        . chr(1) . pack('N', $offsets['5:0'])
        . chr(1) . pack('N', $offsets['6:0'])
        . chr(2) . pack('N', 6);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress zero-width member xref-stream fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 12 1] /W [1 4 0] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'reviews zero-width xref object-stream member indexes recovered for current-base import' => static function (TestRunner $t) use ($xrefObjectStreamIndexZeroWidthMemberReviewPdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamIndexZeroWidthMemberReviewPdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');

        $t->same(['Current zero-width member page', 'Recovered by header object number'], $extractor->extractTextLines($pdf));
        $t->same(['Current zero-width member page', 'Recovered by header object number'], $extractor->extractTextRuns($pdf));
        $t->same("Current zero-width member page\nRecovered by header object number", $text);
        $t->same("Current zero-width member page\nRecovered by header object number\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'strict zero member decoy'));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(2, $review['compressed_entry_count']);
        $t->same(2, $review['zero_width_index_entry_count']);
        $t->same(1, $review['recovered_zero_width_member_count']);
        $t->same(1, $review['strict_dependency_rejection_count']);
        $t->same('default_zero_member_index', $entries[12]['selection_policy']);
        $t->same(0, $entries[12]['actual_member_index']);
        $t->true($entries[12]['strict_member_match']);
        $t->same('recovered_by_header_object_number', $entries[4]['selection_policy']);
        $t->same(0, $entries[4]['xref_member_index']);
        $t->same(1, $entries[4]['actual_member_index']);
        $t->true($entries[4]['index_field_is_zero_width']);
        $t->true($entries[4]['recovered_by_object_number']);
        $t->true($entries[4]['strict_dependency_would_reject']);
        $t->true($review['review_only']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
