<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$filenamePathBoundaryPdf = static function (): array {
    $sourcePayload = '<wp-export><post id="path-filename"/></wp-export>';
    $urlPayload = '{"source":"url-filespec"}';
    $sourceChecksum = md5($sourcePayload);
    $urlChecksum = md5($urlPayload);

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n"
        . "6 0 obj\n<< /Names [(path-source) 10 0 R (url-source) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (C:\\\\Users\\\\Editor\\\\Downloads\\\\legacy-export.xml) /UF (../exports/wp-export.xml) /Desc (Path-shaped WordPress source) /AFRelationship /Source /EF << /UF 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260605073720Z) >> /Length " . strlen($sourcePayload) . " >>\n"
        . "stream\n{$sourcePayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /FS /URL /F (https://example.test/private/archive/wp-payload.json?download=1) /Desc (URL-backed payload source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($urlPayload) . " /CheckSum <{$urlChecksum}> /ModDate (D:20260605073721Z) >> /Length " . strlen($urlPayload) . " >>\n"
        . "stream\n{$urlPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $sourcePayload, $urlPayload];
};

$findAttachment = static function (array $rows, string $filename): array {
    foreach ($rows as $row) {
        if (is_array($row) && ($row['filename'] ?? null) === $filename) {
            return $row;
        }
    }

    throw new RuntimeException('Unable to find attachment row for ' . $filename);
};

return [
    'adds safe basename review for path shaped FileSpec names in attachment preflight' => static function (
        TestRunner $t
    ) use ($filenamePathBoundaryPdf, $findAttachment): void {
        [$pdf, $sourcePayload, $urlPayload] = $filenamePathBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $encoded = json_encode($summary, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);

        $pathAttachment = $findAttachment($summary['attachments'], '../exports/wp-export.xml');
        $t->same('embedded-files-name-tree', $pathAttachment['source']);
        $t->same('path-source', $pathAttachment['name_key']);
        $t->same('UF', $pathAttachment['filename_source']);
        $t->same('../exports/wp-export.xml', $pathAttachment['filename']);
        $t->same('wp-export.xml', $pathAttachment['filename_leaf']);
        $t->same('wp-export.xml', $pathAttachment['filename_storage_name']);
        $t->same('relative_path_segments_review_only', $pathAttachment['filename_path_status']);
        $t->same(true, $pathAttachment['filename_has_path_segments']);
        $t->same(true, $pathAttachment['filename_contains_parent_segment']);
        $t->same('Source', $pathAttachment['relationship']);
        $t->same('original_source', $pathAttachment['relationship_role']);
        $t->same(strlen($sourcePayload), $pathAttachment['byte_length']);
        $t->same(true, $pathAttachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $pathAttachment));

        $urlAttachment = $findAttachment($summary['attachments'], 'https://example.test/private/archive/wp-payload.json?download=1');
        $t->same('URL', $urlAttachment['file_system']);
        $t->same('external_url_file_system_review_only', $urlAttachment['file_system_status']);
        $t->same('wp-payload.json', $urlAttachment['filename_leaf']);
        $t->same('wp-payload.json', $urlAttachment['filename_storage_name']);
        $t->same('url_path_review_only', $urlAttachment['filename_path_status']);
        $t->same('https', $urlAttachment['filename_url_scheme']);
        $t->same(true, $urlAttachment['filename_has_path_segments']);
        $t->same('Data', $urlAttachment['relationship']);
        $t->same('base_data_for_visual_presentation', $urlAttachment['relationship_role']);
        $t->same(strlen($urlPayload), $urlAttachment['byte_length']);
        $t->same(true, $urlAttachment['checksum_matches']);
        $t->same(false, array_key_exists('bytes', $urlAttachment));

        $t->true(is_string($encoded) && !str_contains($encoded, $sourcePayload));
        $t->true(is_string($encoded) && !str_contains($encoded, $urlPayload));
    },
    'propagates safe basename review through embedded-file rows without visible text leakage' => static function (
        TestRunner $t
    ) use ($filenamePathBoundaryPdf, $findAttachment): void {
        [$pdf, $sourcePayload, $urlPayload] = $filenamePathBoundaryPdf();

        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);

        $t->same(2, count($files));

        $pathFile = $findAttachment($files, '../exports/wp-export.xml');
        $t->same('catalog_names_embedded_files', $pathFile['source']);
        $t->same('path-source', $pathFile['name']);
        $t->same('wp-export.xml', $pathFile['filename_leaf']);
        $t->same('wp-export.xml', $pathFile['filename_storage_name']);
        $t->same('relative_path_segments_review_only', $pathFile['filename_path_status']);
        $t->same(true, $pathFile['filename_has_path_segments']);
        $t->same(true, $pathFile['filename_contains_parent_segment']);
        $t->same($sourcePayload, $pathFile['content']);

        $urlFile = $findAttachment($files, 'https://example.test/private/archive/wp-payload.json?download=1');
        $t->same('URL', $urlFile['file_system']);
        $t->same('wp-payload.json', $urlFile['filename_leaf']);
        $t->same('wp-payload.json', $urlFile['filename_storage_name']);
        $t->same('url_path_review_only', $urlFile['filename_path_status']);
        $t->same('https', $urlFile['filename_url_scheme']);
        $t->same(true, $urlFile['filename_has_path_segments']);
        $t->same($urlPayload, $urlFile['content']);

        $t->same('', (new PdfTextExtractor())->extractPlainText($pdf));
    },
];
