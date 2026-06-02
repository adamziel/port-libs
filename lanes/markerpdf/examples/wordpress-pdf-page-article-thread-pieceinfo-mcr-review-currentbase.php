<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$firstPageContent = 'BT /F1 12 Tf '
    . '/Article << /MCID 0 >> BDC 72 720 Td (Article heading visible) Tj EMC '
    . '/Article << /MCID 1 >> BDC 72 680 Td (Article body visible) Tj EMC ET';
$secondPageContent = 'BT /F1 12 Tf /Aside << /MCID 0 >> BDC 72 720 Td (Related article visible) Tj EMC ET';

$pdf = "%PDF-1.7\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Threads [20 0 R] /MarkInfo << /Marked true >> /StructTreeRoot 40 0 R /PageLabels << /Nums [0 << /P (A-) /S /D /St 9 >> 1 << /P (B-) /S /D /St 10 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 30 0 R /PieceInfo << /WPArticle << /LastModified (D:20260602165500Z) /Private << /ThreadId (thread-9) /ReviewStage /mcr-check /NeedsReview true >> >> >> >>\nendobj\n"
    . "4 0 obj\n<< /Type /Page /Parent 2 0 R /Contents 31 0 R >>\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Editorial Article Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 700 260 740] /N 22 0 R /V 23 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 660 260 699] /N 23 0 R /V 21 0 R >>\nendobj\n"
    . "23 0 obj\n<< /Type /Bead /T 20 0 R /P 4 0 R /R [60 700 260 740] /N 21 0 R /V 22 0 R >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($firstPageContent) . " >>\nstream\n{$firstPageContent}\nendstream\nendobj\n"
    . "31 0 obj\n<< /Length " . strlen($secondPageContent) . " >>\nstream\n{$secondPageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Art /Article /Aside /P >> /K [41 0 R 42 0 R] >>\nendobj\n"
    . "41 0 obj\n<< /Type /StructElem /S /Art /Pg 3 0 R /Lang (en-US) /T (Thread article section) /Alt (Article alternate review text) /ActualText (Article actual review text) /ID (article-9) /C [/feature /review] /K [<< /Type /MCR /Pg 3 0 R /MCID 0 >> << /Type /MCR /Pg 3 0 R /MCID 1 >>] >>\nendobj\n"
    . "42 0 obj\n<< /Type /StructElem /S /Aside /Pg 4 0 R /T (Related aside) /K << /Type /MCR /Pg 4 0 R /MCID 0 >> >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$pageTexts = (new PdfTextExtractor())->extractLabeledPageTexts($pdf);
$plainText = (new PdfTextExtractor())->extractPlainText($pdf);

if (count($pageReviews) !== 2) {
    throw new RuntimeException('Expected two page review rows.');
}
if (array_column($pageReviews[0]['article_thread_beads'] ?? [], 'bead_object') !== [21, 22]) {
    throw new RuntimeException('Expected first page article-thread beads.');
}
if (array_column($pageReviews[0]['structure_marked_content'] ?? [], 'mcid') !== [0, 1]) {
    throw new RuntimeException('Expected first page StructTree MCR rows.');
}
if (($pageReviews[0]['piece_info']['WPArticle']['private']['ThreadId'] ?? null) !== 'thread-9') {
    throw new RuntimeException('Expected page PieceInfo thread review metadata.');
}
if (array_column($pageReviews[1]['article_thread_beads'] ?? [], 'bead_object') !== [23]) {
    throw new RuntimeException('Expected second page article-thread bead.');
}
if (str_contains($plainText, 'Editorial Article Thread')
    || str_contains($plainText, 'Thread article section')
    || str_contains($plainText, 'Article alternate review text')
    || str_contains($plainText, 'Article actual review text')
    || str_contains($plainText, 'thread-9')
) {
    throw new RuntimeException('Expected page review metadata to stay out of visible text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-article-thread-pieceinfo-mcr-review-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-article-thread-pieceinfo-mcr-review-parser',
    'native_boundary' => 'page /PieceInfo review rows composed with catalog /Threads beads and StructTree /MCR page links before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'page_review_count' => count($pageReviews),
    'piece_info_apps' => array_keys($pageReviews[0]['piece_info'] ?? []),
    'article_thread_titles' => $pageReviews[0]['article_thread_titles'] ?? [],
    'first_page_article_beads' => array_column($pageReviews[0]['article_thread_beads'] ?? [], 'bead_object'),
    'first_page_mcr_mcids' => array_column($pageReviews[0]['structure_marked_content'] ?? [], 'mcid'),
    'second_page_article_beads' => array_column($pageReviews[1]['article_thread_beads'] ?? [], 'bead_object'),
    'second_page_mcr_mcids' => array_column($pageReviews[1]['structure_marked_content'] ?? [], 'mcid'),
    'review_metadata_visible_text_source' => false,
    'visible_text_excludes_review_metadata' => !str_contains($plainText, 'Editorial Article Thread')
        && !str_contains($plainText, 'Thread article section')
        && !str_contains($plainText, 'Article alternate review text')
        && !str_contains($plainText, 'Article actual review text')
        && !str_contains($plainText, 'thread-9'),
]) . " -->\n";

foreach ($pageTexts as $page) {
    echo '<!-- wp:separator {"className":"markerpdf-page-break","metadata":{"name":"PDF page '
        . htmlspecialchars($page['page_label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        . '"}} -->' . "\n";
    echo '<hr class="wp-block-separator has-alpha-channel-opacity markerpdf-page-break"/>' . "\n";
    echo "<!-- /wp:separator -->\n\n";
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($page['text'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

foreach ($pageReviews as $pageReview) {
    echo '<!-- markerpdf:page-article-thread-pieceinfo-mcr-review ' . $htmlJson([
        'pnum' => $pageReview['pnum'],
        'page_object' => $pageReview['page_object'],
        'piece_info' => $pageReview['piece_info'] ?? [],
        'article_thread_titles' => $pageReview['article_thread_titles'] ?? [],
        'article_thread_beads' => array_map(static fn (array $bead): array => [
            'thread_title' => $bead['thread_title'] ?? null,
            'bead_object' => $bead['bead_object'] ?? null,
            'page_label' => $bead['page_label'] ?? null,
            'rect' => $bead['rect'] ?? null,
            'next_bead_object' => $bead['next_bead_object'] ?? null,
            'previous_bead_object' => $bead['previous_bead_object'] ?? null,
        ], $pageReview['article_thread_beads'] ?? []),
        'structure_marked_content' => array_map(static fn (array $row): array => [
            'struct_object' => $row['struct_object'] ?? null,
            'raw_role' => $row['raw_role'] ?? null,
            'role' => $row['role'] ?? null,
            'mcid' => $row['mcid'] ?? null,
            'page_label' => $row['page_label'] ?? null,
            'title' => $row['title'] ?? null,
            'alternate_text' => $row['alternate_text'] ?? null,
            'actual_text' => $row['actual_text'] ?? null,
            'review_only' => $row['review_only'] ?? null,
        ], $pageReview['structure_marked_content'] ?? []),
    ]) . " -->\n";
}
