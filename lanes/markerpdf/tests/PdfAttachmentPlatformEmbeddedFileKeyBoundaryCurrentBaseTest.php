<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$platformEmbeddedFileKeyPdf = static function (): array {
    $currentPayload = '<wp-export><post id="dos-current-ef"/></wp-export>';
    $stalePayload = '<wp-export><post id="fallback-f-stale"/></wp-export>';
    $currentChecksum = md5($currentPayload);
    $staleChecksum = md5($stalePayload);
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Platform EF Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(legacy-dos-source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /DOS (LEGACY\\\\WP-SOURCE.XML) /Desc (DOS-specific WordPress source packet) /AFRelationship /Source /EF << /F 11 0 R /DOS 12 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> /ModDate (D:20260605091200Z) >> /Length " . strlen($stalePayload) . " >>\n"
        . "stream\n{$stalePayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260605091230Z) >> /Length " . strlen($currentPayload) . " >>\n"
        . "stream\n{$currentPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $currentPayload, $stalePayload, $currentChecksum, $staleChecksum];
};

return [
    'selects platform-matched EF stream for DOS FileSpec attachment preflight' => static function (
        TestRunner $t
    ) use ($platformEmbeddedFileKeyPdf): void {
        [$pdf, $currentPayload, $stalePayload, $currentChecksum, $staleChecksum] = $platformEmbeddedFileKeyPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($currentPayload), $summary['total_bytes']);
        $t->same(['LEGACY\\WP-SOURCE.XML'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('legacy-dos-source.xml', $attachment['name_key']);
        $t->same('LEGACY\\WP-SOURCE.XML', $attachment['filename']);
        $t->same('DOS', $attachment['filename_source']);
        $t->same('DOS', $attachment['ef_key']);
        $t->same('WP-SOURCE.XML', $attachment['filename_leaf']);
        $t->same('WP-SOURCE.XML', $attachment['filename_storage_name']);
        $t->same('relative_path_segments_review_only', $attachment['filename_path_status']);
        $t->same(true, $attachment['filename_has_path_segments']);
        $t->same('DOS-specific WordPress source packet', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($currentPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($currentPayload), $attachment['byte_length']);
        $t->same(hash('sha256', $currentPayload), $attachment['sha256']);
        $t->same($currentChecksum, $attachment['checksum_hex']);
        $t->same($currentChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605091230Z', $attachment['modified_at']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($encoded) && !str_contains($encoded, $currentPayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $stalePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $staleChecksum));
    },
    'selects platform-matched EF stream for embedded-file review rows' => static function (
        TestRunner $t
    ) use ($platformEmbeddedFileKeyPdf): void {
        [$pdf, $currentPayload, $stalePayload, $currentChecksum, $staleChecksum] = $platformEmbeddedFileKeyPdf();

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $encoded = json_encode($files, JSON_UNESCAPED_SLASHES);
        $plainText = (new PdfTextExtractor())->extractPlainText($pdf);

        $file = null;
        foreach ($files as $candidate) {
            if (($candidate['source'] ?? null) === 'catalog_names_embedded_files') {
                $file = $candidate;
                break;
            }
        }

        $t->true(is_array($file));
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('legacy-dos-source.xml', $file['name']);
        $t->same('LEGACY\\WP-SOURCE.XML', $file['filename']);
        $t->same('DOS', $file['ef_key']);
        $t->same($currentPayload, $file['content']);
        $t->same(strlen($currentPayload), $file['size']);
        $t->same(hash('sha256', $currentPayload), $file['content_sha256']);
        $t->same($currentChecksum, $file['checksum']);
        $t->same($currentChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);
        $t->same('D:20260605091230Z', $file['modified_at']);
        $t->same('WP-SOURCE.XML', $file['filename_leaf']);
        $t->same('WP-SOURCE.XML', $file['filename_storage_name']);
        $t->same('relative_path_segments_review_only', $file['filename_path_status']);
        $t->same(true, $file['filename_has_path_segments']);
        $t->same('Platform EF Boundary Body', trim($plainText));
        $t->true(is_string($encoded) && !str_contains($encoded, $stalePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $staleChecksum));
    },
];
