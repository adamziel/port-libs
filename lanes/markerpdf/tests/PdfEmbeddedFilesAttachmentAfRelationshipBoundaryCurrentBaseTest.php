<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$embeddedFilesAttachmentAfRelationshipBoundaryPdf = static function (): array {
    $duplicatePayload = '<wp-export><post id="duplicate-afrelationship"/></wp-export>';
    $trailingPayload = '<wp-export><post id="trailing-afrelationship"/></wp-export>';
    $validPayload = '<wp-export><post id="valid-afrelationship"/></wp-export>';
    $duplicateChecksum = md5($duplicatePayload);
    $trailingChecksum = md5($trailingPayload);
    $validChecksum = md5($validPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Visible AFRelationship Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(duplicate-afrelationship.xml) 10 0 R (trailing-afrelationship.xml) 20 0 R (valid-afrelationship.xml) 30 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (duplicate-afrelationship.xml) /Desc (Duplicate associated-file relationship) /AFRelationship /Source /AFRelationship /Data /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicatePayload) . " /CheckSum <{$duplicateChecksum}> >> /Length " . strlen($duplicatePayload) . " >>\n"
        . "stream\n{$duplicatePayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (trailing-afrelationship.xml) /Desc (Trailing associated-file relationship operand) /AFRelationship /Alternative 99 0 R /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($trailingPayload) . " /CheckSum <{$trailingChecksum}> >> /Length " . strlen($trailingPayload) . " >>\n"
        . "stream\n{$trailingPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (valid-afrelationship.xml) /Desc (Valid associated-file relationship) /AFRelationship /Source /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608061449Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $duplicatePayload,
        $trailingPayload,
        $validPayload,
        $duplicateChecksum,
        $trailingChecksum,
        $validChecksum,
    ];
};

return [
    'rejects ambiguous FileSpec AFRelationship operands before embedded attachment review' => static function (
        TestRunner $t
    ) use ($embeddedFilesAttachmentAfRelationshipBoundaryPdf): void {
        [
            $pdf,
            $duplicatePayload,
            $trailingPayload,
            $validPayload,
            $duplicateChecksum,
            $trailingChecksum,
            $validChecksum,
        ] = $embeddedFilesAttachmentAfRelationshipBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-afrelationship.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('valid-afrelationship.xml', $attachment['name_key']);
        $t->same('valid-afrelationship.xml', $attachment['filename']);
        $t->same('Valid associated-file relationship', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('standard_pdf_associated_file_relationship', $attachment['relationship_status']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(30, $attachment['file_spec_object_id']);
        $t->same(31, $attachment['stream_object_id']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-afrelationship.xml', $file['name']);
        $t->same('valid-afrelationship.xml', $file['filename']);
        $t->same('Valid associated-file relationship', $file['description']);
        $t->same('Source', $file['relationship']);
        $t->same(30, $file['file_spec_object']);
        $t->same(31, $file['embedded_file_object']);
        $t->same($validPayload, $file['content']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Visible AFRelationship Boundary Body', $plainText);
        foreach ([
            'duplicate-afrelationship.xml',
            'trailing-afrelationship.xml',
            'Duplicate associated-file relationship',
            'Trailing associated-file relationship operand',
            $duplicatePayload,
            $trailingPayload,
            $duplicateChecksum,
            $trailingChecksum,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
