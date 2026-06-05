<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;

$embeddedFilesPdf = static function (): array {
    $payload = "Title,Status\nDraft,Ready\n";
    $compressed = gzcompress($payload);
    $checksum = md5($payload);

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Names 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /EmbeddedFiles << /Kids [3 0 R] >> >>\nendobj\n"
        . "3 0 obj\n<< /Limits [(data.csv) (data.csv)] /Names [(data.csv) 4 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Filespec /F (data.csv) /UF (wp-import.csv) /Desc (WordPress import rows) /EF << /F 5 0 R /UF 5 0 R >> >>\nendobj\n"
        . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter /FlateDecode /Params << /Size " . strlen($payload) . " /ModDate (D:20260603082617Z) /CheckSum <{$checksum}> >> /Length " . strlen($compressed) . " >>\n"
        . "stream\n{$compressed}\nendstream\nendobj\n"
        . "%%EOF\n";

    return [$pdf, $payload, $checksum];
};

$fileAttachmentAnnotationPdf = static function (): array {
    $payload = "Needs alt text\n";
    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Annots [4 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [72 700 90 718] /Contents (Reviewer notes) /FS 5 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Type /Filespec /F (review-notes.txt) /Desc (Editorial handoff) /EF << /F 6 0 R >> >>\nendobj\n"
        . "6 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size " . strlen($payload) . " /CreationDate (D:20260603080000Z) >> /Length " . strlen($payload) . " >>\n"
        . "stream\n{$payload}\nendstream\nendobj\n"
        . "%%EOF\n";

    return [$pdf, $payload];
};

$attachmentStreamFilterStackAscii85 = static function (string $bytes): string {
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

    return $encoded . '~>';
};

return [
    'extracts document EmbeddedFiles name tree attachments for WordPress review' => static function (TestRunner $t) use ($embeddedFilesPdf): void {
        [$pdf, $payload, $checksum] = $embeddedFilesPdf();
        $attachments = (new PdfAttachmentExtractor())->extractAttachments($pdf);

        $t->same(1, count($attachments));
        $attachment = $attachments[0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('data.csv', $attachment['name_key']);
        $t->same('wp-import.csv', $attachment['filename']);
        $t->same('WordPress import rows', $attachment['description']);
        $t->same('text/csv', $attachment['content_type']);
        $t->same(strlen($payload), $attachment['declared_size']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same($payload, $attachment['bytes']);
        $t->same(hash('sha256', $payload), $attachment['sha256']);
        $t->same($checksum, $attachment['checksum_hex']);
        $t->same('D:20260603082617Z', $attachment['modified_at']);
        $t->same(false, $attachment['executes_python_or_models']);
        $t->same(false, $attachment['executes_external_pdf_tools']);
    },
    'reports platform FileSpec names relationship and checksum match state in attachment preflight' => static function (TestRunner $t): void {
        $payload = "Title,Status\nLegacy Import,Ready\n";
        $encodedPayload = strtoupper(bin2hex($payload)) . '>';
        $checksum = md5($payload);
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Names [(name-tree-fallback.csv) 4 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /DOS (LEGACY.CSV) /Desc (Legacy import export) /AFRelationship /Supplement /EF << /DOS 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter /ASCIIHexDecode /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /CreationDate (D:20260603091500Z) /ModDate (D:20260603091600Z) >> /Length " . strlen($encodedPayload) . " >>\n"
            . "stream\n{$encodedPayload}\nendstream\nendobj\n"
            . "%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);

        $t->same(1, $summary['attachment_count']);
        $t->same(['LEGACY.CSV'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('name-tree-fallback.csv', $attachment['name_key']);
        $t->same('LEGACY.CSV', $attachment['filename']);
        $t->same('DOS', $attachment['filename_source']);
        $t->same('DOS', $attachment['ef_key']);
        $t->same('Legacy import export', $attachment['description']);
        $t->same('Supplement', $attachment['relationship']);
        $t->same('supplemental_representation', $attachment['relationship_role']);
        $t->same('standard_pdf_associated_file_relationship', $attachment['relationship_status']);
        $t->same('text/csv', $attachment['content_type']);
        $t->same(['ASCIIHexDecode'], $attachment['filters']);
        $t->same(strlen($payload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same($checksum, $attachment['checksum_hex']);
        $t->same($checksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260603091500Z', $attachment['created_at']);
        $t->same('D:20260603091600Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
    },
    'decodes ASCII85 and Flate attachment filter stacks before checksum review' => static function (TestRunner $t) use ($attachmentStreamFilterStackAscii85): void {
        $payload = "Title,Status\nStacked Attachment,Ready\n";
        $encodedPayload = $attachmentStreamFilterStackAscii85(gzcompress($payload));
        $checksum = md5($payload);

        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Names [(stacked.csv) 4 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (stacked.csv) /Desc (Stacked filter attachment) /AFRelationship /Data /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ null /ASCII85Decode /FlateDecode ] /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> >> /Length " . strlen($encodedPayload) . " >>\n"
            . "stream\n{$encodedPayload}\nendstream\nendobj\n"
            . "%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(['stacked.csv'], $summary['filenames']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('stacked.csv', $attachment['name_key']);
        $t->same('stacked.csv', $attachment['filename']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/csv', $attachment['content_type']);
        $t->same(['ASCII85Decode', 'FlateDecode'], $attachment['filters']);
        $t->same(strlen($payload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same(hash('sha256', $payload), $attachment['sha256']);
        $t->same($checksum, $attachment['checksum_hex']);
        $t->same($checksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->true($encodedSummary !== false && !str_contains($encodedSummary, 'Stacked Attachment,Ready'));
        $t->true($encodedSummary !== false && !str_contains($encodedSummary, $payload));
    },
    'fails closed on unsupported attachment DecodeParms before checksum review' => static function (TestRunner $t): void {
        $safePayload = "Title,Status\nDefault Predictor,Ready\n";
        $safeCompressed = gzcompress($safePayload);
        if (!is_string($safeCompressed)) {
            throw new RuntimeException('Unable to compress safe attachment DecodeParms fixture.');
        }

        $unsafePayload = 'RAW_ATTACHMENT_DECODEPARMS_SHOULD_NOT_COUNT';
        $unsafeCompressed = gzcompress($unsafePayload);
        if (!is_string($unsafeCompressed)) {
            throw new RuntimeException('Unable to compress unsafe attachment DecodeParms fixture.');
        }

        $safeHex = strtoupper(bin2hex($safeCompressed)) . '>';
        $unsafeHex = strtoupper(bin2hex($unsafeCompressed)) . '>';
        $safeChecksum = md5($safePayload);
        $unsafeChecksum = md5($unsafePayload);

        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Names [(safe-default.csv) 4 0 R (unsafe-predictor.bin) 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (safe-default.csv) /Desc (Default DecodeParms attachment) /AFRelationship /Data /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Filter [ /ASCIIHexDecode /FlateDecode ] /DecodeParms [ null << /Predictor 1 >> ] /Params << /Size " . strlen($safePayload) . " /CheckSum <{$safeChecksum}> >> /Length " . strlen($safeHex) . " >>\n"
            . "stream\n{$safeHex}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Filespec /F (unsafe-predictor.bin) /Desc (Unsupported DecodeParms attachment) /AFRelationship /Data /EF << /F 7 0 R >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Filter [ /ASCIIHexDecode /FlateDecode ] /DecodeParms [ null << /Predictor 99 /Columns 8 >> ] /Params << /Size " . strlen($unsafePayload) . " /CheckSum <{$unsafeChecksum}> >> /Length " . strlen($unsafeHex) . " >>\n"
            . "stream\n{$unsafeHex}\nendstream\nendobj\n"
            . "%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($safePayload), $summary['total_bytes']);
        $t->same(['safe-default.csv'], $summary['filenames']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('safe-default.csv', $attachment['filename']);
        $t->same('Default DecodeParms attachment', $attachment['description']);
        $t->same(['ASCIIHexDecode', 'FlateDecode'], $attachment['filters']);
        $t->same(strlen($safePayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($safePayload), $attachment['byte_length']);
        $t->same($safeChecksum, $attachment['checksum_hex']);
        $t->same($safeChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->true($encodedSummary !== false && !str_contains($encodedSummary, 'unsafe-predictor.bin'));
        $t->true($encodedSummary !== false && !str_contains($encodedSummary, 'RAW_ATTACHMENT_DECODEPARMS'));
        $t->true($encodedSummary !== false && !str_contains($encodedSummary, $unsafeChecksum));
    },
    'prunes out-of-limits EmbeddedFiles name-tree attachments in WordPress preflight' => static function (TestRunner $t): void {
        $currentPayload = "Title,Status\nCurrent,Ready\n";
        $stalePayload = "Title,Status\nStale,Ignore\n";
        $currentChecksum = md5($currentPayload);
        $staleChecksum = md5($stalePayload);

        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Limits [(current.csv) (current.csv)] /Kids [3 0 R] >>\nendobj\n"
            . "3 0 obj\n<< /Limits [(current.csv) (current.csv)] /Names [(current.csv) 4 0 R (zz-stale.csv) 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (current.csv) /Desc (Current attachment import rows) /AFRelationship /Data /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> >> /Length " . strlen($currentPayload) . " >>\n"
            . "stream\n{$currentPayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Filespec /F (zz-stale.csv) /Desc (Stale out-of-limits import rows) /AFRelationship /Data /EF << /F 7 0 R >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
            . "stream\n{$stalePayload}\nendstream\nendobj\n"
            . "%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same(['current.csv'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('current.csv', $attachment['name_key']);
        $t->same('current.csv', $attachment['filename']);
        $t->same('Current attachment import rows', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/csv', $attachment['content_type']);
        $t->same(strlen($currentPayload), $attachment['byte_length']);
        $t->same($currentChecksum, $attachment['checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->true(is_string($encoded) && !str_contains($encoded, 'zz-stale.csv'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale,Ignore'));
    },
    'keeps first EmbeddedFiles duplicate name-tree key before stale duplicate attachment rows' => static function (TestRunner $t): void {
        $currentPayload = "Title,Status\nDuplicate Key Current,Ready\n";
        $stalePayload = "Title,Status\nDuplicate Key Stale,Ignore\n";
        $currentChecksum = md5($currentPayload);
        $staleChecksum = md5($stalePayload);

        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Names [(review.csv) 4 0 R (review.csv) 6 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (current-review.csv) /Desc (Current duplicate-key attachment rows) /AFRelationship /Data /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605081646Z) >> /Length " . strlen($currentPayload) . " >>\n"
            . "stream\n{$currentPayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Filespec /F (stale-duplicate-review.csv) /Desc (Stale duplicate-key attachment rows) /AFRelationship /Alternative /EF << /F 7 0 R >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
            . "stream\n{$stalePayload}\nendstream\nendobj\n"
            . "%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same(['current-review.csv'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('review.csv', $attachment['name_key']);
        $t->same('current-review.csv', $attachment['filename']);
        $t->same('Current duplicate-key attachment rows', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/csv', $attachment['content_type']);
        $t->same(strlen($currentPayload), $attachment['byte_length']);
        $t->same($currentChecksum, $attachment['checksum_hex']);
        $t->same($currentChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605081646Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-duplicate-review.csv'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Duplicate Key Stale'));
        $t->true(is_string($encoded) && !str_contains($encoded, $currentPayload));
    },
    'resolves indirect EmbeddedFiles name-tree Names arrays in attachment preflight' => static function (TestRunner $t): void {
        $currentPayload = "Title,Status\nIndirect Names Array,Ready\n";
        $stalePayload = "Title,Status\nIndirect Names Stale,Ignore\n";
        $currentChecksum = md5($currentPayload);
        $staleChecksum = md5($stalePayload);

        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Limits [(current-indirect.csv) (current-indirect.csv)] /Names 8 0 R >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (current-indirect.csv) /Desc (Current indirect Names array rows) /AFRelationship /Data /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605015055Z) >> /Length " . strlen($currentPayload) . " >>\n"
            . "stream\n{$currentPayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /Filespec /F (zz-indirect-stale.csv) /Desc (Stale indirect Names array rows) /AFRelationship /Alternative /EF << /F 7 0 R >> >>\nendobj\n"
            . "7 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
            . "stream\n{$stalePayload}\nendstream\nendobj\n"
            . "8 0 obj\n[(current-indirect.csv) 4 0 R (zz-indirect-stale.csv) 6 0 R]\nendobj\n"
            . "%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same(['current-indirect.csv'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('current-indirect.csv', $attachment['name_key']);
        $t->same('current-indirect.csv', $attachment['filename']);
        $t->same('Current indirect Names array rows', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/csv', $attachment['content_type']);
        $t->same(strlen($currentPayload), $attachment['byte_length']);
        $t->same($currentChecksum, $attachment['checksum_hex']);
        $t->same($currentChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605015055Z', $attachment['modified_at']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'zz-indirect-stale.csv'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Indirect Names Stale'));
        $t->true(is_string($encoded) && !str_contains($encoded, $currentPayload));
    },
    'resolves indirect EmbeddedFiles and page-tree Kids arrays in attachment preflight' => static function (TestRunner $t): void {
        $nameTreePayload = "Title,Status\nIndirect Kids Attachment,Ready\n";
        $pagePayload = '<wp-page><attachment role="indirect-kids"/></wp-page>';
        $stalePayload = "Title,Status\nIndirect Kids Stale,Ignore\n";
        $nameTreeChecksum = md5($nameTreePayload);
        $pageChecksum = md5($pagePayload);
        $staleChecksum = md5($stalePayload);

        $pdf = "%PDF-2.0\n";
        $offsets = [];
        $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
        };

        $addObject(1, '<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>');
        $addObject(2, '<< /Type /Pages /Kids 30 0 R /Count 1 >>');
        $addObject(3, '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /AF [20 0 R] >>');
        $addObject(6, '<< /Limits [(kids-current.csv) (kids-current.csv)] /Kids 7 0 R >>');
        $addObject(7, '[8 0 R]');
        $addObject(8, '<< /Limits [(kids-current.csv) (kids-current.csv)] /Names [(kids-current.csv) 10 0 R (zz-kids-stale.csv) 12 0 R] >>');
        $addObject(10, '<< /Type /Filespec /F (kids-current.csv) /Desc (Current indirect Kids rows) /AFRelationship /Data /EF << /F 11 0 R >> >>');
        $addObject(11, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($nameTreePayload) . " /CheckSum <{$nameTreeChecksum}> /ModDate (D:20260605054817Z) >> /Length " . strlen($nameTreePayload) . " >>\nstream\n{$nameTreePayload}\nendstream");
        $addObject(12, '<< /Type /Filespec /F (zz-kids-stale.csv) /Desc (Stale indirect Kids rows) /AFRelationship /Alternative /EF << /F 13 0 R >> >>');
        $addObject(13, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\nstream\n{$stalePayload}\nendstream");
        $addObject(20, '<< /Type /Filespec /F (page-kids-source.xml) /Desc (Page source through indirect Kids) /AFRelationship /Source /EF << /F 21 0 R >> >>');
        $addObject(21, "<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pagePayload) . " /CheckSum <{$pageChecksum}> /ModDate (D:20260605054818Z) >> /Length " . strlen($pagePayload) . " >>\nstream\n{$pagePayload}\nendstream");
        $addObject(30, '[3 0 R]');

        $xrefOffset = strlen($pdf);
        $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
        $pdf .= "xref\n0 31\n" . $xrefRow(0, 65535, 'f');
        for ($objectNumber = 1; $objectNumber <= 30; $objectNumber++) {
            $pdf .= isset($offsets[$objectNumber])
                ? $xrefRow($offsets[$objectNumber])
                : $xrefRow(0, 0, 'f');
        }
        $pdf .= "trailer\n<< /Size 31 /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(strlen($nameTreePayload) + strlen($pagePayload), $summary['total_bytes']);
        $t->same(['kids-current.csv', 'page-kids-source.xml'], $summary['filenames']);

        $nameTreeAttachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $nameTreeAttachment['source']);
        $t->same('kids-current.csv', $nameTreeAttachment['name_key']);
        $t->same('kids-current.csv', $nameTreeAttachment['filename']);
        $t->same('Current indirect Kids rows', $nameTreeAttachment['description']);
        $t->same('Data', $nameTreeAttachment['relationship']);
        $t->same('base_data_for_visual_presentation', $nameTreeAttachment['relationship_role']);
        $t->same('text/csv', $nameTreeAttachment['content_type']);
        $t->same(strlen($nameTreePayload), $nameTreeAttachment['byte_length']);
        $t->same($nameTreeChecksum, $nameTreeAttachment['checksum_hex']);
        $t->same($nameTreeChecksum, $nameTreeAttachment['computed_checksum_hex']);
        $t->same(true, $nameTreeAttachment['checksum_matches']);
        $t->same('D:20260605054817Z', $nameTreeAttachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $nameTreeAttachment));

        $pageAttachment = $summary['attachments'][1];
        $t->same('page-associated-file', $pageAttachment['source']);
        $t->same(true, $pageAttachment['associated_file']);
        $t->same(true, $pageAttachment['page_associated_file']);
        $t->same(1, $pageAttachment['page_number']);
        $t->same(3, $pageAttachment['page_object_id']);
        $t->same(0, $pageAttachment['page_associated_file_index']);
        $t->same('page-kids-source.xml', $pageAttachment['filename']);
        $t->same('Page source through indirect Kids', $pageAttachment['description']);
        $t->same('Source', $pageAttachment['relationship']);
        $t->same('original_source', $pageAttachment['relationship_role']);
        $t->same('text/xml', $pageAttachment['content_type']);
        $t->same(strlen($pagePayload), $pageAttachment['byte_length']);
        $t->same($pageChecksum, $pageAttachment['checksum_hex']);
        $t->same($pageChecksum, $pageAttachment['computed_checksum_hex']);
        $t->same(true, $pageAttachment['checksum_matches']);
        $t->same('D:20260605054818Z', $pageAttachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $pageAttachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'zz-kids-stale.csv'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Indirect Kids Stale'));
        $t->true(is_string($encoded) && !str_contains($encoded, $nameTreePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $pagePayload));
    },
    'summarizes catalog associated FileSpec attachments and dedupes EmbeddedFiles mirrors' => static function (TestRunner $t): void {
        $sourcePayload = '<wp-export><post id="catalog-af"/></wp-export>';
        $sourceChecksum = md5($sourcePayload);

        $catalogOnlyPdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AF [4 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /EF << /F 5 0 R /UF 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260604174658Z) >> /Length " . strlen($sourcePayload) . " >>\n"
            . "stream\n{$sourcePayload}\nendstream\nendobj\n"
            . "%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($catalogOnlyPdf);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($sourcePayload), $summary['total_bytes']);
        $t->same(['source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('catalog-associated-file', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same(0, $attachment['associated_file_index']);
        $t->same(1, $attachment['catalog_object_id']);
        $t->same(4, $attachment['file_spec_object_id']);
        $t->same(5, $attachment['stream_object_id']);
        $t->same('UF', $attachment['ef_key']);
        $t->same('source.xml', $attachment['filename']);
        $t->same('UF', $attachment['filename_source']);
        $t->same('Original WordPress export', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($sourcePayload), $attachment['byte_length']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same($sourceChecksum, $attachment['checksum_hex']);
        $t->same($sourceChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260604174658Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $mirroredPdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [4 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Mirrored associated source) /AFRelationship /Source /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> >> /Length " . strlen($sourcePayload) . " >>\n"
            . "stream\n{$sourcePayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(source.xml) 4 0 R] >>\nendobj\n"
            . "%%EOF\n";

        $mirrored = (new PdfAttachmentExtractor())->attachmentSummary($mirroredPdf);
        $mirroredEncoded = json_encode($mirrored, JSON_UNESCAPED_SLASHES);

        $t->same(1, $mirrored['attachment_count']);
        $t->same(['source.xml'], $mirrored['filenames']);
        $t->same('embedded-files-name-tree', $mirrored['attachments'][0]['source']);
        $t->same('source.xml', $mirrored['attachments'][0]['name_key']);
        $t->same(true, $mirrored['attachments'][0]['associated_file']);
        $t->same('catalog_af', $mirrored['attachments'][0]['associated_file_source']);
        $t->same(0, $mirrored['attachments'][0]['associated_file_index']);
        $t->same(1, $mirrored['attachments'][0]['catalog_object_id']);
        $t->same(strlen($sourcePayload), $mirrored['attachments'][0]['byte_length']);
        $t->same(false, array_key_exists('bytes', $mirrored['attachments'][0]));
        $t->same(false, $mirrored['executes_python_or_models']);
        $t->same(false, $mirrored['executes_external_pdf_tools']);
        $t->true(is_string($mirroredEncoded) && substr_count($mirroredEncoded, 'source.xml') >= 1);
        $t->true(is_string($mirroredEncoded) && !str_contains($mirroredEncoded, $sourcePayload));
    },
    'summarizes page associated FileSpec attachments and marks EmbeddedFiles mirrors' => static function (TestRunner $t): void {
        $pagePayload = '<wp-page><attachment role="page-associated"/></wp-page>';
        $pageChecksum = md5($pagePayload);

        $pageOnlyPdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /AF [4 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (page-source.xml) /Desc (Page source export) /AFRelationship /Source /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pagePayload) . " /CheckSum <{$pageChecksum}> /ModDate (D:20260604210100Z) >> /Length " . strlen($pagePayload) . " >>\n"
            . "stream\n{$pagePayload}\nendstream\nendobj\n"
            . "%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pageOnlyPdf);

        $t->same(1, $summary['attachment_count']);
        $t->same(['page-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('page-associated-file', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same(true, $attachment['page_associated_file']);
        $t->same(1, $attachment['page_number']);
        $t->same(3, $attachment['page_object_id']);
        $t->same(0, $attachment['page_associated_file_index']);
        $t->same(4, $attachment['file_spec_object_id']);
        $t->same(5, $attachment['stream_object_id']);
        $t->same('page-source.xml', $attachment['filename']);
        $t->same('Page source export', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($pagePayload), $attachment['byte_length']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same($pageChecksum, $attachment['checksum_hex']);
        $t->same($pageChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260604210100Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $mirroredPdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /AF [4 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (page-source.xml) /Desc (Mirrored page source export) /AFRelationship /Source /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pagePayload) . " /CheckSum <{$pageChecksum}> >> /Length " . strlen($pagePayload) . " >>\n"
            . "stream\n{$pagePayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(page-source.xml) 4 0 R] >>\nendobj\n"
            . "%%EOF\n";

        $mirrored = (new PdfAttachmentExtractor())->attachmentSummary($mirroredPdf);
        $mirroredEncoded = json_encode($mirrored, JSON_UNESCAPED_SLASHES);

        $t->same(1, $mirrored['attachment_count']);
        $t->same(['page-source.xml'], $mirrored['filenames']);
        $t->same('embedded-files-name-tree', $mirrored['attachments'][0]['source']);
        $t->same(true, $mirrored['attachments'][0]['page_associated_file']);
        $t->same('page_af', $mirrored['attachments'][0]['page_associated_file_source']);
        $t->same(1, $mirrored['attachments'][0]['page_number']);
        $t->same(3, $mirrored['attachments'][0]['page_object_id']);
        $t->same(0, $mirrored['attachments'][0]['page_associated_file_index']);
        $t->same(strlen($pagePayload), $mirrored['attachments'][0]['byte_length']);
        $t->same(false, array_key_exists('bytes', $mirrored['attachments'][0]));
        $t->true(is_string($mirroredEncoded) && substr_count($mirroredEncoded, 'page-source.xml') >= 1);
        $t->true(is_string($mirroredEncoded) && !str_contains($mirroredEncoded, $pagePayload));
    },
    'marks FileAttachment annotation mirrors without duplicating EmbeddedFiles payload summaries' => static function (TestRunner $t): void {
        $payload = "Title,Status\nAnnotation Mirror,Ready\n";
        $checksum = md5($payload);

        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Annots [8 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (review-mirror.csv) /Desc (Annotation mirrored import rows) /AFRelationship /Data /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605022515Z) >> /Length " . strlen($payload) . " >>\n"
            . "stream\n{$payload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(review-mirror.csv) 4 0 R] >>\nendobj\n"
            . "8 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [144 660 168 684] /Contents (Mirrored WordPress reviewer note) /FS 4 0 R >>\nendobj\n"
            . "%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(['review-mirror.csv'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('review-mirror.csv', $attachment['name_key']);
        $t->same('review-mirror.csv', $attachment['filename']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same(true, $attachment['file_attachment_annotation']);
        $t->same('page_annotation', $attachment['file_attachment_annotation_source']);
        $t->same(1, $attachment['page_number']);
        $t->same(3, $attachment['page_object_id']);
        $t->same(8, $attachment['annotation_object_id']);
        $t->same('Mirrored WordPress reviewer note', $attachment['annotation_contents']);
        $t->same([144.0, 660.0, 168.0, 684.0], $attachment['annotation_rect']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same($checksum, $attachment['checksum_hex']);
        $t->same($checksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605022515Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && substr_count($encoded, 'review-mirror.csv') >= 1);
        $t->true(is_string($encoded) && !str_contains($encoded, $payload));
    },
    'dedupes direct FileSpec mirrors across EmbeddedFiles AF and annotations' => static function (TestRunner $t): void {
        $payload = '<wp-export><post id="direct-filespec-mirror"/></wp-export>';
        $checksum = md5($payload);
        $fileSpec = '<< /Type /Filespec /F (direct-source.xml) /Desc (Direct FileSpec mirror export) /AFRelationship /Source /EF << /F 5 0 R >> >>';

        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [{$fileSpec}] >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /AF [{$fileSpec}] /Annots [8 0 R] >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605025837Z) >> /Length " . strlen($payload) . " >>\n"
            . "stream\n{$payload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Names [(direct-source.xml) {$fileSpec}] >>\nendobj\n"
            . "8 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [180 640 202 662] /Contents (Direct FileSpec mirrored note) /FS {$fileSpec} >>\nendobj\n"
            . "%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(['direct-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('direct-source.xml', $attachment['name_key']);
        $t->same(null, $attachment['file_spec_object_id']);
        $t->same(5, $attachment['stream_object_id']);
        $t->same('direct-source.xml', $attachment['filename']);
        $t->same('Direct FileSpec mirror export', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same($checksum, $attachment['checksum_hex']);
        $t->same($checksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same(0, $attachment['associated_file_index']);
        $t->same(true, $attachment['page_associated_file']);
        $t->same('page_af', $attachment['page_associated_file_source']);
        $t->same(1, $attachment['page_number']);
        $t->same(3, $attachment['page_object_id']);
        $t->same(0, $attachment['page_associated_file_index']);
        $t->same(true, $attachment['file_attachment_annotation']);
        $t->same('page_annotation', $attachment['file_attachment_annotation_source']);
        $t->same(8, $attachment['annotation_object_id']);
        $t->same('Direct FileSpec mirrored note', $attachment['annotation_contents']);
        $t->same([180.0, 640.0, 202.0, 662.0], $attachment['annotation_rect']);
        $t->same('D:20260605025837Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && substr_count($encoded, 'direct-source.xml') >= 1);
        $t->true(is_string($encoded) && !str_contains($encoded, $payload));
    },
    'dedupes associated FileSpec mirrors when EmbeddedFiles name tree is absent' => static function (TestRunner $t): void {
        $payload = '<wp-export><post id="associated-filespec-mirror"/></wp-export>';
        $checksum = md5($payload);

        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AF [4 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
            . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /AF [4 0 R] /Annots [8 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (shared-source.xml) /Desc (Shared associated source export) /AFRelationship /Source /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605051156Z) >> /Length " . strlen($payload) . " >>\n"
            . "stream\n{$payload}\nendstream\nendobj\n"
            . "8 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [210 620 232 642] /Contents (Shared associated FileSpec note) /FS 4 0 R >>\nendobj\n"
            . "%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(['shared-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('catalog-associated-file', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same(0, $attachment['associated_file_index']);
        $t->same(1, $attachment['catalog_object_id']);
        $t->same(true, $attachment['page_associated_file']);
        $t->same('page_af', $attachment['page_associated_file_source']);
        $t->same(1, $attachment['page_number']);
        $t->same(3, $attachment['page_object_id']);
        $t->same(0, $attachment['page_associated_file_index']);
        $t->same(true, $attachment['file_attachment_annotation']);
        $t->same('page_annotation', $attachment['file_attachment_annotation_source']);
        $t->same(8, $attachment['annotation_object_id']);
        $t->same('Shared associated FileSpec note', $attachment['annotation_contents']);
        $t->same([210.0, 620.0, 232.0, 642.0], $attachment['annotation_rect']);
        $t->same(4, $attachment['file_spec_object_id']);
        $t->same(5, $attachment['stream_object_id']);
        $t->same('shared-source.xml', $attachment['filename']);
        $t->same('Shared associated source export', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same($checksum, $attachment['checksum_hex']);
        $t->same($checksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605051156Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && substr_count($encoded, 'shared-source.xml') >= 1);
        $t->true(is_string($encoded) && !str_contains($encoded, $payload));
    },
    'summarizes related-file streams in WordPress attachment preflight without bytes' => static function (TestRunner $t): void {
        $sourcePayload = '<wp-export><post id="preflight-related"/></wp-export>';
        $relatedJson = '{"review":"preflight-related"}';
        $relatedText = 'BT /F1 12 Tf 72 720 Td (Preflight Related File Leak) Tj ET';
        $compressedJson = gzcompress($relatedJson);
        if (!is_string($compressedJson)) {
            throw new RuntimeException('Unable to compress preflight related-file fixture.');
        }

        $sourceChecksum = md5($sourcePayload);
        $relatedJsonChecksum = md5($relatedJson);
        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Names [(source.xml) 4 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Source with related preflight files) /AFRelationship /Source /EF << /F 5 0 R >> /RF << /F [6 0 R 7 0 R] >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> >> /Length " . strlen($sourcePayload) . " >>\n"
            . "stream\n{$sourcePayload}\nendstream\nendobj\n"
            . "6 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Filter /FlateDecode /Params << /Size " . strlen($relatedJson) . " /CheckSum <{$relatedJsonChecksum}> /CreationDate (D:20260604195521Z) >> /Length " . strlen($compressedJson) . " >>\n"
            . "stream\n{$compressedJson}\nendstream\nendobj\n"
            . "7 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Length " . strlen($relatedText) . " >>\n"
            . "stream\n{$relatedText}\nendstream\nendobj\n"
            . "%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('source.xml', $attachment['filename']);
        $t->same('Source', $attachment['relationship']);
        $t->same(2, $attachment['related_file_count']);

        $related = $attachment['related_files'];
        $t->same('filespec_related_files', $related[0]['source']);
        $t->same('F', $related[0]['rf_key']);
        $t->same(0, $related[0]['related_file_index']);
        $t->same(6, $related[0]['stream_object_id']);
        $t->same('application/json', $related[0]['content_type']);
        $t->same(['FlateDecode'], $related[0]['filters']);
        $t->same(strlen($relatedJson), $related[0]['byte_length']);
        $t->same(hash('sha256', $relatedJson), $related[0]['sha256']);
        $t->same($relatedJsonChecksum, $related[0]['checksum_hex']);
        $t->same($relatedJsonChecksum, $related[0]['computed_checksum_hex']);
        $t->same(true, $related[0]['checksum_matches']);
        $t->same('D:20260604195521Z', $related[0]['created_at']);
        $t->same(false, array_key_exists('bytes', $related[0]));

        $t->same(7, $related[1]['stream_object_id']);
        $t->same('text/plain', $related[1]['content_type']);
        $t->same(strlen($relatedText), $related[1]['byte_length']);
        $t->same(hash('sha256', $relatedText), $related[1]['sha256']);
        $t->same(false, array_key_exists('bytes', $related[1]));

        $t->same(false, array_key_exists('bytes', $attachment));
        $t->true(is_string($encoded) && !str_contains($encoded, $relatedJson));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Preflight Related File Leak'));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
    },
    'bounds attachment preflight object scanning at terminal EOF before stale appended FileSpecs' => static function (TestRunner $t): void {
        $currentPayload = "Title,Status\nCurrent EOF Attachment,Ready\n";
        $stalePayload = "Title,Status\nStale EOF Attachment,Ignore\n";
        $currentChecksum = md5($currentPayload);
        $staleChecksum = md5($stalePayload);

        $pdf = "%PDF-2.0\n"
            . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>\nendobj\n"
            . "2 0 obj\n<< /Names [(current-eof.csv) 4 0 R] >>\nendobj\n"
            . "4 0 obj\n<< /Type /Filespec /F (current-eof.csv) /Desc (Current EOF-bounded attachment rows) /AFRelationship /Data /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260604234038Z) >> /Length " . strlen($currentPayload) . " >>\n"
            . "stream\n{$currentPayload}\nendstream\nendobj\n"
            . "%%EOF\n"
            . "4 0 obj\n<< /Type /Filespec /F (stale-eof.csv) /Desc (Stale appended attachment rows) /AFRelationship /Alternative /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
            . "stream\n{$stalePayload}\nendstream\nendobj\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same(['current-eof.csv'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('current-eof.csv', $attachment['name_key']);
        $t->same('current-eof.csv', $attachment['filename']);
        $t->same('Current EOF-bounded attachment rows', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/csv', $attachment['content_type']);
        $t->same(strlen($currentPayload), $attachment['byte_length']);
        $t->same($currentChecksum, $attachment['checksum_hex']);
        $t->same($currentChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260604234038Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-eof.csv'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale EOF Attachment'));
    },
    'uses current xref rows before stale same-object attachment preflight rows' => static function (TestRunner $t): void {
        $currentPayload = "Title,Status\nCurrent XRef Attachment,Ready\n";
        $stalePayload = "Title,Status\nStale XRef Attachment,Ignore\n";
        $currentChecksum = md5($currentPayload);
        $staleChecksum = md5($stalePayload);

        $pdf = "%PDF-1.7\n";
        $offsets = [];
        $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
        };

        $addObject(1, '<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> /AF [4 0 R] >>');
        $addObject(2, '<< /Names [(current-xref.csv) 4 0 R] >>');
        $addObject(4, '<< /Type /Filespec /F (current-xref.csv) /Desc (Current xref-selected attachment rows) /AFRelationship /Data /EF << /F 5 0 R >> >>');
        $addObject(5, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605001158Z) >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

        $pdf .= "4 0 obj\n<< /Type /Filespec /F (stale-xref.csv) /Desc (Stale unindexed attachment rows) /AFRelationship /Alternative /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
            . "stream\n{$stalePayload}\nendstream\nendobj\n";

        $xrefOffset = strlen($pdf);
        $xrefRow = static fn (int $offset, int $generation = 0, string $state = 'n'): string => sprintf("%010d %05d %s \n", $offset, $generation, $state);
        $pdf .= "xref\n0 6\n"
            . $xrefRow(0, 65535, 'f')
            . $xrefRow($offsets[1])
            . $xrefRow($offsets[2])
            . $xrefRow(0, 0, 'f')
            . $xrefRow($offsets[4])
            . $xrefRow($offsets[5])
            . "trailer\n<< /Size 6 /Root 1 0 R >>\n"
            . "startxref\n{$xrefOffset}\n%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same(['current-xref.csv'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('current-xref.csv', $attachment['name_key']);
        $t->same('current-xref.csv', $attachment['filename']);
        $t->same('Current xref-selected attachment rows', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/csv', $attachment['content_type']);
        $t->same(strlen($currentPayload), $attachment['byte_length']);
        $t->same($currentChecksum, $attachment['checksum_hex']);
        $t->same($currentChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605001158Z', $attachment['modified_at']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-xref.csv'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale XRef Attachment'));
    },
    'uses current xref stream rows before stale same-object attachment preflight rows' => static function (TestRunner $t): void {
        $currentPayload = "Title,Status\nCurrent XRef Stream Attachment,Ready\n";
        $stalePayload = "Title,Status\nStale XRef Stream Attachment,Ignore\n";
        $currentChecksum = md5($currentPayload);
        $staleChecksum = md5($stalePayload);

        $pdf = "%PDF-1.7\n";
        $offsets = [];
        $addObject = static function (int $objectNumber, string $body) use (&$pdf, &$offsets): void {
            $offsets[$objectNumber] = strlen($pdf);
            $pdf .= "{$objectNumber} 0 obj\n{$body}\nendobj\n";
        };

        $addObject(1, '<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> >>');
        $addObject(2, '<< /Names [(current-xref-stream.csv) 4 0 R] >>');
        $addObject(4, '<< /Type /Filespec /F (current-xref-stream.csv) /Desc (Current xref-stream-selected attachment rows) /AFRelationship /Source /EF << /F 5 0 R >> >>');
        $addObject(5, "<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605001201Z) >> /Length " . strlen($currentPayload) . " >>\nstream\n{$currentPayload}\nendstream");

        $pdf .= "4 0 obj\n<< /Type /Filespec /F (stale-xref-stream.csv) /Desc (Stale xref-stream attachment rows) /AFRelationship /Alternative /EF << /F 5 0 R >> >>\nendobj\n"
            . "5 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
            . "stream\n{$stalePayload}\nendstream\nendobj\n";

        $xrefOffset = strlen($pdf);
        $rows = '';
        for ($objectNumber = 0; $objectNumber < 10; $objectNumber++) {
            if ($objectNumber === 0 || (!isset($offsets[$objectNumber]) && $objectNumber !== 9)) {
                $rows .= pack('CNn', 0, 0, $objectNumber === 0 ? 65535 : 0);
                continue;
            }

            $rows .= pack('CNn', 1, $objectNumber === 9 ? $xrefOffset : $offsets[$objectNumber], 0);
        }

        $compressedXref = gzcompress($rows);
        if (!is_string($compressedXref)) {
            throw new RuntimeException('Unable to compress attachment xref stream fixture.');
        }

        $pdf .= "9 0 obj\n"
            . '<< /Type /XRef /Size 10 /Root 1 0 R /W [1 4 2] /Filter /FlateDecode /Length ' . strlen($compressedXref) . " >>\n"
            . "stream\n{$compressedXref}\nendstream\nendobj\n"
            . "startxref\n{$xrefOffset}\n%%EOF\n";

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['current-xref-stream.csv'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('current-xref-stream.csv', $attachment['name_key']);
        $t->same('current-xref-stream.csv', $attachment['filename']);
        $t->same('Current xref-stream-selected attachment rows', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same(strlen($currentPayload), $attachment['byte_length']);
        $t->same($currentChecksum, $attachment['checksum_hex']);
        $t->same($currentChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605001201Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->true(is_string($encoded) && !str_contains($encoded, 'stale-xref-stream.csv'));
        $t->true(is_string($encoded) && !str_contains($encoded, 'Stale XRef Stream Attachment'));
    },
    'extracts page FileAttachment annotation embedded streams with page metadata' => static function (TestRunner $t) use ($fileAttachmentAnnotationPdf): void {
        [$pdf, $payload] = $fileAttachmentAnnotationPdf();
        $attachments = (new PdfAttachmentExtractor())->extractAttachments($pdf);

        $t->same(1, count($attachments));
        $attachment = $attachments[0];
        $t->same('file-attachment-annotation', $attachment['source']);
        $t->same(1, $attachment['page_number']);
        $t->same(3, $attachment['page_object_id']);
        $t->same(4, $attachment['annotation_object_id']);
        $t->same([72.0, 700.0, 90.0, 718.0], $attachment['annotation_rect']);
        $t->same('Reviewer notes', $attachment['annotation_contents']);
        $t->same('review-notes.txt', $attachment['filename']);
        $t->same('Editorial handoff', $attachment['description']);
        $t->same('text/plain', $attachment['content_type']);
        $t->same('D:20260603080000Z', $attachment['created_at']);
        $t->same($payload, $attachment['bytes']);
    },
    'summarizes attachments without exposing bytes in WordPress preflight payloads' => static function (TestRunner $t) use ($embeddedFilesPdf): void {
        [$pdf, $payload] = $embeddedFilesPdf();
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(['wp-import.csv'], $summary['filenames']);
        $t->same(false, array_key_exists('bytes', $summary['attachments'][0]));
        $t->same(hash('sha256', $payload), $summary['attachments'][0]['sha256']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
    },
    'ignores external file specifications and non-attachment streams' => static function (TestRunner $t): void {
        $image = "not an embedded file attachment";
        $pdf = "%PDF-1.7\n"
            . "1 0 obj\n<< /Type /Catalog /Names << /EmbeddedFiles 2 0 R >> >>\nendobj\n"
            . "2 0 obj\n<< /Names [(external.txt) 3 0 R] >>\nendobj\n"
            . "3 0 obj\n<< /Type /Filespec /F (external.txt) >>\nendobj\n"
            . "4 0 obj\n<< /Type /XObject /Subtype /Image /Length " . strlen($image) . " >>\nstream\n{$image}\nendstream\nendobj\n"
            . "%%EOF\n";

        $extractor = new PdfAttachmentExtractor();
        $t->same([], $extractor->extractAttachments($pdf));
        $t->throws(InvalidArgumentException::class, static fn (): array => $extractor->extractAttachments('not a pdf'));
    },
];
