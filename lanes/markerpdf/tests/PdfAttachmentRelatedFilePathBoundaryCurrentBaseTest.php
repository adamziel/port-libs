<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$relatedFilePathBoundaryPdf = static function (): array {
    $sourcePayload = '<wp-export><post id="related-path-source"/></wp-export>';
    $notesPayload = "review=private\n";
    $manifestPayload = '{"manifest":"url-related"}';
    $notesChecksum = md5($notesPayload);
    $manifestChecksum = md5($manifestPayload);
    $pageContent = 'BT /F1 12 Tf 72 720 Td (Related File Path Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
        . "6 0 obj\n<< /Names [(source.xml) 10 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Source with path-shaped related files) /AFRelationship /Source /EF << /F 11 0 R >>"
        . " /RF << /F [(../private/review-notes.txt) 12 0 R] /UF [(https://example.test/download/private/manifest.json?token=secret) 13 0 R] >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " >> /Length " . strlen($sourcePayload) . " >>\n"
        . "stream\n{$sourcePayload}\nendstream\nendobj\n"
        . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fplain /Params << /Size " . strlen($notesPayload) . " /CheckSum <{$notesChecksum}> >> /Length " . strlen($notesPayload) . " >>\n"
        . "stream\n{$notesPayload}\nendstream\nendobj\n"
        . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($manifestPayload) . " /CheckSum <{$manifestChecksum}> >> /Length " . strlen($manifestPayload) . " >>\n"
        . "stream\n{$manifestPayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $sourcePayload, $notesPayload, $manifestPayload, $notesChecksum, $manifestChecksum];
};

return [
    'adds safe basename review for path shaped FileSpec related filenames' => static function (
        TestRunner $t
    ) use ($relatedFilePathBoundaryPdf): void {
        [$pdf, $sourcePayload, $notesPayload, $manifestPayload, $notesChecksum, $manifestChecksum] = $relatedFilePathBoundaryPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(1, $summary['attachment_count']);
        $t->same(['source.xml'], $summary['filenames']);

        $attachment = $summary['attachments'][0];
        $t->same('embedded-files-name-tree', $attachment['source']);
        $t->same(true, $attachment['associated_file']);
        $t->same('catalog_af', $attachment['associated_file_source']);
        $t->same('source.xml', $attachment['filename']);
        $t->same('Source', $attachment['relationship']);
        $t->same(2, $attachment['related_file_count']);

        $related = $attachment['related_files'];
        $t->same('../private/review-notes.txt', $related[0]['related_filename']);
        $t->same('review-notes.txt', $related[0]['related_filename_leaf']);
        $t->same('review-notes.txt', $related[0]['related_filename_storage_name']);
        $t->same('relative_path_segments_review_only', $related[0]['related_filename_path_status']);
        $t->same(true, $related[0]['related_filename_has_path_segments']);
        $t->same(true, $related[0]['related_filename_contains_parent_segment']);
        $t->same($notesChecksum, $related[0]['checksum_hex']);
        $t->same(true, $related[0]['checksum_matches']);

        $t->same('https://example.test/download/private/manifest.json?token=secret', $related[1]['related_filename']);
        $t->same('manifest.json', $related[1]['related_filename_leaf']);
        $t->same('manifest.json', $related[1]['related_filename_storage_name']);
        $t->same('url_path_review_only', $related[1]['related_filename_path_status']);
        $t->same('https', $related[1]['related_filename_url_scheme']);
        $t->same(true, $related[1]['related_filename_has_path_segments']);
        $t->same($manifestChecksum, $related[1]['checksum_hex']);
        $t->same(true, $related[1]['checksum_matches']);

        $t->same(1, count($files));
        $file = $files[0];
        $t->same('catalog_names_embedded_files', $file['source']);
        $t->same('source.xml', $file['filename']);
        $t->same($sourcePayload, $file['content']);
        $t->same(2, $file['related_file_count']);
        $t->same('review-notes.txt', $file['related_files'][0]['related_filename_leaf']);
        $t->same('review-notes.txt', $file['related_files'][0]['related_filename_storage_name']);
        $t->same('relative_path_segments_review_only', $file['related_files'][0]['related_filename_path_status']);
        $t->same(true, $file['related_files'][0]['related_filename_contains_parent_segment']);
        $t->same('manifest.json', $file['related_files'][1]['related_filename_leaf']);
        $t->same('manifest.json', $file['related_files'][1]['related_filename_storage_name']);
        $t->same('url_path_review_only', $file['related_files'][1]['related_filename_path_status']);
        $t->same('https', $file['related_files'][1]['related_filename_url_scheme']);

        $t->same('Related File Path Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->same(false, array_key_exists('bytes', $attachment));
        $t->same(false, array_key_exists('content', $related[0]));
        $t->same(false, array_key_exists('content', $related[1]));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $sourcePayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $notesPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $manifestPayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $notesPayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $manifestPayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'url-related'));
    },
];
