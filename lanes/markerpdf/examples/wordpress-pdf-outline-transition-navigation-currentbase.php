<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Cover page stays visible) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Deck page stays visible) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 6 /Trans 16 0 R /AA << /O 15 0 R >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Deck Action) /Parent 5 0 R /A 12 0 R /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (External Notes) /Parent 5 0 R /A << /S /URI /URI (javascript:alert\\(1\\)) >> >>\nendobj\n"
    . "8 0 obj\n<< /Names [(DeckStart) 9 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /D [4 0 R /XYZ 72 640 0] >>\nendobj\n"
    . "12 0 obj\n<< /S /GoTo /D /DeckStart /Next [13 0 R 14 0 R 14 0 R] >>\nendobj\n"
    . "13 0 obj\n<< /S /URI /URI (https://example.com/deck-notes) >>\nendobj\n"
    . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden outline script'\\)) >>\nendobj\n"
    . "15 0 obj\n<< /S /URI /URI (https://example.com/page-open) >>\nendobj\n"
    . "16 0 obj\n<< /S /Push /D 1 /Di 0 >>\nendobj\n"
    . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Deck ) /St 3 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$outlineActions = $navigation['outline_action_review_actions'];

echo '<!-- markerpdf-outline-transition-navigation-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-action-navigation-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'outline item actions plus chained followups are review-only while local action targets inherit page transition metadata',
    'outline_titles' => array_column($navigation['outline'], 'title'),
    'outline_action_titles' => array_column($outlineActions, 'outline_title'),
    'outline_action_count' => count($outlineActions),
    'outline_action_safeties' => array_column($outlineActions, 'safety'),
    'outline_action_chained_count' => count(array_filter($outlineActions, static fn (array $row): bool => ($row['chained'] ?? false) === true)),
    'local_action_target_label' => $outlineActions[0]['page_label'] ?? null,
    'local_action_target_transition' => $outlineActions[0]['target_page_transition']['style'] ?? null,
    'all_outline_actions_review_only' => array_reduce(
        $outlineActions,
        static fn (bool $carry, array $row): bool => $carry && ($row['executes_on_import'] ?? true) === false,
        true
    ),
    'visible_text_excludes_outline_actions' => !str_contains($plainText, 'https://example.com/deck-notes')
        && !str_contains($plainText, 'hidden outline script')
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
        . '" data-marker-outline-view="' . htmlspecialchars((string) $outline['view_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-target-transition="' . htmlspecialchars((string) ($transition['style'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">Outline: ' . htmlspecialchars($outline['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}

foreach ($outlineActions as $row) {
    echo '<li data-marker-outline-action-title="' . htmlspecialchars($row['outline_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-action-type="' . htmlspecialchars($row['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-action-safety="' . htmlspecialchars($row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="' . (($row['executes_on_import'] ?? true) ? 'true' : 'false')
        . '">Outline action: ' . htmlspecialchars($row['outline_title'] . ' ' . $row['action_type'] . ' ' . $row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
