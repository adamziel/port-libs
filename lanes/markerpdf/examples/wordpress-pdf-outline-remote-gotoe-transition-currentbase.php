<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$coverText = 'BT /F1 12 Tf 72 720 Td (Cover page remains visible) Tj ET';
$deckText = 'BT /F1 12 Tf 72 720 Td (Deck transition target remains visible) Tj ET';
$attachmentPayload = 'BT /F1 12 Tf 72 720 Td (Embedded Appendix Payload Leak) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /PageMode /UseOutlines >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 7 /Trans 17 0 R /AA << /O 18 0 R >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Embedded Appendix Action) /Parent 5 0 R /A 12 0 R /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Named Embedded Appendix Action) /Parent 5 0 R /Dest /EmbeddedReview >>\nendobj\n"
    . "8 0 obj\n<< /Names [(DeckStart) [4 0 R /FitH 640] (EmbeddedReview) 13 0 R] >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToE /F 21 0 R /D [2 /FitH 612] /NewWindow true /T << /R /C /N (review-pack.pdf) /P 0 /A 40 0 R >> /Next [14 0 R 15 0 R] >>\nendobj\n"
    . "13 0 obj\n<< /S /GoToE /D (named-appendix) /T 25 0 R /Next 16 0 R >>\nendobj\n"
    . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('embedded outline hidden script'\\)) >>\nendobj\n"
    . "15 0 obj\n<< /S /GoTo /D /DeckStart >>\nendobj\n"
    . "16 0 obj\n<< /S /URI /URI (https://example.com/embedded-outline-notes) >>\nendobj\n"
    . "17 0 obj\n<< /S /Push /D .6 /Dm /H /M /I /Di 0 /SS .75 /B false >>\nendobj\n"
    . "18 0 obj\n<< /S /URI /URI (https://example.com/deck-open-review) >>\nendobj\n"
    . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Deck ) /St 3 >>] >>\nendobj\n"
    . "21 0 obj\n<< /Type /Filespec /F (fallback-pack.pdf) /UF <FEFF007200650076006900650077002D007000610063006B002E007000640066> /Desc (Embedded appendix packet) /AFRelationship /Data /EF << /F 22 0 R >> >>\nendobj\n"
    . "22 0 obj\n<< /Type /EmbeddedFile /Subtype /application#2Fpdf /Length " . strlen($attachmentPayload) . " >>\nstream\n{$attachmentPayload}\nendstream\nendobj\n"
    . "25 0 obj\n<< /R /C /N (named-review-pack.pdf) /P 3 /T << /R /P /N (root.pdf) >> >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($deckText) . " >>\nstream\n{$deckText}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /Annot /Subtype /Screen /Rect [72 520 360 640] >>\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$actions = $navigation['outline_action_review_actions'];
$plainText = $textExtractor->extractPlainText($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$goToEActions = array_values(array_filter(
    $actions,
    static fn (array $action): bool => ($action['action_type'] ?? null) === 'GoToE'
));

if (count($goToEActions) !== 2 || $outlineExtractor->getPdfToc($pdf) !== []) {
    throw new RuntimeException('Expected two embedded-document outline actions and no local TOC rows.');
}
if (($goToEActions[0]['safety'] ?? null) !== 'embedded-document-review' || ($goToEActions[0]['target']['relation_label'] ?? null) !== 'child') {
    throw new RuntimeException('Expected GoToE action target metadata to be review-only and child-scoped.');
}
if (($actions[2]['target_page_transition']['style'] ?? null) !== 'Push' || ($actions[2]['page_label'] ?? null) !== 'Deck 3') {
    throw new RuntimeException('Expected chained local GoTo followup to inherit target page transition metadata.');
}
if (str_contains($plainText, 'review-pack.pdf') || str_contains($plainText, 'Embedded Appendix Payload Leak') || str_contains($plainText, 'embedded outline hidden script')) {
    throw new RuntimeException('Expected embedded-document action operands and payload bytes to stay out of visible text.');
}

echo '<!-- markerpdf-outline-remote-gotoe-transition-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-gotoe-action-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'outline /S /GoToE actions and name-tree destination actions become embedded-document review metadata, while chained local GoTo rows inherit current-page transition context',
    'local_toc_titles' => [],
    'outline_action_count' => count($actions),
    'outline_action_types' => array_column($actions, 'action_type'),
    'outline_action_safeties' => array_column($actions, 'safety'),
    'go_to_e_files' => array_values(array_filter(array_map(
        static fn (array $action): ?string => $action['file'] ?? null,
        $goToEActions
    ))),
    'go_to_e_target_names' => array_values(array_filter(array_map(
        static fn (array $action): ?string => $action['target']['name'] ?? null,
        $goToEActions
    ))),
    'local_followup_page_label' => $actions[2]['page_label'] ?? null,
    'local_followup_target_transition' => $actions[2]['target_page_transition']['style'] ?? null,
    'all_outline_actions_review_only' => array_reduce(
        $actions,
        static fn (bool $carry, array $row): bool => $carry && ($row['executes_on_import'] ?? true) === false,
        true
    ),
    'visible_text_excludes_gotoe_operands' => !str_contains($plainText, 'review-pack.pdf')
        && !str_contains($plainText, 'named-review-pack.pdf')
        && !str_contains($plainText, 'Embedded Appendix Payload Leak')
        && !str_contains($plainText, 'embedded outline hidden script')
        && !str_contains($plainText, 'embedded-outline-notes'),
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
foreach ($actions as $action) {
    $label = $action['file']
        ?? $action['target']['name']
        ?? $action['destination']
        ?? $action['uri']
        ?? $action['action_type'];
    echo '<li data-marker-action-type="' . htmlspecialchars((string) $action['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-action-safety="' . htmlspecialchars((string) $action['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"'
        . ' data-marker-action-review-only="true">'
        . htmlspecialchars((string) $label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
