<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$stalePayload = '<wp-export><post id="stale-local-names-smoke"/></wp-export>';
$currentPayload = '<wp-export><post id="current-child-kids-smoke"/></wp-export>';
$staleChecksum = md5($stalePayload);
$currentChecksum = md5($currentPayload);
$content = 'BT /F1 12 Tf 72 720 Td (Visible Names Kids Attachment Smoke) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 40 0 R >> >> /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Limits [(child-source.xml) (local-stale.xml)] /Names [(local-stale.xml) 10 0 R] /Kids [7 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /Limits [(child-source.xml) (child-source.xml)] /Names [(child-source.xml) 20 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (local-stale.xml) /Desc (Malformed local Names smoke entry) /AFRelationship /Alternative /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($stalePayload) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($stalePayload) . " >>\n"
    . "stream\n{$stalePayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (child-source.xml) /Desc (Child node WordPress smoke source) /AFRelationship /Data /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($currentPayload) . " /CheckSum <{$currentChecksum}> /ModDate (D:20260607105338Z) >> /Length " . strlen($currentPayload) . " >>\n"
    . "stream\n{$currentPayload}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF\n";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$files = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES);
$filesJson = json_encode($files, JSON_UNESCAPED_SLASHES);

if (
    ($summary['attachment_count'] ?? null) !== 1
    || ($summary['filenames'] ?? []) !== ['child-source.xml']
    || count($files) !== 1
    || ($files[0]['name'] ?? null) !== 'child-source.xml'
    || ($files[0]['filename'] ?? null) !== 'child-source.xml'
    || ($files[0]['content'] ?? null) !== $currentPayload
    || ($plainText !== 'Visible Names Kids Attachment Smoke')
    || !is_string($summaryJson)
    || !is_string($filesJson)
    || str_contains($summaryJson, 'local-stale.xml')
    || str_contains($filesJson, 'local-stale.xml')
    || str_contains($summaryJson, $stalePayload)
    || str_contains($filesJson, $stalePayload)
    || str_contains($summaryJson, $currentPayload)
    || str_contains($plainText, '<wp-export>')
) {
    throw new RuntimeException('Expected malformed EmbeddedFiles node /Names entries to be skipped when /Kids is present.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-attachment-nametree-names-kids-boundary-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-embeddedfiles-name-tree-parser',
    'native_boundary' => 'EmbeddedFiles nodes with Kids are treated as intermediate nodes before attachment review',
    'child_attachment_preserved' => ($summary['filenames'] ?? []) === ['child-source.xml'],
    'local_names_entry_excluded' => !str_contains($summaryJson, 'local-stale.xml')
        && !str_contains($filesJson, 'local-stale.xml'),
    'payload_bytes_omitted_from_summary' => !array_key_exists('bytes', $summary['attachments'][0] ?? []),
    'visible_text_excludes_attachment_payloads' => true,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";
