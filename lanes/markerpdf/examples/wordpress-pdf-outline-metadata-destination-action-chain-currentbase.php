<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress destination action metadata intro remains visible) Tj ET';
$targetContent = 'BT /F1 12 Tf 72 720 Td (WordPress destination action metadata target remains visible) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Destination Action Metadata) /Parent 5 0 R /Dest /ReviewAction >>\nendobj\n"
    . "20 0 obj\n<< /Names [(ReviewTarget) [4 0 R /FitH 640] (ReviewAction) 21 0 R] >>\nendobj\n"
    . "21 0 obj\n<< /S /GoTo /D /ReviewTarget /Next [22 0 R 23 0 R 24 0 R] >>\nendobj\n"
    . "22 0 obj\n<< /S /URI /URI (https://example.com/wordpress-outline-document-metadata-action) >>\nendobj\n"
    . "23 0 obj\n<< /S /JavaScript /JS (app.alert\\('wordpress outline document metadata action should not execute'\\)) >>\nendobj\n"
    . "24 0 obj\n<< /S /Launch /F (wordpress-metadata-review-helper.exe) /Win << /F (wordpress-metadata-review-helper.exe) /O (open) >> >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$item = $outline['items'][0] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);

if (($item['destination_action_name'] ?? null) !== 'ReviewAction') {
    throw new RuntimeException('Expected document outline metadata to preserve the outer named destination action key.');
}
if (($item['destination_action_chain_types'] ?? []) !== ['GoTo', 'URI', 'JavaScript', 'Launch']) {
    throw new RuntimeException('Expected document outline metadata to summarize the destination action chain types.');
}
if (($item['destination_action_payload_included'] ?? null) !== false || ($item['destination_action_executes_action'] ?? null) !== false) {
    throw new RuntimeException('Expected document outline destination action metadata to be review-only and payload-free.');
}
if (!is_string($encodedMetadata)
    || str_contains($encodedMetadata, 'wordpress-outline-document-metadata-action')
    || str_contains($encodedMetadata, 'wordpress outline document metadata action should not execute')
    || str_contains($encodedMetadata, 'wordpress-metadata-review-helper.exe')
) {
    throw new RuntimeException('Expected payload-bearing destination action strings to stay out of document metadata.');
}
if (str_contains($plainText, 'WordPress Destination Action Metadata')
    || str_contains($plainText, 'ReviewAction')
    || str_contains($plainText, 'ReviewTarget')
    || str_contains($plainText, 'wordpress-outline-document-metadata-action')
    || str_contains($plainText, 'wordpress outline document metadata action should not execute')
    || str_contains($plainText, 'wordpress-metadata-review-helper.exe')
) {
    throw new RuntimeException('Expected outline destination action operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-metadata-destination-action-chain-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-metadata-destination-action-chain-currentbase',
    'support_component' => 'native-pdf-outline-destination-action-metadata-boundary',
    'native_boundary' => 'catalog outline named destinations that resolve to action dictionaries expose payload-free document metadata without visible text leakage',
    'outline_title' => $item['title'] ?? null,
    'outline_destination' => $item['destination'] ?? null,
    'outline_target_page' => $item['page'] ?? null,
    'outline_target_view_mode' => $item['view_mode'] ?? null,
    'destination_action_name' => $item['destination_action_name'] ?? null,
    'destination_action_object' => $item['destination_action_object'] ?? null,
    'destination_action_chain_count' => $item['destination_action_chain_count'] ?? null,
    'destination_action_chain_types' => $item['destination_action_chain_types'] ?? [],
    'destination_action_chain_objects' => $item['destination_action_chain_objects'] ?? [],
    'document_metadata_payload_excluded' => is_string($encodedMetadata)
        && !str_contains($encodedMetadata, 'wordpress-outline-document-metadata-action')
        && !str_contains($encodedMetadata, 'wordpress outline document metadata action should not execute')
        && !str_contains($encodedMetadata, 'wordpress-metadata-review-helper.exe'),
    'direct_outline_action_keys_absent' => !array_key_exists('action_chain_count', $item)
        && !array_key_exists('action_payload_included', $item)
        && !array_key_exists('executes_action', $item),
    'visible_text_excludes_outline_action_metadata' => !str_contains($plainText, 'WordPress Destination Action Metadata')
        && !str_contains($plainText, 'ReviewAction')
        && !str_contains($plainText, 'ReviewTarget')
        && !str_contains($plainText, 'wordpress-outline-document-metadata-action')
        && !str_contains($plainText, 'wordpress outline document metadata action should not execute')
        && !str_contains($plainText, 'wordpress-metadata-review-helper.exe'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF destination action metadata review\"><ul>\n";
foreach ($outline['items'] ?? [] as $row) {
    echo '<li data-marker-outline-page="' . htmlspecialchars((string) ($row['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-action-name="' . htmlspecialchars((string) ($row['destination_action_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-action-chain-count="' . htmlspecialchars((string) ($row['destination_action_chain_count'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-action-payload-included="' . (($row['destination_action_payload_included'] ?? true) ? 'true' : 'false')
        . '">' . htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
