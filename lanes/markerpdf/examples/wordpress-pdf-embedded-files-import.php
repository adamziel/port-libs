<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfEmbeddedFileExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$manifest = '{"title":"WP Import","blocks":2}';
$compressedManifest = gzcompress($manifest);
if (!is_string($compressedManifest)) {
    throw new RuntimeException('Unable to compress embedded manifest fixture.');
}

$filenameBytes = iconv('UTF-8', 'UTF-16BE', 'wp-import-manifest.json');
if (!is_string($filenameBytes)) {
    throw new RuntimeException('Unable to encode UTF-16BE filename fixture.');
}
$filenameHex = strtoupper(bin2hex("\xFE\xFF" . $filenameBytes));
$checksum = '00112233445566778899AABBCCDDEEFF';
$pageContent = 'BT /F1 12 Tf 72 720 Td (Visible Attachment Review) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Names << /EmbeddedFiles 6 0 R >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 4 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "6 0 obj\n<< /Kids [7 0 R] >>\nendobj\n"
    . "7 0 obj\n<< /Limits [(wp-import-manifest.json) (wp-import-manifest.json)] /Names [(wp-import-manifest.json) 10 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (legacy-manifest.json) /UF <{$filenameHex}> /Desc (WordPress import manifest) /AFRelationship /Data /EF << /UF 12 0 R >> >>\nendobj\n"
    . "12 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fjson /Filter /FlateDecode /Params << /Size " . strlen($manifest) . " /CheckSum <{$checksum}> /ModDate (D:20260602033800Z) >> /Length " . strlen($compressedManifest) . " >>\nstream\n{$compressedManifest}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$attachments = (new PdfEmbeddedFileExtractor())->extractEmbeddedFiles($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$attachment = $attachments[0] ?? null;
if (!is_array($attachment)) {
    throw new RuntimeException('Expected one embedded-file attachment.');
}

echo '<!-- markerpdf-pdf-embedded-files-smoke ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-embedded-files-name-tree-parser',
    'native_boundary' => 'catalog /Names /EmbeddedFiles name-tree attachment review before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'attachment_count' => count($attachments),
    'filename' => $attachment['filename'],
    'mime_type' => $attachment['mime_type'] ?? null,
    'relationship' => $attachment['relationship'] ?? null,
    'declared_size_matches' => ($attachment['declared_size'] ?? null) === strlen($manifest),
    'checksum' => $attachment['checksum'] ?? null,
    'content_sha256' => $attachment['content_sha256'] ?? null,
    'excluded_attachment_payload_text' => !str_contains($plainText, 'WP Import'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n\n";

echo '<!-- wp:file {"href":"media/' . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"} -->' . "\n";
echo '<div class="wp-block-file"><a href="media/' . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">'
    . htmlspecialchars($attachment['filename'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . "</a></div>\n";
echo "<!-- /wp:file -->\n\n";

echo '<!-- markerpdf:embedded-file ' . htmlspecialchars(json_encode([
    'name' => $attachment['name'],
    'filename' => $attachment['filename'],
    'description' => $attachment['description'] ?? null,
    'relationship' => $attachment['relationship'] ?? null,
    'mime_type' => $attachment['mime_type'] ?? null,
    'size' => $attachment['size'],
    'declared_size' => $attachment['declared_size'] ?? null,
    'modified_at' => $attachment['modified_at'] ?? null,
    'checksum' => $attachment['checksum'] ?? null,
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";
