<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentFileSpecTrailingOperandPdf = static function (): array {
    $badEfPayload = '<wp-export><post id="malformed-ef-reference"/></wp-export>';
    $badEfDecoyPayload = '<wp-export><post id="malformed-ef-reference-decoy"/></wp-export>';
    $validPayload = '<wp-export><post id="valid-source"/></wp-export>';
    $relatedPrimaryPayload = '<wp-export><post id="related-primary"/></wp-export>';
    $relatedSidecarPayload = 'RELATED_SIDECAR_SHOULD_NOT_BE_REVIEWED';
    $relatedDecoyPayload = 'RELATED_DECOY_SHOULD_NOT_BE_REVIEWED';
    $badEfChecksum = md5($badEfPayload);
    $badEfDecoyChecksum = md5($badEfDecoyPayload);
    $validChecksum = md5($validPayload);
    $relatedPrimaryChecksum = md5($relatedPrimaryPayload);
    $relatedSidecarChecksum = md5($relatedSidecarPayload);
    $relatedDecoyChecksum = md5($relatedDecoyPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Attachment Trailing Operand Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(malformed-ef.xml) 10 0 R (valid-source.xml) 20 0 R (related-primary.xml) 30 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (malformed-ef.xml) /Desc (Malformed EF trailing operand source) /AFRelationship /Source /EF << /F 11 0 R 12 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($badEfPayload) . " /CheckSum <{$badEfChecksum}> >> /Length " . strlen($badEfPayload) . " >>\n"
        . "stream\n{$badEfPayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($badEfDecoyPayload) . " /CheckSum <{$badEfDecoyChecksum}> >> /Length " . strlen($badEfDecoyPayload) . " >>\n"
        . "stream\n{$badEfDecoyPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (valid-source.xml) /Desc (Valid current WordPress source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260607154020Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (related-primary.xml) /Desc (Primary with malformed related file dictionary) /AFRelationship /Supplement /EF << /F 31 0 R >> /RF << /F [(sidecar.css) 32 0 R] 33 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($relatedPrimaryPayload) . " /CheckSum <{$relatedPrimaryChecksum}> >> /Length " . strlen($relatedPrimaryPayload) . " >>\n"
        . "stream\n{$relatedPrimaryPayload}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($relatedSidecarPayload) . " /CheckSum <{$relatedSidecarChecksum}> >> /Length " . strlen($relatedSidecarPayload) . " >>\n"
        . "stream\n{$relatedSidecarPayload}\nendstream\nendobj\n"
        . "33 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcss /Params << /Size " . strlen($relatedDecoyPayload) . " /CheckSum <{$relatedDecoyChecksum}> >> /Length " . strlen($relatedDecoyPayload) . " >>\n"
        . "stream\n{$relatedDecoyPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $badEfPayload,
        $badEfDecoyPayload,
        $validPayload,
        $relatedPrimaryPayload,
        $relatedSidecarPayload,
        $relatedDecoyPayload,
        $badEfChecksum,
        $badEfDecoyChecksum,
        $validChecksum,
        $relatedPrimaryChecksum,
        $relatedSidecarChecksum,
        $relatedDecoyChecksum,
    ];
};

return [
    'fails closed on FileSpec EF and RF trailing operands before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($attachmentFileSpecTrailingOperandPdf): void {
        [
            $pdf,
            $badEfPayload,
            $badEfDecoyPayload,
            $validPayload,
            $relatedPrimaryPayload,
            $relatedSidecarPayload,
            $relatedDecoyPayload,
            $badEfChecksum,
            $badEfDecoyChecksum,
            $validChecksum,
            $relatedPrimaryChecksum,
            $relatedSidecarChecksum,
            $relatedDecoyChecksum,
        ] = $attachmentFileSpecTrailingOperandPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(strlen($validPayload) + strlen($relatedPrimaryPayload), $summary['total_bytes']);
        $t->same(['valid-source.xml', 'related-primary.xml'], $summary['filenames']);

        $valid = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $valid['source']);
        $t->same('valid-source.xml', $valid['name_key']);
        $t->same('valid-source.xml', $valid['filename']);
        $t->same('Data', $valid['relationship']);
        $t->same('base_data_for_visual_presentation', $valid['relationship_role']);
        $t->same(20, $valid['file_spec_object_id']);
        $t->same(21, $valid['stream_object_id']);
        $t->same($validChecksum, $valid['checksum_hex']);
        $t->same(true, $valid['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $valid));

        $relatedPrimary = $summary['attachments'][1];
        $t->same('embedded-files-name-tree', $relatedPrimary['source']);
        $t->same('related-primary.xml', $relatedPrimary['filename']);
        $t->same('Supplement', $relatedPrimary['relationship']);
        $t->same('supplemental_representation', $relatedPrimary['relationship_role']);
        $t->same(30, $relatedPrimary['file_spec_object_id']);
        $t->same(31, $relatedPrimary['stream_object_id']);
        $t->same($relatedPrimaryChecksum, $relatedPrimary['checksum_hex']);
        $t->same(true, $relatedPrimary['checksum_matches']);
        $t->same(false, array_key_exists('related_file_count', $relatedPrimary));
        $t->same(false, array_key_exists('related_files', $relatedPrimary));
        $t->same(false, array_key_exists('bytes', $relatedPrimary));

        $t->same(2, count($files));
        $t->same('valid-source.xml', $files[0]['filename']);
        $t->same($validPayload, $files[0]['content']);
        $t->same($validChecksum, $files[0]['checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same('related-primary.xml', $files[1]['filename']);
        $t->same($relatedPrimaryPayload, $files[1]['content']);
        $t->same($relatedPrimaryChecksum, $files[1]['checksum']);
        $t->same(true, $files[1]['checksum_matches']);
        $t->same(false, array_key_exists('related_file_count', $files[1]));
        $t->same(false, array_key_exists('related_files', $files[1]));

        $t->same('Attachment Trailing Operand Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'malformed-ef.xml',
            'Malformed EF trailing operand source',
            $badEfPayload,
            $badEfDecoyPayload,
            $relatedSidecarPayload,
            $relatedDecoyPayload,
            $badEfChecksum,
            $badEfDecoyChecksum,
            $relatedSidecarChecksum,
            $relatedDecoyChecksum,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
