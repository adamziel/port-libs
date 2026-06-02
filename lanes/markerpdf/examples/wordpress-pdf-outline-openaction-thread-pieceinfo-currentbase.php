<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfOutlineExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$htmlJson = static fn (array $value): string => htmlspecialchars(
    json_encode($value, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
    ENT_QUOTES | ENT_SUBSTITUTE,
    'UTF-8'
);

$introContent = 'BT /F1 12 Tf 72 720 Td (Intro page remains visible) Tj ET';
$articleContent = 'BT /F1 12 Tf 72 720 Td (Open action article target remains visible) Tj ET';
$reviewPayload = '<wp-openaction-review target="article"/>';
$reviewChecksum = strtoupper(hash('md5', $reviewPayload));

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Outlines 5 0 R /OpenAction /ArticleOpen /Names << /Dests 8 0 R >> /PageLabels 25 0 R /Threads [20 0 R] >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 31 0 R >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Resources << /Font << /F1 30 0 R >> >> /Contents 32 0 R /PieceInfo << /WPOpenAction << /LastModified (D:20260602175420Z) /Private << /ReviewState (openaction-thread-pieceinfo) /NeedsReview true /ImportBatch 33 >> >> >> /AF [12 0 R] >>\nendobj\n"
    . "5 0 obj\n<< /Type /Outlines /First 6 0 R /Count 1 >>\nendobj\n"
    . "6 0 obj\n<< /Title (Article Outline Target) /Parent 5 0 R /Dest /ArticleTarget >>\nendobj\n"
    . "8 0 obj\n<< /Names [(ArticleOpen) 9 0 R (ArticleTarget) [4 0 R /FitH 690]] >>\nendobj\n"
    . "9 0 obj\n<< /S /GoTo /D /ArticleTarget /Next [10 0 R 11 0 R] >>\nendobj\n"
    . "10 0 obj\n<< /S /URI /URI (https://example.com/open-action-review) >>\nendobj\n"
    . "11 0 obj\n<< /S /JavaScript /JS (app.alert\\('hidden open action script'\\)) >>\nendobj\n"
    . "12 0 obj\n<< /Type /Filespec /F (open-action-review.xml) /Desc (OpenAction review source) /AFRelationship /Source /EF << /F 13 0 R >> >>\nendobj\n"
    . "13 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($reviewPayload) . " /CheckSum <{$reviewChecksum}> >> /Length " . strlen($reviewPayload) . " >>\nstream\n{$reviewPayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (OpenAction Article Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [58 690 280 732] /N 22 0 R /V 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [300 690 540 732] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "25 0 obj\n<< /Nums [0 << /S /D /P (Intro ) /St 1 >> 1 << /S /D /P (Article ) /St 12 >>] >>\nendobj\n"
    . "30 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($introContent) . " >>\nstream\n{$introContent}\nendstream\nendobj\n"
    . "32 0 obj\n<< /Length " . strlen($articleContent) . " >>\nstream\n{$articleContent}\nendstream\nendobj\n"
    . "%%EOF";

$outlineExtractor = new PdfOutlineExtractor();
$textExtractor = new PdfTextExtractor();
$navigation = $outlineExtractor->getNavigationReviewMetadata($pdf);
$plainText = $textExtractor->extractPlainText($pdf);
$pages = $textExtractor->extractLabeledPageTexts($pdf);

$openActionRows = $navigation['open_action_review_actions'] ?? [];
$chainedRows = array_values(array_filter($openActionRows, static fn (array $row): bool => ($row['chained'] ?? false) === true));
$targetReview = $chainedRows[0]['destination_action_target_page_review'] ?? [];

if (count($openActionRows) !== 3 || array_column($openActionRows, 'destination_action_name') !== ['ArticleOpen', 'ArticleOpen', 'ArticleOpen']) {
    throw new RuntimeException('Expected OpenAction name-tree action and chained rows.');
}
if (($targetReview['piece_info']['WPOpenAction']['private']['ReviewState'] ?? null) !== 'openaction-thread-pieceinfo') {
    throw new RuntimeException('Expected chained OpenAction rows to carry target page PieceInfo.');
}
if (array_column($chainedRows[0]['destination_action_target_article_beads'] ?? [], 'bead_object') !== [21, 22]) {
    throw new RuntimeException('Expected chained OpenAction rows to carry article thread bead metadata.');
}
if (($targetReview['page_associated_files'][0]['checksum_matches'] ?? null) !== true) {
    throw new RuntimeException('Expected target associated-file checksum state.');
}
foreach (['ArticleOpen', 'ArticleTarget', 'open-action-review', 'hidden open action script', 'OpenAction Article Thread', 'openaction-thread-pieceinfo'] as $hiddenText) {
    if (str_contains($plainText, $hiddenText)) {
        throw new RuntimeException('Review-only OpenAction metadata leaked into visible text: ' . $hiddenText);
    }
}

echo '<!-- markerpdf-outline-openaction-thread-pieceinfo-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-outline-openaction-thread-pieceinfo-review-parser',
    'native_boundary' => 'catalog OpenAction name-tree action dictionaries emit chained review rows whose target page PieceInfo, associated-file checksum, and article-thread beads remain review-only',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'executes_pdf_actions' => false,
    'open_action_destination_names' => array_column($openActionRows, 'destination_action_name'),
    'open_action_types' => array_column($openActionRows, 'action_type'),
    'open_action_safeties' => array_column($openActionRows, 'safety'),
    'chained_action_count' => count($chainedRows),
    'target_page_label' => $chainedRows[0]['destination_action_target_page_label'] ?? null,
    'target_pieceinfo_apps' => array_keys($targetReview['piece_info'] ?? []),
    'target_pieceinfo_review_state' => $targetReview['piece_info']['WPOpenAction']['private']['ReviewState'] ?? null,
    'target_attachment_filename' => $targetReview['page_associated_files'][0]['filename'] ?? null,
    'target_attachment_checksum_matches' => $targetReview['page_associated_files'][0]['checksum_matches'] ?? null,
    'target_article_thread_titles' => $chainedRows[0]['destination_action_target_article_thread_titles'] ?? [],
    'target_article_beads' => array_column($chainedRows[0]['destination_action_target_article_beads'] ?? [], 'bead_object'),
    'visible_text_excludes_review_metadata' => !str_contains($plainText, 'openaction-thread-pieceinfo')
        && !str_contains($plainText, 'hidden open action script')
        && !str_contains($plainText, 'open-action-review'),
], JSON_UNESCAPED_SLASHES) . " -->\n";

foreach ($pages as $page) {
    echo '<!-- wp:paragraph {"metadata":{"name":"PDF page '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '"}} -->' . "\n";
    echo '<p>' . htmlspecialchars($page['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo "<!-- wp:list -->\n<ul>\n";
foreach ($openActionRows as $row) {
    $review = $row['destination_action_target_page_review'] ?? $row['target_page_review'] ?? [];
    echo '<li data-marker-openaction-name="' . htmlspecialchars((string) ($row['destination_action_name'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-openaction-type="' . htmlspecialchars($row['action_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-openaction-safety="' . htmlspecialchars($row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-target-page-label="' . htmlspecialchars((string) ($row['destination_action_target_page_label'] ?? $row['page_label'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-pieceinfo-state="' . htmlspecialchars((string) ($review['piece_info']['WPOpenAction']['private']['ReviewState'] ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '" data-marker-executes-on-import="' . (($row['executes_on_import'] ?? true) ? 'true' : 'false')
        . '">OpenAction review: ' . htmlspecialchars($row['action_type'] . ' ' . $row['safety'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</li>\n";
}
echo "</ul>\n<!-- /wp:list -->\n";
