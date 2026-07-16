<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$structElemAssociatedFileBoundaryPdf = static function (): array {
    $validPayload = '<wp-export><post id="structelem-associated-valid"/></wp-export>';
    $decoyPayload = '<wp-export><post id="structelem-associated-decoy"/></wp-export>';
    $validChecksum = md5($validPayload);
    $decoyChecksum = md5($decoyPayload);
    $content = 'BT /F1 12 Tf /ArticleTitle << /MCID 0 >> BDC 72 720 Td (StructElem Associated File Body) Tj EMC ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 20 0 R >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 4 /Contents 5 0 R >>\nendobj\n"
        . "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (structelem-source.xml) /Desc (StructElem associated source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260608151218Z) >> /Length " . strlen($validPayload) . " >>\nstream\n{$validPayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /Filespec /F (structelem-decoy.xml) /Desc (Malformed StructElem trailing AF decoy) /AFRelationship /Alternative /EF << /F 13 0 R >> >>\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($decoyPayload) . " /CheckSum <{$decoyChecksum}> >> /Length " . strlen($decoyPayload) . " >>\nstream\n{$decoyPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ArticleTitle /H1 /PrivateAttachment /P >> /K [21 0 R 22 0 R] >>\nendobj\n"
        . "21 0 obj\n<< /Type /StructElem /S /ArticleTitle /T (Tagged source handoff) /Pg 3 0 R /AF [10 0 R] /K 0 >>\nendobj\n"
        . "22 0 obj\n<< /Type /StructElem /S /PrivateAttachment /T (Malformed trailing associated file) /Pg 3 0 R /AF [12 0 R] 10 0 R /K 0 >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $validPayload, $decoyPayload, $validChecksum, $decoyChecksum];
};

return [
    'carries StructElem associated files into attachment summaries while rejecting malformed AF decoys' => static function (
        TestRunner $t
    ) use ($structElemAssociatedFileBoundaryPdf): void {
        [$pdf, $validPayload, $decoyPayload, $validChecksum, $decoyChecksum] = $structElemAssociatedFileBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['structelem-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('structure-associated-file', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same(true, $attachment['structure_associated_file']);
        $t->same('structure_element_af', $attachment['structure_associated_file_source']);
        $t->same(21, $attachment['structure_object_id']);
        $t->same('ArticleTitle', $attachment['structure_role']);
        $t->same('Tagged source handoff', $attachment['structure_title']);
        $t->same(0, $attachment['structure_associated_file_index']);
        $t->same(10, $attachment['file_spec_object_id']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same('structelem-source.xml', $attachment['filename']);
        $t->same('StructElem associated source export', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260608151218Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('structure_element_associated_files', $file['source']);
        $t->same(true, $file['associated_file']);
        $t->same(true, $file['structure_associated_file']);
        $t->same(21, $file['structure_object_id']);
        $t->same('ArticleTitle', $file['structure_role']);
        $t->same('Tagged source handoff', $file['structure_title']);
        $t->same(0, $file['structure_associated_file_index']);
        $t->same('structelem-source.xml', $file['filename']);
        $t->same($validPayload, $file['content']);
        $t->same(strlen($validPayload), $file['size']);
        $t->same(hash('sha256', $validPayload), $file['content_sha256']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('StructElem Associated File Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'structelem-decoy.xml',
            'Malformed StructElem trailing AF decoy',
            $validPayload,
            $decoyPayload,
            $decoyChecksum,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden === $validPayload ? $decoyPayload : $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
