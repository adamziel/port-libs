<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$annotationFsKeyBoundaryPdf = static function (): array {
    $duplicatePayload = '<wp-export><post id="duplicate-annotation-fs"/></wp-export>';
    $duplicateDecoyPayload = '<wp-export><post id="duplicate-annotation-fs-decoy"/></wp-export>';
    $trailingPayload = '<wp-export><post id="trailing-annotation-fs"/></wp-export>';
    $trailingDecoyPayload = '<wp-export><post id="trailing-annotation-fs-decoy"/></wp-export>';
    $validPayload = '<wp-export><post id="valid-annotation-fs"/></wp-export>';
    $duplicateChecksum = md5($duplicatePayload);
    $duplicateDecoyChecksum = md5($duplicateDecoyPayload);
    $trailingChecksum = md5($trailingPayload);
    $trailingDecoyChecksum = md5($trailingDecoyPayload);
    $validChecksum = md5($validPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Annotation FS Key Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Annots [8 0 R 12 0 R 16 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "8 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [72 700 92 720] /Contents (Duplicate annotation FS source) /FS 9 0 R /#46S 10 0 R >>\nendobj\n"
        . "9 0 obj\n<< /Type /Filespec /F (duplicate-fs-current.xml) /Desc (Duplicate FS current source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (duplicate-fs-decoy.xml) /Desc (Duplicate FS decoy source) /AFRelationship /Alternative /EF << /F 19 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicatePayload) . " /CheckSum <{$duplicateChecksum}> >> /Length " . strlen($duplicatePayload) . " >>\n"
        . "stream\n{$duplicatePayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [120 700 140 720] /Contents (Trailing annotation FS source) /FS 13 0 R 14 0 R >>\nendobj\n"
        . "13 0 obj\n<< /Type /Filespec /F (trailing-fs-current.xml) /Desc (Trailing FS current source) /AFRelationship /Supplement /EF << /F 15 0 R >> >>\nendobj\n"
        . "14 0 obj\n<< /Type /Filespec /F (trailing-fs-decoy.xml) /Desc (Trailing FS decoy source) /AFRelationship /Alternative /EF << /F 20 0 R >> >>\nendobj\n"
        . "15 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($trailingPayload) . " /CheckSum <{$trailingChecksum}> >> /Length " . strlen($trailingPayload) . " >>\n"
        . "stream\n{$trailingPayload}\nendstream\nendobj\n"
        . "16 0 obj\n<< /Type /Annot /Subtype /FileAttachment /Rect [168 700 188 720] /Contents (Valid annotation FS source) /FS 17 0 R >>\nendobj\n"
        . "17 0 obj\n<< /Type /Filespec /F (valid-annotation-fs.xml) /Desc (Valid annotation FS source) /AFRelationship /Data /EF << /F 18 0 R >> >>\nendobj\n"
        . "18 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608131159Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "19 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($duplicateDecoyPayload) . " /CheckSum <{$duplicateDecoyChecksum}> >> /Length " . strlen($duplicateDecoyPayload) . " >>\n"
        . "stream\n{$duplicateDecoyPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($trailingDecoyPayload) . " /CheckSum <{$trailingDecoyChecksum}> >> /Length " . strlen($trailingDecoyPayload) . " >>\n"
        . "stream\n{$trailingDecoyPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $duplicatePayload,
        $duplicateDecoyPayload,
        $trailingPayload,
        $trailingDecoyPayload,
        $validPayload,
        $duplicateChecksum,
        $duplicateDecoyChecksum,
        $trailingChecksum,
        $trailingDecoyChecksum,
        $validChecksum,
    ];
};

return [
    'fails closed on duplicate and tailed FileAttachment annotation FS keys before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($annotationFsKeyBoundaryPdf): void {
        [
            $pdf,
            $duplicatePayload,
            $duplicateDecoyPayload,
            $trailingPayload,
            $trailingDecoyPayload,
            $validPayload,
            $duplicateChecksum,
            $duplicateDecoyChecksum,
            $trailingChecksum,
            $trailingDecoyChecksum,
            $validChecksum,
        ] = $annotationFsKeyBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-annotation-fs.xml'], $summary['filenames']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $attachment = $summary['attachments'][0];
        $t->same('file-attachment-annotation', $attachment['source']);
        $t->same(true, $attachment['file_attachment_annotation']);
        $t->same('page_annotation', $attachment['file_attachment_annotation_source']);
        $t->same(1, $attachment['page_number']);
        $t->same(3, $attachment['page_object_id']);
        $t->same(16, $attachment['annotation_object_id']);
        $t->same('Valid annotation FS source', $attachment['annotation_contents']);
        $t->same([168.0, 700.0, 188.0, 720.0], $attachment['annotation_rect']);
        $t->same(17, $attachment['file_spec_object_id']);
        $t->same(18, $attachment['stream_object_id']);
        $t->same('valid-annotation-fs.xml', $attachment['filename']);
        $t->same('Valid annotation FS source', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260608131159Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same('Annotation FS Key Boundary Body', $plainText);
        foreach ([
            'duplicate-fs-current.xml',
            'duplicate-fs-decoy.xml',
            'trailing-fs-current.xml',
            'trailing-fs-decoy.xml',
            'Duplicate FS current source',
            'Duplicate FS decoy source',
            'Trailing FS current source',
            'Trailing FS decoy source',
            $duplicatePayload,
            $duplicateDecoyPayload,
            $trailingPayload,
            $trailingDecoyPayload,
            $duplicateChecksum,
            $duplicateDecoyChecksum,
            $trailingChecksum,
            $trailingDecoyChecksum,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $validPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
    },
];
