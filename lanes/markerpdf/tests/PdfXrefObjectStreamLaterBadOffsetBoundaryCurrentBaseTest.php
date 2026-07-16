<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamLaterBadOffsetBoundaryCurrentBasePdf = static function (): array {
    $payload = "Title,Status\nCurrent Later Offset Boundary,Ready\n";
    $checksum = md5($payload);
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Current later bad offset object-stream page) Tj ET';
    $fileSpec = '<< /Type /Filespec /F (current-later-offset.csv) /Desc (Current compressed FileSpec survives later bad offset) /AFRelationship /Source /EF << /F 9 0 R >> >>';
    $badOffset = strpos($fileSpec, '/EF <<');
    if ($badOffset === false) {
        throw new RuntimeException('Unable to locate malformed later object-stream member offset.');
    }

    $laterMember = '<< /Type /Review /Note (valid later object-stream member after bad offset) >>';
    $laterOffset = strlen($fileSpec . "\n");
    $header = '4 0 12 ' . $badOffset . ' 16 ' . $laterOffset;
    $objectStream = gzcompress($header . "\n" . $fileSpec . "\n" . $laterMember . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress later-bad-offset object stream fixture.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, '<< /Type /Catalog /Pages 3 0 R /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>');
    $addObject(2, '<< /Names [(current-later-offset.csv) 4 0 R] >>');
    $addObject(3, '<< /Type /Pages /Kids [7 0 R] /Count 1 >>');
    $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, '<< /Type /ObjStm /N 3 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(7, '<< /Type /Page /Parent 3 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 8 0 R >>');
    $addObject(8, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
    $addObject(9, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605150345Z) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream");

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber <= 20; $objectNumber++) {
        if ($objectNumber === 0) {
            $rows .= $xrefRow(0, 0, 255);
            continue;
        }

        if ($objectNumber === 4) {
            $rows .= $xrefRow(2, 6, 0);
            continue;
        }

        if ($objectNumber === 12) {
            $rows .= $xrefRow(2, 6, 1);
            continue;
        }

        if ($objectNumber === 16) {
            $rows .= $xrefRow(2, 6, 2);
            continue;
        }

        if ($objectNumber === 20) {
            $rows .= $xrefRow(1, $xrefOffset, 0);
            continue;
        }

        if (isset($offsets[$objectNumber])) {
            $rows .= $xrefRow(1, $offsets[$objectNumber], 0);
            continue;
        }

        $rows .= $xrefRow(0, 0, 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress later-bad-offset xref stream fixture.');
    }

    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $payload, $checksum];
};

