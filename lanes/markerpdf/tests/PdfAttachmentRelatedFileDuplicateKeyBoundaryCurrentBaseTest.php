<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$relatedFileDuplicateKeyPdf = static function (): array {
    $sourcePayload = '<wp-export><post id="rf-duplicate-source"/></wp-export>';
    $safePayload = 'SAFE_RELATED_FILE_SHOULD_NOT_BE_REVIEWED';
    $stalePayload = 'STALE_RELATED_FILE_SHOULD_NOT_BE_REVIEWED';
    $manifestPayload = '{"related":"duplicate-key"}';
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Related File Duplicate Boundary Body) Tj ET';

    $sourceChecksum = md5($sourcePayload);
    $safeChecksum = md5($safePayload);
    $staleChecksum = md5($stalePayload);
    $manifestChecksum = md5($manifestPayload);

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Source with duplicate related-file keys) /AFRelationship /Source /EF << /F 11 0 R >>"
        . " /RF << /F [(safe.css) 12 0 R] /F [(stale.css) 13 0 R] /UF [(manifest.json) 14 0 R] >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> >> /Length " . strlen($sourcePayload) . " >>\n"
        . "stream\n{$sourcePayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($safePayload) . " /CheckSum <{$safeChecksum}> >> /Length " . strlen($safePayload) . " >>\n"
        . "stream\n{$safePayload}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
        . "stream\n{$stalePayload}\nendstream\nendobj\n"
        . "14 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($manifestPayload) . " /CheckSum <{$manifestChecksum}> >> /Length " . strlen($manifestPayload) . " >>\n"
        . "stream\n{$manifestPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $sourcePayload, $safePayload, $stalePayload, $manifestPayload, $sourceChecksum, $safeChecksum, $staleChecksum, $manifestChecksum];
};

$relatedFileDuplicateEntryPdf = static function (): array {
    $sourcePayload = '<wp-export><post id="rf-duplicate-entry-source"/></wp-export>';
    $sidecarPayload = 'DUPLICATE_RF_ENTRY_SHOULD_NOT_BE_REVIEWED';
    $manifestPayload = '{"related":"duplicate-entry"}';
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Duplicate RF Entry Boundary Body) Tj ET';

    $sourceChecksum = md5($sourcePayload);
    $sidecarChecksum = md5($sidecarPayload);
    $manifestChecksum = md5($manifestPayload);

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Source with duplicate RF entries) /AFRelationship /Source /EF << /F 11 0 R >>"
        . " /RF << /F [(sidecar.css) 12 0 R] >> /RF << /UF [(manifest.json) 13 0 R] >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> >> /Length " . strlen($sourcePayload) . " >>\n"
        . "stream\n{$sourcePayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($sidecarPayload) . " /CheckSum <{$sidecarChecksum}> >> /Length " . strlen($sidecarPayload) . " >>\n"
        . "stream\n{$sidecarPayload}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($manifestPayload) . " /CheckSum <{$manifestChecksum}> >> /Length " . strlen($manifestPayload) . " >>\n"
        . "stream\n{$manifestPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $sourcePayload, $sidecarPayload, $manifestPayload, $sourceChecksum, $sidecarChecksum, $manifestChecksum];
};

return [
    'fails closed on duplicate FileSpec RF platform keys before related-file attachment review' => static function (
        TestRunner $t
    ) use ($relatedFileDuplicateKeyPdf): void {
        [$pdf, $sourcePayload, $safePayload, $stalePayload, $manifestPayload, $sourceChecksum, $safeChecksum, $staleChecksum, $manifestChecksum] = $relatedFileDuplicateKeyPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['source.xml'], $summary['filenames']);
        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('source.xml', $attachment['filename']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same($sourceChecksum, $attachment['checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('related_file_count', $attachment));
        $t->same(false, array_key_exists('related_files', $attachment));
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('source.xml', $file['filename']);
        $t->same($sourcePayload, $file['content']);
        $t->same($sourceChecksum, $file['checksum']);
        $t->same(true, $file['checksum_matches']);
        $t->same(false, array_key_exists('related_file_count', $file));
        $t->same(false, array_key_exists('related_files', $file));

        $t->same('Related File Duplicate Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $safePayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $stalePayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $manifestPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $safeChecksum));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $staleChecksum));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $manifestChecksum));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $safePayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $stalePayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $manifestPayload));
        $t->true(!str_contains($plainText, 'RELATED_FILE_SHOULD_NOT_BE_REVIEWED'));
    },
    'fails closed on duplicate FileSpec RF dictionary entries while preserving primary attachment' => static function (
        TestRunner $t
    ) use ($relatedFileDuplicateEntryPdf): void {
        [$pdf, $sourcePayload, $sidecarPayload, $manifestPayload, $sourceChecksum, $sidecarChecksum, $manifestChecksum] = $relatedFileDuplicateEntryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['source.xml'], $summary['filenames']);
        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('source.xml', $attachment['filename']);
        $t->same('Source', $attachment['relationship']);
        $t->same($sourceChecksum, $attachment['checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('related_file_count', $attachment));
        $t->same(false, array_key_exists('related_files', $attachment));
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('source.xml', $file['filename']);
        $t->same($sourcePayload, $file['content']);
        $t->same($sourceChecksum, $file['checksum']);
        $t->same(true, $file['checksum_matches']);
        $t->same(false, array_key_exists('related_file_count', $file));
        $t->same(false, array_key_exists('related_files', $file));

        $t->same('Duplicate RF Entry Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $sidecarPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $manifestPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $sidecarChecksum));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $manifestChecksum));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $sidecarPayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $manifestPayload));
        $t->true(!str_contains($plainText, 'DUPLICATE_RF_ENTRY_SHOULD_NOT_BE_REVIEWED'));
    },
];
