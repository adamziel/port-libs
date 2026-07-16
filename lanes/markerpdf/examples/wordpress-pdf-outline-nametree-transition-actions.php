<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Title slide stays visible) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Deck target stays visible) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 9 /Trans 15 0 R /AA << /O 42 0 R /C << /S /URI /URI (javascript:alert\\(1\\)) >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Deck Target) /Parent 5 0 R /Dest /DeckStart /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Action Target) /Parent 5 0 R /A << /S /GoTo /D 41 0 R >> >>\nendobj\n"
    . "8 0 obj\n<< /Kids [9 0 R 10 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /Limits [(A) (M)] /Names [(DeckStart) 14 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /Limits [(N) (Z)] /Names [(Stale) [99 0 R /Fit]] >>\nendobj\n"
    . "13 0 obj\n[4 0 R 16 0 R 17 0 R]\nendobj\n"
    . "14 0 obj\n<< /D 13 0 R >>\nendobj\n"
    . "15 0 obj\n<< /S /Fly /D .75 /M /I /Di 270 /SS .8 /B false >>\nendobj\n"
    . "16 0 obj\n/FitH\nendobj\n"
    . "17 0 obj\n610\nendobj\n"
    . "20 0 obj\n<< /Nums [0 << /S /D /P (Slide ) /St 1 >> 1 << /S /D /P (Deck ) /St 5 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "41 0 obj\n(DeckStart)\nendobj\n"
    . "42 0 obj\n<< /S /URI /URI (https://example.com/deck-notes) >>\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

$outlineTargetActions = [];
foreach ($navigation['outline'] as $outline) {
    foreach (($outline['target_page_actions'] ?? []) as $action) {
        $outlineTargetActions[] = $action;
    }
}

echo '<!-- markerpdf-outline-nametree-transition-actions ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-navigation-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'outline name-tree destinations annotated with target page transition and review-only page actions',
    'outline_titles' => array_column($navigation['outline'], 'title'),
    'outline_target_labels' => array_column($navigation['outline'], 'page_label'),
    'outline_target_transitions' => array_map(
        static fn (array $outline): ?string => $outline['target_page_transition']['style'] ?? null,
        $navigation['outline']
    ),
    'outline_target_action_safeties' => array_column($outlineTargetActions, 'safety'),
    'all_outline_target_actions_review_only' => array_reduce(
        $outlineTargetActions,
        static fn (bool $carry, array $row): bool => $carry && ($row['executes_on_import'] ?? true) === false,
        true
    ),
    'visible_text_excludes_action_targets' => !str_contains($plainText, 'https://example.com/deck-notes')
        && !str_contains($plainText, 'javascript:alert'),
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
foreach ($navigation['outline'] as $outline) {
    $transition = $outline['target_page_transition'] ?? [];
    echo '<li data-marker-outline-page-label="' . htmlspecialchars($outline['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-target-transition="' . htmlspecialchars((string) ($transition['style'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-target-actions="' . htmlspecialchars((string) count($outline['target_page_actions'] ?? []), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">Outline target: ' . htmlspecialchars($outline['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
foreach ($outlineTargetActions as $action) {
    echo '<li data-marker-page-action-event="' . htmlspecialchars((string) $action['event_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-page-action-safety="' . htmlspecialchars((string) $action['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="' . (($action['executes_on_import'] ?? true) ? 'true' : 'false')
        . '">Target page action: ' . htmlspecialchars($action['event_label'] . ' ' . $action['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
