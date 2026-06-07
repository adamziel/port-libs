<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationFileSpecBoundaryPdf = static function (): array {
    $validPayload = '<wp-export><post id="valid-annotation-filespec"/></wp-export>';
    $duplicateFileSpecPayload = '<wp-export><post id="duplicate-annotation-filespec-f"/></wp-export>';
    $duplicateEfPayload = '<wp-export><post id="duplicate-annotation-ef-current"/></wp-export>';
    $duplicateEfDecoyPayload = '<wp-export><post id="duplicate-annotation-ef-decoy"/></wp-export>';
    $validChecksum = md5($validPayload);
    $duplicateFileSpecChecksum = md5($duplicateFileSpecPayload);
    $duplicateEfChecksum = md5($duplicateEfPayload);
    $duplicateEfDecoyChecksum = md5($duplicateEfDecoyPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Annotation FileSpec Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Annots [8 0 R 9 0 R 10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [72 700 90 718] /Contents (Duplicate annotation FileSpec filename keys) /FS << /Type /Filespec /F (annotation-current.xml) /F (annotation-stale.xml) /Desc (Duplicate direct annotation FileSpec) /AFRelationship /Source /EF << /F 11 0 R >> >> >>\nendobj\n"
        . "9 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [108 700 126 718] /Contents (Duplicate annotation EF stream keys) /FS << /Type /Filespec /F (annotation-duplicate-ef.xml) /Desc (Duplicate direct annotation EF keys) /AFRelationship /Supplement /EF << /F 21 0 R /F 22 0 R >> >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [144 700 162 718] /Contents (Valid annotation FileSpec) /FS << /Type /Filespec /F (valid-annotation.xml) /Desc (Valid direct annotation FileSpec) /AFRelationship /Data /EF << /F 31 0 R >> >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateFileSpecPayload) . " /CheckSum <{$duplicateFileSpecChecksum}> >> /Length " . strlen($duplicateFileSpecPayload) . " >>\n"
        . "stream\n{$duplicateFileSpecPayload}\nendstream\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateEfPayload) . " /CheckSum <{$duplicateEfChecksum}> >> /Length " . strlen($duplicateEfPayload) . " >>\n"
        . "stream\n{$duplicateEfPayload}\nendstream\nendobj\n"
        . "22 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateEfDecoyPayload) . " /CheckSum <{$duplicateEfDecoyChecksum}> >> /Length " . strlen($duplicateEfDecoyPayload) . " >>\n"
        . "stream\n{$duplicateEfDecoyPayload}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260607044412Z) >> /Length " . strlen($validPayload) . " >>\n"
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
    'fails closed on direct FileAttachment annotation FileSpec duplicate keys before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($annotationFileSpecBoundaryPdf): void {
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
        ] = $annotationFileSpecBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-annotation.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('file-attachment-annotation', $attachment['source']);
        $t->same(true, $attachment['file_attachment_annotation']);
        $t->same('page_annotation', $attachment['file_attachment_annotation_source']);
        $t->same(1, $attachment['page_number']);
        $t->same(3, $attachment['page_object_id']);
        $t->same(10, $attachment['annotation_object_id']);
        $t->same('Valid annotation FileSpec', $attachment['annotation_contents']);
        $t->same([144.0, 700.0, 162.0, 718.0], $attachment['annotation_rect']);
        $t->same(null, $attachment['file_spec_object_id']);
        $t->same(31, $attachment['stream_object_id']);
        $t->same('F', $attachment['ef_key']);
        $t->same('valid-annotation.xml', $attachment['filename']);
        $t->same('Valid direct annotation FileSpec', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($validPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same(hash('sha256', $validPayload), $attachment['sha256']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260607044412Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same('Annotation FileSpec Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'annotation-stale.xml',
            'annotation-duplicate-ef.xml',
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
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
