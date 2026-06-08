<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$associatedFileArrayBoundaryPdf = static function (): array {
    $catalogPrimaryPayload = '<wp-export><post id="catalog-primary-af-duplicate"/></wp-export>';
    $catalogDuplicatePayload = '<wp-export><post id="catalog-duplicate-af-decoy"/></wp-export>';
    $validNameTreePayload = '<wp-export><post id="valid-nametree-after-af-boundary"/></wp-export>';
    $pageTrailingPayload = '<wp-page><attachment role="page-af-trailing"/></wp-page>';
    $pageTrailingDecoyPayload = '<wp-page><attachment role="page-af-trailing-decoy"/></wp-page>';
    $catalogPrimaryChecksum = md5($catalogPrimaryPayload);
    $catalogDuplicateChecksum = md5($catalogDuplicatePayload);
    $validNameTreeChecksum = md5($validNameTreePayload);
    $pageTrailingChecksum = md5($pageTrailingPayload);
    $pageTrailingDecoyChecksum = md5($pageTrailingDecoyPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Associated File Array Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] /#41F [20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /AF [40 0 R] 41 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(valid-name-tree.xml) 30 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (catalog-primary.xml) /Desc (Catalog primary duplicate AF source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogPrimaryPayload) . " /CheckSum <{$catalogPrimaryChecksum}> >> /Length " . strlen($catalogPrimaryPayload) . " >>\n"
        . "stream\n{$catalogPrimaryPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (catalog-duplicate.xml) /Desc (Catalog duplicate AF decoy) /AFRelationship /Alternative /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogDuplicatePayload) . " /CheckSum <{$catalogDuplicateChecksum}> >> /Length " . strlen($catalogDuplicatePayload) . " >>\n"
        . "stream\n{$catalogDuplicatePayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (valid-name-tree.xml) /Desc (Valid name-tree source after AF boundary) /AFRelationship /Data /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($validNameTreePayload) . " /CheckSum <{$validNameTreeChecksum}> /ModDate (D:20260608003930Z) >> /Length " . strlen($validNameTreePayload) . " >>\n"
        . "stream\n{$validNameTreePayload}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /Filespec /F (page-trailing.xml) /Desc (Page AF trailing operand source) /AFRelationship /Supplement /EF << /F 42 0 R >> >>\nendobj\n"
        . "41 0 obj\n<< /Type /Filespec /F (page-trailing-decoy.xml) /Desc (Page AF trailing operand decoy) /AFRelationship /Alternative /EF << /F 43 0 R >> >>\nendobj\n"
        . "42 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageTrailingPayload) . " /CheckSum <{$pageTrailingChecksum}> >> /Length " . strlen($pageTrailingPayload) . " >>\n"
        . "stream\n{$pageTrailingPayload}\nendstream\nendobj\n"
        . "43 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageTrailingDecoyPayload) . " /CheckSum <{$pageTrailingDecoyChecksum}> >> /Length " . strlen($pageTrailingDecoyPayload) . " >>\n"
        . "stream\n{$pageTrailingDecoyPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $catalogPrimaryPayload,
        $catalogDuplicatePayload,
        $validNameTreePayload,
        $pageTrailingPayload,
        $pageTrailingDecoyPayload,
        $catalogPrimaryChecksum,
        $catalogDuplicateChecksum,
        $validNameTreeChecksum,
        $pageTrailingChecksum,
        $pageTrailingDecoyChecksum,
    ];
};

return [
    'fails closed on duplicate catalog and tailed page AF arrays before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($associatedFileArrayBoundaryPdf): void {
        [
            $pdf,
            $catalogPrimaryPayload,
            $catalogDuplicatePayload,
            $validNameTreePayload,
            $pageTrailingPayload,
            $pageTrailingDecoyPayload,
            $catalogPrimaryChecksum,
            $catalogDuplicateChecksum,
            $validNameTreeChecksum,
            $pageTrailingChecksum,
            $pageTrailingDecoyChecksum,
        ] = $associatedFileArrayBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($validNameTreePayload), $summary['total_bytes']);
        $t->same(['valid-name-tree.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same('valid-name-tree.xml', $attachment['name_key']);
        $t->same('valid-name-tree.xml', $attachment['filename']);
        $t->same('Valid name-tree source after AF boundary', $attachment['description']);
        $t->same('Data', $attachment['relationship']);
        $t->same('base_data_for_visual_presentation', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(30, $attachment['file_spec_object_id']);
        $t->same(31, $attachment['stream_object_id']);
        $t->same(strlen($validNameTreePayload), $attachment['byte_length']);
        $t->same($validNameTreeChecksum, $attachment['checksum_hex']);
        $t->same($validNameTreeChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260608003930Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('associated_file', $attachment));
        $t->same(false, array_key_exists('page_associated_file', $attachment));
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('valid-name-tree.xml', $file['name']);
        $t->same('valid-name-tree.xml', $file['filename']);
        $t->same(30, $file['file_spec_object']);
        $t->same(31, $file['embedded_file_object']);
        $t->same($validNameTreePayload, $file['content']);
        $t->same($validNameTreeChecksum, $file['checksum']);
        $t->same($validNameTreeChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);
        $t->same(false, array_key_exists('associated_file', $file));
        $t->same(false, array_key_exists('page_associated_file', $file));

        $t->same('Associated File Array Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'catalog-primary.xml',
            'catalog-duplicate.xml',
            'page-trailing.xml',
            'page-trailing-decoy.xml',
            'Catalog primary duplicate AF source',
            'Catalog duplicate AF decoy',
            'Page AF trailing operand source',
            'Page AF trailing operand decoy',
            $catalogPrimaryPayload,
            $catalogDuplicatePayload,
            $pageTrailingPayload,
            $pageTrailingDecoyPayload,
            $catalogPrimaryChecksum,
            $catalogDuplicateChecksum,
            $pageTrailingChecksum,
            $pageTrailingDecoyChecksum,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
