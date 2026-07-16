<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$portfolioFolderPdf = static function (): array {
    $sourcePayload = '<wp-export><post id="portfolio-folder-source"/></wp-export>';
    $reportPayload = "Title,Status\nFolder Report,Ready\n";
    $privatePayload = 'BT /F1 12 Tf 72 720 Td (Portfolio Folder Private Payload Leak) Tj ET';
    $sourceChecksum = md5($sourcePayload);
    $reportChecksum = md5($reportPayload);
    $content = 'BT /F1 12 Tf 72 720 Td (Portfolio Folder Boundary Body) Tj ET';

    $pdf = "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R 20 0 R] >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Collection /View /D /D (source.xml) /Folders 50 0 R /Schema << /NameField << /Subtype /F /N (Filename) /O 1 >> /Subject << /Subtype /S /N (Subject) /O 2 >> >> >>\nendobj\n"
        . "6 0 obj\n<< /Names [(source.xml) 10 0 R (report.csv) 20 0 R] >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Folder source WordPress export) /AFRelationship /Source /CI << /Subject (Folder Source) >> /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260605225819Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Filespec /UF (report.csv) /Desc (Folder report data) /AFRelationship /Data /CI << /Subject (Folder Report) >> /EF << /UF 21 0 R >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fcsv /Params << /Size " . strlen($reportPayload) . " /CheckSum <{$reportChecksum}> /ModDate (D:20260605225820Z) >> /Length " . strlen($reportPayload) . " >>\nstream\n{$reportPayload}\nendstream\nendobj\n"
        . "50 0 obj\n<< /Type /Folder /Name (Exports) /Desc (Current export folder) /CreationDate (D:20260605225819Z) /ModDate (D:20260605225919Z) /F [10 0 R] /Child 60 0 R /Next 70 0 R /Private 80 0 R >>\nendobj\n"
        . "60 0 obj\n<< /Type /Folder /Name (Reports) /Parent 50 0 R /F [20 0 R] >>\nendobj\n"
        . "70 0 obj\n<< /Type /Folder /Name (Archive) /Desc (External archive pointer) /F [(legacy/external.txt)] /Child 50 0 R >>\nendobj\n"
        . "80 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Length " . strlen($privatePayload) . " >>\nstream\n{$privatePayload}\nendstream\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";

    return [$pdf, $sourcePayload, $reportPayload, $privatePayload];
};

return [
    'carries bounded Portfolio Collection folder tree metadata into attachment review' => static function (
        TestRunner $t
    ) use ($portfolioFolderPdf): void {
        [$pdf, $sourcePayload, $reportPayload, $privatePayload] = $portfolioFolderPdf();

        $summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
        $files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
        $plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
        $summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
        $filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

        $t->same(2, $summary['attachment_count']);
        $t->same(['source.xml', 'report.csv'], $summary['filenames']);
        $t->same(strlen($sourcePayload) + strlen($reportPayload), $summary['total_bytes']);

        $attachment = $summary['attachments'][0];
        $portfolio = $attachment['portfolio'] ?? [];
        $t->same('catalog_collection', $portfolio['source'] ?? null);
        $t->same('Collection', $portfolio['type'] ?? null);
        $t->same('D', $portfolio['view'] ?? null);
        $t->same('source.xml', $portfolio['default_document'] ?? null);
        $t->same(true, $portfolio['has_folders'] ?? null);
        $t->same(3, $portfolio['folder_count'] ?? null);

        $folders = $portfolio['folders'] ?? [];
        $t->same(['Exports', 'Reports', 'Archive'], array_map(
            static fn (array $folder): string => (string) ($folder['name'] ?? ''),
            $folders
        ));

        $root = $folders[0] ?? [];
        $t->same('collection_folder_tree', $root['source'] ?? null);
        $t->same(true, $root['review_only'] ?? null);
        $t->same(false, $root['payload_bytes_included'] ?? null);
        $t->same(50, $root['folder_object_id'] ?? null);
        $t->same(0, $root['depth'] ?? null);
        $t->same(0, $root['sibling_index'] ?? null);
        $t->same('Current export folder', $root['description'] ?? null);
        $t->same('D:20260605225819Z', $root['created_at'] ?? null);
        $t->same('2026-06-05T22:58:19Z', $root['created_at_utc'] ?? null);
        $t->same('D:20260605225919Z', $root['modified_at'] ?? null);
        $t->same('2026-06-05T22:59:19Z', $root['modified_at_utc'] ?? null);
        $t->same(60, $root['child_folder_object_id'] ?? null);
        $t->same(70, $root['next_folder_object_id'] ?? null);
        $t->same(1, $root['file_count'] ?? null);
        $t->same(10, $root['files'][0]['file_spec_object_id'] ?? null);
        $t->same('source.xml', $root['files'][0]['filename'] ?? null);
        $t->same('Folder source WordPress export', $root['files'][0]['description'] ?? null);
        $t->same('Source', $root['files'][0]['relationship'] ?? null);
        $t->same(false, $root['files'][0]['payload_bytes_included'] ?? null);

        $child = $folders[1] ?? [];
        $t->same(60, $child['folder_object_id'] ?? null);
        $t->same(50, $child['parent_folder_object_id'] ?? null);
        $t->same(1, $child['depth'] ?? null);
        $t->same(20, $child['files'][0]['file_spec_object_id'] ?? null);
        $t->same('report.csv', $child['files'][0]['filename'] ?? null);
        $t->same('UF', $child['files'][0]['filename_source'] ?? null);
        $t->same('Data', $child['files'][0]['relationship'] ?? null);

        $sibling = $folders[2] ?? [];
        $t->same(70, $sibling['folder_object_id'] ?? null);
        $t->same(0, $sibling['depth'] ?? null);
        $t->same(1, $sibling['sibling_index'] ?? null);
        $t->same(50, $sibling['child_folder_object_id'] ?? null);
        $t->same('legacy/external.txt', $sibling['files'][0]['filename'] ?? null);
        $t->same('collection_folder_file_name', $sibling['files'][0]['filename_source'] ?? null);
        $t->same('relative_path_segments_review_only', $sibling['files'][0]['filename_path_status'] ?? null);
        $t->same('external.txt', $sibling['files'][0]['filename_storage_name'] ?? null);

        $embeddedPortfolio = $files[0]['portfolio'] ?? [];
        $t->same(3, $embeddedPortfolio['folder_count'] ?? null);
        $t->same('Exports', $embeddedPortfolio['folders'][0]['name'] ?? null);
        $t->same('Reports', $embeddedPortfolio['folders'][1]['name'] ?? null);
        $t->same('Archive', $embeddedPortfolio['folders'][2]['name'] ?? null);

        $t->same('Portfolio Folder Boundary Body', $plainText);
        $t->same(false, $summary['executes_python_or_models']);
        $t->same(false, $summary['executes_external_pdf_tools']);
        $t->same(false, array_key_exists('bytes', $summary['attachments'][0]));
        $t->same(false, array_key_exists('bytes', $summary['attachments'][1]));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $sourcePayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $reportPayload));
        $t->true(is_string($summaryJson) && !str_contains($summaryJson, $privatePayload));
        $t->true(is_string($filesJson) && !str_contains($filesJson, $privatePayload));
        $t->true(!str_contains($plainText, '<wp-export>'));
        $t->true(!str_contains($plainText, 'Portfolio Folder Private Payload Leak'));
    },
];
