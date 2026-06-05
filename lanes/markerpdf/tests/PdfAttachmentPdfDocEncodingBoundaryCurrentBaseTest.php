<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pdfDocEncodingAttachmentPdf = static function (): array {
    $payload = '<wp-export><post id="pdfdoc-encoded-attachment"/></wp-export>';
    $relatedPayload = 'body{font-family:serif}';
    $stalePayload = '<wp-export><post id="stale-pdfdoc-attachment"/></wp-export>';
    $checksum = md5($payload);
    $relatedChecksum = md5($relatedPayload);
    $staleChecksum = md5($stalePayload);
    $content = 'BT /F1 12 Tf 72 720 Td (PDFDoc Attachment Boundary Body) Tj ET';

    $nameKeyBytes = 'Review ' . chr(0x8d) . 'Attachment' . chr(0x8e);
    $filenameBytes = 'WP' . chr(0x80) . '-Import' . chr(0x81) . '.xml';
    $relatedNameBytes = 'review' . chr(0x8d) . 'style' . chr(0x8e) . '.css';
    $staleNameBytes = 'ZZ' . chr(0x80) . 'Stale';

    $nameKeyHex = strtoupper(bin2hex($nameKeyBytes));
    $filenameHex = strtoupper(bin2hex($filenameBytes));
    $relatedNameHex = strtoupper(bin2hex($relatedNameBytes));
    $staleNameHex = strtoupper(bin2hex($staleNameBytes));

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 8 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "6 0 obj\n<< /Limits [<{$nameKeyHex}> <{$nameKeyHex}>] /Names [<{$nameKeyHex}> 10 0 R <{$staleNameHex}> 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (legacy-import.xml) /UF <{$filenameHex}> /Desc (PDFDocEncoding WordPress source) /AFRelationship /Source /EF << /UF 11 0 R >> /RF << /UF [<{$relatedNameHex}> 12 0 R] >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605152458Z) >> /Length " . strlen($payload) . " >>\n"
        . "stream\n{$payload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($relatedPayload) . " /CheckSum <{$relatedChecksum}> >> /Length " . strlen($relatedPayload) . " >>\n"
        . "stream\n{$relatedPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (stale-pdfdoc.xml) /Desc (Stale PDFDocEncoding attachment) /AFRelationship /Alternative /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
        . "stream\n{$stalePayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $payload,
        $relatedPayload,
        $stalePayload,
        $checksum,
        $relatedChecksum,
        "Review \u{201C}Attachment\u{201D}",
        "WP\u{2022}-Import\u{2020}.xml",
        "review\u{201C}style\u{201D}.css",
    ];
};

return [
    'decodes PDFDocEncoding EmbeddedFiles names and FileSpec filenames before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($pdfDocEncodingAttachmentPdf): void {
        [
            $pdf,
            $payload,
            $relatedPayload,
            $stalePayload,
            $checksum,
            $relatedChecksum,
            $expectedNameKey,
            $expectedFilename,
            $expectedRelatedFilename,
        ] = $pdfDocEncodingAttachmentPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same([$expectedFilename], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same($expectedNameKey, $attachment['name_key']);
        $t->same($expectedFilename, $attachment['filename']);
        $t->same('UF', $attachment['filename_source']);
        $t->same('UF', $attachment['ef_key']);
        $t->same($expectedFilename, $attachment['filename_leaf']);
        $t->same('WP--Import-.xml', $attachment['filename_storage_name']);
        $t->same('basename_only', $attachment['filename_path_status']);
        $t->same(false, $attachment['filename_has_path_segments']);
        $t->same('PDFDocEncoding WordPress source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same($checksum, $attachment['checksum_hex']);
        $t->same($checksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $related = $attachment['related_files'][0] ?? null;
        $t->true(is_array($related));
        $t->same('UF', $related['rf_key']);
        $t->same($expectedRelatedFilename, $related['related_filename']);
        $t->same('rf_name_pair', $related['related_filename_source']);
        $t->same($expectedRelatedFilename, $related['related_filename_leaf']);
        $t->same('review-style-.css', $related['related_filename_storage_name']);
        $t->same('text/css', $related['content_type']);
        $t->same(strlen($relatedPayload), $related['byte_length']);
        $t->same($relatedChecksum, $related['checksum_hex']);
        $t->same($relatedChecksum, $related['computed_checksum_hex']);
        $t->same(true, $related['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $related));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same($expectedNameKey, $file['name']);
        $t->same($expectedFilename, $file['filename']);
        $t->same('WP--Import-.xml', $file['filename_storage_name']);
        $t->same($payload, $file['content']);
        $t->same(true, $file['checksum_matches']);
        $t->same($expectedRelatedFilename, $file['related_files'][0]['related_filename'] ?? null);
        $t->same('review-style-.css', $file['related_files'][0]['related_filename_storage_name'] ?? null);

        $t->same('PDFDoc Attachment Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $payload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $relatedPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $stalePayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, 'stale-pdfdoc.xml'));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $relatedPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'stale-pdfdoc'));
    },
];
