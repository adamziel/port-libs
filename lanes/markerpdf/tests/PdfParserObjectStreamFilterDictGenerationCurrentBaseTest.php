<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserObjectStreamFilterDictGenerationCurrentBasePdf = static function (): string {
    $objectStreamMembers = [
        20 => '<< /Review (Object stream dictionary filter member excluded) >>',
        21 => '<< /Review (Stale generation Flate filter ignored) >>',
    ];

    $objectData = '';
    $headerPairs = [];
    $memberIndexes = [];
    foreach ($objectStreamMembers as $objectNumber => $body) {
        $headerPairs[] = $objectNumber . ' ' . strlen($objectData);
        $memberIndexes[$objectNumber] = count($memberIndexes);
        $objectData .= $body . "\n";
    }

    $header = implode(' ', $headerPairs);
    $plainObjectStream = $header . "\n" . $objectData;
    $compressedObjectStream = gzcompress($plainObjectStream, 0);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress focused object-stream filter-dict fixture.');
    }

    $safeContent = 'BT /F1 12 Tf 72 720 Td (Safe page before object stream filter dictionary) Tj ET';
    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };

    $addObject(1, 0, '<< /Type /Catalog /Pages 2 0 R >>');
    $addObject(2, 0, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, 0, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>');
    $addObject(4, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(5, 0, "<< /Length " . strlen($safeContent) . " >>\nstream\n{$safeContent}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N ' . count($objectStreamMembers)
        . ' /First ' . (strlen($header) + 1)
        . ' /Length ' . strlen($compressedObjectStream)
        . " /Filter 8 1 R >>\nstream\n{$compressedObjectStream}\nendstream");
    $addObject(8, 0, '/FlateDecode');
    $addObject(8, 1, '<< /Owner (Current object-stream Filter dictionary is not a decoder) /Name /FlateDecode >>');

    $xrefRows = ''
        . chr(1) . pack('N', $offsets['1:0']) . chr(0)
        . chr(1) . pack('N', $offsets['2:0']) . chr(0)
        . chr(1) . pack('N', $offsets['3:0']) . chr(0)
        . chr(1) . pack('N', $offsets['4:0']) . chr(0)
        . chr(1) . pack('N', $offsets['5:0']) . chr(0)
        . chr(1) . pack('N', $offsets['6:0']) . chr(0)
        . chr(0) . pack('N', 0) . chr(0)
        . chr(1) . pack('N', $offsets['8:1']) . chr(1)
        . chr(2) . pack('N', 6) . chr($memberIndexes[20])
        . chr(2) . pack('N', 6) . chr($memberIndexes[21]);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress focused object-stream filter-dict xref stream.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "30 0 obj\n"
        . '<< /Type /XRef /Size 31 /Root 1 0 R /Index [1 8 20 2] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects current-generation dictionary Filter operands on object streams before WordPress extraction' => static function (TestRunner $t) use ($parserObjectStreamFilterDictGenerationCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserObjectStreamFilterDictGenerationCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractObjectStreamStreamDictionaryGenerationReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $filterOperand = $entry['operand_groups']['Filter'][0] ?? [];

        $t->same(['Safe page before object stream filter dictionary'], $extractor->extractTextLines($pdf));
        $t->same(['Safe page before object stream filter dictionary'], $extractor->extractTextRuns($pdf));
        $t->same('Safe page before object stream filter dictionary', $text);
        $t->same("Safe page before object stream filter dictionary\n", $extractor->naiveGetText($pdf));
        $t->true(!str_contains($text, 'Object stream dictionary filter member excluded'));
        $t->true(!str_contains($text, 'Stale generation Flate filter ignored'));
        $t->true(!str_contains($text, 'Current object-stream Filter dictionary is not a decoder'));
        $t->same('pdf_object_stream_stream_dictionary_generation_review', $review['source']);
        $t->same(1, $review['object_stream_count']);
        $t->same(1, $review['indirect_operand_count']);
        $t->same(1, $review['xref_selected_operand_count']);
        $t->same(0, $review['unresolved_operand_count']);
        $t->same(1, $review['invalid_filter_operand_count']);
        $t->same(1, $review['dictionary_filter_operand_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same(true, $entry['filter_resolution_failed'] ?? null);
        $t->same(false, $entry['decodeparms_resolution_failed'] ?? null);
        $t->same(0, $entry['decoded_member_count'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same(1, $entry['invalid_filter_operand_count'] ?? null);
        $t->same(1, $entry['dictionary_filter_operand_count'] ?? null);
        $t->same('reject_dictionary_filter_operands', $entry['filter_operand_policy'] ?? null);
        $t->same('indirect', $filterOperand['kind'] ?? null);
        $t->same(8, $filterOperand['object_number'] ?? null);
        $t->same(1, $filterOperand['generation'] ?? null);
        $t->same(true, $filterOperand['resolved'] ?? null);
        $t->same(true, $filterOperand['xref_selected'] ?? null);
        $t->same('xref_selected_direct_object', $filterOperand['owner_policy'] ?? null);
        $t->true(str_starts_with((string) ($filterOperand['value_preview'] ?? ''), '<< /Owner'));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
