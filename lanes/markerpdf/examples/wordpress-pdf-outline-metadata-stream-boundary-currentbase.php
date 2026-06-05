<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline metadata stream visible body) Tj ET';
$metadataPayload = 'BT /F1 12 Tf 72 720 Td (WordPress outline item metadata stream must stay review only) Tj ET';
$metadataStream = gzcompress($metadataPayload);
if (!is_string($metadataStream)) {
    throw new RuntimeException('Unable to compress WordPress outline item metadata stream.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Outline Metadata Stream Boundary) /Parent 5 0 R /Metadata 8 0 R /C [0 .2 .4] /F 1 >>\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($metadataStream) . " >>\nstream\n{$metadataStream}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$item = $outline['items'][0] ?? [];
$review = $item['metadata_stream_review'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($outline['titles'] ?? []) !== ['WordPress Outline Metadata Stream Boundary']) {
    throw new RuntimeException('Expected outline item title to remain review metadata.');
}
if (($review['status'] ?? null) !== 'reviewed_outline_item_metadata_stream') {
    throw new RuntimeException('Expected outline item metadata stream review summary.');
}
if (($review['payload_included'] ?? null) !== false || ($review['visible_text_source'] ?? null) !== false) {
    throw new RuntimeException('Expected outline item metadata stream to stay payload-free and non-visible.');
}
if (($review['bytes'] ?? null) !== strlen($metadataPayload) || ($review['sha256'] ?? null) !== hash('sha256', $metadataPayload)) {
    throw new RuntimeException('Expected outline item metadata stream hash and decoded byte count.');
}
if ($plainText !== 'WordPress outline metadata stream visible body') {
    throw new RuntimeException('Expected lightweight stream fallback to preserve only visible body text.');
}
if (!is_string($encoded) || str_contains($encoded, $metadataPayload) || str_contains($plainText, 'metadata stream must stay review only')) {
    throw new RuntimeException('Expected outline item metadata stream payload to stay out of metadata and WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-stream-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-stream-boundary-currentbase',
    'support_component' => 'native-pdf-outline-item-metadata-stream-review',
    'native_boundary' => 'outline item /Metadata streams are review-only bookmark metadata and not visible WordPress paragraph text',
    'outline_titles' => $outline['titles'] ?? [],
    'outline_object' => $item['outline_object'] ?? null,
    'metadata_stream_status' => $review['status'] ?? null,
    'metadata_stream_object' => $review['object_number'] ?? null,
    'metadata_stream_bytes' => $review['bytes'] ?? null,
    'metadata_stream_sha256' => $review['sha256'] ?? null,
    'metadata_payload_excluded_from_document_metadata' => is_string($encoded) && !str_contains($encoded, $metadataPayload),
    'metadata_payload_excluded_from_visible_text' => !str_contains($plainText, 'metadata stream must stay review only'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline review\"><ul>\n";
echo '<li data-marker-outline-metadata-stream="' . htmlspecialchars((string) ($review['status'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '" data-marker-outline-metadata-object="' . htmlspecialchars((string) ($review['object_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
