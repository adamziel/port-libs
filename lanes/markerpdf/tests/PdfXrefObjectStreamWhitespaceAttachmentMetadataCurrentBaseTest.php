<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamWhitespaceAttachmentCurrentBasePdf = static function (): array {
    $currentPayload = "Title,Status\nCurrent Whitespace Attachment,Ready\n";
    $decoyPayload = "Title,Status\nWhitespace Decoy Attachment,Ignore\n";
    $currentChecksum = md5($currentPayload);
    $decoyChecksum = md5($decoyPayload);
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Current whitespace attachment object-stream page) Tj ET';
    $currentFileSpec = '<< /Type /Filespec /F (current-whitespace-offset.csv) /Desc (Current compressed FileSpec before whitespace offset) /AFRelationship /Source /EF << /F 9 0 R >> >>';
    $decoyFileSpec = '<< /Type /Filespec /F (decoy-whitespace-offset.csv) /Desc (Whitespace-owned decoy FileSpec) /AFRelationship /Alternative /EF << /F 11 0 R >> >>';
    $reviewMember = '<< /Type /Review /Note (valid member after whitespace decoy) >>';
    $badOffset = strlen($currentFileSpec);
    $reviewOffset = strlen($currentFileSpec . "\n" . $decoyFileSpec . "\n");
    $header = '4 0 12 ' . $badOffset . ' 16 ' . $reviewOffset;
    $objectStream = gzcompress($header . "\n" . $currentFileSpec . "\n" . $decoyFileSpec . "\n" . $reviewMember . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress whitespace attachment object stream fixture.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, '<< /Type /Catalog /Pages 3 0 R /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R 12 0 R] >>');
    $addObject(2, '<< /Names [(current-whitespace-offset.csv) 4 0 R (decoy-whitespace-offset.csv) 12 0 R] >>');
    $addObject(3, '<< /Type /Pages /Kids [7 0 R] /Count 1 >>');
    $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, '<< /Type /ObjStm /N 3 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(7, '<< /Type /Page /Parent 3 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 8 0 R >>');
    $addObject(8, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
    $addObject(9, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260608115259Z) >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");
    $addObject(11, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($decoyPayload) . " /CheckSum <{$decoyChecksum}> >> /Length " . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream");

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

        $rows .= isset($offsets[$objectNumber])
            ? $xrefRow(1, $offsets[$objectNumber], 0)
            : $xrefRow(0, 0, 0);
    }

    $compressedXref = gzcompress($rows);
    if (!is_string($compressedXref)) {
        throw new RuntimeException('Unable to compress whitespace attachment xref stream fixture.');
    }

    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $currentPayload, $currentChecksum, $decoyPayload];
};

