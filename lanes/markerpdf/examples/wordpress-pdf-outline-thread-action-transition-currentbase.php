<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$introContent = 'BT /F1 12 Tf 72 720 Td (Intro thread action page remains visible) Tj ET';
$articleContent = 'BT /F1 12 Tf 72 720 Td (Thread action article target remains visible) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R /Dur 9 /Trans 16 0 R /AA << /O 17 0 R >> >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Read Article Thread) /Parent 5 0 R /A 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /S /Thread /D 20 0 R /B 22 0 R /Next [10 0 R 11 0 R 11 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /S /URI /URI (https://example.com/thread-action-review) >>\nendobj\n"
    . "11 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden thread action script'\\)) >>\nendobj\n"
    . "16 0 obj\n<< /S /Push /D .75 /Di 0 >>\nendobj\n"
    . "17 0 obj\n<< /S /URI /URI (https://example.com/thread-page-open) >>\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Feature Article Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [60 690 270 735] /N 22 0 R /V 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [300 690 540 735] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Article ) /St 42 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($articleContent) . " >>\nstream\n{$articleContent}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$toc = $outlineExtractor->getPdfToc($pdf);
$actions = $navigation['outline_action_review_actions'];

if ($toc !== []) {
    throw new RuntimeException('Expected Thread outline actions to stay out of same-document TOC rows.');
}
if (($actions[0]['safety'] ?? null) !== 'article-thread-review') {
    throw new RuntimeException('Expected leading outline action to be reviewed as an article Thread action.');
}
if (($actions[0]['thread_bead_object'] ?? null) !== 22) {
    throw new RuntimeException('Expected Thread action to select bead 22.');
}
if (($actions[0]['page_label'] ?? null) !== 'Article 42') {
    throw new RuntimeException('Expected Thread action to inherit selected bead page label.');
}
if (($actions[0]['target_page_transition']['style'] ?? null) !== 'Push') {
    throw new RuntimeException('Expected Thread action to carry target page transition context.');
}
if (($actions[1]['destination_action_target_page_label'] ?? null) !== 'Article 42') {
    throw new RuntimeException('Expected chained URI action to inherit Thread action target context.');
}
if (str_contains($plainText, 'Read Article Thread') || str_contains($plainText, 'Feature Article Thread') || str_contains($plainText, 'thread-action-review') || str_contains($plainText, 'thread-page-open') || str_contains($plainText, 'hidden thread action script')) {
    throw new RuntimeException('Expected Thread action operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-thread-action-transition-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-thread-action-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'native_boundary' => 'outline /S /Thread actions resolve current-document article thread and bead targets as review-only metadata with page transition context',
    'navigation_sources' => $navigation['source'],
    'local_toc_titles' => array_column($toc, 'title'),
    'outline_action_types' => array_column($actions, 'action_type'),
    'outline_action_safeties' => array_column($actions, 'safety'),
    'thread_action_title' => $actions[0]['thread_title'] ?? null,
    'thread_action_bead_object' => $actions[0]['thread_bead_object'] ?? null,
    'thread_action_page_label' => $actions[0]['page_label'] ?? null,
    'thread_action_transition' => $actions[0]['target_page_transition']['style'] ?? null,
    'chained_uri_target_label' => $actions[1]['destination_action_target_page_label'] ?? null,
    'chained_uri_target_thread_titles' => $actions[1]['destination_action_target_article_thread_titles'] ?? [],
    'all_outline_actions_review_only' => array_reduce(
        $actions,
        static fn (bool $carry, array $row): bool => $carry && ($row['executes_on_import'] ?? true) === false,
        true
    ),
    'visible_text_excludes_thread_action_operands' => !str_contains($plainText, 'Read Article Thread')
        && !str_contains($plainText, 'Feature Article Thread')
        && !str_contains($plainText, 'thread-action-review')
        && !str_contains($plainText, 'thread-page-open')
        && !str_contains($plainText, 'hidden thread action script'),
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
        . '" data-marker-thread-title="' . htmlspecialchars((string) ($row['thread_title'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-thread-bead="' . htmlspecialchars((string) ($row['thread_bead_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-target-label="' . htmlspecialchars((string) ($row['page_label'] ?? $row['destination_action_target_page_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="' . (($row['executes_on_import'] ?? true) ? 'true' : 'false')
        . '">Outline Thread action review: ' . htmlspecialchars($row['action_type'] . ' ' . $row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
