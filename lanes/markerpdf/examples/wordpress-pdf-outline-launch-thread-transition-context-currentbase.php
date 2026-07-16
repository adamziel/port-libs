<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$introContent = 'BT /F1 12 Tf 72 720 Td (Intro launch review page remains visible) Tj ET';
$threadContent = 'BT /F1 12 Tf 72 720 Td (Launch thread target page remains visible) Tj ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R /Dur 8 /Trans 16 0 R /AA << /O 17 0 R >> >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Launch Before Article Target) /Parent 5 0 R /A 9 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Names [(ArticleThreadTarget) [4 0 R /XYZ 144 680 1.25]] >>\nendobj\n"
    . "9 0 obj\n<< /S /Launch /F (post-import-helper.exe) /Win << /F (post-import-helper.exe) /O (open) /P (/review-only) >> /NewWindow false /Next [10 0 R 11 0 R 11 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /S /GoTo /D /ArticleThreadTarget >>\nendobj\n"
    . "11 0 obj\n<< /S /URI /URI (https://example.com/thread-launch-review) >>\nendobj\n"
    . "16 0 obj\n<< /S /Fly /D .6 /M /I /Di 270 /SS .75 /B false >>\nendobj\n"
    . "17 0 obj\n<< /S /URI /URI (https://example.com/page-open-launch-review) >>\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Launch Article Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [66 680 300 742] /N 22 0 R /V 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [310 680 540 742] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Article ) /St 18 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($threadContent) . " >>\nstream\n{$threadContent}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$actions = $navigation['outline_action_review_actions'];

if (count($actions) !== 3) {
    throw new RuntimeException('Expected Launch, GoTo, and URI outline action rows.');
}
if (($actions[0]['destination_action_target_page_label'] ?? null) !== 'Article 18') {
    throw new RuntimeException('Expected blocked Launch row to carry chained destination page-label context.');
}
if (($actions[0]['destination_action_target_page_transition']['style'] ?? null) !== 'Fly') {
    throw new RuntimeException('Expected blocked Launch row to carry chained destination transition context.');
}
if (array_column($actions[0]['destination_action_target_article_beads'] ?? [], 'bead_object') !== [21, 22]) {
    throw new RuntimeException('Expected blocked Launch row to carry chained destination article-thread beads.');
}
if (str_contains($plainText, 'post-import-helper.exe') || str_contains($plainText, 'ArticleThreadTarget') || str_contains($plainText, 'thread-launch-review') || str_contains($plainText, 'Launch Article Thread')) {
    throw new RuntimeException('Expected Launch/action/thread operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-launch-thread-transition-context-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-launch-thread-transition-context-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'blocked outline Launch actions inherit chained local destination page-label, transition, page-action, and article-thread context as review-only metadata',
    'navigation_sources' => $navigation['source'],
    'outline_action_types' => array_column($actions, 'action_type'),
    'outline_action_safeties' => array_column($actions, 'safety'),
    'launch_target_label' => $actions[0]['destination_action_target_page_label'] ?? null,
    'launch_target_view' => $actions[0]['destination_action_target_view_mode'] ?? null,
    'launch_target_transition' => $actions[0]['destination_action_target_page_transition']['style'] ?? null,
    'launch_target_page_action_safeties' => array_column($actions[0]['destination_action_target_page_actions'] ?? [], 'safety'),
    'launch_target_article_beads' => array_column($actions[0]['destination_action_target_article_beads'] ?? [], 'bead_object'),
    'all_outline_actions_review_only' => array_reduce(
        $actions,
        static fn (bool $carry, array $row): bool => $carry && ($row['executes_on_import'] ?? true) === false,
        true
    ),
    'visible_text_excludes_launch_action_context_operands' => !str_contains($plainText, 'post-import-helper.exe')
        && !str_contains($plainText, 'ArticleThreadTarget')
        && !str_contains($plainText, 'thread-launch-review')
        && !str_contains($plainText, 'Launch Article Thread'),
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
        . '" data-marker-outline-action-safety="' . htmlspecialchars($row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-target-label="' . htmlspecialchars((string) ($row['destination_action_target_page_label'] ?? $row['page_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-target-transition="' . htmlspecialchars((string) ($row['destination_action_target_page_transition']['style'] ?? $row['target_page_transition']['style'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="' . (($row['executes_on_import'] ?? true) ? 'true' : 'false')
        . '">Outline action review: ' . htmlspecialchars($row['action_type'] . ' ' . $row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
