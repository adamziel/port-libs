<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$coverText = 'BT /F1 12 Tf 72 720 Td (Cover fit action chain page remains visible) Tj ET';
$targetText = 'BT /F1 12 Tf 72 720 Td (Target fit action chain page remains visible) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 25 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 5.25 /Trans 18 0 R /AA << /O 19 0 R >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 3 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Direct FitR Destination Action) /Parent 5 0 R /Dest 12 0 R /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Outline FitBH Action) /Parent 5 0 R /A 15 0 R /Next 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /Title (Named FitB Destination Action) /Parent 5 0 R /Dest /BoxAction >>\nendobj\n"
    . "8 0 obj\n<< /Names [(BoxAction) 21 0 R (TopFit) [4 0 R /FitBH 680 999] (BoxFit) [3 0 R /FitB 111 222]] >>\nendobj\n"
    . "12 0 obj\n<< /S /GoTo /D [4 0 R /FitR 36 120 420 760] /Next [13 0 R 14 0 R 14 0 R] >>\nendobj\n"
    . "13 0 obj\n<< /S /URI /URI (https://example.com/fitr-followup) >>\nendobj\n"
    . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden fitr action chain script'\\)) >>\nendobj\n"
    . "15 0 obj\n<< /S /GoTo /D /TopFit /Next 16 0 R >>\nendobj\n"
    . "16 0 obj\n<< /S /URI /URI (https://example.com/topfit-followup) >>\nendobj\n"
    . "18 0 obj\n<< /S /Glitter /D .6 /Di 270 >>\nendobj\n"
    . "19 0 obj\n<< /S /URI /URI (https://example.com/page-open-fit-review) >>\nendobj\n"
    . "21 0 obj\n<< /S /GoTo /D /BoxFit /Next 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /S /Launch /F (fit-review-helper.exe) >>\nendobj\n"
    . "25 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Target ) /St 3 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($targetText) . " >>\nstream\n{$targetText}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$actions = $navigation['outline_action_review_actions'];

if (count($actions) !== 7) {
    throw new RuntimeException('Expected seven outline destination fit action review rows.');
}
if (($actions[1]['destination_action_target_view_mode'] ?? null) !== 'FitR') {
    throw new RuntimeException('Expected chained direct destination action row to carry FitR target metadata.');
}
if (($actions[4]['destination_action_target_view_mode'] ?? null) !== 'FitBH') {
    throw new RuntimeException('Expected chained outline action row to carry FitBH target metadata.');
}
if (($actions[6]['destination_action_target_view_mode'] ?? null) !== 'FitB') {
    throw new RuntimeException('Expected named destination action chain row to carry FitB target metadata.');
}
if (str_contains($plainText, 'fitr-followup') || str_contains($plainText, 'topfit-followup') || str_contains($plainText, 'hidden fitr action chain script') || str_contains($plainText, 'fit-review-helper.exe')) {
    throw new RuntimeException('Expected outline fit action chain operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-destination-fit-action-chain-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-destination-fit-action-chain-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'outline GoTo action chains preserve Fit family destination view operands on chained review rows without executing actions',
    'outline_titles' => array_column($navigation['outline'], 'title'),
    'outline_view_modes' => array_column($navigation['outline'], 'view_mode'),
    'outline_action_count' => count($actions),
    'outline_action_types' => array_column($actions, 'action_type'),
    'outline_action_safeties' => array_column($actions, 'safety'),
    'chained_target_view_modes' => array_map(
        static fn (array $row): ?string => $row['destination_action_target_view_mode'] ?? null,
        $actions
    ),
    'fitr_target_parameters' => $actions[1]['destination_action_target_view_parameters'] ?? [],
    'fitbh_target_parameters' => $actions[4]['destination_action_target_view_parameters'] ?? [],
    'fitb_target_parameters' => $actions[6]['destination_action_target_view_parameters'] ?? [],
    'all_outline_actions_review_only' => array_reduce(
        $actions,
        static fn (bool $carry, array $row): bool => $carry && ($row['executes_on_import'] ?? true) === false,
        true
    ),
    'visible_text_excludes_fit_action_operands' => !str_contains($plainText, 'fitr-followup')
        && !str_contains($plainText, 'topfit-followup')
        && !str_contains($plainText, 'hidden fitr action chain script')
        && !str_contains($plainText, 'fit-review-helper.exe'),
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
        . '" data-marker-outline-action-safety="' . htmlspecialchars($row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="' . (($row['executes_on_import'] ?? true) ? 'true' : 'false')
        . '">Destination fit action: ' . htmlspecialchars($row['outline_title'] . ' ' . $row['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
