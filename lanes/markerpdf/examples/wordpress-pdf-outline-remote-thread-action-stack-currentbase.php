<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$coverText = 'BT /F1 12 Tf 72 720 Td (Cover page remains visible) Tj ET';
$threadText = 'BT /F1 12 Tf 72 720 Td (Thread destination page remains visible) Tj ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 20 0 R /Threads [30 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 40 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Dur 7 /Trans 17 0 R /AA << /O 18 0 R >> /Contents 41 0 R >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Remote Thread Stack) /Parent 5 0 R /A 12 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Names [(ArticleStart) [4 0 R /FitH 690]] >>\nendobj\n"
    . "12 0 obj\n<< /S /GoToR /F << /UF <FEFF00720065006D006F00740065002D00610072007400690063006C0065002E007000640066> /F (fallback-remote.pdf) >> /D (RemoteThread) /NewWindow true /Next [13 0 R 15 0 R 15 0 R] >>\nendobj\n"
    . "13 0 obj\n<< /S /GoTo /D /ArticleStart /Next 14 0 R >>\nendobj\n"
    . "14 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden remote thread stack script'\\)) >>\nendobj\n"
    . "15 0 obj\n<< /S /URI /URI (https://example.com/remote-thread-review) >>\nendobj\n"
    . "17 0 obj\n<< /S /Blinds /D .5 /Dm /H >>\nendobj\n"
    . "18 0 obj\n<< /S /URI /URI (https://example.com/thread-page-open) >>\nendobj\n"
    . "20 0 obj\n<< /Nums [0 << /S /D /P (Cover ) /St 1 >> 1 << /S /D /P (Article ) /St 8 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Type /Thread /F 31 0 R /I << /Title (Remote Stack Article Thread) >> >>\nendobj\n"
    . "31 0 obj\n<< /Type /Bead /T 30 0 R /P 4 0 R /R [60 682 260 730] /N 32 0 R /V 32 0 R >>\nendobj\n"
    . "32 0 obj\n<< /Type /Bead /T 30 0 R /P 4 0 R /R [280 682 540 730] /N 31 0 R /V 31 0 R >>\nendobj\n"
    . "40 0 obj\n<< /Length " . strlen($coverText) . " >>\nstream\n{$coverText}\nendstream\nendobj\n"
    . "41 0 obj\n<< /Length " . strlen($threadText) . " >>\nstream\n{$threadText}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$remoteActions = $outlineExtractor->getRemoteGoToActions($pdf);
$toc = $outlineExtractor->getPdfToc($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$outlineActions = $navigation['outline_action_review_actions'];

if ($toc !== []) {
    throw new RuntimeException('Expected remote-first action stack to stay out of same-document TOC rows.');
}
if (array_column($remoteActions, 'file') !== ['remote-article.pdf']) {
    throw new RuntimeException('Expected remote GoToR file review row.');
}
if (($outlineActions[0]['destination_action_target_page_label'] ?? null) !== 'Article 8') {
    throw new RuntimeException('Expected leading remote action to inherit local action-stack page label context.');
}
if (array_column($outlineActions[0]['destination_action_target_article_beads'] ?? [], 'bead_object') !== [31, 32]) {
    throw new RuntimeException('Expected leading remote action to inherit article-thread bead context.');
}
if (array_column($outlineActions[0]['destination_action_target_page_actions'] ?? [], 'safety') !== ['review-uri']) {
    throw new RuntimeException('Expected leading remote action to inherit target page action review context.');
}
if (str_contains($plainText, 'remote-article.pdf') || str_contains($plainText, 'RemoteThread') || str_contains($plainText, 'ArticleStart') || str_contains($plainText, 'remote-thread-review') || str_contains($plainText, 'hidden remote thread stack script') || str_contains($plainText, 'Remote Stack Article Thread')) {
    throw new RuntimeException('Expected remote action stack operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-remote-thread-action-stack-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-remote-thread-action-stack-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'remote-first outline action stacks preserve local /Next article-thread target context as review metadata without creating same-document TOC rows',
    'navigation_sources' => $navigation['source'],
    'local_toc_titles' => array_column($toc, 'title'),
    'remote_action_files' => array_column($remoteActions, 'file'),
    'outline_action_types' => array_column($outlineActions, 'action_type'),
    'outline_action_safeties' => array_column($outlineActions, 'safety'),
    'remote_stack_target_label' => $outlineActions[0]['destination_action_target_page_label'] ?? null,
    'remote_stack_target_thread_titles' => $outlineActions[0]['destination_action_target_article_thread_titles'] ?? [],
    'remote_stack_target_beads' => array_column($outlineActions[0]['destination_action_target_article_beads'] ?? [], 'bead_object'),
    'remote_stack_target_page_action_safeties' => array_column($outlineActions[0]['destination_action_target_page_actions'] ?? [], 'safety'),
    'all_outline_actions_review_only' => array_reduce(
        $outlineActions,
        static fn (bool $carry, array $row): bool => $carry && ($row['executes_on_import'] ?? true) === false,
        true
    ),
    'visible_text_excludes_remote_stack_operands' => !str_contains($plainText, 'remote-article.pdf')
        && !str_contains($plainText, 'RemoteThread')
        && !str_contains($plainText, 'ArticleStart')
        && !str_contains($plainText, 'remote-thread-review')
        && !str_contains($plainText, 'hidden remote thread stack script')
        && !str_contains($plainText, 'Remote Stack Article Thread'),
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
        . '" data-marker-remote-file="' . htmlspecialchars((string) ($row['file'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-stack-target-label="' . htmlspecialchars((string) ($row['destination_action_target_page_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-stack-target-thread="' . htmlspecialchars(implode(',', $row['destination_action_target_article_thread_titles'] ?? []), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="' . (($row['executes_on_import'] ?? true) ? 'true' : 'false')
        . '">Remote thread action stack review: ' . htmlspecialchars($row['action_type'] . ' ' . $row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
