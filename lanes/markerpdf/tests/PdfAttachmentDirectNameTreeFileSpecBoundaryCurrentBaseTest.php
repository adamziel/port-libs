<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$directNameTreeFileSpecBoundaryPdf = static function (): array {
    $validPayload = '<wp-export><post id="valid-direct-nametree-filespec"/></wp-export>';
    $duplicateFileSpecPayload = '<wp-export><post id="duplicate-direct-nametree-filespec"/></wp-export>';
    $duplicateEfPayload = '<wp-export><post id="duplicate-direct-nametree-ef"/></wp-export>';
    $duplicateEfDecoyPayload = '<wp-export><post id="duplicate-direct-nametree-ef-decoy"/></wp-export>';
    $validChecksum = md5($validPayload);
    $duplicateFileSpecChecksum = md5($duplicateFileSpecPayload);
    $duplicateEfChecksum = md5($duplicateEfPayload);
    $duplicateEfDecoyChecksum = md5($duplicateEfDecoyPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Direct NameTree FileSpec Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles << /Names ["
        . "(inline-duplicate-filespec.xml) << /Type /Filespec /F (current-inline.xml) /F (stale-inline.xml) /Desc (Duplicate inline EmbeddedFiles FileSpec) /AFRelationship /Source /EF << /F 11 0 R >> >> "
        . "(inline-duplicate-ef.xml) << /Type /Filespec /F (inline-duplicate-ef.xml) /Desc (Duplicate inline EmbeddedFiles EF keys) /AFRelationship /Supplement /EF << /F 21 0 R /F 22 0 R >> >> "
        . "(valid-inline-direct.xml) << /Type /Filespec /F (valid-inline-direct.xml) /Desc (Valid inline EmbeddedFiles source) /AFRelationship /Data /EF << /F 31 0 R >> >>"
        . "] >> >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateFileSpecPayload) . " /CheckSum <{$duplicateFileSpecChecksum}> >> /Length " . strlen($duplicateFileSpecPayload) . " >>\n"
        . "stream\n{$duplicateFileSpecPayload}\nendstream\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateEfPayload) . " /CheckSum <{$duplicateEfChecksum}> >> /Length " . strlen($duplicateEfPayload) . " >>\n"
        . "stream\n{$duplicateEfPayload}\nendstream\nendobj\n"
        . "22 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateEfDecoyPayload) . " /CheckSum <{$duplicateEfDecoyChecksum}> >> /Length " . strlen($duplicateEfDecoyPayload) . " >>\n"
        . "stream\n{$duplicateEfDecoyPayload}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260607000037Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $validPayload,
        $duplicateFileSpecPayload,
        $duplicateEfPayload,
        $duplicateEfDecoyPayload,
        $validChecksum,
        $duplicateFileSpecChecksum,
        $duplicateEfChecksum,
        $duplicateEfDecoyChecksum,
    ];
};

return [
    'fails closed on direct inline EmbeddedFiles FileSpec duplicate keys before attachment summary import' => static function (
        TestRunner $t
    ) use ($directNameTreeFileSpecBoundaryPdf): void {
        [
            $pdf,
            $validPayload,
            $duplicateFileSpecPayload,
            $duplicateEfPayload,
            $duplicateEfDecoyPayload,
            $validChecksum,
            $duplicateFileSpecChecksum,
            $duplicateEfChecksum,
            $duplicateEfDecoyChecksum,
        ] = $directNameTreeFileSpecBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-inline-direct.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('valid-inline-direct.xml', $attachment['name_key']);
        $t->same('valid-inline-direct.xml', $attachment['filename']);
        $t->same('Valid inline EmbeddedFiles source', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same(null, $attachment['file_spec_object_id']);
        $t->same(31, $attachment['stream_object_id']);
        $t->same('F', $attachment['ef_key']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($validPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same(hash('sha256', $validPayload), $attachment['sha256']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260607000037Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-inline-direct.xml', $file['name']);
        $t->same('valid-inline-direct.xml', $file['filename']);
        $t->same(null, $file['file_spec_object']);
        $t->same(31, $file['embedded_file_object']);
        $t->same($validPayload, $file['content']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Direct NameTree FileSpec Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'stale-inline.xml',
            'inline-duplicate-ef.xml',
            $validPayload,
            $duplicateFileSpecPayload,
            $duplicateEfPayload,
            $duplicateEfDecoyPayload,
            $duplicateFileSpecChecksum,
            $duplicateEfChecksum,
            $duplicateEfDecoyChecksum,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
        }
        $t->true(is_string($filesJson) && !str_contains($filesJson, $duplicateFileSpecPayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $duplicateEfPayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $duplicateEfDecoyPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
