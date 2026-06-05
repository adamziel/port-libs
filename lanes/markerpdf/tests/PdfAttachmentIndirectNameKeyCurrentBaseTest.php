<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentIndirectNameKeyPdf = static function (): array {
    $payload = "Title,Status\nIndirect Name Key,Ready\n";
    $stalePayload = "Title,Status\nStale Name Key,Ignore\n";
    $checksum = md5($payload);
    $staleChecksum = md5($stalePayload);
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Visible Indirect Attachment Boundary) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Limits [8 0 R 8 0 R] /Names [8 0 R 10 0 R (zz-stale-key.csv) 12 0 R] >>\nendobj\n"
        . "8 0 obj\n(indirect-key.csv)\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (legacy-indirect-key.csv) /UF (indirect-key.csv) /Desc (Indirect name-key WordPress rows) /AFRelationship /Data /EF << /UF 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605093241Z) >> /Length " . strlen($payload) . " >>\n"
        . "stream\n{$payload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /Filespec /F (zz-stale-key.csv) /Desc (Stale out-of-limits name-key rows) /AFRelationship /Alternative /EF << /F 13 0 R >> >>\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
        . "stream\n{$stalePayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $payload, $stalePayload, $checksum];
};

return [
    'resolves indirect EmbeddedFiles name-tree string keys in attachment preflight' => static function (
        TestRunner $t
    ) use ($attachmentIndirectNameKeyPdf): void {
        [$pdf, $payload, $stalePayload, $checksum] = $attachmentIndirectNameKeyPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $encodedSummary = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($payload), $summary['total_bytes']);
        $t->same(['indirect-key.csv'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('indirect-key.csv', $attachment['name_key']);
        $t->same('indirect-key.csv', $attachment['filename']);
        $t->same('UF', $attachment['filename_source']);
        $t->same('UF', $attachment['ef_key']);
        $t->same('Indirect name-key WordPress rows', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/csv', $attachment['content_type']);
        $t->same(strlen($payload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same(hash('sha256', $payload), $attachment['sha256']);
        $t->same($checksum, $attachment['checksum_hex']);
        $t->same($checksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605093241Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $t->same('catalog_names_embedded_files', $files[0]['source']);
        $t->same('indirect-key.csv', $files[0]['name']);
        $t->same('indirect-key.csv', $files[0]['filename']);
        $t->same($payload, $files[0]['content']);
        $t->same(true, $files[0]['checksum_matches']);

        $t->same('Visible Indirect Attachment Boundary', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $payload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, $stalePayload));
        $t->true(is_string($encodedSummary) && !str_contains($encodedSummary, 'zz-stale-key.csv'));
        $t->true(!str_contains($plainText, 'Indirect Name Key'));
        $t->true(!str_contains($plainText, 'Stale Name Key'));
    },
];
