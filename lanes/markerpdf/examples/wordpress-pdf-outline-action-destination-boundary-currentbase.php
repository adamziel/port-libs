<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$introContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline action boundary intro body) Tj ET';
$targetContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline action boundary local target body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 9 0 R /Count 4 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Import Local Outline Target) /Parent 5 0 R /Dest /CurrentLocalTarget /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Import Remote Outline Review) /Parent 5 0 R /A 12 0 R /Prev 6 0 R /Next 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (Import URI Outline Review) /Parent 5 0 R /A 13 0 R /Prev 7 0 R /Next 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Title (Import Embedded Outline Review) /Parent 5 0 R /A 14 0 R /Prev 8 0 R >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F (remote-outline.pdf) /D /CurrentLocalTarget /NewWindow true >>\nendobj\n"
    . "13 0 obj\n<< /S /URI /URI (https://example.com/remote-outline-review) /D /CurrentLocalTarget >>\nendobj\n"
    . "14 0 obj\n<< /S /GoToE /T << /R /C /N (embedded-outline.pdf) >> /D /CurrentLocalTarget /NewWindow false >>\nendobj\n"
    . "20 0 obj\n<< /Names [(CurrentLocalTarget) [4 0 R /FitH 680]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetContent) . " >>\nstream\n{$targetContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$items = $outline['items'] ?? [];
$encodedMetadata = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$encodedNavigation = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($outline['resolved_destination_count'] ?? null) !== 1 || ($outline['unresolved_destination_count'] ?? null) !== 3) {
    throw new RuntimeException('Expected only the direct local outline destination to resolve as current-document metadata.');
}
if (($items[0]['destination_resolved'] ?? null) !== true || ($items[0]['page'] ?? null) !== 1) {
    throw new RuntimeException('Expected the local outline destination to resolve to the current document target page.');
}
foreach ([1 => 'GoToR', 2 => 'URI', 3 => 'GoToE'] as $index => $actionType) {
    if (($items[$index]['action_type'] ?? null) !== $actionType || ($items[$index]['destination_resolved'] ?? null) !== false) {
        throw new RuntimeException('Expected non-GoTo outline actions to stay unresolved in document outline metadata.');
    }
    if (array_key_exists('page', $items[$index]) || array_key_exists('page_object', $items[$index])) {
        throw new RuntimeException('Expected non-GoTo outline actions not to inherit current-document page metadata.');
    }
}
if (!is_string($encodedMetadata)
    || str_contains($encodedMetadata, 'remote-outline.pdf')
    || str_contains($encodedMetadata, 'remote-outline-review')
    || str_contains($encodedMetadata, 'embedded-outline.pdf')
) {
    throw new RuntimeException('Expected remote action operands to stay out of document outline metadata payload.');
}
if (!is_string($encodedNavigation)
    || !str_contains($encodedNavigation, 'remote-outline.pdf')
    || !str_contains($encodedNavigation, 'remote-outline-review')
    || !str_contains($encodedNavigation, 'embedded-outline.pdf')
) {
    throw new RuntimeException('Expected remote and unsafe outline actions to remain visible in review metadata.');
}
if (str_contains($plainText, 'Import Local Outline Target')
    || str_contains($plainText, 'Import Remote Outline Review')
    || str_contains($plainText, 'Import URI Outline Review')
    || str_contains($plainText, 'Import Embedded Outline Review')
    || str_contains($plainText, 'remote-outline.pdf')
    || str_contains($plainText, 'remote-outline-review')
    || str_contains($plainText, 'embedded-outline.pdf')
) {
    throw new RuntimeException('Expected outline titles and action operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-action-destination-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-action-destination-boundary-currentbase',
    'support_component' => 'native-pdf-outline-action-destination-review',
    'native_boundary' => 'only /S /GoTo outline actions resolve /D as current-document destinations; GoToR, URI, and GoToE remain review-only actions',
    'outline_titles' => $outline['titles'] ?? [],
    'outline_action_types' => array_column($items, 'action_type'),
    'resolved_destination_count' => $outline['resolved_destination_count'] ?? null,
    'unresolved_destination_count' => $outline['unresolved_destination_count'] ?? null,
    'non_goto_actions_unresolved' => (($items[1]['destination_resolved'] ?? null) === false)
        && (($items[2]['destination_resolved'] ?? null) === false)
        && (($items[3]['destination_resolved'] ?? null) === false),
    'remote_action_review_retained' => is_string($encodedNavigation)
        && str_contains($encodedNavigation, 'remote-outline.pdf')
        && str_contains($encodedNavigation, 'remote-outline-review')
        && str_contains($encodedNavigation, 'embedded-outline.pdf'),
    'visible_text_excludes_outline_actions' => !str_contains($plainText, 'remote-outline.pdf')
        && !str_contains($plainText, 'remote-outline-review')
        && !str_contains($plainText, 'embedded-outline.pdf'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n<p>" . htmlspecialchars(str_replace("\n", ' ', $plainText), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n<!-- /wp:paragraph -->\n";
echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline action review\"><ul>\n";
foreach ($items as $item) {
    echo '<li data-marker-outline-level="' . (int) ($item['level'] ?? 0)
        . '" data-marker-outline-action="' . htmlspecialchars((string) ($item['action_type'] ?? 'Dest'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-destination-resolved="' . ((($item['destination_resolved'] ?? false) === true) ? 'true' : 'false')
        . '">' . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
