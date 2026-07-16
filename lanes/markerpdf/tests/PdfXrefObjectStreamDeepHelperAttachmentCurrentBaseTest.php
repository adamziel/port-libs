<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamDeepHelperAttachmentCurrentBasePdf = static function (): array {
    $payload = "Title,Status\nDeep Helper Attachment,Ready\n";
    $checksum = md5($payload);
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Deep helper attachment page) Tj ET';
    $fileSpec = '<< /Type /Filespec /F (deep-helper-current.csv) /Desc (Deep helper compressed FileSpec) /AFRelationship /Source /EF << /F 5 0 R >> >>';

    $objectStream = static function (array $members, string $label): array {
        $headerParts = [];
        $data = '';
        $indexes = [];
        $index = 0;
        foreach ($members as $objectNumber => $body) {
            $headerParts[] = (string) $objectNumber . ' ' . strlen($data);
            $indexes[(int) $objectNumber] = $index;
            $data .= (string) $body . "\n";
            $index++;
        }

        $header = implode(' ', $headerParts);
        $compressed = gzcompress($header . "\n" . $data);
        if (!is_string($compressed)) {
            throw new RuntimeException('Unable to compress deep-helper object stream fixture: ' . $label);
        }

        return [
            'count' => count($members),
            'first' => strlen($header) + 1,
            'indexes' => $indexes,
            'length' => strlen($compressed),
            'stream' => $compressed,
        ];
    };

    $streamPayloads = [
        20 => $objectStream([4 => $fileSpec], 'filespec-carrier'),
    ];
    $streamDictionaryHelpers = [];
    $compressedMemberReferences = [
        4 => [20, $streamPayloads[20]['indexes'][4]],
    ];
    $helperObjectNumber = 30;
    for ($streamId = 21; $streamId <= 28; $streamId++) {
        $target = $streamPayloads[$streamId - 1];
        $members = [
            $helperObjectNumber => (string) $target['count'],
            $helperObjectNumber + 1 => (string) $target['first'],
            $helperObjectNumber + 2 => (string) $target['length'],
        ];
        $streamPayloads[$streamId] = $objectStream($members, 'dictionary-helpers-' . $streamId);
        $streamDictionaryHelpers[$streamId - 1] = [
            $helperObjectNumber,
            $helperObjectNumber + 1,
            $helperObjectNumber + 2,
        ];
        foreach (array_keys($members) as $memberObjectNumber) {
            $compressedMemberReferences[$memberObjectNumber] = [
                $streamId,
                $streamPayloads[$streamId]['indexes'][$memberObjectNumber],
            ];
        }
        $helperObjectNumber += 3;
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, '<< /Type /Catalog /Pages 3 0 R /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>');
    $addObject(2, '<< /Names [(deep-helper-current.csv) 4 0 R] >>');
    $addObject(3, '<< /Type /Pages /Kids [7 0 R] /Count 1 >>');
    $addObject(5, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260608234438Z) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream");
    $addObject(7, "<< /Type /Page /Parent 3 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 9 0 R >>");
    $addObject(8, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(9, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
    foreach (range(20, 28) as $streamId) {
        $streamPayload = $streamPayloads[$streamId];
        if ($streamId === 28) {
            $dictionary = "<< /Type /ObjStm /N {$streamPayload['count']} /First {$streamPayload['first']} /Filter /FlateDecode /Length {$streamPayload['length']} >>";
        } else {
            [$nObject, $firstObject, $lengthObject] = $streamDictionaryHelpers[$streamId];
            $dictionary = "<< /Type /ObjStm /N {$nObject} 0 R /First {$firstObject} 0 R /Filter /FlateDecode /Length {$lengthObject} 0 R >>";
        }
        $addObject($streamId, "{$dictionary}\nstream\n{$streamPayload['stream']}\nendstream");
    }

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 1; $objectNumber <= 9; $objectNumber++) {
        if ($objectNumber === 4) {
            [$objectStreamId, $memberIndex] = $compressedMemberReferences[4];
            $rows .= $xrefRow(2, $objectStreamId, $memberIndex);
            continue;
        }

        $rows .= isset($offsets[$objectNumber])
            ? $xrefRow(1, $offsets[$objectNumber], 0)
            : $xrefRow(0, 0, 0);
    }
    for ($objectNumber = 20; $objectNumber <= 29; $objectNumber++) {
        $rows .= $xrefRow(1, $objectNumber === 29 ? $xrefOffset : $offsets[$objectNumber], 0);
    }
    for ($objectNumber = 30; $objectNumber <= 53; $objectNumber++) {
        [$objectStreamId, $memberIndex] = $compressedMemberReferences[$objectNumber];
        $rows .= $xrefRow(2, $objectStreamId, $memberIndex);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress deep-helper xref stream fixture.');
    }

    $pdf .= "29 0 obj\n"
        . '<< /Type /XRef /Size 54 /Root 1 0 R /Index [1 9 20 10 30 24] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $payload, $checksum];
};

return [
    'expands deep compressed object-stream dictionary helpers before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamDeepHelperAttachmentCurrentBasePdf): void {
        [$pdf, $payload, $checksum] = $xrefObjectStreamDeepHelperAttachmentCurrentBasePdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $textExtractor = new PdfTextExtractor();
        $review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(['Deep helper attachment page'], $textExtractor->extractTextLines($pdf));
        $t->same('Deep helper attachment page', $textExtractor->extractPlainText($pdf));
        $t->same(25, $review['compressed_entry_count']);
        $t->same(1, $entry['object_stream_member_count'] ?? null);
        $t->same(true, $entry['object_stream_carrier_resolved'] ?? null);
        $t->same('explicit_member_index', $entry['selection_policy'] ?? null);
        $t->same(1, $summary['attachment_count']);
        $t->same(['deep-helper-current.csv'], $summary['filenames']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same('deep-helper-current.csv', $summary['attachments'][0]['filename'] ?? null);
        $t->same('Deep helper compressed FileSpec', $summary['attachments'][0]['description'] ?? null);
        $t->same('Source', $summary['attachments'][0]['relationship'] ?? null);
        $t->same($checksum, $summary['attachments'][0]['checksum_hex'] ?? null);
        $t->same($checksum, $summary['attachments'][0]['computed_checksum_hex'] ?? null);
        $t->same(true, $summary['attachments'][0]['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $summary['attachments'][0] ?? []));
        $t->true(is_string($summaryJson));
        $t->true(!str_contains($summaryJson, $payload));
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
