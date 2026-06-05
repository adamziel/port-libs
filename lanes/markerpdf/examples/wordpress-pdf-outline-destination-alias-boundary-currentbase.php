<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$pageContent = 'BT /F1 12 Tf 72 720 Td (WordPress outline alias target body) Tj ET';
$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 8 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress Alias Boundary Chapter) /Parent 5 0 R /Dest /AliasStart /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (WordPress Alias Cycle Chapter) /Parent 5 0 R /Prev 6 0 R /Dest /CycleA /Next 8 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Title (WordPress Direct Destination Chapter) /Parent 5 0 R /Prev 7 0 R /Dest /FinalTarget >>\nendobj\n"
    . "20 0 obj\n<< /Names [(AliasStart) /FinalTarget (CycleA) /CycleB (CycleB) /CycleA (FinalTarget) [3 0 R /FitH 700]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$items = $outline['items'] ?? [];
$aliasItem = $items[0] ?? [];
$cycleItem = $items[1] ?? [];
$navigationAlias = $navigation['outline'][0] ?? [];
$metadataEncoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($aliasItem['declared_destination'] ?? null) !== 'AliasStart'
    || ($aliasItem['destination_target'] ?? null) !== 'FinalTarget'
    || ($aliasItem['destination_alias_chain'] ?? []) !== ['AliasStart', 'FinalTarget']
) {
    throw new RuntimeException('Expected document outline metadata to preserve the resolved destination alias chain.');
}
if (($cycleItem['destination_unresolved_reason'] ?? null) !== 'destination_alias_cycle'
    || ($cycleItem['destination_alias_chain'] ?? []) !== ['CycleA', 'CycleB', 'CycleA']
) {
    throw new RuntimeException('Expected cyclic destination aliases to remain unresolved review metadata.');
}
if (($navigationAlias['destination_alias_chain'] ?? []) !== ['AliasStart', 'FinalTarget']
    || ($navigationAlias['destination_target'] ?? null) !== 'FinalTarget'
) {
    throw new RuntimeException('Expected navigation review rows to carry resolved alias-chain metadata.');
}
if (!is_string($metadataEncoded)
    || !is_string($navigationEncoded)
    || str_contains($navigationEncoded, 'WordPress Alias Cycle Chapter')
) {
    throw new RuntimeException('Expected alias-cycle outline rows to stay out of importable navigation review.');
}
foreach ([
    'WordPress Alias Boundary Chapter',
    'WordPress Alias Cycle Chapter',
    'WordPress Direct Destination Chapter',
    'AliasStart',
    'FinalTarget',
    'CycleA',
    'CycleB',
] as $reviewOnly) {
    if (str_contains($plainText, $reviewOnly)) {
        throw new RuntimeException('Expected outline alias operands to stay out of visible WordPress text.');
    }
}

echo '<!-- markerpdf-outline-destination-alias-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-destination-alias-boundary-currentbase',
    'support_component' => 'native-pdf-outline-destination-alias-review',
    'native_boundary' => 'outline destination names may alias terminal name-tree targets; aliases and cycles stay review-only while visible text stays clean',
    'metadata_item_count' => $outline['item_count'] ?? null,
    'metadata_resolved_destination_count' => $outline['resolved_destination_count'] ?? null,
    'metadata_unresolved_destination_count' => $outline['unresolved_destination_count'] ?? null,
    'alias_declared_destination' => $aliasItem['declared_destination'] ?? null,
    'alias_destination_target' => $aliasItem['destination_target'] ?? null,
    'alias_chain' => $aliasItem['destination_alias_chain'] ?? [],
    'cycle_unresolved_reason' => $cycleItem['destination_unresolved_reason'] ?? null,
    'cycle_alias_chain' => $cycleItem['destination_alias_chain'] ?? [],
    'navigation_titles' => array_column($navigation['outline'] ?? [], 'title'),
    'navigation_alias_chain' => $navigationAlias['destination_alias_chain'] ?? [],
    'navigation_cycle_omitted' => is_string($navigationEncoded) && !str_contains($navigationEncoded, 'WordPress Alias Cycle Chapter'),
    'visible_text_excludes_outline_alias_operands' => !str_contains($plainText, 'AliasStart')
        && !str_contains($plainText, 'CycleA')
        && !str_contains($plainText, 'WordPress Alias Boundary Chapter'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";

echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline alias review\"><ul>\n";
foreach ($navigation['outline'] ?? [] as $row) {
    echo '<li data-marker-outline-destination="' . htmlspecialchars((string) ($row['destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-target="' . htmlspecialchars((string) ($row['destination_target'] ?? $row['destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">' . htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
