<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamGenerationBoundaryCurrentBasePdf = static function (): string {
    $staleCompressedContent = 'BT /F1 12 Tf 72 720 Td (Stale generation zero compressed page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current nonzero generation boundary page) Tj T* (Compressed member generation zero skipped) Tj ET';

    $memberBody = '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (compressed generation zero page object) >>';
    $header = '4 0';
    $objectStream = gzcompress($header . "\n" . $memberBody . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress object-stream generation-boundary fixture.');
    }

    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $row = static fn (int $type, int $fieldTwo, int $fieldThree): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [4 1 R 8 0 R] /Count 2 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($staleCompressedContent) . " >>\nstream\n{$staleCompressedContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(8, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 9 0 R >>');
    $addObject(9, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $xrefRows = ''
        . $row(1, $offsets['1:0'], 0)
        . $row(1, $offsets['2:0'], 0)
        . $row(1, $offsets['3:0'], 0)
        . $row(2, 6, 0)
        . $row(1, $offsets['5:0'], 0)
        . $row(1, $offsets['6:0'], 0)
        . $row(1, $offsets['8:0'], 0)
        . $row(1, $offsets['9:0'], 0);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress xref generation-boundary fixture.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6 8 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'keeps object-stream generation-zero members from satisfying nonzero page references' => static function (TestRunner $t) use ($xrefObjectStreamGenerationBoundaryCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamGenerationBoundaryCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];

        $t->same(['Current nonzero generation boundary page', 'Compressed member generation zero skipped'], $extractor->extractTextLines($pdf));
        $t->same(['Current nonzero generation boundary page', 'Compressed member generation zero skipped'], $extractor->extractTextRuns($pdf));
        $t->same("Current nonzero generation boundary page\nCompressed member generation zero skipped", $text);
        $t->same("Current nonzero generation boundary page\nCompressed member generation zero skipped\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale generation zero compressed page'));
        $t->true(!str_contains($text, 'compressed generation zero page object'));
        $t->true(!str_contains($text, "\0"));
        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(1, $review['compressed_entry_count']);
        $t->same(1, $review['nonzero_generation_reference_count']);
        $t->same(1, $review['compressed_generation_zero_boundary_count']);
        $t->same(0, $entry['compressed_member_generation'] ?? null);
        $t->same(0, $entry['selected_object_generation'] ?? null);
        $t->same([1], $entry['nonzero_referenced_generations'] ?? null);
        $t->true($entry['has_nonzero_generation_reference'] ?? false);
        $t->same('compressed_generation_zero_not_selected_for_nonzero_reference', $entry['generation_boundary_policy'] ?? null);
        $t->same('explicit_member_index', $entry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
