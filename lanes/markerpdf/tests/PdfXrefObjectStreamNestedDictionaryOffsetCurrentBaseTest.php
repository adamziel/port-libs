<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamNestedDictionaryOffsetCurrentBasePdf = static function (): array {
    $currentPayload = "Title,Status\nCurrent Attachment,Ready\n";
    $stalePayload = "Title,Status\nNested Dictionary Leak,Ignore\n";
    $currentChecksum = md5($currentPayload);
    $staleChecksum = md5($stalePayload);
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Current nested-dictionary attachment page) Tj ET';
    $fakeFileSpec = '<< /Type /Filespec /F (nested-stale.csv) /Desc (Nested dictionary stale FileSpec) /AFRelationship /Alternative /EF << /F 5 0 R >> >>';
    $carrierBody = '<< /Type /Catalog /Pages 3 0 R /PieceInfo << /WP << /Private ' . $fakeFileSpec . ' >> >> >>';
    $objectData = $carrierBody . "\n";
    $badOffset = strpos($objectData, $fakeFileSpec);
    if ($badOffset === false) {
        throw new RuntimeException('Unable to locate nested dictionary FileSpec decoy.');
    }

    $header = '12 0 4 ' . $badOffset;
    $objectStream = gzcompress($header . "\n" . $objectData);
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress nested dictionary attachment object stream.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, int $generation, string $body) use (&$pdf, &$offsets): int {
        $offset = strlen($pdf);
        $offsets[$objectNumber . ':' . $generation] = $offset;
        $pdf .= "{$objectNumber} {$generation} obj\n{$body}\nendobj\n";

        return $offset;
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, 0, '<< /Type /Catalog /Pages 3 0 R /Names << /EmbeddedFiles 2 0 R >> /AF [8 0 R] >>');
    $addObject(2, 0, '<< /Names [(current.csv) 8 0 R (nested-stale.csv) 4 0 R] >>');
    $addObject(3, 0, '<< /Type /Pages /Kids [7 0 R] /Count 1 >>');
    $addObject(5, 0, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");
    $addObject(6, 0, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(7, 0, '<< /Type /Page /Parent 3 0 R /Resources << /Font << /F1 11 0 R >> >> /Contents 10 0 R >>');
    $addObject(8, 0, '<< /Type /Filespec /F (current.csv) /Desc (Current direct FileSpec) /AFRelationship /Source /EF << /F 9 0 R >> >>');
    $addObject(9, 0, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605063544Z) >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
    $addObject(10, 0, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
    $addObject(11, 0, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');

    $xrefRows = ''
        . $xrefRow(1, $offsets['1:0'])
        . $xrefRow(1, $offsets['2:0'])
        . $xrefRow(1, $offsets['3:0'])
        . $xrefRow(2, 6, 1)
        . $xrefRow(1, $offsets['5:0'])
        . $xrefRow(1, $offsets['6:0'])
        . $xrefRow(1, $offsets['7:0'])
        . $xrefRow(1, $offsets['8:0'])
        . $xrefRow(1, $offsets['9:0'])
        . $xrefRow(1, $offsets['10:0'])
        . $xrefRow(1, $offsets['11:0'])
        . $xrefRow(2, 6, 0);
    $compressedXref = gzcompress($xrefRows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress nested dictionary attachment xref stream.');
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /Index [1 11 12 1] /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $currentPayload, $stalePayload, $currentChecksum];
};

return [
    'rejects object-stream attachment member offsets that point inside nested dictionaries' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamNestedDictionaryOffsetCurrentBasePdf): void {
        [$pdf, $currentPayload, $stalePayload, $currentChecksum] = $xrefObjectStreamNestedDictionaryOffsetCurrentBasePdf();

        $textExtractor = new PdfTextExtractor();
        $attachmentSummary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $embeddedFiles = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $text = $textExtractor->extractPlainText($pdf);
        $review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];
        $summaryJson = json_encode($attachmentSummary, JSON_UNESCAPED_SLASHES);
        $embeddedJson = json_encode($embeddedFiles, JSON_UNESCAPED_SLASHES);

        $t->same(['Current nested-dictionary attachment page'], $textExtractor->extractTextLines($pdf));
        $t->same('Current nested-dictionary attachment page', $text);
        $t->same(1, $attachmentSummary['attachment_count']);
        $t->same(strlen($currentPayload), $attachmentSummary['total_bytes']);
        $t->same(['current.csv'], $attachmentSummary['filenames']);

        $attachment = $attachmentSummary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('current.csv', $attachment['name_key']);
        $t->same('current.csv', $attachment['filename']);
        $t->same('Current direct FileSpec', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same(strlen($currentPayload), $attachment['byte_length']);
        $t->same($currentChecksum, $attachment['checksum_hex']);
        $t->same($currentChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);

        $t->same(1, count($embeddedFiles));
        $t->same('current.csv', $embeddedFiles[0]['filename']);
        $t->same($currentPayload, $embeddedFiles[0]['content']);
        $t->same($currentChecksum, $embeddedFiles[0]['checksum']);
        $t->true(is_string($summaryJson));
        $t->true(is_string($embeddedJson));
        $t->true(!str_contains($summaryJson, 'nested-stale.csv'));
        $t->true(!str_contains($summaryJson, 'Nested Dictionary Leak'));
        $t->true(!str_contains($summaryJson, $stalePayload));
        $t->true(!str_contains($embeddedJson, 'nested-stale.csv'));
        $t->true(!str_contains($embeddedJson, 'Nested Dictionary Leak'));
        $t->true(!str_contains($embeddedJson, $stalePayload));
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $attachmentSummary['executes_python_or_models']);
        $t->same(false, $attachmentSummary['executes_external_pdf_tools']);

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(2, $review['compressed_entry_count']);
        $t->same(1, $review['invalid_member_offset_rejection_count'] ?? null);
        $t->same(false, $entry['member_offset_token_boundary'] ?? null);
        $t->same(true, $entry['invalid_member_offset_rejected'] ?? null);
        $t->same('invalid_object_stream_member_offset', $entry['selection_policy'] ?? null);
    },
];
