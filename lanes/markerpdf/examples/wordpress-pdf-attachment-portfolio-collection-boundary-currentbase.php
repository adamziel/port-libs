<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfAttachmentExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = '<wp-export><post id="portfolio-preflight"/></wp-export>';
$privatePayload = 'BT /F1 12 Tf 72 720 Td (Portfolio Private Payload Leak) Tj ET';
$checksum = md5($payload);
$privateChecksum = md5($privatePayload);
$content = 'BT /F1 12 Tf 72 720 Td (Portfolio Attachment Body) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /Names << /EmbeddedFiles 6 0 R >> /AF [10 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Collection /View /T /D (source.xml) /Schema << /NameField << /Subtype /F /N (Filename) /O 1 /V true >> /DescriptionField << /Subtype /Desc /N (Description) /O 2 /E false >> /BytesField << /Subtype /Size /N (Bytes) /O 3 >> /ModifiedField << /Subtype /ModDate /N (Modified) /O 4 >> /Subject << /Subtype /S /N (Subject) /O 5 >> /Priority << /Subtype /N /N (Priority) /O 6 >> >> /Sort << /S [/Priority /ModifiedField] /A [true false] >> >>\nendobj\n"
    . "6 0 obj\n<< /Names [(source.xml) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (legacy-source.xml) /UF (source.xml) /Desc (Portfolio WordPress source) /AFRelationship /Source /CI 30 0 R /EF << /UF 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($payload) . " /CheckSum <{$checksum}> /ModDate (D:20260605111833Z) >> /Length " . strlen($payload) . " >>\nstream\n{$payload}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Type /CollectionItem /Subject (Current Portfolio Export) /Priority << /Type /CollectionSubitem /D 2 /P (P) >> /PrivateStream 40 0 R >>\nendobj\n"
    . "40 0 obj\n<< /Type /Metadata /Subtype /text#2Fplain /Params << /Size " . strlen($privatePayload) . " /CheckSum <{$privateChecksum}> >> /Length " . strlen($privatePayload) . " >>\nstream\n{$privatePayload}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$summary = (new PdfAttachmentExtractor())->attachmentSummary($pdf);
$summaryJson = json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
$attachment = $summary['attachments'][0] ?? null;

if (!is_array($attachment)
    || ($summary['attachment_count'] ?? null) !== 1
    || ($attachment['filename'] ?? null) !== 'source.xml'
    || ($attachment['unicode_filename'] ?? null) !== 'source.xml'
    || ($attachment['portfolio']['source'] ?? null) !== 'catalog_collection'
    || ($attachment['portfolio']['view'] ?? null) !== 'T'
    || ($attachment['portfolio']['sort']['keys'] ?? null) !== ['Priority', 'ModifiedField']
    || ($attachment['portfolio_item']['Subject'] ?? null) !== 'Current Portfolio Export'
    || ($attachment['portfolio_item']['Priority']['display_value'] ?? null) !== 'P2'
    || ($attachment['portfolio_field_values']['NameField']['value'] ?? null) !== 'source.xml'
    || ($attachment['portfolio_field_values']['BytesField']['value'] ?? null) !== strlen($payload)
    || ($attachment['portfolio_field_values']['Priority']['source'] ?? null) !== 'collection_subitem'
    || array_key_exists('PrivateStream', $attachment['portfolio_item'] ?? [])
    || str_contains($summaryJson, $payload)
    || str_contains($summaryJson, $privatePayload)
    || $plainText !== 'Portfolio Attachment Body'
) {
    throw new RuntimeException('Expected portfolio collection attachment preflight metadata without payload leakage.');
}

echo '<!-- markerpdf-pdf-attachment-portfolio-collection-boundary ' . htmlspecialchars(json_encode([
    'native_boundary' => 'catalog /Collection schema and FileSpec /CI attachment preflight',
    'attachment_count' => $summary['attachment_count'],
    'filename' => $attachment['filename'],
    'portfolio_view' => $attachment['portfolio']['view'],
    'portfolio_sort_keys' => $attachment['portfolio']['sort']['keys'],
    'portfolio_item_subject' => $attachment['portfolio_item']['Subject'],
    'portfolio_item_priority' => $attachment['portfolio_item']['Priority']['display_value'],
    'portfolio_field_name_value' => $attachment['portfolio_field_values']['NameField']['value'],
    'portfolio_field_size_value' => $attachment['portfolio_field_values']['BytesField']['value'],
    'portfolio_private_stream_excluded' => !array_key_exists('PrivateStream', $attachment['portfolio_item']),
    'attachment_payload_omitted' => !str_contains($summaryJson, $payload),
    'private_payload_omitted' => !str_contains($summaryJson, $privatePayload),
    'visible_text' => $plainText,
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:list -->\n<ul>\n";
echo '<li data-marker-attachment-sha256="'
    . htmlspecialchars((string) $attachment['sha256'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">'
    . htmlspecialchars(
        $attachment['filename'] . ' portfolio item: '
            . $attachment['portfolio_item']['Subject']
            . ' / '
            . $attachment['portfolio_item']['Priority']['display_value'],
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    )
    . "</li>\n";
echo "</ul>\n<!-- /wp:list -->\n";
