<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$introText = 'BT /F1 12 Tf 72 720 Td (Intro name tree action page remains visible) Tj ET';
$articleText = 'BT /F1 12 Tf 72 720 Td (Name tree thread target page remains visible) Tj ET';
$reviewPayload = '<wp-outline-nametree-review target="thread-action"/>';
$reviewChecksum = strtoupper(hash('md5', $reviewPayload));

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R /Dur 4 /Trans 16 0 R /AA << /O 17 0 R >> /PieceInfo << /WPThreadAction << /LastModified (D:20260602211200Z) /Private << /ReviewState (nametree-thread-page-review) /NeedsReview true /Batch 48 >> >> >> /AF [12 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (GoTo NameTree Thread Action) /Parent 5 0 R /A 9 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Names [(ThreadAction) 10 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /S /GoTo /D /ThreadAction /Next 13 0 R >>\nendobj\n"
    . "10 0 obj\n<< /S /Thread /D (NameTree Article Thread) /B 22 0 R /Next 11 0 R >>\nendobj\n"
    . "11 0 obj\n<< /S /URI /URI (https://example.com/name-tree-thread-review) >>\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (thread-action-review.xml) /Desc (Name tree thread action source) /AFRelationship /Source /EF << /F 14 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden outer goto followup'\\)) >>\nendobj\n"
    . "14 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($reviewPayload) . " /CheckSum <{$reviewChecksum}> >> /Length " . strlen($reviewPayload) . " >>\nstream\n{$reviewPayload}\nendstream\nendobj\n"
    . "16 0 obj\n<< /S /Split /D .35 /Dm /V /M /O >>\nendobj\n"
    . "17 0 obj\n<< /S /URI /URI (https://example.com/name-tree-page-open) >>\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (NameTree Article Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [64 684 280 734] /N 22 0 R /V 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [292 684 548 734] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Article ) /St 48 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($introText) . " >>\nstream\n{$introText}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($articleText) . " >>\nstream\n{$articleText}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$toc = $outlineExtractor->getPdfToc($pdf);
$actions = $navigation['outline_action_review_actions'];

if ($toc !== []) {
    throw new RuntimeException('Expected name-tree Thread action targets to stay out of same-document TOC rows.');
}
if (count($actions) !== 3 || array_column($actions, 'action_type') !== ['Thread', 'URI', 'JavaScript']) {
    throw new RuntimeException('Expected Thread action, named /Next URI, and outer GoTo /Next JavaScript review rows.');
}
if (($actions[0]['page_label'] ?? null) !== 'Article 48') {
    throw new RuntimeException('Expected name-tree Thread action to resolve selected bead page label.');
}
if (($actions[0]['target_page_transition']['style'] ?? null) !== 'Split') {
    throw new RuntimeException('Expected name-tree Thread action to inherit target page transition metadata.');
}
if (($actions[1]['destination_action_target_page_review']['page_associated_files'][0]['filename'] ?? null) !== 'thread-action-review.xml') {
    throw new RuntimeException('Expected named /Next action row to inherit target page associated-file review metadata.');
}
if (($actions[2]['destination_action_target_page_review']['piece_info']['WPThreadAction']['private']['ReviewState'] ?? null) !== 'nametree-thread-page-review') {
    throw new RuntimeException('Expected outer GoTo /Next action to inherit the name-tree Thread page-review context.');
}
if (str_contains($plainText, 'GoTo NameTree Thread Action') || str_contains($plainText, 'ThreadAction') || str_contains($plainText, 'NameTree Article Thread') || str_contains($plainText, 'name-tree-thread-review') || str_contains($plainText, 'name-tree-page-open') || str_contains($plainText, 'hidden outer goto followup') || str_contains($plainText, 'wp-outline-nametree-review') || str_contains($plainText, 'nametree-thread-page-review')) {
    throw new RuntimeException('Expected outline action/name-tree/page-review operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-action-nametree-page-review-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-action-nametree-page-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'native_boundary' => 'outline GoTo actions whose D operand resolves through /Names /Dests to a Thread action dictionary expose target page review metadata without creating TOC rows',
    'navigation_sources' => $navigation['source'],
    'toc_titles' => array_column($toc, 'title'),
    'outline_action_types' => array_column($actions, 'action_type'),
    'outline_action_safeties' => array_column($actions, 'safety'),
    'destination_action_names' => array_column($actions, 'destination_action_name'),
    'thread_action_title' => $actions[0]['thread_title'] ?? null,
    'thread_action_page_label' => $actions[0]['page_label'] ?? null,
    'thread_action_bead_object' => $actions[0]['thread_bead_object'] ?? null,
    'thread_action_transition' => $actions[0]['target_page_transition']['style'] ?? null,
    'chained_uri_target_attachment' => $actions[1]['destination_action_target_page_review']['page_associated_files'][0]['filename'] ?? null,
    'outer_next_target_review_state' => $actions[2]['destination_action_target_page_review']['piece_info']['WPThreadAction']['private']['ReviewState'] ?? null,
    'all_outline_actions_review_only' => array_reduce(
        $actions,
        static fn (bool $carry, array $row): bool => $carry && ($row['executes_on_import'] ?? true) === false,
        true
    ),
    'visible_text_excludes_action_operands' => !str_contains($plainText, 'GoTo NameTree Thread Action')
        && !str_contains($plainText, 'ThreadAction')
        && !str_contains($plainText, 'NameTree Article Thread')
        && !str_contains($plainText, 'name-tree-thread-review')
        && !str_contains($plainText, 'name-tree-page-open')
        && !str_contains($plainText, 'hidden outer goto followup')
        && !str_contains($plainText, 'wp-outline-nametree-review')
        && !str_contains($plainText, 'nametree-thread-page-review'),
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
    $targetReview = $row['destination_action_target_page_review'] ?? $row['target_page_review'] ?? [];
    echo '<li data-marker-outline-action-title="' . htmlspecialchars($row['outline_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-action-type="' . htmlspecialchars($row['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-action-safety="' . htmlspecialchars($row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-action-name="' . htmlspecialchars((string) ($row['destination_action_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-target-label="' . htmlspecialchars((string) ($row['page_label'] ?? $row['destination_action_target_page_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-review-state="' . htmlspecialchars((string) ($targetReview['piece_info']['WPThreadAction']['private']['ReviewState'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-target-attachment="' . htmlspecialchars((string) ($targetReview['page_associated_files'][0]['filename'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="' . (($row['executes_on_import'] ?? true) ? 'true' : 'false')
        . '">Name-tree outline action review: ' . htmlspecialchars($row['action_type'] . ' ' . $row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
