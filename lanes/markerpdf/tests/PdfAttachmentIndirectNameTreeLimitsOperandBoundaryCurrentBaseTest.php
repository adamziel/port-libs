<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentIndirectNameTreeLimitsOperandBoundaryPdf = static function (): array {
    $validPayload = '<wp-export><post id="valid-limits-sibling"/></wp-export>';
    $tailedLimitsPayload = '<wp-export><post id="tailed-limits-node"/></wp-export>';
    $tailDecoyPayload = '<wp-export><post id="limits-tail-decoy"/></wp-export>';
    $validChecksum = md5($validPayload);
    $tailedLimitsChecksum = md5($tailedLimitsPayload);
    $tailDecoyChecksum = md5($tailDecoyPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Indirect Limits Operand Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Limits [(tailed-limits.xml) (valid-limits.xml)] /Kids [8 0 R 7 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /Limits [(valid-limits.xml) (valid-limits.xml)] /Names [(valid-limits.xml) 10 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Limits 50 0 R /Names [(tailed-limits.xml) 20 0 R (limits-tail-decoy.xml) 30 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (valid-limits.xml) /Desc (Valid sibling after tailed Limits) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608153711Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (tailed-limits.xml) /Desc (Malformed tailed Limits source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($tailedLimitsPayload) . " /CheckSum <{$tailedLimitsChecksum}> >> /Length " . strlen($tailedLimitsPayload) . " >>\n"
        . "stream\n{$tailedLimitsPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (limits-tail-decoy.xml) /Desc (Limits tail operand decoy) /AFRelationship /Alternative /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($tailDecoyPayload) . " /CheckSum <{$tailDecoyChecksum}> >> /Length " . strlen($tailDecoyPayload) . " >>\n"
        . "stream\n{$tailDecoyPayload}\nendstream\nendobj\n"
        . "50 0 obj\n[(tailed-limits.xml) (tailed-limits.xml)] 30 0 R\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $validPayload,
        $tailedLimitsPayload,
        $tailDecoyPayload,
        $validChecksum,
        $tailedLimitsChecksum,
        $tailDecoyChecksum,
    ];
};

return [
    'rejects tailed indirect EmbeddedFiles Limits arrays before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($attachmentIndirectNameTreeLimitsOperandBoundaryPdf): void {
        [
            $pdf,
            $validPayload,
            $tailedLimitsPayload,
            $tailDecoyPayload,
            $validChecksum,
            $tailedLimitsChecksum,
            $tailDecoyChecksum,
        ] = $attachmentIndirectNameTreeLimitsOperandBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-limits.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('valid-limits.xml', $attachment['name_key']);
        $t->same('valid-limits.xml', $attachment['filename']);
        $t->same('Valid sibling after tailed Limits', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(10, $attachment['file_spec_object_id']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same(strlen($validPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same(hash('sha256', $validPayload), $attachment['sha256']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260608153711Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-limits.xml', $file['name']);
        $t->same('valid-limits.xml', $file['filename']);
        $t->same('Valid sibling after tailed Limits', $file['description']);
        $t->same('Source', $file['relationship']);
        $t->same(10, $file['file_spec_object']);
        $t->same(11, $file['embedded_file_object']);
        $t->same($validPayload, $file['content']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Indirect Limits Operand Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'tailed-limits.xml',
            'limits-tail-decoy.xml',
            'Malformed tailed Limits source',
            'Limits tail operand decoy',
            $tailedLimitsPayload,
            $tailDecoyPayload,
            $tailedLimitsChecksum,
            $tailDecoyChecksum,
            $validPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
