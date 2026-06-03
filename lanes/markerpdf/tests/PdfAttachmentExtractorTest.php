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
