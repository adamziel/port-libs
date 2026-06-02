<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Intro article text stays visible) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Story left column text) Tj ET '
    . 'BT /F1 12 Tf 320 720 Td (Story right column text) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /OpenAction /ArticleIntro /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Story Bead Target) /Parent 5 0 R /Dest /ArticleStory >>\nendobj\n"
    . "8 0 obj\n<< /Names [(ArticleIntro) [3 0 R /Fit] (ArticleStory) [4 0 R /FitH 700]] >>\nendobj\n"
    . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Magazine Article Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 700 260 740] /N 22 0 R /V 23 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [60 700 260 740] /N 23 0 R /V 21 0 R >>\nendobj\n"
    . "23 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [300 700 520 740] /N 21 0 R /V 22 0 R >>\nendobj\n"
    . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Story ) /St 7 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$articleThreads = $navigation['article_threads'] ?? [];
$outline = $navigation['outline'][0] ?? [];
$openAction = $navigation['open_action_review_actions'][0] ?? [];

if (count($articleThreads) !== 1 || ($articleThreads[0]['bead_count'] ?? 0) !== 3) {
    throw new RuntimeException('Expected one article thread with three navigable beads.');
}
if (array_column($outline['target_article_beads'] ?? [], 'bead_object') !== [22, 23]) {
    throw new RuntimeException('Expected outline target to carry page-local article bead metadata.');
}
if (array_column($openAction['target_article_beads'] ?? [], 'bead_object') !== [21]) {
    throw new RuntimeException('Expected OpenAction target to carry intro article bead metadata.');
}
if (str_contains($plainText, 'Magazine Article Thread') || str_contains($plainText, 'Story Bead Target') || str_contains($plainText, 'ArticleIntro')) {
    throw new RuntimeException('Expected thread and outline dictionaries to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-article-thread-bead-navigation-review-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-article-thread-bead-navigation-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'catalog /Threads bead chains are exposed as review metadata and attached to outline/OpenAction targets while body text stays page-stream-only',
    'navigation_sources' => $navigation['source'],
    'article_thread_titles' => array_column($articleThreads, 'title'),
    'article_thread_bead_count' => $articleThreads[0]['bead_count'] ?? 0,
    'article_thread_bead_objects' => array_column($articleThreads[0]['beads'] ?? [], 'bead_object'),
    'article_thread_next_beads' => array_column($articleThreads[0]['beads'] ?? [], 'next_bead_object'),
    'outline_target_label' => $outline['page_label'] ?? null,
    'outline_target_beads' => array_column($outline['target_article_beads'] ?? [], 'bead_object'),
    'open_action_target_label' => $openAction['page_label'] ?? null,
    'open_action_target_beads' => array_column($openAction['target_article_beads'] ?? [], 'bead_object'),
    'visible_text_excludes_thread_navigation' => !str_contains($plainText, 'Magazine Article Thread')
        && !str_contains($plainText, 'Story Bead Target')
        && !str_contains($plainText, 'ArticleIntro'),
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
foreach ($articleThreads as $thread) {
    foreach ($thread['beads'] as $bead) {
        echo '<li data-marker-thread-title="' . htmlspecialchars((string) $thread['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '" data-marker-thread-bead="' . htmlspecialchars((string) $bead['bead_object'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '" data-marker-thread-next-bead="' . htmlspecialchars((string) ($bead['next_bead_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '" data-marker-thread-page-label="' . htmlspecialchars($bead['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">Article bead: ' . htmlspecialchars($bead['page_label'] . ' #' . $bead['bead_object'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
    }
}
echo "</ul>\n<!-- /wp:list -->\n";
