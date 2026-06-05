<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

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

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$attachment = $summary['attachments'][0] ?? [];
$portfolio = is_array($attachment) ? ($attachment['portfolio'] ?? []) : [];
$folders = is_array($portfolio) ? ($portfolio['folders'] ?? []) : [];

if (!is_array($portfolio)
    || ($summary['attachment_count'] ?? null) !== 2
    || ($portfolio['folder_count'] ?? null) !== 3
    || array_map(static fn (array $folder): string => (string) ($folder['name'] ?? ''), $folders) !== ['Exports', 'Reports', 'Archive']
    || str_contains($summaryJson, $sourcePayload)
    || str_contains($summaryJson, $reportPayload)
    || str_contains($summaryJson, $privatePayload)
    || str_contains($filesJson, $privatePayload)
    || $plainText !== 'Portfolio Folder Boundary Body'
) {
    throw new RuntimeException('Expected bounded Portfolio folder review metadata without payload leakage.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-attachment-portfolio-folder-boundary-currentbase ' . $htmlJson([
    'native_boundary' => 'catalog /Collection /Folders portfolio folder tree review',
    'attachment_count' => $summary['attachment_count'],
    'folder_count' => $portfolio['folder_count'],
    'folder_names' => array_map(static fn (array $folder): string => (string) ($folder['name'] ?? ''), $folders),
    'root_folder_file' => $folders[0]['files'][0]['filename'] ?? null,
    'child_folder_file' => $folders[1]['files'][0]['filename'] ?? null,
    'cycle_guard_keeps_three_rows' => count($folders) === 3,
    'summary_payloads_omitted' => !str_contains($summaryJson, $sourcePayload)
        && !str_contains($summaryJson, $reportPayload)
        && !str_contains($summaryJson, $privatePayload),
    'embedded_file_private_folder_payload_omitted' => !str_contains($filesJson, $privatePayload),
    'visible_text' => $plainText,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
foreach ($folders as $folder) {
    echo '<li>' . htmlspecialchars((string) ($folder['name'] ?? 'Folder'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
