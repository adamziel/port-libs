<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline action Next intro remains visible) Tj ET';
$targetContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline action Next target remains visible) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Action Next Operand Review) /Parent 5 0 R /A 12 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoTo /D /ReviewTarget /Next 13 0 R 14 0 R >>\nendobj\n"
    . "13 0 obj\n<< /S /URI /URI (https://example.com/wordpress-tailed-outline-next-action) >>\nendobj\n"
    . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('wordpress tailed outline action should not execute'\\)) >>\nendobj\n"
    . "20 0 obj\n<< /Names [(ReviewTarget) [4 0 R /FitH 640]] >>\nendobj\n"
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

if (($item['action_chain_count'] ?? null) !== 1 || ($item['action_chain_types'] ?? []) !== ['GoTo']) {
    throw new RuntimeException('Expected malformed action /Next operands to be excluded from document outline action summaries.');
}
if (($navigation['outline_action_review_actions'] ?? null) !== []) {
    throw new RuntimeException('Expected malformed action /Next operands to keep tailed followups out of navigation review.');
}
if (!is_string($encodedMetadata)
    || str_contains($encodedMetadata, 'wordpress-tailed-outline-next-action')
    || str_contains($encodedMetadata, 'wordpress tailed outline action should not execute')
) {
    throw new RuntimeException('Expected document metadata to exclude tailed action payloads.');
}
if (!is_string($encodedNavigation)
    || str_contains($encodedNavigation, 'wordpress-tailed-outline-next-action')
    || str_contains($encodedNavigation, 'wordpress tailed outline action should not execute')
) {
    throw new RuntimeException('Expected navigation review to exclude tailed action payloads.');
}
if (str_contains($plainText, 'WordPress Action Next Operand Review')
    || str_contains($plainText, 'ReviewTarget')
    || str_contains($plainText, 'wordpress-tailed-outline-next-action')
    || str_contains($plainText, 'wordpress tailed outline action should not execute')
) {
    throw new RuntimeException('Expected outline action metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-action-next-operand-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-action-next-operand-boundary-currentbase',
    'support_component' => 'native-pdf-outline-action-next-boundary',
    'native_boundary' => 'malformed outline action /Next operands are not traversed into URI or JavaScript followups',
    'outline_title' => $item['title'] ?? null,
    'outline_destination' => $item['destination'] ?? null,
    'outline_target_page' => $item['page'] ?? null,
    'action_chain_count' => $item['action_chain_count'] ?? null,
    'action_chain_types' => $item['action_chain_types'] ?? [],
    'action_chain_objects' => $item['action_chain_objects'] ?? [],
    'malformed_next_followups_excluded' => ($item['action_chain_types'] ?? []) === ['GoTo']
        && ($navigation['outline_action_review_actions'] ?? []) === [],
    'metadata_payload_excluded' => is_string($encodedMetadata)
        && !str_contains($encodedMetadata, 'wordpress-tailed-outline-next-action'),
    'navigation_payload_excluded' => is_string($encodedNavigation)
        && !str_contains($encodedNavigation, 'wordpress-tailed-outline-next-action'),
    'visible_text_excludes_outline_action_metadata' => !str_contains($plainText, 'WordPress Action Next Operand Review')
        && !str_contains($plainText, 'ReviewTarget')
        && !str_contains($plainText, 'wordpress-tailed-outline-next-action'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline action Next review\"><ul>\n";
echo '<li data-marker-outline-page="' . htmlspecialchars((string) ($item['page_number'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '" data-marker-outline-action-count="' . htmlspecialchars((string) ($item['action_chain_count'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '" data-marker-outline-action-types="' . htmlspecialchars(implode(',', $item['action_chain_types'] ?? []), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '" data-marker-tailed-next-excluded="true">'
    . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
