<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$introText = 'BT /F1 12 Tf 72 720 Td (Intro destination thread page remains visible) Tj ET';
$articleText = 'BT /F1 12 Tf 72 720 Td (Destination thread action target remains visible) Tj ET';
$reviewPayload = '<wp-outline-destination-thread action="direct-dest-thread"/>';
$reviewChecksum = strtoupper(hash('md5', $reviewPayload));

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /Threads [20 0 R] /PageLabels 25 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R /Dur 7 /Trans 16 0 R /AA << /O 17 0 R >> /PieceInfo << /WPDestinationThread << /Private << /ReviewState (destination-thread-action-review) /NeedsReview true >> >> >> /AF [12 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Destination Thread Action Metadata) /Parent 5 0 R /Dest 9 0 R >>\nendobj\n"
    . "9 0 obj\n<< /S /Thread /D 0 /B 22 0 R /Next [10 0 R 11 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /S /URI /URI (https://example.com/destination-thread-review) >>\nendobj\n"
    . "11 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden destination thread script'\\)) >>\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (destination-thread-review.xml) /Desc (Destination thread action review source) /AFRelationship /Source /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($reviewPayload) . " /CheckSum <{$reviewChecksum}> >> /Length " . strlen($reviewPayload) . " >>\nstream\n{$reviewPayload}\nendstream\nendobj\n"
    . "16 0 obj\n<< /S /Push /D .65 /Di 180 >>\nendobj\n"
    . "17 0 obj\n<< /S /URI /URI (https://example.com/destination-thread-page-open) >>\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Destination Article Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [64 680 280 735] /N 22 0 R /V 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [300 680 548 735] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Article ) /St 9 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($introText) . " >>\nstream\n{$introText}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($articleText) . " >>\nstream\n{$articleText}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$actions = $navigation['outline_action_review_actions'];
$plainText = $textExtractor->extractPlainText($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);

if (count($actions) !== 3 || array_column($actions, 'action_type') !== ['Thread', 'URI', 'JavaScript']) {
    throw new RuntimeException('Expected direct outline destination Thread action and chained review rows.');
}
if (($actions[0]['destination_action_target_page_object'] ?? null) !== 4 || ($actions[0]['destination_action_target_thread_page_object'] ?? null) !== 4) {
    throw new RuntimeException('Expected normalized destination target page object and thread page object.');
}
if (($actions[1]['destination_action_target_thread_destination'] ?? null) !== '0') {
    throw new RuntimeException('Expected chained action row to carry original thread destination metadata.');
}
if (($actions[1]['destination_action_target_page_review']['page_associated_files'][0]['filename'] ?? null) !== 'destination-thread-review.xml') {
    throw new RuntimeException('Expected chained action row to keep target page associated-file metadata.');
}
if (str_contains($plainText, 'Destination Thread Action Metadata') || str_contains($plainText, 'Destination Article Thread') || str_contains($plainText, 'destination-thread-review') || str_contains($plainText, 'hidden destination thread script') || str_contains($plainText, 'wp-outline-destination-thread')) {
    throw new RuntimeException('Expected outline destination Thread action operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-destination-thread-action-metadata-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-destination-thread-action-metadata-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'outline /Dest values pointing directly at Thread action dictionaries expose normalized destination target page and thread metadata on primary and chained review rows',
    'navigation_sources' => $navigation['source'],
    'outline_action_count' => count($actions),
    'outline_action_types' => array_column($actions, 'action_type'),
    'outline_action_safeties' => array_column($actions, 'safety'),
    'thread_action_target_page_object' => $actions[0]['destination_action_target_page_object'] ?? null,
    'thread_action_target_thread_page_object' => $actions[0]['destination_action_target_thread_page_object'] ?? null,
    'thread_action_target_destination' => $actions[0]['destination_action_target_thread_destination'] ?? null,
    'thread_action_target_label' => $actions[0]['destination_action_target_page_label'] ?? null,
    'thread_action_target_beads' => array_column($actions[0]['destination_action_target_article_beads'] ?? [], 'bead_object'),
    'chained_target_attachment' => $actions[1]['destination_action_target_page_review']['page_associated_files'][0]['filename'] ?? null,
    'visible_text_excludes_action_operands' => !str_contains($plainText, 'Destination Thread Action Metadata')
        && !str_contains($plainText, 'Destination Article Thread')
        && !str_contains($plainText, 'destination-thread-review')
        && !str_contains($plainText, 'hidden destination thread script')
        && !str_contains($plainText, 'wp-outline-destination-thread'),
], JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . " -->\n";

foreach ($pages as $page) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($page['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($actions as $row) {
    echo '<li data-marker-outline-action-type="' . htmlspecialchars($row['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-thread-destination="' . htmlspecialchars((string) ($row['destination_action_target_thread_destination'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-thread-page-object="' . htmlspecialchars((string) ($row['destination_action_target_thread_page_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-target-page-object="' . htmlspecialchars((string) ($row['destination_action_target_page_object'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-target-label="' . htmlspecialchars((string) ($row['destination_action_target_page_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="' . (($row['executes_on_import'] ?? true) ? 'true' : 'false')
        . '">Outline destination Thread action review: ' . htmlspecialchars($row['action_type'] . ' ' . $row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