$xrefObjectStreamLaterBadOffsetMetadataCurrentBasePdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Current later bad offset metadata page) Tj ET';
    $catalog = '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /PageLayout /TwoPageRight /PageMode /UseOutlines /ViewerPreferences << /DisplayDocTitle true /Direction /L2R >> >>';
    $badOffset = strpos($catalog, '/Lang');
    if ($badOffset === false) {
        throw new RuntimeException('Unable to locate malformed later metadata object-stream member offset.');
    }

    $laterMember = '<< /Type /Review /Note (valid later metadata member after bad offset) >>';
    $laterOffset = strlen($catalog . "\n");
    $header = '1 0 12 ' . $badOffset . ' 16 ' . $laterOffset;
    $objectStream = gzcompress($header . "\n" . $catalog . "\n" . $laterMember . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress later-bad-offset metadata object stream fixture.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
    $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, '<< /Type /ObjStm /N 3 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber <= 20; $objectNumber++) {
        if ($objectNumber === 0) {
            $rows .= $xrefRow(0, 0, 255);
            continue;
        }

        if ($objectNumber === 1) {
            $rows .= $xrefRow(2, 6, 0);
            continue;
        }

        if ($objectNumber === 12) {
            $rows .= $xrefRow(2, 6, 1);
            continue;
        }

        if ($objectNumber === 16) {
            $rows .= $xrefRow(2, 6, 2);
            continue;
        }

        if ($objectNumber === 20) {
            $rows .= $xrefRow(1, $xrefOffset, 0);
            continue;
        }

        $rows .= isset($offsets[$objectNumber])
            ? $xrefRow(1, $offsets[$objectNumber], 0)
            : $xrefRow(0, 0, 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress later-bad-offset metadata xref stream fixture.');
    }

    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'ignores malformed later object-stream offsets when slicing current compressed attachments' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamLaterBadOffsetBoundaryCurrentBasePdf): void {
        [$pdf, $payload, $checksum] = $xrefObjectStreamLaterBadOffsetBoundaryCurrentBasePdf();

        $textExtractor = new PdfTextExtractor();
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $embedded = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $validEntry = $entries[4] ?? [];
        $badEntry = $entries[12] ?? [];
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $embeddedJson = json_encode($embedded, JSON_UNESCAPED_SLASHES);

        $t->same(['Current later bad offset object-stream page'], $textExtractor->extractTextLines($pdf));
        $t->same('Current later bad offset object-stream page', $textExtractor->extractPlainText($pdf));
        $t->same(1, $summary['attachment_count']);
        $t->same(['current-later-offset.csv'], $summary['filenames']);
        $t->same(strlen($payload), $summary['total_bytes']);

        $attachment = $summary['attachments'][0] ?? [];
        $t->same('embedded-files-name-tree', $attachment['source'] ?? null);
        $t->same('current-later-offset.csv', $attachment['filename'] ?? null);
        $t->same('Current compressed FileSpec survives later bad offset', $attachment['description'] ?? null);
        $t->same('Source', $attachment['relationship'] ?? null);
        $t->same('original_source', $attachment['relationship_role'] ?? null);
        $t->same(strlen($payload), $attachment['byte_length'] ?? null);
        $t->same($checksum, $attachment['checksum_hex'] ?? null);
        $t->same($checksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(true, $attachment['associated_file'] ?? null);
        $t->same('catalog_af', $attachment['associated_file_source'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($embedded));
        $t->same('current-later-offset.csv', $embedded[0]['filename'] ?? null);
        $t->same($payload, $embedded[0]['content'] ?? null);
        $t->same($checksum, $embedded[0]['checksum'] ?? null);
        $t->true(is_string($summaryJson));
        $t->true(is_string($embeddedJson));
        $t->true(!str_contains($summaryJson, 'valid later object-stream member'));
        $t->true(!str_contains($embeddedJson, 'valid later object-stream member'));
        $t->true(!str_contains($summaryJson, $payload));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(3, $review['compressed_entry_count']);
        $t->same(1, $review['invalid_member_offset_rejection_count'] ?? null);
        $t->same('explicit_member_index', $validEntry['selection_policy'] ?? null);
        $t->same(true, $validEntry['member_offset_token_boundary'] ?? null);
        $t->same(3, $validEntry['object_stream_member_count'] ?? null);
        $t->same('invalid_object_stream_member_offset', $badEntry['selection_policy'] ?? null);
        $t->same(false, $badEntry['member_offset_token_boundary'] ?? null);
        $t->same(true, $badEntry['invalid_member_offset_rejected'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'ignores malformed later object-stream offsets when slicing current compressed catalog metadata' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamLaterBadOffsetMetadataCurrentBasePdf): void {
        $pdf = $xrefObjectStreamLaterBadOffsetMetadataCurrentBasePdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $textExtractor = new PdfTextExtractor();
        $review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $validEntry = $entries[1] ?? [];
        $badEntry = $entries[12] ?? [];
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['Current later bad offset metadata page'], $textExtractor->extractTextLines($pdf));
        $t->same('Current later bad offset metadata page', $textExtractor->extractPlainText($pdf));
        $t->same(['catalog'], $metadata['source']);
        $t->same('en-US', $metadata['language']);
        $t->same('en-US', $metadata['catalog']['language']);
        $t->same('TwoPageRight', $metadata['page_layout']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same(['display_doc_title' => true, 'direction' => 'L2R'], $metadata['viewer_preferences']);
        $t->same($metadata['viewer_preferences'], $metadata['catalog']['viewer_preferences']);
        $t->true(is_string($metadataJson));
        $t->true(!str_contains($metadataJson, 'valid later metadata member'));
        $t->same(3, $review['compressed_entry_count']);
        $t->same(1, $review['invalid_member_offset_rejection_count'] ?? null);
        $t->same('explicit_member_index', $validEntry['selection_policy'] ?? null);
        $t->same(true, $validEntry['member_offset_token_boundary'] ?? null);
        $t->same('invalid_object_stream_member_offset', $badEntry['selection_policy'] ?? null);
        $t->same(false, $badEntry['member_offset_token_boundary'] ?? null);
        $t->same(true, $badEntry['invalid_member_offset_rejected'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
