<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfTextExtractor;

$parserXrefStreamFilterStackBoundaryCurrentBaseAscii85 = static function (string $bytes): string {
    $encoded = '';
    $length = strlen($bytes);
    for ($offset = 0; $offset < $length; $offset += 4) {
        $chunk = substr($bytes, $offset, 4);
        $chunkLength = strlen($chunk);
        if ($chunkLength < 4) {
            $chunk = str_pad($chunk, 4, "\0");
        }

        $value = unpack('N', $chunk)[1];
        if ($value === 0 && $chunkLength === 4) {
            $encoded .= 'z';
            continue;
        }

        $chars = '';
        for ($index = 0; $index < 5; $index++) {
            $chars = chr(($value % 85) + 33) . $chars;
            $value = intdiv($value, 85);
        }

        $encoded .= substr($chars, 0, $chunkLength + 1);
    }

    return $encoded;
};

$parserXrefStreamFilterStackBoundaryCurrentBaseRow = static function (int $type, int $offset, int $generation): string {
    if ($offset < 0 || $offset > 0xffff || $generation < 0 || $generation > 0xff) {
        throw new RuntimeException('Focused xref-stream row fixture uses out-of-range fields.');
    }

    return chr($type) . pack('n', $offset) . chr($generation);
};

$parserXrefStreamFilterStackBoundaryCurrentBasePdf = static function (string $payload, string $filterOperand = '/FlateDecode'): string {
    $header = "%PDF-1.5\n";
    $xrefOffset = strlen($header);
    $xrefObject = "1 0 obj\n"
        . "<< /Type /XRef /Size 2 /Index [1 1] /W [1 2 1] /Filter {$filterOperand} /Length " . strlen($payload) . " >>\n"
        . "stream\n{$payload}\nendstream\nendobj\n";

    return $header
        . $xrefObject
        . "startxref\n{$xrefOffset}\n%%EOF";
};

$parserXrefStreamFilterStackBoundaryCurrentBaseEntry = static function (string $pdf): array {
    $review = (new PdfTextExtractor())->extractXrefStreamFilterLengthOwnerReview($pdf);

    return [$review, $review['entries'][0] ?? []];
};

return [
    'rejects concatenated Flate members before xref stream row admission' => static function (TestRunner $t) use (
        $parserXrefStreamFilterStackBoundaryCurrentBasePdf,
        $parserXrefStreamFilterStackBoundaryCurrentBaseRow,
        $parserXrefStreamFilterStackBoundaryCurrentBaseEntry
    ): void {
        $xrefOffset = strlen("%PDF-1.5\n");
        $firstMember = gzcompress($parserXrefStreamFilterStackBoundaryCurrentBaseRow(1, $xrefOffset, 0));
        $tailMember = gzcompress($parserXrefStreamFilterStackBoundaryCurrentBaseRow(1, $xrefOffset + 1, 0));
        $t->true(is_string($firstMember));
        $t->true(is_string($tailMember));

        $payload = $firstMember . $tailMember;
        [$review, $entry] = $parserXrefStreamFilterStackBoundaryCurrentBaseEntry(
            $parserXrefStreamFilterStackBoundaryCurrentBasePdf($payload)
        );

        $t->same('pdf_xref_stream_filter_length_owner_review', $review['source']);
        $t->same(1, $review['xref_stream_count']);
        $t->same(false, $review['encrypted']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
        $t->same(1, $entry['object_number'] ?? null);
        $t->same(true, $entry['startxref_selected'] ?? null);
        $t->same(['FlateDecode'], $entry['filters'] ?? null);
        $t->same(strlen($payload), $entry['declared_length'] ?? null);
        $t->same(0, $entry['decoded_entry_count'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('direct_operands', $entry['owner_policy'] ?? null);
    },
    'rejects concatenated inner Flate members inside xref stream filter stacks' => static function (TestRunner $t) use (
        $parserXrefStreamFilterStackBoundaryCurrentBaseAscii85,
        $parserXrefStreamFilterStackBoundaryCurrentBasePdf,
        $parserXrefStreamFilterStackBoundaryCurrentBaseRow,
        $parserXrefStreamFilterStackBoundaryCurrentBaseEntry
    ): void {
        $xrefOffset = strlen("%PDF-1.5\n");
        $firstMember = gzcompress($parserXrefStreamFilterStackBoundaryCurrentBaseRow(1, $xrefOffset, 0));
        $tailMember = gzcompress($parserXrefStreamFilterStackBoundaryCurrentBaseRow(1, $xrefOffset + 2, 0));
        $t->true(is_string($firstMember));
        $t->true(is_string($tailMember));

        $wrappedPayload = $parserXrefStreamFilterStackBoundaryCurrentBaseAscii85($firstMember . $tailMember) . '~>';
        [$review, $entry] = $parserXrefStreamFilterStackBoundaryCurrentBaseEntry(
            $parserXrefStreamFilterStackBoundaryCurrentBasePdf($wrappedPayload, '[ /ASCII85Decode /FlateDecode ]')
        );

        $t->same('pdf_xref_stream_filter_length_owner_review', $review['source']);
        $t->same(1, $review['xref_stream_count']);
        $t->same(false, $review['encrypted']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
        $t->same(1, $entry['object_number'] ?? null);
        $t->same(true, $entry['startxref_selected'] ?? null);
        $t->same(['ASCII85Decode', 'FlateDecode'], $entry['filters'] ?? null);
        $t->same(strlen($wrappedPayload), $entry['declared_length'] ?? null);
        $t->same(0, $entry['decoded_entry_count'] ?? null);
        $t->same(false, $entry['decoded_with_current_operands'] ?? null);
        $t->same('direct_operands', $entry['owner_policy'] ?? null);
    },
    'keeps single-member Flate xref streams with trailing PDF whitespace' => static function (TestRunner $t) use (
        $parserXrefStreamFilterStackBoundaryCurrentBasePdf,
        $parserXrefStreamFilterStackBoundaryCurrentBaseRow,
        $parserXrefStreamFilterStackBoundaryCurrentBaseEntry
    ): void {
        $xrefOffset = strlen("%PDF-1.5\n");
        $firstMember = gzcompress($parserXrefStreamFilterStackBoundaryCurrentBaseRow(1, $xrefOffset, 0));
        $t->true(is_string($firstMember));

        $payload = $firstMember . "\n \r\t";
        [$review, $entry] = $parserXrefStreamFilterStackBoundaryCurrentBaseEntry(
            $parserXrefStreamFilterStackBoundaryCurrentBasePdf($payload)
        );

        $t->same('pdf_xref_stream_filter_length_owner_review', $review['source']);
        $t->same(1, $review['xref_stream_count']);
        $t->same(false, $review['encrypted']);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
        $t->same(1, $entry['object_number'] ?? null);
        $t->same(true, $entry['startxref_selected'] ?? null);
        $t->same(['FlateDecode'], $entry['filters'] ?? null);
        $t->same(strlen($payload), $entry['declared_length'] ?? null);
        $t->same(1, $entry['decoded_entry_count'] ?? null);
        $t->same(true, $entry['decoded_with_current_operands'] ?? null);
        $t->same('direct_operands', $entry['owner_policy'] ?? null);
    },
];
