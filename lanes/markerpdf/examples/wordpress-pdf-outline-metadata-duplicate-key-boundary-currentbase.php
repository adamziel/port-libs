<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$visibleContent = 'BT /F1 12 Tf 72 720 Td (WordPress duplicate outline metadata body) Tj ET';
$unselectedPayload = '<outline-metadata review="unselected">WordPress stale outline metadata stream</outline-metadata>';
$selectedPayload = '<outline-metadata review="selected">WordPress selected outline metadata stream</outline-metadata>';
$decoyPayload = '<outline-metadata review="nested-decoy">WordPress nested outline metadata decoy</outline-metadata>';

$unselectedStream = gzcompress($unselectedPayload);
$selectedStream = gzcompress($selectedPayload);
$decoyStream = gzcompress($decoyPayload);
if (!is_string($unselectedStream) || !is_string($selectedStream) || !is_string($decoyStream)) {
    throw new RuntimeException('Unable to compress WordPress duplicate outline metadata streams.');
}

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Duplicate Metadata Chapter) /Parent 5 0 R /Dest [3 0 R /FitH 720] /Note (/Metadata 10 0 R literal decoy) /Private << /Metadata 10 0 R >> /Metadata 8 0 R /C [0 .4 .8] /Metadata 9 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($unselectedStream) . " >>\nstream\n{$unselectedStream}\nendstream\nendobj\n"
    . "9 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($selectedStream) . " >>\nstream\n{$selectedStream}\nendstream\nendobj\n"
    . "10 0 obj\n<< /Type /Metadata /Subtype /XML /Filter /FlateDecode /Length " . strlen($decoyStream) . " >>\nstream\n{$decoyStream}\nendstream\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($visibleContent) . " >>\nstream\n{$visibleContent}\nendstream\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$outlineExtractor = new PdfOutlineExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$toc = $outlineExtractor->getPdfTocWithDestinationViews($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$item = $outline['items'][0] ?? [];
$review = $item['metadata_stream_review'] ?? [];
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($outline['titles'] ?? []) !== ['WordPress Duplicate Metadata Chapter']) {
    throw new RuntimeException('Expected current outline metadata title.');
}
if (($review['declared_entry_count'] ?? null) !== 2 || ($review['duplicate_entries'] ?? null) !== true) {
    throw new RuntimeException('Expected duplicate top-level /Metadata declarations to be recorded.');
}
if (($review['selected_entry_index'] ?? null) !== 1 || ($review['object_number'] ?? null) !== 9) {
    throw new RuntimeException('Expected the last top-level /Metadata stream to be selected for review.');
}
if (($review['sha256'] ?? null) !== hash('sha256', $selectedPayload)) {
    throw new RuntimeException('Expected selected outline metadata stream hash.');
}
if (array_column($toc, 'title') !== ['WordPress Duplicate Metadata Chapter']) {
    throw new RuntimeException('Expected duplicate metadata outline row to stay importable.');
}
if (array_column($navigation['outline'] ?? [], 'title') !== ['WordPress Duplicate Metadata Chapter']) {
    throw new RuntimeException('Expected navigation metadata to include the outline row.');
}
if (!is_string($metadataEncoded)
    || str_contains($metadataEncoded, $selectedPayload)
    || str_contains($metadataEncoded, $unselectedPayload)
    || str_contains($metadataEncoded, $decoyPayload)
    || !is_string($navigationEncoded)
    || str_contains($navigationEncoded, 'WordPress selected outline metadata stream')
) {
    throw new RuntimeException('Expected duplicate outline metadata payloads to stay review-only.');
}
if (str_contains($plainText, 'WordPress Duplicate Metadata Chapter')
    || str_contains($plainText, 'WordPress selected outline metadata stream')
    || str_contains($plainText, 'WordPress stale outline metadata stream')
    || str_contains($plainText, 'WordPress nested outline metadata decoy')
) {
    throw new RuntimeException('Expected outline metadata payloads to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-duplicate-key-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-duplicate-key-boundary-currentbase',
    'support_component' => 'native-pdf-outline-metadata-stream-review',
    'native_boundary' => 'duplicate top-level outline /Metadata keys preserve selected-entry provenance while nested and literal decoys remain review-only',
    'outline_title' => $item['title'] ?? null,
    'outline_object' => $item['outline_object'] ?? null,
    'metadata_declared_entry_count' => $review['declared_entry_count'] ?? null,
    'metadata_duplicate_entries' => $review['duplicate_entries'] ?? null,
    'metadata_selected_entry_index' => $review['selected_entry_index'] ?? null,
    'metadata_selected_object' => $review['object_number'] ?? null,
    'metadata_selected_sha256' => $review['sha256'] ?? null,
    'metadata_payload_included' => $review['payload_included'] ?? null,
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'visible_text_excludes_outline_metadata_payloads' => !str_contains($plainText, 'WordPress selected outline metadata stream')
        && !str_contains($plainText, 'WordPress stale outline metadata stream')
        && !str_contains($plainText, 'WordPress nested outline metadata decoy'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline metadata duplicate-key review\"><ul>\n";
foreach ($navigation['outline'] ?? [] as $row) {
    echo '<li data-marker-outline-object="' . (int) ($row['outline_object'] ?? 0)
        . '" data-marker-outline-page="' . htmlspecialchars((string) ($row['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
