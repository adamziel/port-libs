<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$introText = 'BT /F1 12 Tf 72 720 Td (Intro direct named thread page remains visible) Tj ET';
$threadText = 'BT /F1 12 Tf 72 720 Td (Direct named destination thread target remains visible) Tj ET';
$reviewPayload = '<wp-outline-thread-review action="ThreadAction"/>';
$reviewChecksum = strtoupper(hash('md5', $reviewPayload));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R /Dur 6 /Trans 16 0 R /PieceInfo << /WPDirectThread << /Private << /ReviewState (direct-named-thread-review) /NeedsReview true >> >> >> /AF [12 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Direct Named Thread Action) /Parent 5 0 R /Dest /ThreadAction >>\nendobj\n"
    . "8 0 obj\n<< /Names [(ThreadAction) 9 0 R] >>\nendobj\n"
    . "9 0 obj\n<< /S /Thread /D (Direct Named Thread) /B 22 0 R /Next 10 0 R >>\nendobj\n"
    . "10 0 obj\n<< /S /URI /URI (https://example.com/direct-named-thread-review) >>\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (direct-thread-review.xml) /Desc (Direct named thread review source) /AFRelationship /Source /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($reviewPayload) . " /CheckSum <{$reviewChecksum}> >> /Length " . strlen($reviewPayload) . " >>\nstream\n{$reviewPayload}\nendstream\nendobj\n"
    . "16 0 obj\n<< /S /Push /D .5 /Di 90 >>\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Direct Named Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [64 680 280 735] /N 22 0 R /V 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [300 680 548 735] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Thread ) /St 7 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($introText) . " >>\nstream\n{$introText}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($threadText) . " >>\nstream\n{$threadText}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$actions = $navigation['outline_action_review_actions'];
$plainText = $textExtractor->extractPlainText($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);

if (($actions[0]['destination_action_target_page'] ?? null) !== 1) {
    throw new RuntimeException('Expected primary Thread row to expose normalized named-destination target page context.');
}
if (($actions[0]['destination_action_target_page_review']['piece_info']['WPDirectThread']['private']['ReviewState'] ?? null) !== 'direct-named-thread-review') {
    throw new RuntimeException('Expected primary Thread row to expose named-destination target page review context.');
}
if (array_column($actions[0]['destination_action_target_article_beads'] ?? [], 'bead_object') !== [21, 22]) {
    throw new RuntimeException('Expected primary Thread row to expose named-destination target article beads.');
}
if (($actions[1]['destination_action_target_page_review']['page_associated_files'][0]['filename'] ?? null) !== 'direct-thread-review.xml') {
    throw new RuntimeException('Expected chained action row to keep named-destination target attachment metadata.');
}
if (str_contains($plainText, 'Direct Named Thread Action') || str_contains($plainText, 'ThreadAction') || str_contains($plainText, 'direct-named-thread-review') || str_contains($plainText, 'wp-outline-thread-review')) {
    throw new RuntimeException('Expected direct named Thread action operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-direct-named-thread-action-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-direct-named-thread-action-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'outline /Dest names that resolve directly to Thread action dictionaries stay review-only while primary and chained rows carry normalized target page/thread context',
    'navigation_sources' => $navigation['source'],
    'outline_action_count' => count($actions),
    'outline_action_types' => array_column($actions, 'action_type'),
    'outline_action_safeties' => array_column($actions, 'safety'),
    'outline_action_destination_names' => array_column($actions, 'destination_action_name'),
    'primary_target_label' => $actions[0]['destination_action_target_page_label'] ?? null,
    'primary_target_thread_titles' => $actions[0]['destination_action_target_article_thread_titles'] ?? [],
    'primary_target_beads' => array_column($actions[0]['destination_action_target_article_beads'] ?? [], 'bead_object'),
    'primary_target_review_state' => $actions[0]['destination_action_target_page_review']['piece_info']['WPDirectThread']['private']['ReviewState'] ?? null,
    'chained_target_attachment' => $actions[1]['destination_action_target_page_review']['page_associated_files'][0]['filename'] ?? null,
    'visible_text_excludes_action_operands' => !str_contains($plainText, 'Direct Named Thread Action')
        && !str_contains($plainText, 'ThreadAction')
        && !str_contains($plainText, 'direct-named-thread-review')
        && !str_contains($plainText, 'wp-outline-thread-review'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($pages as $page) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($page['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($actions as $row) {
    echo '<li data-marker-outline-action-type="' . htmlspecialchars($row['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-destination-action-name="' . htmlspecialchars((string) ($row['destination_action_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-target-label="' . htmlspecialchars((string) ($row['destination_action_target_page_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-target-review-state="' . htmlspecialchars((string) ($row['destination_action_target_page_review']['piece_info']['WPDirectThread']['private']['ReviewState'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="' . (($row['executes_on_import'] ?? true) ? 'true' : 'false')
        . '">Direct named Thread action review: ' . htmlspecialchars($row['action_type'] . ' ' . $row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
