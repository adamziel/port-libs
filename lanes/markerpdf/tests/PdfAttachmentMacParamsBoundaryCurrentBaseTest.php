<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentMacParamsBoundaryPdf = static function (): array {
    $payload = '<wp-export><post id="mac-params-source"/></wp-export>';
    $resourceForkPayload = "BT /F1 12 Tf 72 720 Td (Mac Resource Fork Payload Leak) Tj ET";
    $payloadChecksum = md5($payload);
    $resourceForkChecksum = md5($resourceForkPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Mac Params Attachment Body) Tj ET';
    $fileType = unpack('N', 'TEXT')[1];
    $creator = unpack('N', 'MPRT')[1];

    $pdf = "%PDF-1.7\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(mac-source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (mac-source.xml) /Desc (Mac params WordPress source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$payloadChecksum}> /ModDate (D:20260605184200Z) /Mac << /Subtype {$fileType} /Creator {$creator} /ResFork 12 0 R >> >> /Length " . strlen($payload) . " >>\n"
        . "stream\n{$payload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Foctet-stream /Params << /Size " . strlen($resourceForkPayload) . " /CheckSum <{$resourceForkChecksum}> /CreationDate (D:20260605184201Z) >> /Length " . strlen($resourceForkPayload) . " >>\n"
        . "stream\n{$resourceForkPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $payload, $resourceForkPayload, $payloadChecksum, $resourceForkChecksum];
};

return [
    'summarizes EmbeddedFile Mac Params resource-fork metadata without payload leakage' => static function (
        TestRunner $t
    ) use ($attachmentMacParamsBoundaryPdf): void {
        [$pdf, $payload, $resourceForkPayload, $payloadChecksum, $resourceForkChecksum] = $attachmentMacParamsBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['mac-source.xml'], $summary['filenames']);
        $t->same(strlen($payload), $summary['total_bytes']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('mac-source.xml', $attachment['filename']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($payload), $attachment['byte_length']);
        $t->same($payloadChecksum, $attachment['checksum_hex']);
        $t->same($payloadChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260605184200Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $mac = $attachment['mac_file_info'] ?? null;
        $t->true(is_array($mac));
        $t->same('embedded_file_params_mac', $mac['source'] ?? null);
        $t->same(true, $mac['review_only'] ?? null);
        $t->same(false, $mac['payload_bytes_included'] ?? null);
        $t->same(1413830740, $mac['file_type']['integer'] ?? null);
        $t->same('54455854', $mac['file_type']['hex'] ?? null);
        $t->same('TEXT', $mac['file_type']['four_char_code'] ?? null);
        $t->same(1297109588, $mac['creator']['integer'] ?? null);
        $t->same('4d505254', $mac['creator']['hex'] ?? null);
        $t->same('MPRT', $mac['creator']['four_char_code'] ?? null);

        $resourceFork = $mac['resource_fork'] ?? null;
        $t->true(is_array($resourceFork));
        $t->same('embedded_file_params_mac_resource_fork', $resourceFork['source'] ?? null);
        $t->same(12, $resourceFork['stream_object_id'] ?? null);
        $t->same(false, $resourceFork['payload_bytes_included'] ?? null);
        $t->same('application/octet-stream', $resourceFork['content_type'] ?? null);
        $t->same(strlen($resourceForkPayload), $resourceFork['byte_length'] ?? null);
        $t->same(hash('sha256', $resourceForkPayload), $resourceFork['sha256'] ?? null);
        $t->same(strlen($resourceForkPayload), $resourceFork['declared_size'] ?? null);
        $t->same(true, $resourceFork['declared_size_matches'] ?? null);
        $t->same($resourceForkChecksum, $resourceFork['checksum_hex'] ?? null);
        $t->same($resourceForkChecksum, $resourceFork['computed_checksum_hex'] ?? null);
        $t->same(true, $resourceFork['checksum_matches'] ?? null);
        $t->same('D:20260605184201Z', $resourceFork['created_at'] ?? null);
        $t->same(false, array_key_exists('bytes', $resourceFork));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('mac-source.xml', $file['filename']);
        $t->same($payload, $file['content']);
        $t->same(true, $file['checksum_matches']);
        $fileMac = $file['mac_file_info'] ?? null;
        $t->true(is_array($fileMac));
        $t->same('TEXT', $fileMac['file_type']['four_char_code'] ?? null);
        $t->same('MPRT', $fileMac['creator']['four_char_code'] ?? null);
        $t->same(12, $fileMac['resource_fork']['embedded_file_object'] ?? null);
        $t->same(strlen($resourceForkPayload), $fileMac['resource_fork']['size'] ?? null);
        $t->same(hash('sha256', $resourceForkPayload), $fileMac['resource_fork']['content_sha256'] ?? null);
        $t->same($resourceForkChecksum, $fileMac['resource_fork']['checksum'] ?? null);
        $t->same($resourceForkChecksum, $fileMac['resource_fork']['computed_checksum'] ?? null);
        $t->same(true, $fileMac['resource_fork']['checksum_matches'] ?? null);
        $t->same(false, $fileMac['resource_fork']['payload_included'] ?? null);

        $t->same('Mac Params Attachment Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $payload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $resourceForkPayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $resourceForkPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'Mac Resource Fork Payload Leak'));
    },
];
