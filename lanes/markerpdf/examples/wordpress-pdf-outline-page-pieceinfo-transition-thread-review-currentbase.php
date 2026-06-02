<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="95"/></wp-export>';
$pageOneContent = 'BT /F1 12 Tf 72 720 Td (Intro navigation review text) Tj ET';
$pageTwoContent = 'BT /F1 12 Tf 72 720 Td (Deck review body text) Tj ET';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /OpenAction /DeckTarget /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] /MarkInfo << /Marked true /UserProperties true /Suspects false >> /StructTreeRoot 40 0 R >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 30 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 9 0 R >> >> /Contents 31 0 R /Dur 6 /Trans 16 0 R /AA << /O 15 0 R >> /PieceInfo << /WPImport << /LastModified (D:20260602165300Z) /Private << /ReviewState (needs-page-review) /OutlineLinked true /Priority 4 >> >> >> /AF [10 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 2 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Deck Review Target) /Parent 5 0 R /Dest /DeckTarget /Next 7 0 R >>\nendobj\n"
    . "7 0 obj\n<< /Title (Deck Review Action) /Parent 5 0 R /A 18 0 R >>\nendobj\n"
    . "8 0 obj\n<< /Names [(DeckTarget) [4 0 R /FitH 640]] >>\nendobj\n"
    . "9 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (deck-source.xml) /Desc (Original migration source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /CreationDate (D:20260602165200Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "15 0 obj\n<< /S /URI /URI (https://example.com/deck-open-review) >>\nendobj\n"
    . "16 0 obj\n<< /S /Dissolve /D 0.5 /B true >>\nendobj\n"
    . "18 0 obj\n<< /S /GoTo /D /DeckTarget /Next 19 0 R >>\nendobj\n"
    . "19 0 obj\n<< /S /URI /URI (javascript:alert\\(95\\)) >>\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Deck Article Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [70 700 340 742] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Deck ) /St 95 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageOneContent) . " >>\nstream\n{$pageOneContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($pageTwoContent) . " >>\nstream\n{$pageTwoContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /StructTreeRoot /K 41 0 R >>\nendobj\n"
    . "41 0 obj\n<< /Type /StructElem /S /Sect /T (Review section) /Pg 4 0 R /A 42 0 R /K << /Type /MCR /Pg 4 0 R /MCID 0 >> >>\nendobj\n"
    . "42 0 obj\n<< /O /UserProperties /P [<< /N (WP Block) /V (core/group) /F (Grouped deck section) >> << /N (Needs Manual Review) /V true /H true >>] >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

$outline = $navigation['outline'][0] ?? [];
$openDestination = $navigation['open_action_destination'] ?? [];
$pageReview = $outline['target_page_review'] ?? [];

if (($pageReview['piece_info']['WPImport']['private']['ReviewState'] ?? null) !== 'needs-page-review') {
    throw new RuntimeException('Expected outline target to carry page PieceInfo review metadata.');
}
if (($outline['target_page_transition']['style'] ?? null) !== 'Dissolve') {
    throw new RuntimeException('Expected outline target to carry page transition metadata.');
}
if (array_column($outline['target_article_beads'] ?? [], 'bead_object') !== [21]) {
    throw new RuntimeException('Expected outline target to carry article bead metadata.');
}
if (($openDestination['target_page_review']['page_associated_files'][0]['filename'] ?? null) !== 'deck-source.xml') {
    throw new RuntimeException('Expected OpenAction destination to carry associated-file page review metadata.');
}
if (str_contains($plainText, 'wp-export') || str_contains($plainText, 'needs-page-review') || str_contains($plainText, 'Deck Article Thread') || str_contains($plainText, 'javascript:alert')) {
    throw new RuntimeException('Expected review dictionaries and action operands to stay out of visible WordPress text.');
}

echo '<!-- markerpdf-outline-page-pieceinfo-transition-thread-review-currentbase ' . htmlspecialchars(json_encode([
    'support_component' => 'native-pdf-outline-page-pieceinfo-transition-thread-review-parser',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'native_boundary' => 'outline/OpenAction page targets inherit page PieceInfo, associated file, UserProperties, transition, page action, and article thread review metadata while visible text stays page-stream-only',
    'navigation_sources' => $navigation['source'],
    'outline_target_label' => $outline['page_label'] ?? null,
    'outline_target_transition' => $outline['target_page_transition']['style'] ?? null,
    'outline_target_pieceinfo_applications' => array_keys($pageReview['piece_info'] ?? []),
    'outline_target_attachment_filenames' => array_column($pageReview['page_associated_files'] ?? [], 'filename'),
    'outline_target_user_properties' => array_column($pageReview['user_properties'] ?? [], 'name'),
    'outline_target_article_threads' => $outline['target_article_thread_titles'] ?? [],
    'open_action_target_attachment' => $openDestination['target_page_review']['page_associated_files'][0]['filename'] ?? null,
    'visible_text_excludes_review_metadata' => !str_contains($plainText, 'wp-export')
        && !str_contains($plainText, 'needs-page-review')
        && !str_contains($plainText, 'Deck Article Thread')
        && !str_contains($plainText, 'javascript:alert'),
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
foreach ($navigation['outline'] as $row) {
    $targetReview = $row['target_page_review'] ?? [];
    echo '<li data-marker-outline-page-label="' . htmlspecialchars($row['page_label'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-transition="' . htmlspecialchars((string) ($row['target_page_transition']['style'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-pieceinfo="' . htmlspecialchars(implode(',', array_keys($targetReview['piece_info'] ?? [])), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-outline-thread="' . htmlspecialchars(implode(',', $row['target_article_thread_titles'] ?? []), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '">Outline target review: ' . htmlspecialchars($row['title'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
