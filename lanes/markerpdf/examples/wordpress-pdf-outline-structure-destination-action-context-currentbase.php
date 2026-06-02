<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$coverText = 'BT /F1 12 Tf 72 720 Td (Cover structure action context page remains visible) Tj ET';
$deckText = 'BT /F1 12 Tf 72 720 Td (Deck structure action target page remains visible) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 4.25 /Trans 16 0 R /AA << /O 15 0 R >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Structured Destination Action) /Parent 5 0 R /Dest /DeckAction /First 7 0 R /Last 7 0 R /Count -1 /C [0.8 0 0.2] /F 2 >>\nendobj\n"
    . "7 0 obj\n<< /Title (Direct Child Destination) /Parent 6 0 R /Dest [4 0 R /FitH 610] /Count 0 /F 1 >>\nendobj\n"
    . "8 0 obj\n<< /Names [(DeckAction) 9 0 R (DeckView) [4 0 R /XYZ 120 640 0]] >>\nendobj\n"
    . "9 0 obj\n<< /S /GoTo /D /DeckView /Next [13 0 R 14 0 R] >>\nendobj\n"
    . "13 0 obj\n<< /S /URI /URI (https://example.com/structured-action-context) >>\nendobj\n"
    . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden structured action context script'\\)) >>\nendobj\n"
    . "15 0 obj\n<< /S /URI /URI (https://example.com/structured-page-open) >>\nendobj\n"
    . "16 0 obj\n<< /S /Push /D .25 /M /I /Di 90 >>\nendobj\n"
    . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Deck ) /St 8 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($deckText) . " >>\nstream\n{$deckText}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$outlineRows = $outlineExtractor->getOutlineStructureDestinationPageContext($pdf);
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

if (count($outlineRows) !== 2) {
    throw new RuntimeException('Expected two structured outline rows.');
}
if (($outlineRows[0]['destination_action_name'] ?? null) !== 'DeckAction') {
    throw new RuntimeException('Expected structured outline row to keep the action-backed destination name.');
}
if (($outlineRows[0]['destination_action_target_view_mode'] ?? null) !== 'XYZ') {
    throw new RuntimeException('Expected structured outline row to inherit destination action target view metadata.');
}
if (($outlineRows[0]['destination_action_target_page_transition']['style'] ?? null) !== 'Push') {
    throw new RuntimeException('Expected structured outline row to inherit destination action target transition metadata.');
}
if (($navigation['outline'][0]['destination_action_name'] ?? null) !== 'DeckAction') {
    throw new RuntimeException('Expected composite navigation outline rows to carry destination action context.');
}
if (str_contains($plainText, 'Structured Destination Action')
    || str_contains($plainText, 'DeckAction')
    || str_contains($plainText, 'DeckView')
    || str_contains($plainText, 'structured-action-context')
    || str_contains($plainText, 'structured-page-open')
    || str_contains($plainText, 'hidden structured action context script')
) {
    throw new RuntimeException('Expected outline/action operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-structure-destination-action-context-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-structure-destination-action-context-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'structured outline rows expose action-backed destination context and target page review metadata without executing PDF actions',
    'outline_titles' => array_column($outlineRows, 'title'),
    'outline_states' => array_column($outlineRows, 'structure_state'),
    'outline_destination_names' => array_column($outlineRows, 'destination'),
    'destination_action_name' => $outlineRows[0]['destination_action_name'] ?? null,
    'destination_action_object' => $outlineRows[0]['destination_action_object'] ?? null,
    'destination_action_types' => $outlineRows[0]['destination_action_types'] ?? [],
    'destination_action_safeties' => $outlineRows[0]['destination_action_safeties'] ?? [],
    'destination_action_chained_count' => $outlineRows[0]['destination_action_chained_count'] ?? null,
    'destination_action_all_review_only' => $outlineRows[0]['destination_action_all_review_only'] ?? null,
    'destination_action_target_page_label' => $outlineRows[0]['destination_action_target_page_label'] ?? null,
    'destination_action_target_view_mode' => $outlineRows[0]['destination_action_target_view_mode'] ?? null,
    'destination_action_target_transition' => $outlineRows[0]['destination_action_target_page_transition']['style'] ?? null,
    'visible_text_excludes_outline_action_context' => !str_contains($plainText, 'Structured Destination Action')
        && !str_contains($plainText, 'DeckAction')
        && !str_contains($plainText, 'DeckView')
        && !str_contains($plainText, 'structured-action-context')
        && !str_contains($plainText, 'structured-page-open')
        && !str_contains($plainText, 'hidden structured action context script'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($pages as $page) {
    echo '<!-- wp:separator {"className":"markerpdf-page-break","metadata":{"name":"PDF page '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '"}} -->' . "\n";
    echo '<hr class="wp-block-separator has-alpha-channel-opacity markerpdf-page-break"/>' . "\n";
    echo "<!-- /wp:separator -->\n\n";
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($page['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($outlineRows as $row) {
    echo '<li data-marker-outline-state="' . htmlspecialchars($row['structure_state'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-destination="' . htmlspecialchars((string) ($row['destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-action-name="' . htmlspecialchars((string) ($row['destination_action_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-target-view="' . htmlspecialchars((string) ($row['destination_action_target_view_mode'] ?? $row['view_mode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-target-transition="' . htmlspecialchars((string) ($row['destination_action_target_page_transition']['style'] ?? $row['target_page_transition']['style'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="false">'
        . htmlspecialchars($row['title'] . ' -> ' . $row['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
