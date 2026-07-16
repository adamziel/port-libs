<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$catalogNamesDuplicateEmbeddedFilesPdf = static function (): array {
    $catalogPayload = '<wp-export><post id="catalog-af-source"/></wp-export>';
    $currentNameTreePayload = '<wp-export><post id="current-name-tree-source"/></wp-export>';
    $staleNameTreePayload = '<wp-export><post id="stale-duplicate-embeddedfiles"/></wp-export>';
    $catalogChecksum = md5($catalogPayload);
    $currentNameTreeChecksum = md5($currentNameTreePayload);
    $staleNameTreeChecksum = md5($staleNameTreePayload);
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Catalog Names Duplicate EmbeddedFiles Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /#45mbeddedFiles 6 0 R /EmbeddedFiles 7 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(current-name-tree.xml) 20 0 R] >>\nendobj\n"
        . "7 0 obj\n<< /Names [(stale-name-tree.xml) 30 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (catalog-source.xml) /Desc (Catalog AF fallback source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($catalogPayload) . " /CheckSum <{$catalogChecksum}> /ModDate (D:20260607004300Z) >> /Length " . strlen($catalogPayload) . " >>\n"
        . "stream\n{$catalogPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /F (current-name-tree.xml) /Desc (Current duplicate catalog Names source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($currentNameTreePayload) . " /CheckSum <{$currentNameTreeChecksum}> >> /Length " . strlen($currentNameTreePayload) . " >>\n"
        . "stream\n{$currentNameTreePayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /Filespec /F (stale-name-tree.xml) /Desc (Stale duplicate catalog Names source) /AFRelationship /Alternative /EF << /F 31 0 R >> >>\nendobj\n"
        . "31 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($staleNameTreePayload) . " /CheckSum <{$staleNameTreeChecksum}> >> /Length " . strlen($staleNameTreePayload) . " >>\n"
        . "stream\n{$staleNameTreePayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [
        $pdf,
        $catalogPayload,
        $currentNameTreePayload,
        $staleNameTreePayload,
        $catalogChecksum,
        $currentNameTreeChecksum,
        $staleNameTreeChecksum,
    ];
};

return [
    'fails closed on duplicate catalog Names EmbeddedFiles keys before WordPress attachment review' => static function (
        TestRunner $t
    ) use ($catalogNamesDuplicateEmbeddedFilesPdf): void {
        [
            $pdf,
            $catalogPayload,
            $currentNameTreePayload,
            $staleNameTreePayload,
            $catalogChecksum,
            $currentNameTreeChecksum,
            $staleNameTreeChecksum,
        ] = $catalogNamesDuplicateEmbeddedFilesPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(strlen($catalogPayload), $summary['total_bytes']);
        $t->same(['catalog-source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('catalog-associated-file', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same(1, $attachment['catalog_object_id']);
        $t->same(0, $attachment['associated_file_index']);
        $t->same(10, $attachment['file_spec_object_id']);
        $t->same(11, $attachment['stream_object_id']);
        $t->same('catalog-source.xml', $attachment['filename']);
        $t->same('Catalog AF fallback source', $attachment['description']);
        $t->same('Source', $attachment['relationship']);
        $t->same('original_source', $attachment['relationship_role']);
        $t->same('text/xml', $attachment['content_type']);
        $t->same(strlen($catalogPayload), $attachment['declared_size']);
        $t->same(true, $attachment['declared_size_matches']);
        $t->same(strlen($catalogPayload), $attachment['byte_length']);
        $t->same(hash('sha256', $catalogPayload), $attachment['sha256']);
        $t->same($catalogChecksum, $attachment['checksum_hex']);
        $t->same($catalogChecksum, $attachment['computed_checksum_hex']);
        $t->same(true, $attachment['checksum_matches']);
        $t->same('D:20260607004300Z', $attachment['modified_at']);
        $t->same(false, array_key_exists('bytes', $attachment));

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_associated_files', $file['source']);
        $t->same(true, $file['associated_file']);
        $t->same(0, $file['associated_file_index']);
        $t->same('catalog-source.xml', $file['name']);
        $t->same('catalog-source.xml', $file['filename']);
        $t->same(10, $file['file_spec_object']);
        $t->same(11, $file['embedded_file_object']);
        $t->same($catalogPayload, $file['content']);
        $t->same($catalogChecksum, $file['checksum']);
        $t->same($catalogChecksum, $file['computed_checksum']);
        $t->same(true, $file['checksum_matches']);

        $t->same('Catalog Names Duplicate EmbeddedFiles Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        foreach ([
            'current-name-tree.xml',
            'stale-name-tree.xml',
            'Current duplicate catalog Names source',
            'Stale duplicate catalog Names source',
            $catalogPayload,
            $currentNameTreePayload,
            $staleNameTreePayload,
            $currentNameTreeChecksum,
            $staleNameTreeChecksum,
        ] as $hidden) {
            $t->true(is_string($summaryJson) && !str_contains($summaryJson, $hidden));
        }
        foreach ([
            'current-name-tree.xml',
            'stale-name-tree.xml',
            $currentNameTreePayload,
            $staleNameTreePayload,
            $currentNameTreeChecksum,
            $staleNameTreeChecksum,
        ] as $hidden) {
            $t->true(is_string($filesJson) && !str_contains($filesJson, $hidden));
            $t->true(!str_contains($plainText, $hidden));
        }
    },
];
