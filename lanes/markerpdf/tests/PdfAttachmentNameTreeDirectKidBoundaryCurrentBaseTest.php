<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentNameTreeDirectKidBoundaryCurrentBasePdf = static function (): array {
    $decoyPayload = '<wp-export><post id="inline-direct-kid-decoy"/></wp-export>';
    $validPayload = '<wp-export><post id="valid-referenced-kid"/></wp-export>';
    $decoyChecksum = md5($decoyPayload);
    $validChecksum = md5($validPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Direct Kid Boundary) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Limits [(decoy-direct-kid.xml) (valid-direct-kid.xml)] /Kids [<< /Limits [(decoy-direct-kid.xml) (decoy-direct-kid.xml)] /Names [(decoy-direct-kid.xml) 10 0 R] >> 7 0 R (scalar-kid-decoy)] >>\nendobj\n"
        . "7 0 obj\n<< /Limits [(valid-direct-kid.xml) (valid-direct-kid.xml)] /Names [(valid-direct-kid.xml) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (decoy-direct-kid.xml) /Desc (Inline direct kid decoy attachment) /AFRelationship /Alternative /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($decoyPayload) . " /CheckSum <{$decoyChecksum}> >> /Length " . strlen($decoyPayload) . " >>\n"
        . "stream\n{$decoyPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (valid-direct-kid.xml) /Desc (Referenced kid WordPress source) /AFRelationship /Source /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260607223518Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

    return [$pdf, $validPayload, $decoyPayload, $validChecksum, $decoyChecksum];
};

return [
    'requires EmbeddedFiles name-tree Kids entries to be references before attachment review' => static function (
        TestRunner $t
    ) use ($attachmentNameTreeDirectKidBoundaryCurrentBasePdf): void {
        [$pdf, $validPayload, $decoyPayload, $validChecksum, $decoyChecksum] = $attachmentNameTreeDirectKidBoundaryCurrentBasePdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-direct-kid.xml'], $summary['filenames']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('valid-direct-kid.xml', $attachment['name_key']);
        $t->same('valid-direct-kid.xml', $attachment['filename']);
        $t->same('Referenced kid WordPress source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(20, $attachment['file_spec_object_id']);
        $t->same(21, $attachment['stream_object_id']);
        $t->same(strlen($validPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same(hash('sha256', $validPayload), $attachment['sha256']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260607223518Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-direct-kid.xml', $file['name']);
        $t->same('valid-direct-kid.xml', $file['filename']);
        $t->same('Referenced kid WordPress source', $file['description']);
        $t->same('Source', $file['relationship']);
        $t->same(20, $file['file_spec_object']);
        $t->same(21, $file['embedded_file_object']);
        $t->same($validPayload, $file['content']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Visible Attachment Direct Kid Boundary', $plainText);
        foreach ([
            'decoy-direct-kid.xml',
            'Inline direct kid decoy attachment',
            'scalar-kid-decoy',
            $decoyPayload,
            $decoyChecksum,
            $validPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
