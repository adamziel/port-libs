<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserObjectStreamStreamDictGenerationCurrentBasePdf = static function (): string {
    $pngSubPredictorEncode = static function (string $bytes, int $columns): string {
        $encoded = '';
        for ($offset = 0, $length = strlen($bytes); $offset < $length; $offset += $columns) {
            $row = substr($bytes, $offset, $columns);
            if (strlen($row) !== $columns) {
                throw new RuntimeException('Focused object-stream stream dictionary rows must be fixed-width.');
            }

            $encoded .= "\x01";
            for ($index = 0; $index < $columns; $index++) {
                $left = $index > 0 ? ord($row[$index - 1]) : 0;
                $encoded .= chr((ord($row[$index]) - $left) & 0xff);
            }
        }

        return $encoded;
    };

    $content = 'BT /F1 12 Tf 72 720 Td (Current object stream streamdict generation) Tj T* (Current N First Length Filter DecodeParms applied) Tj ET';
    $members = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
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
    $plainObjectStream = $header . "\n" . $objectData;
    $compressedObjectStream = gzcompress($pngSubPredictorEncode($plainObjectStream, strlen($plainObjectStream)), 0);
    if (!is_string($compressedObjectStream)) {
        throw new RuntimeException('Unable to compress focused object-stream streamdict-generation fixture.');
    }

    $staleLength = max(1, strlen($compressedObjectStream) - 6);
    $pdf = "%PDF-1.5\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (?int $offset, int $generation = 0, string $state = 'n'): string => sprintf(
        "%010d %05d %s \n",
        $offset ?? 0,
        $generation,
        $state
    );

    $addObject(7, 0, (string) $staleLength);
    $addObject(8, 0, '/ASCIIHexDecode');
    $addObject(9, 0, '<< /Predictor /Twelve /Columns 1 >>');
    $addObject(10, 0, '1');
    $addObject(11, 0, '0');
    $addObject(5, 0, "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream");
    $addObject(6, 0, "<< /Type /ObjStm /N 10 1 R /First 11 1 R /Length 7 1 R /Filter 8 1 R /DecodeParms 9 1 R /Note (generation zero streamdict helpers are stale) >>\nstream\n{$compressedObjectStream}\nendstream");
    $addObject(7, 1, (string) strlen($compressedObjectStream));
    $addObject(8, 1, '/FlateDecode');
    $addObject(9, 1, '<< /Predictor 12 /Columns ' . strlen($plainObjectStream) . ' >>');
    $addObject(10, 1, (string) count($members));
    $addObject(11, 1, (string) (strlen($header) + 1));

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 12\n" . $xrefRow(0, 65535, 'f');
    for ($objectNumber = 1; $objectNumber <= 11; $objectNumber++) {
        if (isset($memberIndexes[$objectNumber])) {
            $pdf .= $xrefRow(0, 0, 'f');
            continue;
        }

        if (isset($offsets[$objectNumber . ':1'])) {
            $pdf .= $xrefRow($offsets[$objectNumber . ':1'], 1);
            continue;
        }

        $pdf .= isset($offsets[$objectNumber . ':0'])
            ? $xrefRow($offsets[$objectNumber . ':0'])
            : $xrefRow(0, 65535, 'f');
    }
    $pdf .= "trailer\n<< /Size 12 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

    $xrefRows = ''
        . chr(2) . pack('N', 6) . chr($memberIndexes[1])
        . chr(2) . pack('N', 6) . chr($memberIndexes[2])
        . chr(2) . pack('N', 6) . chr($memberIndexes[3])
        . chr(2) . pack('N', 6) . chr($memberIndexes[4])
        . chr(1) . pack('N', $offsets['5:0']) . chr(0)
        . chr(1) . pack('N', $offsets['6:0']) . chr(0)
        . chr(1) . pack('N', $offsets['7:1']) . chr(1)
        . chr(1) . pack('N', $offsets['8:1']) . chr(1)
        . chr(1) . pack('N', $offsets['9:1']) . chr(1)
        . chr(1) . pack('N', $offsets['10:1']) . chr(1)
        . chr(1) . pack('N', $offsets['11:1']) . chr(1);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress focused streamdict-generation xref stream.');
    }

    $xrefStreamOffset = strlen($pdf);
    $pdf .= "\n20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 11] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefStreamOffset}\n%%EOF";

    return $pdf;
};

return [
    'uses current object-stream stream dictionary helper generations before WordPress extraction' => static function (TestRunner $t) use ($parserObjectStreamStreamDictGenerationCurrentBasePdf): void {
        $extractor = new PdfTextExtractor();
        $pdf = $parserObjectStreamStreamDictGenerationCurrentBasePdf();
        $text = $extractor->extractPlainText($pdf);
        $review = $extractor->extractObjectStreamStreamDictionaryGenerationReview($pdf);
        $entry = $review['entries'][0] ?? [];
        $operandGroups = $entry['operand_groups'] ?? [];

        $expected = ['Current object stream streamdict generation', 'Current N First Length Filter DecodeParms applied'];
        $t->same($expected, $extractor->extractTextLines($pdf));
        $t->same($expected, $extractor->extractTextRuns($pdf));
        $t->same("Current object stream streamdict generation\nCurrent N First Length Filter DecodeParms applied", $text);
        $t->same("Current object stream streamdict generation\nCurrent N First Length Filter DecodeParms applied\n", $extractor->naiveGetText($pdf));
        $t->same(1, $extractor->extractOutlineMetadata($pdf)['pages']);
        $t->same(['1'], $extractor->extractPageLabels($pdf));
        $t->true(!str_contains($text, 'ASCIIHexDecode'));
        $t->true(!str_contains($text, 'Twelve'));
        $t->true(!str_contains($text, 'generation zero streamdict helpers are stale'));
        $t->true(!str_contains($text, "\0"));
        $t->same('pdf_object_stream_stream_dictionary_generation_review', $review['source']);
        $t->true($review['review_only']);
        $t->same(1, $review['object_stream_count']);
        $t->same(5, $review['indirect_operand_count']);
        $t->same(5, $review['xref_selected_operand_count']);
        $t->same(0, $review['unresolved_operand_count']);
        $t->same(6, $entry['object_number'] ?? null);
        $t->same(0, $entry['generation'] ?? null);
        $t->same(4, $entry['declared_member_count'] ?? null);
        $t->true(($entry['first_object_offset'] ?? 0) > 0);
        $t->true(($entry['declared_length'] ?? 0) > 0);
        $t->same(['FlateDecode'], $entry['filters'] ?? null);
        $t->same(4, $entry['decoded_member_count'] ?? null);
        $t->same(true, $entry['decoded_with_current_operands'] ?? null);
        $t->same('xref_selected_indirect_operands', $entry['owner_policy'] ?? null);
        foreach (['N', 'First', 'Length', 'Filter', 'DecodeParms'] as $name) {
            $operand = $operandGroups[$name][0] ?? [];
            $t->same('indirect', $operand['kind'] ?? null);
            $t->same(1, $operand['generation'] ?? null);
            $t->same(true, $operand['resolved'] ?? null);
            $t->same(true, $operand['xref_selected'] ?? null);
            $t->same('xref_selected_direct_object', $operand['owner_policy'] ?? null);
        }
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
