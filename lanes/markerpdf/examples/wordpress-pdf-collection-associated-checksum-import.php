<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourceXml = '<wp-export><post id="108"/></wp-export>';
$previewJson = '{"preview":"stale-checksum"}';
$sourceChecksum = strtoupper(hash('md5', $sourceXml));
$staleChecksum = str_repeat('0f', 16);
$pageContent = 'BT /F1 12 Tf 72 720 Td (Portfolio Attachment Review) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /PageMode /UseAttachments /Collection 5 0 R /AF [10 0 R 20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "5 0 obj\n<< /Type /Collection /View /T /D (source.xml) /Schema << /Subject << /Subtype /S /N (Subject) /O 1 /V true >> /Checksum << /Subtype /S /N (Checksum state) /O 2 >> >> /Sort << /S [/Subject /Checksum] /A [true false] >> >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (source.xml) /Desc (Original WordPress export) /AFRelationship /Source /CI 30 0 R /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourceXml) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602092000Z) >> /Length " . strlen($sourceXml) . " >>\nstream\n{$sourceXml}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Filespec /F (preview.json) /Desc (Generated preview payload) /AFRelationship /Alternative /CI << /Subject (Preview JSON) /Checksum << /Type /CollectionSubitem /D (stale) /P (checksum: ) >> >> /EF << /F 21 0 R >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Params << /Size " . strlen($previewJson) . " /CheckSum <{$staleChecksum}> >> /Length " . strlen($previewJson) . " >>\nstream\n{$previewJson}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Subject (WordPress Export) /Checksum << /Type /CollectionSubitem /D (verified) /P (checksum: ) >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$attachments = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = trim((new PdfTextExtractor())->extractPlainText($pdf));
if (count($attachments) !== 2) {
    throw new RuntimeException('Expected two catalog-associated portfolio attachments.');
}

$source = $attachments[0];
$preview = $attachments[1];
if (($source['portfolio']['source'] ?? null) !== 'catalog_collection') {
    throw new RuntimeException('Expected catalog collection metadata on associated attachment.');
}
if (($source['checksum_matches'] ?? null) !== true || ($preview['checksum_matches'] ?? null) !== false) {
    throw new RuntimeException('Expected verified and stale checksum review states.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-pdf-collection-associated-checksum-smoke ' . $htmlJson([
    'support_component' => 'native-pdf-collection-associated-file-checksum-parser',
    'native_boundary' => 'catalog /Collection metadata and associated-file /Params /CheckSum review before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => count($attachments),
    'portfolio_view' => $source['portfolio']['view'] ?? null,
    'default_document' => $source['portfolio']['default_document'] ?? null,
    'schema_fields' => array_keys($source['portfolio']['schema'] ?? []),
    'sort_keys' => $source['portfolio']['sort']['keys'] ?? [],
    'portfolio_item_subjects' => array_map(
        static fn (array $attachment): ?string => $attachment['portfolio_item']['Subject'] ?? null,
        $attachments
    ),
    'checksum_matches' => array_map(
        static fn (array $attachment): ?bool => $attachment['checksum_matches'] ?? null,
        $attachments
    ),
    'excluded_source_payload_text' => !str_contains($plainText, '<wp-export>'),
    'excluded_preview_payload_text' => !str_contains($plainText, 'stale-checksum'),
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

foreach ($attachments as $attachment) {
    echo '<!-- wp:file {"href":"media/' . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
    echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
        . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</a></div>\n";
    echo "<!-- /wp:file -->\n\n";
    echo '<!-- markerpdf:collection-associated-file ' . $htmlJson([
        'name' => $attachment['name'],
        'filename' => $attachment['filename'],
        'description' => $attachment['description'] ?? null,
        'relationship' => $attachment['relationship'] ?? null,
        'mime_type' => $attachment['mime_type'] ?? null,
        'portfolio_item' => $attachment['portfolio_item'] ?? [],
        'checksum' => $attachment['checksum'] ?? null,
        'computed_checksum' => $attachment['computed_checksum'] ?? null,
        'checksum_matches' => $attachment['checksum_matches'] ?? null,
    ]) . " -->\n\n";
}