$xrefObjectStreamWhitespaceMetadataCurrentBasePdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Current whitespace metadata object-stream page) Tj ET';
    $prefixMember = '<< /Type /Review /Note (unowned prefix before whitespace viewer preferences) >>';
    $viewerPreferences = '<< /DisplayDocTitle true /Direction /R2L /PrintScaling /None /NumCopies 3 >>';
    $reviewMember = '<< /Type /Review /Note (valid member after whitespace viewer preferences) >>';
    $badOffset = strlen($prefixMember);
    $reviewOffset = strlen($prefixMember . "\n" . $viewerPreferences . "\n");
    $header = '12 ' . $badOffset . ' 16 ' . $reviewOffset;
    $objectStream = gzcompress($header . "\n" . $prefixMember . "\n" . $viewerPreferences . "\n" . $reviewMember . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress whitespace metadata object stream fixture.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Lang (en-US) /PageLayout /SinglePage /PageMode /UseOutlines /ViewerPreferences 12 0 R >>');
    $addObject(2, '<< /Type /Pages /Kids [3 0 R] /Count 1 >>');
    $addObject(3, '<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>');
    $addObject(4, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
    $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

    $xrefOffset = strlen($pdf);
    $rows = '';
    for ($objectNumber = 0; $objectNumber <= 20; $objectNumber++) {
        if ($objectNumber === 0) {
            $rows .= $xrefRow(0, 0, 255);
            continue;
        }

        if ($objectNumber === 12) {
            $rows .= $xrefRow(2, 6, 0);
            continue;
        }

        if ($objectNumber === 16) {
            $rows .= $xrefRow(2, 6, 1);
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
        throw new RuntimeException('Unable to compress whitespace metadata xref stream fixture.');
    }

    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

return [
    'rejects whitespace-owned object-stream FileSpec offsets before attachment review' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamWhitespaceAttachmentCurrentBasePdf): void {
        [$pdf, $currentPayload, $currentChecksum, $decoyPayload] = $xrefObjectStreamWhitespaceAttachmentCurrentBasePdf();

        $textExtractor = new PdfTextExtractor();
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $embedded = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $attachment = $summary['attachments'][0] ?? [];
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $embeddedJson = json_encode($embedded, JSON_UNESCAPED_SLASHES);

        $t->same(['Current whitespace attachment object-stream page'], $textExtractor->extractTextLines($pdf));
        $t->same('Current whitespace attachment object-stream page', $textExtractor->extractPlainText($pdf));
        $t->same(1, $summary['attachment_count']);
        $t->same(['current-whitespace-offset.csv'], $summary['filenames']);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same('current-whitespace-offset.csv', $attachment['filename'] ?? null);
        $t->same('Current compressed FileSpec before whitespace offset', $attachment['description'] ?? null);
        $t->same('Source', $attachment['relationship'] ?? null);
        $t->same(strlen($currentPayload), $attachment['byte_length'] ?? null);
        $t->same($currentChecksum, $attachment['checksum_hex'] ?? null);
        $t->same($currentChecksum, $attachment['computed_checksum_hex'] ?? null);
        $t->same(true, $attachment['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($embedded));
        $t->same('current-whitespace-offset.csv', $embedded[0]['filename'] ?? null);
        $t->same($currentPayload, $embedded[0]['content'] ?? null);
        $t->true(is_string($summaryJson));
        $t->true(is_string($embeddedJson));
        $t->true(!str_contains($summaryJson, 'decoy-whitespace-offset.csv'));
        $t->true(!str_contains($embeddedJson, 'decoy-whitespace-offset.csv'));
        $t->true(!str_contains($summaryJson, 'Whitespace-owned decoy FileSpec'));
        $t->true(!str_contains($embeddedJson, 'Whitespace-owned decoy FileSpec'));
        $t->true(!str_contains($summaryJson, $decoyPayload));
        $t->true(!str_contains($embeddedJson, $decoyPayload));
        $t->true(!str_contains($summaryJson, $currentPayload));

        $t->same('pdf_xref_object_stream_index_review', $review['source']);
        $t->same(3, $review['compressed_entry_count']);
        $t->same(1, $review['invalid_member_offset_rejection_count'] ?? null);
        $t->same('explicit_member_index', $entries[4]['selection_policy'] ?? null);
        $t->same(true, $entries[4]['member_offset_token_boundary'] ?? null);
        $t->same('invalid_object_stream_member_offset', $entries[12]['selection_policy'] ?? null);
        $t->same(false, $entries[12]['member_offset_token_boundary'] ?? null);
        $t->same(true, $entries[12]['invalid_member_offset_rejected'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'rejects whitespace-owned object-stream catalog metadata operands before import metadata' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamWhitespaceMetadataCurrentBasePdf): void {
        $pdf = $xrefObjectStreamWhitespaceMetadataCurrentBasePdf();

        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $textExtractor = new PdfTextExtractor();
        $review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['Current whitespace metadata object-stream page'], $textExtractor->extractTextLines($pdf));
        $t->same('Current whitespace metadata object-stream page', $textExtractor->extractPlainText($pdf));
        $t->same(['catalog'], $metadata['source']);
        $t->same('en-US', $metadata['language']);
        $t->same('en-US', $metadata['catalog']['language']);
        $t->same('SinglePage', $metadata['page_layout']);
        $t->same('UseOutlines', $metadata['page_mode']);
        $t->same(false, array_key_exists('viewer_preferences', $metadata));
        $t->same(false, array_key_exists('viewer_preferences', $metadata['catalog']));
        $t->true(is_string($encodedMetadata));
        $t->true(!str_contains($encodedMetadata, 'display_doc_title'));
        $t->true(!str_contains($encodedMetadata, 'R2L'));
        $t->true(!str_contains($encodedMetadata, 'PrintScaling'));
        $t->true(!str_contains($encodedMetadata, 'NumCopies'));
        $t->same(2, $review['compressed_entry_count']);
        $t->same(1, $review['invalid_member_offset_rejection_count'] ?? null);
        $t->same('invalid_object_stream_member_offset', $entries[12]['selection_policy'] ?? null);
        $t->same(false, $entries[12]['member_offset_token_boundary'] ?? null);
        $t->same(true, $entries[12]['invalid_member_offset_rejected'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
