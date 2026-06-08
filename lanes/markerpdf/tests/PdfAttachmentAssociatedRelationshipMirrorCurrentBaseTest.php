<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentAssociatedRelationshipMirrorPdf = static function (): array {
    $sourcePayload = '<wp-export><post id="relationship-mirror-source"/></wp-export>';
    $orphanPayload = '{"review":"missing-associated-relationship"}';
    $sourceChecksum = md5($sourcePayload);
    $orphanChecksum = md5($orphanPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Associated Relationship Mirror Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF ["
        . "<< /Type /Filespec /AFRelationship /Source /EF << /F 11 0 R >> >> "
        . "<< /Type /Filespec /EF << /F 21 0 R >> >>"
        . "] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names ["
        . "(source.xml) << /Type /Filespec /Desc (Name-tree source without relationship) /EF << /F 11 0 R >> >> "
        . "(orphan-review.json) << /Type /Filespec /Desc (Name-tree review without relationship) /EF << /F 21 0 R >> >>"
        . "] >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260608122825Z) >> /Length " . strlen($sourcePayload) . " >>\n"
        . "stream\n{$sourcePayload}\nendstream\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($orphanPayload) . " /CheckSum <{$orphanChecksum}> /ModDate (D:20260608122826Z) >> /Length " . strlen($orphanPayload) . " >>\n"
        . "stream\n{$orphanPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $sourcePayload, $orphanPayload, $sourceChecksum, $orphanChecksum];
};

return [
    'carries associated-file relationship review through name-tree mirror summaries' => static function (
        TestRunner $t
    ) use ($attachmentAssociatedRelationshipMirrorPdf): void {
        [$pdf, $sourcePayload, $orphanPayload, $sourceChecksum, $orphanChecksum] = $attachmentAssociatedRelationshipMirrorPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(strlen($sourcePayload) + strlen($orphanPayload), $summary['total_bytes']);
        $t->same(['source.xml', 'orphan-review.json'], $summary['filenames']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $source = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $source['source']);
        $t->same('source.xml', $source['name_key']);
        $t->same('source.xml', $source['filename']);
        $t->same('name_tree_key', $source['filename_source']);
        $t->same(true, $source['associated_file']);
        $t->same('catalog_af', $source['associated_file_source']);
        $t->same('Source', $source['relationship']);
        $t->same('original_source', $source['relationship_role']);
        $t->same('standard_pdf_associated_file_relationship', $source['relationship_status']);
        $t->same(11, $source['stream_object_id']);
        $t->same('text/xml', $source['content_type']);
        $t->same(strlen($sourcePayload), $source['byte_length']);
        $t->same($sourceChecksum, $source['checksum_hex']);
        $t->same($sourceChecksum, $source['computed_checksum_hex']);
        $t->same(true, $source['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $source));

        $orphan = $summary['attachments'][1];
        $t->same('embedded-files-name-tree', $orphan['source']);
        $t->same('orphan-review.json', $orphan['name_key']);
        $t->same('orphan-review.json', $orphan['filename']);
        $t->same('name_tree_key', $orphan['filename_source']);
        $t->same(true, $orphan['associated_file']);
        $t->same('catalog_af', $orphan['associated_file_source']);
        $t->same(false, array_key_exists('relationship', $orphan));
        $t->same(false, array_key_exists('relationship_role', $orphan));
        $t->same('missing_pdf_associated_file_relationship', $orphan['relationship_status']);
        $t->same(21, $orphan['stream_object_id']);
        $t->same('application/json', $orphan['content_type']);
        $t->same(strlen($orphanPayload), $orphan['byte_length']);
        $t->same($orphanChecksum, $orphan['checksum_hex']);
        $t->same($orphanChecksum, $orphan['computed_checksum_hex']);
        $t->same(true, $orphan['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $orphan));

        $t->same('Associated Relationship Mirror Body', $plainText);
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $sourcePayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $orphanPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'missing-associated-relationship'));
    },
];
