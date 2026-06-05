<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline action chain metadata intro) Tj ET';
$targetContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline action chain metadata target) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Action Chain Review) /Parent 5 0 R /A 12 0 R /C [0 .25 .5] /F 2 >>\nendobj\n"
    . "12 0 obj\n<< /S /GoTo /D /ReviewTarget /Next [13 0 R 14 0 R 13 0 R 15 0 R] >>\nendobj\n"
    . "13 0 obj\n<< /S /URI /URI (https://example.com/wordpress-outline-action-chain-review) >>\nendobj\n"
    . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('wordpress outline action chain should not execute'\\)) /Next 12 0 R >>\nendobj\n"
    . "15 0 obj\n<< /S /Launch /F (wordpress-outline-review-helper.exe) /Win << /F (wordpress-outline-review-helper.exe) /O (open) >> >>\nendobj\n"
    . "20 0 obj\n<< /Names [(ReviewTarget) [4 0 R /FitH 680]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$item = $outline['items'][0] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($item['action_chain_count'] ?? null) !== 4) {
    throw new RuntimeException('Expected four sanitized document-outline action-chain rows.');
}
if (($item['action_chain_types'] ?? []) !== ['GoTo', 'URI', 'JavaScript', 'Launch']) {
    throw new RuntimeException('Expected outline action-chain types to be summarized without payloads.');
}
if (($item['action_chain_objects'] ?? []) !== [12, 13, 14, 15]) {
    throw new RuntimeException('Expected duplicate/cyclic outline action references to be bounded.');
}
if (!is_string($encodedMetadata)
    || str_contains($encodedMetadata, 'wordpress-outline-action-chain-review')
    || str_contains($encodedMetadata, 'wordpress outline action chain should not execute')
    || str_contains($encodedMetadata, 'wordpress-outline-review-helper.exe')
) {
    throw new RuntimeException('Expected document metadata to exclude action payload text.');
}
if (!is_string($encodedNavigation) || !str_contains($encodedNavigation, 'wordpress-outline-action-chain-review')) {
    throw new RuntimeException('Expected richer navigation review metadata to retain action-review operands.');
}
if (str_contains($plainText, 'Import Action Chain Review')
    || str_contains($plainText, 'wordpress-outline-action-chain-review')
    || str_contains($plainText, 'wordpress outline action chain should not execute')
    || str_contains($plainText, 'wordpress-outline-review-helper.exe')
) {
    throw new RuntimeException('Expected outline action metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-action-chain-metadata-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-action-chain-metadata-currentbase',
    'support_component' => 'native-pdf-document-outline-action-chain-review',
    'native_boundary' => 'outline /A /Next chains are summarized in document metadata without URI file or JavaScript payloads',
    'outline_titles' => $outline['titles'] ?? [],
    'action_chain_count' => $item['action_chain_count'] ?? null,
    'action_chain_types' => $item['action_chain_types'] ?? [],
    'action_chain_objects' => $item['action_chain_objects'] ?? [],
    'metadata_payload_excluded' => is_string($encodedMetadata)
        && !str_contains($encodedMetadata, 'wordpress-outline-action-chain-review')
        && !str_contains($encodedMetadata, 'wordpress-outline-review-helper.exe'),
    'navigation_review_retains_payload_context' => is_string($encodedNavigation)
        && str_contains($encodedNavigation, 'wordpress-outline-action-chain-review'),
    'visible_text_excludes_outline_action_metadata' => !str_contains($plainText, 'Import Action Chain Review')
        && !str_contains($plainText, 'wordpress-outline-action-chain-review'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline action review\"><ul>\n";
echo '<li data-marker-outline-actions="' . htmlspecialchars(implode(',', $item['action_chain_types'] ?? []), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '" data-marker-outline-action-count="' . (int) ($item['action_chain_count'] ?? 0)
    . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
