<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$directAssociatedFileSpecBoundaryPdf = static function (): array {
    $validPayload = '<wp-export><post id="valid-direct-af-filespec"/></wp-export>';
    $catalogDuplicatePayload = '<wp-export><post id="duplicate-catalog-af-filespec"/></wp-export>';
    $pageDuplicatePayload = '<wp-page><attachment role="duplicate-page-af-ef"/></wp-page>';
    $pageDuplicateDecoyPayload = '<wp-page><attachment role="duplicate-page-af-decoy"/></wp-page>';
    $validChecksum = md5($validPayload);
    $catalogDuplicateChecksum = md5($catalogDuplicatePayload);
    $pageDuplicateChecksum = md5($pageDuplicatePayload);
    $pageDuplicateDecoyChecksum = md5($pageDuplicateDecoyPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Direct Associated FileSpec Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /AF ["
        . "<< /Type /Filespec /F (catalog-current.xml) /F (catalog-stale.xml) /Desc (Duplicate direct catalog AF FileSpec) /AFRelationship /Source /EF << /F 11 0 R >> >> "
        . "<< /Type /Filespec /F (valid-direct-af.xml) /Desc (Valid direct catalog associated file) /AFRelationship /Data /EF << /F 21 0 R >> >>"
        . "] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /AF ["
        . "<< /Type /Filespec /F (page-current.xml) /Desc (Duplicate direct page AF EF keys) /AFRelationship /Supplement /EF << /F 31 0 R /F 32 0 R >> >>"
        . "] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogDuplicatePayload) . " /CheckSum <{$catalogDuplicateChecksum}> >> /Length " . strlen($catalogDuplicatePayload) . " >>\n"
        . "stream\n{$catalogDuplicatePayload}\nendstream\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validPayload) . " /CheckSum <{$validChecksum}> /ModDate (D:20260606025138Z) >> /Length " . strlen($validPayload) . " >>\n"
        . "stream\n{$validPayload}\nendstream\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageDuplicatePayload) . " /CheckSum <{$pageDuplicateChecksum}> >> /Length " . strlen($pageDuplicatePayload) . " >>\n"
        . "stream\n{$pageDuplicatePayload}\nendstream\nendobj\n"
        . "32 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageDuplicateDecoyPayload) . " /CheckSum <{$pageDuplicateDecoyChecksum}> >> /Length " . strlen($pageDuplicateDecoyPayload) . " >>\n"
        . "stream\n{$pageDuplicateDecoyPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $validPayload,
        $catalogDuplicatePayload,
        $pageDuplicatePayload,
        $pageDuplicateDecoyPayload,
        $validChecksum,
        $catalogDuplicateChecksum,
        $pageDuplicateChecksum,
        $pageDuplicateDecoyChecksum,
    ];
};

return [
    'fails closed on malformed direct catalog and page AF FileSpecs before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($directAssociatedFileSpecBoundaryPdf): void {
        [
            $pdf,
            $validPayload,
            $catalogDuplicatePayload,
            $pageDuplicatePayload,
            $pageDuplicateDecoyPayload,
            $validChecksum,
            $catalogDuplicateChecksum,
            $pageDuplicateChecksum,
            $pageDuplicateDecoyChecksum,
        ] = $directAssociatedFileSpecBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validPayload), $summary['total_bytes']);
        $t->same(['valid-direct-af.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('catalog-associated-file', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same(1, $attachment['catalog_object_id']);
        $t->same(1, $attachment['associated_file_index']);
        $t->same(null, $attachment['file_spec_object_id']);
        $t->same(21, $attachment['stream_object_id']);
        $t->same('valid-direct-af.xml', $attachment['filename']);
        $t->same('Valid direct catalog associated file', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($validPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($validPayload), $attachment['byte_length']);
        $t->same(hash('sha256', $validPayload), $attachment['sha256']);
        $t->same($validChecksum, $attachment['checksum_hex']);
        $t->same($validChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260606025138Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_associated_files', $file['source']);
        $t->same(true, $file['associated_file']);
        $t->same(1, $file['associated_file_index']);
        $t->same('valid-direct-af.xml', $file['name']);
        $t->same('valid-direct-af.xml', $file['filename']);
        $t->same(null, $file['file_spec_object']);
        $t->same(21, $file['embedded_file_object']);
        $t->same($validPayload, $file['content']);
        $t->same($validChecksum, $file['checksum']);
        $t->same($validChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Direct Associated FileSpec Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'catalog-stale.xml',
            'page-current.xml',
            $validPayload,
            $catalogDuplicatePayload,
            $pageDuplicatePayload,
            $pageDuplicateDecoyPayload,
            $catalogDuplicateChecksum,
            $pageDuplicateChecksum,
            $pageDuplicateDecoyChecksum,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
        }
        $t->true(is_string($filesJson) && !str_contains($filesJson, $catalogDuplicatePayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $pageDuplicatePayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $pageDuplicateDecoyPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, '<wp-page>'));
    },
];
