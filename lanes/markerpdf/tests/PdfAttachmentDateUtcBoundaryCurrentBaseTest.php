<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentDateUtcBoundaryPdf = static function (): array {
    $sourcePayload = '<wp-export><post id="attachment-date-utc"/></wp-export>';
    $relatedPayload = '{"preview":"attachment-date-utc"}';
    $sourceChecksum = md5($sourcePayload);
    $relatedChecksum = md5($relatedPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Attachment Date Boundary Body) Tj ET';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(date-source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (date-source.xml) /Desc (Date-normalized source export) /AFRelationship /Source /RF << /F [(preview.json) 12 0 R] >> /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /CreationDate (D:20260605221530-07'30') /ModDate (D:20260606011530+05'45') >> /Length " . strlen($sourcePayload) . " >>\n"
        . "stream\n{$sourcePayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($relatedPayload) . " /CheckSum <{$relatedChecksum}> /CreationDate (D:20260605230000Z) /ModDate (D:20260605231631) >> /Length " . strlen($relatedPayload) . " >>\n"
        . "stream\n{$relatedPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $sourcePayload, $relatedPayload, $sourceChecksum, $relatedChecksum];
};

return [
    'normalizes timezone-bearing EmbeddedFile Params dates for attachment review only' => static function (
        TestRunner $t
    ) use ($attachmentDateUtcBoundaryPdf): void {
        [$pdf, $sourcePayload, $relatedPayload, $sourceChecksum, $relatedChecksum] = $attachmentDateUtcBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['date-source.xml'], $summary['filenames']);
        $t->same(strlen($sourcePayload), $summary['total_bytes']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('date-source.xml', $attachment['filename']);
        $t->same('Date-normalized source export', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('D:20260605221530-07\'30\'', $attachment['created_at']);
        $t->same('2026-06-06T05:45:30Z', $attachment['created_at_utc']);
        $t->same('D:20260606011530+05\'45\'', $attachment['modified_at']);
        $t->same('2026-06-05T19:30:30Z', $attachment['modified_at_utc']);
        $t->same($sourceChecksum, $attachment['checksum_hex']);
        $t->same($sourceChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $related = $attachment['related_files'][0] ?? [];
        $t->same(1, $attachment['related_file_count'] ?? null);
        $t->same('filespec_related_files', $related['source'] ?? null);
        $t->same('preview.json', $related['related_filename'] ?? null);
        $t->same('D:20260605230000Z', $related['created_at'] ?? null);
        $t->same('2026-06-05T23:00:00Z', $related['created_at_utc'] ?? null);
        $t->same('D:20260605231631', $related['modified_at'] ?? null);
        $t->same(false, array_key_exists('modified_at_utc', $related));
        $t->same($relatedChecksum, $related['checksum_hex'] ?? null);
        $t->same($relatedChecksum, $related['computed_checksum_hex'] ?? null);
        $t->same(true, $related['checksum_matches'] ?? null);

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('date-source.xml', $file['filename']);
        $t->same('D:20260605221530-07\'30\'', $file['created_at']);
        $t->same('2026-06-06T05:45:30Z', $file['created_at_utc']);
        $t->same('D:20260606011530+05\'45\'', $file['modified_at']);
        $t->same('2026-06-05T19:30:30Z', $file['modified_at_utc']);
        $t->same($sourcePayload, $file['content']);
        $t->same(true, $file['checksum_matches']);
        $fileRelated = $file['related_files'][0] ?? [];
        $t->same('D:20260605230000Z', $fileRelated['created_at'] ?? null);
        $t->same('2026-06-05T23:00:00Z', $fileRelated['created_at_utc'] ?? null);
        $t->same('D:20260605231631', $fileRelated['modified_at'] ?? null);
        $t->same(false, array_key_exists('modified_at_utc', $fileRelated));

        $t->same('Attachment Date Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $sourcePayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $relatedPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'attachment-date-utc'));
    },
];
