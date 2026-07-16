<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$attachmentNameTreePairOperandBoundaryCurrentBasePdf = static function (): array {
    $malformedPayload = '<wp-export><post id="malformed-name-pair"/></wp-export>';
    $trailingDecoyPayload = '<wp-export><post id="trailing-name-pair-decoy"/></wp-export>';
    $validPayload = '<wp-export><post id="valid-resynced-name-pair"/></wp-export>';
    $malformedChecksum = md5($malformedPayload);
    $trailingDecoyChecksum = md5($trailingDecoyPayload);
    $validChecksum = md5($validPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Name Pair Operand Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(malformed-name.xml) 10 0 R 12 0 R (valid-source.xml) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (malformed-name.xml) /Desc (Malformed name-pair attachment) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($malformedPayload) . " /CheckSum <{$malformedChecksum}> >> /Length " . strlen($malformedPayload) . " >>\n"
        . "stream\n{$malformedPayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /Filespec /F (trailing-decoy.xml) /Desc (Trailing operand decoy attachment) /AFRelationship /Alternative /EF << /F 13 0 R >> >>\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($trailingDecoyPayload) . " /CheckSum <{$trailingDecoyChecksum}> >> /Length " . strlen($trailingDecoyPayload) . " >>\n"
        . "stream\n{$trailingDecoyPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (valid-source.xml) /Desc (Valid resynchronized WordPress source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260607211432Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $malformedPayload,
        $trailingDecoyPayload,
        $validPayload,
        $malformedChecksum,
        $trailingDecoyChecksum,
        $validChecksum,
    ];
};

return [
    'rejects malformed EmbeddedFiles name pairs with trailing operands before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($attachmentNameTreePairOperandBoundaryCurrentBasePdf): void {
        [
            $pdf,
            $malformedPayload,
            $trailingDecoyPayload,
            $validPayload,
            $malformedChecksum,
            $trailingDecoyChecksum,
            $validChecksum,
        ] = $attachmentNameTreePairOperandBoundaryCurrentBasePdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('valid-source.xml', $attachment['name_key']);
        $t->same('valid-source.xml', $attachment['filename']);
        $t->same('Valid resynchronized WordPress source', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
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
        $t->same('D:20260607211432Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-source.xml', $file['name']);
        $t->same('valid-source.xml', $file['filename']);
        $t->same('Valid resynchronized WordPress source', $file['description']);
        $t->same('Data', $file['relationship']);
        $t->same(20, $file['file_spec_object']);
        $t->same(21, $file['embedded_file_object']);
        $t->same($validPayload, $file['content']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Visible Attachment Name Pair Operand Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        foreach ([
            'malformed-name.xml',
            'trailing-decoy.xml',
            'Malformed name-pair attachment',
            'Trailing operand decoy attachment',
            $malformedPayload,
            $trailingDecoyPayload,
            $malformedChecksum,
            $trailingDecoyChecksum,
            $validPayload,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
