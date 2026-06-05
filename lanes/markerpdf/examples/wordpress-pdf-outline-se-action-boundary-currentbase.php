<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfMetadataExtractor;
use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

$pageText = 'BT /F1 12 Tf 72 720 Td (WordPress outline SE action boundary visible body) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 20 0 R >> /StructTreeRoot 50 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 7 0 R >> >> /Contents 30 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Last 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (WordPress SE action boundary chapter) /Parent 5 0 R /Dest /CurrentTarget /SE 12 0 R /A 13 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "12 0 obj\n<< /S /JavaScript /JS (app.alert('wordpress outline se action should not become structure metadata')) /P 50 0 R /K 0 >>\nendobj\n"
    . "13 0 obj\n<< /S /GoTo /D /CurrentTarget /Next 14 0 R >>\nendobj\n"
    . "14 0 obj\n<< /S /URI /URI (https://example.com/wordpress-outline-se-action-review) >>\nendobj\n"
    . "20 0 obj\n<< /Names [(CurrentTarget) [3 0 R /FitH 720]] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageText) . " >>\nstream\n{$pageText}\nendstream\nendobj\n"
    . "50 0 obj\n<< /Type /StructTreeRoot /K [] >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdf);
$navigation = (new PdfOutlineExtractor())->getNavigationReviewMetadata($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);
$outline = $metadata['document_outline'] ?? [];
$item = $outline['items'][0] ?? [];
$navigationItem = $navigation['outline'][0] ?? [];
$actions = $navigation['outline_action_review_actions'] ?? [];
$encoded = json_encode($metadata, JSON_UNESCAPED_SLASHES);
$navigationEncoded = json_encode($navigation, JSON_UNESCAPED_SLASHES);

if (($item['title'] ?? null) !== 'WordPress SE action boundary chapter') {
    throw new RuntimeException('Expected WordPress outline title metadata.');
}
if (($item['destination'] ?? null) !== 'CurrentTarget' || ($item['page'] ?? null) !== 0) {
    throw new RuntimeException('Expected named destination to resolve to the first page.');
}
if (($item['action_chain_types'] ?? []) !== ['GoTo', 'URI']) {
    throw new RuntimeException('Expected review-only GoTo/URI action chain metadata.');
}
if (array_key_exists('structure_element', $item)
    || array_key_exists('structure_element_role', $item)
    || array_key_exists('structure_element_count', $outline)
) {
    throw new RuntimeException('Expected action-shaped outline /SE dictionary to be rejected as structure metadata.');
}
if (array_key_exists('structure_element', $navigationItem)
    || array_key_exists('structure_element_role', $navigationItem)
) {
    throw new RuntimeException('Expected navigation review to omit false outline /SE structure metadata.');
}
foreach ($actions as $action) {
    if (array_key_exists('outline_structure_element', $action)
        || array_key_exists('outline_structure_element_role', $action)
    ) {
        throw new RuntimeException('Expected action rows to omit false outline /SE structure context.');
    }
}
if (!is_string($encoded)
    || !is_string($navigationEncoded)
    || str_contains($encoded, 'wordpress outline se action should not become structure metadata')
    || str_contains($navigationEncoded, 'wordpress outline se action should not become structure metadata')
) {
    throw new RuntimeException('Expected JavaScript payload to stay out of metadata exports.');
}
if (str_contains($plainText, 'WordPress SE action boundary chapter')
    || str_contains($plainText, 'wordpress outline se action should not become structure metadata')
    || str_contains($plainText, 'wordpress-outline-se-action-review')
) {
    throw new RuntimeException('Expected outline/action metadata to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-se-action-boundary-currentbase ' . $htmlJson([
    'scenario' => 'wordpress-pdf-outline-se-action-boundary-currentbase',
    'support_component' => 'native-pdf-outline-structure-element-review',
    'native_boundary' => 'outline /SE action dictionaries are rejected as structure metadata while outline actions remain review-only metadata',
    'outline_title' => $item['title'] ?? null,
    'outline_page_number' => $item['page_number'] ?? null,
    'action_chain_types' => $item['action_chain_types'] ?? [],
    'structure_element_rejected' => !array_key_exists('structure_element', $item),
    'navigation_structure_element_rejected' => !array_key_exists('structure_element', $navigationItem),
    'action_rows_omit_structure_context' => array_reduce(
        $actions,
        static fn (bool $ok, array $action): bool => $ok
            && !array_key_exists('outline_structure_element', $action)
            && !array_key_exists('outline_structure_element_role', $action),
        true
    ),
    'visible_text_excludes_outline_metadata' => !str_contains($plainText, 'WordPress SE action boundary chapter')
        && !str_contains($plainText, 'wordpress outline se action should not become structure metadata'),
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
]) . " -->\n";

echo "<!-- wp:paragraph -->\n";
echo '<p>' . htmlspecialchars($plainText, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
echo "<!-- /wp:paragraph -->\n";

echo "<!-- wp:navigation -->\n<nav aria-label=\"PDF outline action review\"><ul>\n";
echo '<li data-marker-outline-actions="' . htmlspecialchars(implode(',', $item['action_chain_types'] ?? []), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
    . '" data-marker-outline-structure-rejected="true">'
    . htmlspecialchars((string) ($item['title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
echo "</ul></nav>\n<!-- /wp:navigation -->\n";
