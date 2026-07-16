<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$embeddedFileGeneratedMirrorRelationshipBoundaryPdf = static function (): array {
    $payload = '<wp-export><post id="generated-mirror-relationship"/></wp-export>';
    $checksum = md5($payload);
    $content = 'BT /F1 12 Tf 72 720 Td (Generated Mirror Relationship Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF ["
        . "<< /Type /Filespec /AFRelationship /Source /EF << /F 11 0 R >> >>"
        . "] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(source.xml) << /Type /Filespec /Desc (Name-tree source without relationship) /EF << /F 11 0 R >> >>] >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260608165030Z) >> /Length " . strlen($payload) . " >>\n"
        . "stream\n{$payload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $payload, $checksum];
};

return [
    'merges generated catalog AF mirrors into named EmbeddedFiles rows with relationship provenance' => static function (
        TestRunner $t
    ) use ($embeddedFileGeneratedMirrorRelationshipBoundaryPdf): void {
        [$pdf, $payload, $checksum] = $embeddedFileGeneratedMirrorRelationshipBoundaryPdf();

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('source.xml', $file['name']);
        $t->same('source.xml', $file['filename']);
        $t->same('name_tree_key', $file['filename_source']);
        $t->same('Name-tree source without relationship', $file['description']);
        $t->same('Source', $file['relationship']);
        $t->same(true, $file['associated_file']);
        $t->same('catalog_af', $file['associated_file_source']);
        $t->same(0, $file['associated_file_index']);
        $t->same(null, $file['file_spec_object']);
        $t->same(11, $file['embedded_file_object']);
        $t->same('text/xml', $file['mime_type']);
        $t->same(strlen($payload), $file['declared_size']);
        $t->same(strlen($payload), $file['size']);
        $t->same($payload, $file['content']);
        $t->same(hash('sha256', $payload), $file['content_sha256']);
        $t->same($checksum, $file['checksum']);
        $t->same($checksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);
        $t->same('D:20260608165030Z', $file['modified_at']);

        $provenance = $file['associated_file_provenance_review'] ?? null;
        $t->true(is_array($provenance));
        $t->same('associated_file_provenance', $provenance['source'] ?? null);
        $t->same(true, $provenance['review_only'] ?? null);
        $t->same(false, $provenance['payload_included'] ?? null);
        $t->same(true, $provenance['payload_content_returned'] ?? null);
        $t->same('Source', $provenance['relationship'] ?? null);
        $t->same('original_source', $provenance['relationship_role'] ?? null);
        $t->same('standard_pdf_associated_file_relationship', $provenance['relationship_status'] ?? null);
        $t->same(['filespec_afrelationship', 'embedded_file_payload_hash', 'embedded_file_params_checksum'], $provenance['sources'] ?? null);

        $t->same(1, $summary['attachment_count']);
        $t->same(['source.xml'], $summary['filenames']);
        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('source.xml', $attachment['name_key']);
        $t->same('source.xml', $attachment['filename']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('standard_pdf_associated_file_relationship', $attachment['relationship_status']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same($checksum, $attachment['checksum_hex']);
        $t->same($checksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $t->same('Generated Mirror Relationship Boundary Body', $plainText);
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $payload));
        $t->true(is_string($filesJson) && substr_count($filesJson, 'embedded-file') === 0);
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
