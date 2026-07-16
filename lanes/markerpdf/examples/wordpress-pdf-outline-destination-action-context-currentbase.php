<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$coverText = 'BT /F1 12 Tf 72 720 Td (Cover action context page remains visible) Tj ET';
$deckText = 'BT /F1 12 Tf 72 720 Td (Deck action target page remains visible) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 4.5 /Trans 16 0 R /AA << /O 15 0 R >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Destination Action Context) /Parent 5 0 R /Dest /DeckAction >>\nendobj\n"
    . "8 0 obj\n<< /Names [(DeckAction) 9 0 R (DeckView) [4 0 R /XYZ 120 640 0]] >>\nendobj\n"
    . "9 0 obj\n<< /S /GoTo /D /DeckView /Next [13 0 R 14 0 R] >>\nendobj\n"
    . "13 0 obj\n<< /S /URI /URI (https://example.com/deck-context-review) >>\nendobj\n"
    . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden action context script'\\)) >>\nendobj\n"
    . "15 0 obj\n<< /S /URI /URI (https://example.com/page-open-context) >>\nendobj\n"
    . "16 0 obj\n<< /S /Push /D .25 /M /I /Di 90 >>\nendobj\n"
    . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Deck ) /St 8 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($deckText) . " >>\nstream\n{$deckText}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$actions = $navigation['outline_action_review_actions'];

if (count($actions) !== 3) {
    throw new RuntimeException('Expected three outline destination action review rows.');
}
if (($actions[1]['destination_action_target_view_mode'] ?? null) !== 'XYZ') {
    throw new RuntimeException('Expected chained action rows to carry destination target view metadata.');
}
if (($actions[1]['destination_action_target_page_transition']['style'] ?? null) !== 'Push') {
    throw new RuntimeException('Expected chained action rows to carry destination target transition metadata.');
}
if (str_contains($plainText, 'deck-context-review') || str_contains($plainText, 'page-open-context') || str_contains($plainText, 'hidden action context script')) {
    throw new RuntimeException('Expected outline action operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-destination-action-context-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-destination-action-context-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'outline destination action chains carry target view and page-presentation context as non-executing review metadata',
    'outline_titles' => array_column($navigation['outline'], 'title'),
    'outline_destination_names' => array_column($navigation['outline'], 'destination'),
    'outline_action_types' => array_column($actions, 'action_type'),
    'outline_action_safeties' => array_column($actions, 'safety'),
    'destination_action_names' => array_column($actions, 'destination_action_name'),
    'chained_target_view_modes' => array_map(
        static fn (array $row): ?string => $row['destination_action_target_view_mode'] ?? null,
        $actions
    ),
    'chained_target_view_parameters' => $actions[1]['destination_action_target_view_parameters'] ?? [],
    'chained_target_transition' => $actions[1]['destination_action_target_page_transition']['style'] ?? null,
    'chained_target_page_action_safeties' => array_column($actions[1]['destination_action_target_page_actions'] ?? [], 'safety'),
    'all_outline_actions_review_only' => array_reduce(
        $actions,
        static fn (bool $carry, array $row): bool => $carry && ($row['executes_on_import'] ?? true) === false,
        true
    ),
    'visible_text_excludes_action_context_operands' => !str_contains($plainText, 'deck-context-review')
        && !str_contains($plainText, 'page-open-context')
        && !str_contains($plainText, 'hidden action context script'),
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
foreach ($actions as $row) {
    echo '<li data-marker-outline-action-title="' . htmlspecialchars($row['outline_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-action-type="' . htmlspecialchars($row['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-target-view="' . htmlspecialchars((string) ($row['destination_action_target_view_mode'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-target-transition="' . htmlspecialchars((string) ($row['destination_action_target_page_transition']['style'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="' . (($row['executes_on_import'] ?? true) ? 'true' : 'false')
        . '">Destination action context: ' . htmlspecialchars($row['outline_title'] . ' ' . $row['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
