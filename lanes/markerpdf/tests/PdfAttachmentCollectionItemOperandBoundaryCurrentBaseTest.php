<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentCollectionItemOperandBoundaryPdf = static function (): array {
    $badPayload = '<wp-export><post id="bad-ci-still-attachment"/></wp-export>';
    $validPayload = '<wp-export><post id="valid-ci-attachment"/></wp-export>';
    $badPrivatePayload = 'BT /F1 12 Tf 72 720 Td (Bad CI Private Payload Leak) Tj ET';
    $validPrivatePayload = 'BT /F1 12 Tf 72 720 Td (Valid CI Private Payload Leak) Tj ET';
    $badChecksum = md5($badPayload);
    $validChecksum = md5($validPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Collection Item Operand Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Collection /View /T /D (valid-ci.xml) /Schema << /SubjectField << /Subtype /S /N (Subject) /O 1 >> /StatusField << /Subtype /S /N (Status) /O 2 >> /DescriptionField << /Subtype /Desc /N (Description) /O 3 >> /BytesField << /Subtype /Size /N (Bytes) /O 4 >> /ModifiedField << /Subtype /ModDate /N (Modified) /O 5 >> >> >>\nendobj\n"
        . "6 0 obj\n<< /Names [(bad-ci.xml) 10 0 R (valid-ci.xml) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (bad-ci.xml) /Desc (Bad CI source still imports) /AFRelationship /Data /CI 30 0 R /CI 31 0 R /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($badPayload) . " /CheckSum <{$badChecksum}> /ModDate (D:20260608173200Z) >> /Length " . strlen($badPayload) . " >>\nstream\n{$badPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (valid-ci.xml) /Desc (Valid CI source) /AFRelationship /Source /CI 32 0 R /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608173300Z) >> /Length " . strlen($validPayload) . " >>\nstream\n{$validPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /CollectionItem /SubjectField (Bad First CI Subject) /StatusField << /Type /CollectionSubitem /D (stale-first) /P (status:) >> /PrivateStream 40 0 R >>\nendobj\n"
        . "31 0 obj\n<< /Type /CollectionItem /SubjectField (Bad Duplicate CI Subject) /StatusField (stale-duplicate) /PrivateStream 41 0 R >>\nendobj\n"
        . "32 0 obj\n<< /Type /CollectionItem /SubjectField (Valid CI Subject) /StatusField << /Type /CollectionSubitem /D (ready) /P (status:) >> /PrivateStream 42 0 R >>\nendobj\n"
        . "40 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Length " . strlen($badPrivatePayload) . " >>\nstream\n{$badPrivatePayload}\nendstream\nendobj\n"
        . "41 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Length " . strlen($badPrivatePayload) . " >>\nstream\n{$badPrivatePayload}\nendstream\nendobj\n"
        . "42 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Length " . strlen($validPrivatePayload) . " >>\nstream\n{$validPrivatePayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $badPayload, $validPayload, $badPrivatePayload, $validPrivatePayload, $badChecksum, $validChecksum];
};

return [
    'omits malformed FileSpec collection item operands while preserving attachment payload review' => static function (
        TestRunner $t
    ) use ($attachmentCollectionItemOperandBoundaryPdf): void {
        [$pdf, $badPayload, $validPayload, $badPrivatePayload, $validPrivatePayload, $badChecksum, $validChecksum] = $attachmentCollectionItemOperandBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(['bad-ci.xml', 'valid-ci.xml'], $summary['filenames']);
        $t->same(strlen($badPayload) + strlen($validPayload), $summary['total_bytes']);

        $bad = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $bad['source']);
        $t->same('bad-ci.xml', $bad['name_key']);
        $t->same('bad-ci.xml', $bad['filename']);
        $t->same('Bad CI source still imports', $bad['description']);
        $t->same('Data', $bad['relationship']);
        $t->same('base_data_for_visual_presentation', $bad['relationship_role']);
        $t->same('text/xml', $bad['content_type']);
        $t->same(10, $bad['file_spec_object_id']);
        $t->same(11, $bad['stream_object_id']);
        $t->same(strlen($badPayload), $bad['declared_size']);
        $t->same(true, $bad['declared_size_matches']);
        $t->same(strlen($badPayload), $bad['byte_length']);
        $t->same($badChecksum, $bad['checksum_hex']);
        $t->same($badChecksum, $bad['computed_checksum_hex']);
        $t->same(true, $bad['checksum_matches']);
        $t->same('D:20260608173200Z', $bad['modified_at']);
        $t->same('malformed_filespec_collection_item_omitted', $bad['portfolio_item_status'] ?? null);
        $t->same(false, array_key_exists('portfolio_item', $bad));
        $t->same(false, array_key_exists('portfolio_item_count', $bad));
        $badFields = $bad['portfolio_field_values'] ?? [];
        $t->same(false, array_key_exists('SubjectField', $badFields));
        $t->same(false, array_key_exists('StatusField', $badFields));
        $t->same('Bad CI source still imports', $badFields['DescriptionField']['value'] ?? null);
        $t->same(strlen($badPayload), $badFields['BytesField']['value'] ?? null);
        $t->same('D:20260608173200Z', $badFields['ModifiedField']['value'] ?? null);
        $t->same(false, array_key_exists('bytes', $bad));

        $valid = $summary['attachments'][1];
        $t->same('valid-ci.xml', $valid['filename']);
        $t->same('Source', $valid['relationship']);
        $t->same('original_source', $valid['relationship_role']);
        $t->same(20, $valid['file_spec_object_id']);
        $t->same(21, $valid['stream_object_id']);
        $t->same($validChecksum, $valid['checksum_hex']);
        $t->same($validChecksum, $valid['computed_checksum_hex']);
        $t->same(true, $valid['checksum_matches']);
        $t->same('Valid CI Subject', $valid['portfolio_item']['SubjectField'] ?? null);
        $t->same('status:ready', $valid['portfolio_item']['StatusField']['display_value'] ?? null);
        $t->same('Valid CI Subject', $valid['portfolio_field_values']['SubjectField']['value'] ?? null);
        $t->same('status:ready', $valid['portfolio_field_values']['StatusField']['display_value'] ?? null);
        $t->same(false, array_key_exists('PrivateStream', $valid['portfolio_item']));
        $t->same(false, array_key_exists('bytes', $valid));

        $t->same(2, count($files));
        $badFile = $files[0];
        $t->same('catalog_names_embedded_files', $badFile['source']);
        $t->same('bad-ci.xml', $badFile['name']);
        $t->same('bad-ci.xml', $badFile['filename']);
        $t->same(10, $badFile['file_spec_object']);
        $t->same(11, $badFile['embedded_file_object']);
        $t->same($badPayload, $badFile['content']);
        $t->same(strlen($badPayload), $badFile['declared_size']);
        $t->same($badChecksum, $badFile['checksum']);
        $t->same(true, $badFile['checksum_matches']);
        $t->same('malformed_filespec_collection_item_omitted', $badFile['portfolio_item_status'] ?? null);
        $t->same(false, array_key_exists('portfolio_item', $badFile));
        $badFileFields = $badFile['portfolio_field_values'] ?? [];
        $t->same(false, array_key_exists('SubjectField', $badFileFields));
        $t->same(false, array_key_exists('StatusField', $badFileFields));
        $t->same('Bad CI source still imports', $badFileFields['DescriptionField']['value'] ?? null);
        $t->same(strlen($badPayload), $badFileFields['BytesField']['value'] ?? null);

        $validFile = $files[1];
        $t->same('valid-ci.xml', $validFile['filename']);
        $t->same($validPayload, $validFile['content']);
        $t->same('Valid CI Subject', $validFile['portfolio_item']['SubjectField'] ?? null);
        $t->same('ready', $validFile['portfolio_item']['StatusField']['value'] ?? null);
        $t->same('status:', $validFile['portfolio_item']['StatusField']['prefix'] ?? null);
        $t->same('Valid CI Subject', $validFile['portfolio_field_values']['SubjectField']['value'] ?? null);
        $t->same('status:ready', $validFile['portfolio_field_values']['StatusField']['display_value'] ?? null);

        $t->same('Collection Item Operand Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'Bad First CI Subject',
            'Bad Duplicate CI Subject',
            'stale-first',
            'stale-duplicate',
            $badPrivatePayload,
            $validPrivatePayload,
            $badPayload,
            $validPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
