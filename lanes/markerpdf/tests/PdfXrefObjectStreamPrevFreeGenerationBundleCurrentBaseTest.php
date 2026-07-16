<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamPrevFreeGenerationBundleCurrentBasePdf = static function (): string {
    $staleMemberContent = 'BT /F1 12 Tf 72 720 Td (Stale bundled compressed member page) Tj ET';
    $currentContent = 'BT /F1 12 Tf 72 720 Td (Current bundled object stream page) Tj T* (Free generation member suppressed) Tj ET';

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
            throw new RuntimeException('Unable to compress bundled object-stream fixture.');
        }

        return [$header, $compressed];
    };

    $staleMemberIndexes = [];
    $currentMemberIndexes = [];
    [$staleHeader, $staleObjectStream] = $objectStream([
        4 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 3 0 R >> >> /Contents 5 0 R /Note (stale previous bundled member) >>',
    ], $staleMemberIndexes);
    [$currentHeader, $currentObjectStream] = $objectStream([
        10 => '<< /Type /Page /Parent 2 1 R /Resources << /Font << /F1 3 0 R >> >> /Contents 11 0 R /Note (current rebuilt bundled member) >>',
    ], $currentMemberIndexes);

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
    $addObject(2, 0, '<< /Type /Pages /Kids [4 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($staleMemberContent) . " >>\nstream\n{$staleMemberContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 1 /First ' . (strlen($staleHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($staleObjectStream) . " >>\nstream\n{$staleObjectStream}\nendstream");

    $previousRows = ''
        . $row(1, $offsets['1:0'], 0)
        . $row(1, $offsets['2:0'], 0)
        . $row(1, $offsets['3:0'], 0)
        . $row(2, 6, $staleMemberIndexes[4])
        . $row(1, $offsets['5:0'], 0)
        . $row(1, $offsets['6:0'], 0);
    $previousXrefStream = gzcompress($previousRows);
    if (!is_string($previousXrefStream)) {
        throw new RuntimeException('Unable to compress previous bundled xref stream.');
    }
    $previousXrefOffset = $addObject(
        20,
        0,
        '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 6] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($previousXrefStream) . " >>\nstream\n{$previousXrefStream}\nendstream"
    );
    $pdf .= "startxref\n{$previousXrefOffset}\n%%EOF\n";

    $addObject(1, 1, '<< /Type /Catalog /Pages 2 1 R >>');
    $addObject(2, 1, '<< /Type /Pages /Kids [10 0 R 4 0 R] /Count 2 >>');
    $addObject(6, 1, '<< /Type /ObjStm /N 1 /First ' . (strlen($currentHeader) + 1) . ' /Filter /FlateDecode /Length ' . strlen($currentObjectStream) . " >>\nstream\n{$currentObjectStream}\nendstream");
    $addObject(11, 0, "<< /Length " . strlen($currentContent) . " >>\nstream\n{$currentContent}\nendstream");

    $currentRows = ''
        . $row(1, $offsets['1:1'], 1)
        . $row(1, $offsets['2:1'], 1)
        . $row(0, 4, 1)
        . $row(2, 6, $currentMemberIndexes[10])
        . $row(1, $offsets['11:0'], 0);
    $currentXrefStream = gzcompress($currentRows);
    if (!is_string($currentXrefStream)) {
        throw new RuntimeException('Unable to compress current bundled xref stream.');
    }

    $currentXrefOffset = strlen($pdf);
    $pdf .= "21 0 obj\n"
        . '<< /Type /XRef /Size 22 /Root 1 1 R /Prev ' . $previousXrefOffset . ' /Index [1 2 4 1 10 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($currentXrefStream) . " >>\n"
        . "stream\n{$currentXrefStream}\nendstream\nendobj\n"
        . "startxref\n{$currentXrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'reviews current free generation rows that suppress stale Prev object-stream members while rebuilding the current carrier' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamPrevFreeGenerationBundleCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $xrefObjectStreamPrevFreeGenerationBundleCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractXrefPrevObjectStreamGenerationReview($pdf);
        $entry = $review['entries'][0] ?? [];

        $t->same(['Current bundled object stream page', 'Free generation member suppressed'], $extractor->extractTextLines($pdf));
        $t->same(['Current bundled object stream page', 'Free generation member suppressed'], $extractor->extractTextRuns($pdf));
        $t->same("Current bundled object stream page\nFree generation member suppressed", $text);
        $t->same("Current bundled object stream page\nFree generation member suppressed\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'Stale bundled compressed member page'));
        $t->true(!str_contains($text, 'stale previous bundled member'));
        $t->true(!str_contains($text, 'current rebuilt bundled member'));
        $t->true(!str_contains($text, "\0"));

        $t->same('pdf_xref_prev_object_stream_generation_review', $review['source']);
        $t->same(1, $review['inherited_type2_entry_count']);
        $t->same(0, $review['preserved_type2_entry_count']);
        $t->same(1, $review['skipped_type2_entry_count']);
        $t->same(1, $review['skipped_current_free_object_count']);
        $t->same(0, $review['skipped_replaced_carrier_count']);
        $t->same(4, $entry['object_number'] ?? null);
        $t->same(6, $entry['object_stream'] ?? null);
        $t->same(0, $entry['member_index'] ?? null);
        $t->same(0, $entry['current_object_entry_type'] ?? null);
        $t->same(1, $entry['current_object_generation'] ?? null);
        $t->same(0, $entry['previous_carrier_generation'] ?? null);
        $t->same(false, $entry['current_carrier_present'] ?? null);
        $t->same('skipped_current_free_object_generation', $entry['owner_policy'] ?? null);
        $t->true($entry['skipped'] ?? false);
        $t->true($entry['current_free_object_suppressed'] ?? false);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
