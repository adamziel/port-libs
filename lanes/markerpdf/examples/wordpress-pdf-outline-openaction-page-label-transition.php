<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Preface text stays visible) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Chapter target text stays visible) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /OpenAction 40 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines /ViewerPreferences << /DisplayDocTitle true >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 5 /Trans 15 0 R /AA << /O 42 0 R >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Chapter Target) /Parent 5 0 R /Dest /ChapterStart >>\nendobj\n"
    . "8 0 obj\n<< /Names [(ChapterStart) 14 0 R] >>\nendobj\n"
    . "14 0 obj\n<< /D 13 0 R >>\nendobj\n"
    . "13 0 obj\n[4 0 R 16 0 R 17 0 R]\nendobj\n"
    . "15 0 obj\n<< /S /Blinds /D .5 /Dm /V >>\nendobj\n"
    . "16 0 obj\n/FitH\nendobj\n"
    . "17 0 obj\n640\nendobj\n"
    . "20 0 obj\n<< /Nums [0 << /S /r /P (front-) /St 2 >> 1 << /S /D /P (Body ) /St 1 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /S /GoTo /D 41 0 R /Next 42 0 R >>\nendobj\n"
    . "41 0 obj\n(ChapterStart)\nendobj\n"
    . "42 0 obj\n<< /S /URI /URI (https://example.com/chapter-notes) >>\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);

$openActionRows = $navigation['open_action_review_actions'];
$openActionDestination = $navigation['open_action_destination'] ?? [];
$targetTransition = is_array($openActionDestination['target_page_transition'] ?? null)
    ? $openActionDestination['target_page_transition']
    : [];

echo '<!-- markerpdf-outline-openaction-page-label-transition ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-navigation-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'outline_page_labels' => array_column($navigation['outline'], 'page_label'),
    'open_action_destination_label' => $openActionDestination['page_label'] ?? null,
    'open_action_view_mode' => $openActionDestination['view_mode'] ?? null,
    'open_action_target_transition' => $targetTransition['style'] ?? null,
    'open_action_count' => count($openActionRows),
    'chained_action_count' => count(array_filter($openActionRows, static fn (array $row): bool => ($row['chained'] ?? false) === true)),
    'all_actions_review_only' => array_reduce(
        $openActionRows,
        static fn (bool $carry, array $row): bool => $carry && ($row['executes_on_import'] ?? true) === false,
        true
    ),
    'visible_text_excludes_openaction_uri' => !str_contains($textExtractor->extractPlainText($pdf), 'https://example.com/chapter-notes'),
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
foreach ($navigation['outline'] as $item) {
    echo '<li data-marker-outline-page-label="' . htmlspecialchars($item['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-view="' . htmlspecialchars((string) $item['view_mode'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">Outline: ' . htmlspecialchars($item['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}

foreach ($openActionRows as $row) {
    echo '<li data-marker-openaction-type="' . htmlspecialchars($row['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-openaction-safety="' . htmlspecialchars($row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="' . (($row['executes_on_import'] ?? true) ? 'true' : 'false')
        . '">OpenAction: ' . htmlspecialchars($row['action_type'] . ' ' . $row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}

foreach ($navigation['page_presentations'] as $pagePresentation) {
    $transition = $pagePresentation['transition'];
    if ($transition === null) {
        continue;
    }

    echo '<li data-marker-transition-page-label="' . htmlspecialchars($pagePresentation['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-transition-style="' . htmlspecialchars((string) $transition['style'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">Transition: PDF page ' . htmlspecialchars($pagePresentation['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
