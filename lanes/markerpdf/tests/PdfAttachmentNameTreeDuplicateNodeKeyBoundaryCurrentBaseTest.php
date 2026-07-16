<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentNameTreeDuplicateNodeKeyBoundaryCurrentBasePdf = static function (): array {
    $cleanPayload = '<wp-export><post id="clean-name-tree-node"/></wp-export>';
    $stalePayload = '<wp-export><post id="stale-duplicate-node"/></wp-export>';
    $malformedPayload = '<wp-export><post id="malformed-duplicate-node"/></wp-export>';
    $cleanChecksum = md5($cleanPayload);
    $staleChecksum = md5($stalePayload);
    $malformedChecksum = md5($malformedPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Duplicate Node Body) Tj ET';

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Limits [(clean.xml) (malformed.xml)] /Kids [7 0 R 8 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /Limits [(clean.xml) (clean.xml)] /Names [(clean.xml) 10 0 R] >>\nendobj\n"
        . "8 0 obj\n<< /Limits [(malformed.xml) (malformed.xml)] /Names [(stale.xml) 20 0 R] /Names [(malformed.xml) 30 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (clean.xml) /Desc (Clean sibling attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($cleanPayload) . " /CheckSum <{$cleanChecksum}> >> /Length " . strlen($cleanPayload) . " >>\n"
        . "stream\n{$cleanPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (stale.xml) /Desc (Stale duplicate name-tree node attachment) /AFRelationship /Alternative /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
        . "stream\n{$stalePayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (malformed.xml) /Desc (Malformed duplicate name-tree node attachment) /AFRelationship /Data /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($malformedPayload) . " /CheckSum <{$malformedChecksum}> >> /Length " . strlen($malformedPayload) . " >>\n"
        . "stream\n{$malformedPayload}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

    return [$pdf, $cleanPayload, $stalePayload, $malformedPayload, $cleanChecksum, $staleChecksum, $malformedChecksum];
};

return [
    'skips malformed EmbeddedFiles name-tree nodes with duplicate traversal keys before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($attachmentNameTreeDuplicateNodeKeyBoundaryCurrentBasePdf): void {
        [$pdf, $cleanPayload, $stalePayload, $malformedPayload, $cleanChecksum, $staleChecksum, $malformedChecksum] = $attachmentNameTreeDuplicateNodeKeyBoundaryCurrentBasePdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['clean.xml'], $summary['filenames']);
        $t->same(strlen($cleanPayload), $summary['total_bytes']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('clean.xml', $attachment['name_key']);
        $t->same('clean.xml', $attachment['filename']);
        $t->same('Clean sibling attachment', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(10, $attachment['file_spec_object_id']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same(strlen($cleanPayload), $attachment['byte_length']);
        $t->same($cleanChecksum, $attachment['checksum_hex']);
        $t->same($cleanChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('clean.xml', $file['name']);
        $t->same('clean.xml', $file['filename']);
        $t->same('Clean sibling attachment', $file['description']);
        $t->same('Source', $file['relationship']);
        $t->same(10, $file['file_spec_object']);
        $t->same(11, $file['embedded_file_object']);
        $t->same($cleanPayload, $file['content']);
        $t->same($cleanChecksum, $file['checksum']);
        $t->same($cleanChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Visible Attachment Duplicate Node Body', $plainText);
        foreach ([
            'stale.xml',
            'malformed.xml',
            'Stale duplicate name-tree node attachment',
            'Malformed duplicate name-tree node attachment',
            $stalePayload,
            $malformedPayload,
            $staleChecksum,
            $malformedChecksum,
            $cleanPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
