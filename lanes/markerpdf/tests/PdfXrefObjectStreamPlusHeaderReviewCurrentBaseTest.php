<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$xrefObjectStreamPlusHeaderMetadataCurrentBasePdf = static function (): string {
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Plus header metadata page) Tj ET';
    $catalog = '<< /Type /Catalog /Pages 2 0 R /Lang (en-GB) /ViewerPreferences << /DisplayDocTitle true /Direction /R2L >> /PageMode /UseThumbs >>';
    $decoy = '<< /Type /Review /Note (plus header metadata decoy) >>';
    $header = '+1 +0 +12 +' . strlen($catalog . "\n");
    $objectStream = gzcompress($header . "\n" . $catalog . "\n" . $decoy . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress plus-header metadata object stream fixture.');
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
    $addObject(6, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");

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
        throw new RuntimeException('Unable to compress plus-header metadata xref stream fixture.');
    }

    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return $pdf;
};

$xrefObjectStreamPlusHeaderAttachmentCurrentBasePdf = static function (): array {
    $payload = "Title,Status\nPlus Header Attachment,Ready\n";
    $checksum = md5($payload);
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Plus header attachment page) Tj ET';
    $fileSpec = '<< /Type /Filespec /F (plus-header-current.csv) /Desc (Current plus-header compressed FileSpec) /AFRelationship /Source /EF << /F 9 0 R >> >>';
    $decoy = '<< /Type /Filespec /F (plus-header-decoy.csv) /Desc (Decoy plus-header FileSpec) >>';
    $header = '+4 +0 +12 +' . strlen($fileSpec . "\n");
    $objectStream = gzcompress($header . "\n" . $fileSpec . "\n" . $decoy . "\n");
    if (!is_string($objectStream)) {
        throw new RuntimeException('Unable to compress plus-header attachment object stream fixture.');
    }

    $pdf = "%PDF-1.7\n";
    $offsets = [];
    $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
        $offsets[$objectNumber] = strlen($pdf);
        $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
    };
    $xrefRow = static fn (int $type, int $fieldTwo, int $fieldThree = 0): string => chr($type) . pack('N', $fieldTwo) . chr($fieldThree);

    $addObject(1, '<< /Type /Catalog /Pages 3 0 R /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>');
    $addObject(2, '<< /Names [(plus-header-current.csv) 4 0 R] >>');
    $addObject(3, '<< /Type /Pages /Kids [7 0 R] /Count 1 >>');
    $addObject(5, '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>');
    $addObject(6, '<< /Type /ObjStm /N 2 /First ' . (strlen($header) + 1) . ' /Filter /FlateDecode /Length ' . strlen($objectStream) . " >>\nstream\n{$objectStream}\nendstream");
    $addObject(7, '<< /Type /Page /Parent 3 0 R /Resources << /Font << /F1 5 0 R >> >> /Contents 8 0 R >>');
    $addObject(8, "<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream");
    $addObject(9, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605173613Z) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream");

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
        throw new RuntimeException('Unable to compress plus-header attachment xref stream fixture.');
    }

    $pdf .= "20 0 obj\n"
        . '<< /Type /XRef /Size 21 /Root 1 0 R /W [1 4 1] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
        . "stream\n{$compressedXref}\nendstream\nendobj\n"
        . "startxref\n{$xrefOffset}\n%%EOF";

    return [$pdf, $payload, $checksum];
};

return [
    'expands plus-signed object-stream header catalog metadata before WordPress review' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamPlusHeaderMetadataCurrentBasePdf): void {
        $pdf = $xrefObjectStreamPlusHeaderMetadataCurrentBasePdf();
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
        $textExtractor = new PdfTextExtractor();
        $review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[1] ?? [];
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES);

        $t->same(['Plus header metadata page'], $textExtractor->extractTextLines($pdf));
        $t->same('Plus header metadata page', $textExtractor->extractPlainText($pdf));
        $t->same(['catalog'], $metadata['source']);
        $t->same('en-GB', $metadata['language']);
        $t->same('UseThumbs', $metadata['page_mode']);
        $t->same(['display_doc_title' => true, 'direction' => 'R2L'], $metadata['viewer_preferences']);
        $t->true(is_string($metadataJson));
        $t->true(!str_contains($metadataJson, 'plus header metadata decoy'));
        $t->same(2, $review['compressed_entry_count']);
        $t->same(2, $entry['object_stream_member_count'] ?? null);
        $t->same('explicit_member_index', $entry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
    'expands plus-signed object-stream header FileSpec members before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($xrefObjectStreamPlusHeaderAttachmentCurrentBasePdf): void {
        [$pdf, $payload, $checksum] = $xrefObjectStreamPlusHeaderAttachmentCurrentBasePdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $embedded = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $textExtractor = new PdfTextExtractor();
        $review = $textExtractor->extractXrefObjectStreamIndexReview($pdf);
        $entries = array_column($review['entries'], null, 'object_number');
        $entry = $entries[4] ?? [];
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(['Plus header attachment page'], $textExtractor->extractTextLines($pdf));
        $t->same('Plus header attachment page', $textExtractor->extractPlainText($pdf));
        $t->same(1, $summary['attachment_count']);
        $t->same(['plus-header-current.csv'], $summary['filenames']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same('plus-header-current.csv', $summary['attachments'][0]['filename'] ?? null);
        $t->same('Current plus-header compressed FileSpec', $summary['attachments'][0]['description'] ?? null);
        $t->same('Source', $summary['attachments'][0]['relationship'] ?? null);
        $t->same($checksum, $summary['attachments'][0]['checksum_hex'] ?? null);
        $t->same($checksum, $summary['attachments'][0]['computed_checksum_hex'] ?? null);
        $t->same(true, $summary['attachments'][0]['checksum_matches'] ?? null);
        $t->same(false, array_key_exists('bytes', $summary['attachments'][0] ?? []));
        $t->same(1, count($embedded));
        $t->same('plus-header-current.csv', $embedded[0]['filename'] ?? null);
        $t->same($payload, $embedded[0]['content'] ?? null);
        $t->same($checksum, $embedded[0]['checksum'] ?? null);
        $t->true(is_string($summaryJson));
        $t->true(!str_contains($summaryJson, 'plus-header-decoy.csv'));
        $t->true(!str_contains($summaryJson, $payload));
        $t->same(2, $review['compressed_entry_count']);
        $t->same(2, $entry['object_stream_member_count'] ?? null);
        $t->same('explicit_member_index', $entry['selection_policy'] ?? null);
        $t->same(false, $review['executes_python_or_models']);
        $t->same(false, $review['executes_external_pdf_tools']);
    },
];
