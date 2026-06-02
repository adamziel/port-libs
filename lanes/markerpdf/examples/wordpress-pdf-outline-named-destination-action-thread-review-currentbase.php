<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Intro article page remains visible) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Threaded action target text remains visible) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Threaded Named Action) /Parent 5 0 R /Dest /ArticleAction >>\nendobj\n"
    . "8 0 obj\n<< /Names [(ArticleAction) 9 0 R (ArticleStory) [4 0 R /FitH 690]] >>\nendobj\n"
    . "9 0 obj\n<< /S /GoTo /D /ArticleStory /Next [10 0 R 11 0 R 11 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /S /URI /URI (https://example.com/thread-review) >>\nendobj\n"
    . "11 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden thread named action script'\\)) >>\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Named Action Article Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [58 690 280 732] /N 22 0 R /V 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [300 690 540 732] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Article ) /St 5 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$outline = $navigation['outline'][0] ?? [];
$outlineActions = $navigation['outline_action_review_actions'];
$articleThreads = $navigation['article_threads'] ?? [];

if (array_column($outline['target_article_beads'] ?? [], 'bead_object') !== [21, 22]) {
    throw new RuntimeException('Expected outline named destination to carry article thread target beads.');
}
if (array_column($outlineActions, 'destination_action_name') !== ['ArticleAction', 'ArticleAction', 'ArticleAction']) {
    throw new RuntimeException('Expected named destination action chain rows to preserve the outer destination key.');
}
if (array_column($outlineActions[1]['destination_action_target_article_beads'] ?? [], 'bead_object') !== [21, 22]) {
    throw new RuntimeException('Expected chained action rows to inherit named action target thread context.');
}
if (str_contains($plainText, 'ArticleAction') || str_contains($plainText, 'ArticleStory') || str_contains($plainText, 'thread-review') || str_contains($plainText, 'hidden thread named action script')) {
    throw new RuntimeException('Expected named destination action operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-named-destination-action-thread-review-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-named-destination-action-thread-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'outline named destinations that resolve to action dictionaries preserve the outer name and attach article-thread target context to review-only action rows',
    'navigation_sources' => $navigation['source'],
    'outline_destination' => $outline['destination'] ?? null,
    'outline_target_label' => $outline['page_label'] ?? null,
    'outline_target_article_beads' => array_column($outline['target_article_beads'] ?? [], 'bead_object'),
    'outline_action_count' => count($outlineActions),
    'outline_action_types' => array_column($outlineActions, 'action_type'),
    'outline_action_safeties' => array_column($outlineActions, 'safety'),
    'outline_action_destination_names' => array_column($outlineActions, 'destination_action_name'),
    'chained_action_target_article_beads' => array_column($outlineActions[1]['destination_action_target_article_beads'] ?? [], 'bead_object'),
    'article_thread_titles' => array_column($articleThreads, 'title'),
    'visible_text_excludes_named_action_operands' => !str_contains($plainText, 'ArticleAction')
        && !str_contains($plainText, 'ArticleStory')
        && !str_contains($plainText, 'thread-review')
        && !str_contains($plainText, 'hidden thread named action script'),
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
foreach ($outlineActions as $row) {
    echo '<li data-marker-outline-action-title="' . htmlspecialchars($row['outline_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-action-type="' . htmlspecialchars($row['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-action-name="' . htmlspecialchars((string) ($row['destination_action_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-action-target-label="' . htmlspecialchars((string) ($row['destination_action_target_page_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="' . (($row['executes_on_import'] ?? true) ? 'true' : 'false')
        . '">Named destination action review: ' . htmlspecialchars($row['action_type'] . ' ' . $row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
