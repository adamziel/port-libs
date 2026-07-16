<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate outline metadata fallback visible body) Tj ET';
$firstMetadataPayload = 'BT /F1 12 Tf 72 720 Td (Unselected WordPress duplicate outline metadata payload) Tj ET';
$selectedMetadataPayload = 'BT /F1 12 Tf 72 720 Td (Selected WordPress duplicate outline metadata payload) Tj ET';
$firstMetadataStream = gzcompress($firstMetadataPayload);
$selectedMetadataStream = gzcompress($selectedMetadataPayload);
if (!is_string($firstMetadataStream) || !is_string($selectedMetadataStream)) {
    throw new RuntimeException('Unable to compress WordPress duplicate outline metadata streams.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 /Metadata 8 0 R /Metadata 9 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Duplicate Root Metadata Fallback) /Parent 5 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($firstMetadataStream) . " >>\nstream\n{$firstMetadataStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($selectedMetadataStream) . " >>\nstream\n{$selectedMetadataStream}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outlineMetadata = (new PdfTextExtractor())->extractOutlineMetadata($pdf);
$outline = $metadata['document_outline'] ?? [];
$review = $outline['metadata_stream_review'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedOutlineMetadata = json_encode($outlineMetadata, JSON_UNESCAPED_SLASHES);

if (($review['source'] ?? null) !== 'outline_root_metadata_stream'
    || ($review['status'] ?? null) !== 'reviewed_outline_root_metadata_stream'
    || ($review['duplicate_entries'] ?? null) !== true
    || ($review['selected_entry_index'] ?? null) !== 1
    || ($review['object_number'] ?? null) !== 9
) {
    throw new RuntimeException('Expected duplicate outline root metadata review to select the current top-level entry.');
}
if ($plainText !== 'WordPress duplicate outline metadata fallback visible body') {
    throw new RuntimeException('Expected only lightweight fallback body text in WordPress paragraph output.');
}
if (!is_string($encodedMetadata)
    || !is_string($encodedOutlineMetadata)
    || str_contains($encodedMetadata, $firstMetadataPayload)
    || str_contains($encodedMetadata, $selectedMetadataPayload)
    || str_contains($encodedOutlineMetadata, $firstMetadataPayload)
    || str_contains($encodedOutlineMetadata, $selectedMetadataPayload)
    || str_contains($plainText, 'Unselected WordPress duplicate outline metadata payload')
    || str_contains($plainText, 'Selected WordPress duplicate outline metadata payload')
    || str_contains($plainText, 'WordPress Duplicate Root Metadata Fallback')
) {
    throw new RuntimeException('Expected duplicate outline metadata payloads and titles to stay out of visible/import text.');
}

echo '<!-- markerpdf-outline-duplicate-root-metadata-fallback-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-duplicate-root-metadata-fallback-currentbase',
    'support_component' => 'native-pdf-outline-duplicate-root-metadata-fallback-exclusion',
    'native_boundary' => 'duplicate outline-root /Metadata streams are review-only and all excluded from lightweight fallback WordPress text',
    'outline_root_object' => $outline['outline_root_object'] ?? null,
    'outline_titles' => $outline['titles'] ?? [],
    'metadata_stream_status' => $review['status'] ?? null,
    'metadata_stream_object' => $review['object_number'] ?? null,
    'metadata_stream_duplicate_entries' => $review['duplicate_entries'] ?? null,
    'metadata_stream_declared_entry_count' => $review['declared_entry_count'] ?? null,
    'metadata_stream_selected_entry_index' => $review['selected_entry_index'] ?? null,
    'metadata_stream_selected_sha256' => $review['sha256'] ?? null,
    'first_metadata_payload_excluded' => !str_contains($plainText, 'Unselected WordPress duplicate outline metadata payload')
        && !str_contains($encodedMetadata, $firstMetadataPayload)
        && !str_contains($encodedOutlineMetadata, $firstMetadataPayload),
    'selected_metadata_payload_excluded' => !str_contains($plainText, 'Selected WordPress duplicate outline metadata payload')
        && !str_contains($encodedMetadata, $selectedMetadataPayload)
        && !str_contains($encodedOutlineMetadata, $selectedMetadataPayload),
    'outline_title_excluded_from_visible_text' => !str_contains($plainText, 'WordPress Duplicate Root Metadata Fallback'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
