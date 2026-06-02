<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="parent-tree-rich"/></wp-export>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$content = 'BT /F1 12 Tf '
    . '/ArticleBody << /MCID 1 >> BDC 72 684 Td (ParentTree body visible) Tj EMC '
    . '/ArticleTitle << /MCID 0 >> BDC 72 720 Td (ParentTree title visible) Tj EMC ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Threads [20 0 R] /MarkInfo << /Marked true >> /StructTreeRoot 40 0 R /PageLabels << /Nums [0 << /P (story-) /S /D /St 12 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 12 /Contents 30 0 R /PieceInfo << /WPImport << /LastModified (D:20260602182324Z) /Private << /ThreadId (story-thread-12) /ReviewStage /parenttree-rich /NeedsReview true >> >> >> >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (story-source.xml) /Desc (Original story source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602182200Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (ParentTree Article Thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 672 280 744] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /StructTreeRoot /Lang (en-US) /RoleMap << /ArticleTitle /H1 /ArticleBody /P >> /ParentTree 41 0 R /K [42 0 R 43 0 R] >>\nendobj\n"
    . "41 0 obj\n<< /Nums [12 [42 0 R 43 0 R]] >>\nendobj\n"
    . "42 0 obj\n<< /Type /StructElem /S /ArticleTitle /T (Story heading structure) /Lang (fr-CA) /Alt (Accessible story heading) /ActualText (Expanded story heading) /ID (story-title-12) /C [/feature /migration] /AF [10 0 R] /K 0 >>\nendobj\n"
    . "43 0 obj\n<< /Type /StructElem /S /ArticleBody /T (Story body structure) /E (Content management system) /K 1 >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

if (count($pageReviews) !== 1) {
    throw new RuntimeException('Expected one page review row.');
}

$pageReview = $pageReviews[0];
$rows = $pageReview['structure_marked_content'] ?? [];
$structureFiles = $rows[0]['associated_files'] ?? [];

if (($pageReview['piece_info']['WPImport']['private']['ThreadId'] ?? null) !== 'story-thread-12') {
    throw new RuntimeException('Expected page PieceInfo thread metadata.');
}
if (($pageReview['article_thread_titles'] ?? []) !== ['ParentTree Article Thread']) {
    throw new RuntimeException('Expected page article thread context.');
}
if (array_column($rows, 'struct_object') !== [42, 43]) {
    throw new RuntimeException('Expected ParentTree rows to carry StructElem object metadata.');
}
if (($rows[0]['title'] ?? null) !== 'Story heading structure'
    || ($rows[0]['actual_text'] ?? null) !== 'Expanded story heading'
    || ($rows[0]['associated_file_count'] ?? null) !== 1
) {
    throw new RuntimeException('Expected rich StructElem review fields on page MCID rows.');
}
if (($structureFiles[0]['checksum_matches'] ?? null) !== true || array_key_exists('content', $structureFiles[0] ?? [])) {
    throw new RuntimeException('Expected structure-associated file checksum metadata without payload exposure.');
}
if ($lines !== ['ParentTree title visible', 'ParentTree body visible']) {
    throw new RuntimeException('Expected ParentTree text order.');
}
if (str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'ParentTree Article Thread')
    || str_contains($plainText, 'Story heading structure')
    || str_contains($plainText, 'Accessible story heading')
    || str_contains($plainText, 'Expanded story heading')
    || str_contains($plainText, 'story-thread-12')
) {
    throw new RuntimeException('Expected page review metadata to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-structparents-pieceinfo-thread-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-structparents-rich-review-parser',
    'native_boundary' => 'page /StructParents ParentTree rows inherit StructElem review fields while page /PieceInfo and catalog /Threads stay review-only before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'page_label' => $pageReview['page_label'] ?? null,
    'struct_parents' => $pageReview['struct_parents'] ?? null,
    'piece_info_apps' => array_keys($pageReview['piece_info'] ?? []),
    'thread_titles' => $pageReview['article_thread_titles'] ?? [],
    'parent_tree_struct_objects' => array_column($pageReview['parent_tree']['entries'] ?? [], 'struct_object'),
    'mcr_struct_objects' => array_column($rows, 'struct_object'),
    'mcr_titles' => array_column($rows, 'title'),
    'structure_associated_files' => array_column($structureFiles, 'filename'),
    'payload_content_omitted' => !array_key_exists('content', $structureFiles[0] ?? []),
    'visible_text_excludes_review_metadata' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'ParentTree Article Thread')
        && !str_contains($plainText, 'Story heading structure')
        && !str_contains($plainText, 'Accessible story heading')
        && !str_contains($plainText, 'Expanded story heading')
        && !str_contains($plainText, 'story-thread-12'),
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-structparents-pieceinfo-thread-review ' . $htmlJson([
    'pnum' => $pageReview['pnum'],
    'page_number' => $pageReview['page_number'] ?? null,
    'page_label' => $pageReview['page_label'] ?? null,
    'page_object' => $pageReview['page_object'],
    'piece_info' => $pageReview['piece_info'] ?? [],
    'article_thread_titles' => $pageReview['article_thread_titles'] ?? [],
    'article_thread_beads' => array_map(static fn (array $bead): array => [
        'thread_title' => $bead['thread_title'] ?? null,
        'bead_object' => $bead['bead_object'] ?? null,
        'page_label' => $bead['page_label'] ?? null,
        'rect' => $bead['rect'] ?? null,
    ], $pageReview['article_thread_beads'] ?? []),
    'structure_marked_content' => array_map(static fn (array $row): array => [
        'struct_object' => $row['struct_object'] ?? null,
        'raw_role' => $row['raw_role'] ?? null,
        'role' => $row['role'] ?? null,
        'mcid' => $row['mcid'] ?? null,
        'title' => $row['title'] ?? null,
        'language' => $row['language'] ?? null,
        'alternate_text' => $row['alternate_text'] ?? null,
        'actual_text' => $row['actual_text'] ?? null,
        'associated_file_count' => $row['associated_file_count'] ?? null,
        'associated_files' => array_map(static fn (array $file): array => [
            'filename' => $file['filename'] ?? null,
            'relationship' => $file['relationship'] ?? null,
            'checksum_matches' => $file['checksum_matches'] ?? null,
            'content_sha256' => $file['content_sha256'] ?? null,
        ], $row['associated_files'] ?? []),
        'review_only' => $row['review_only'] ?? null,
    ], $rows),
]) . " -->\n";
