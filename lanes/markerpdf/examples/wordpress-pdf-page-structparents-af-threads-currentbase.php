<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="struct-thread"/></wp-export>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$pageContent = 'BT /F1 12 Tf '
    . '/Body << /MCID 1 >> BDC 72 684 Td (Thread body visible) Tj EMC '
    . '/Title << /MCID 0 >> BDC 72 720 Td (Thread title visible) Tj EMC ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Threads [20 0 R] /MarkInfo << /Marked true >> /StructTreeRoot 40 0 R /PageLabels << /Nums [0 << /P (thread-) /S /D /St 4 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 7 /Contents 30 0 R /AF [10 0 R] >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (thread-source.xml) /Desc (Original threaded import source) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602180302Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Thread Review Title) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 672 280 744] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($pageContent) . " >>\nstream\n{$pageContent}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Title /H1 /Body /P >> /ParentTree 41 0 R /K [42 0 R 43 0 R] >>\nendobj\n"
    . "41 0 obj\n<< /Nums [7 [42 0 R 43 0 R]] >>\nendobj\n"
    . "42 0 obj\n<< /Type /StructElem /S /Title /Pg 3 0 R /T (Heading MCID review) /K 0 >>\nendobj\n"
    . "43 0 obj\n<< /Type /StructElem /S /Body /Pg 3 0 R /T (Body MCID review) /K 1 >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

if (count($pageReviews) !== 1) {
    throw new RuntimeException('Expected one page StructParents/AF/thread review row.');
}

$pageReview = $pageReviews[0];
$associatedFiles = $pageReview['page_associated_files'] ?? [];
if (($pageReview['struct_parents'] ?? null) !== 7) {
    throw new RuntimeException('Expected page StructParents review key.');
}
if (($pageReview['parent_tree']['mcids'] ?? []) !== [0, 1]) {
    throw new RuntimeException('Expected ParentTree MCID review rows.');
}
if (($pageReview['article_thread_titles'] ?? []) !== ['Thread Review Title']) {
    throw new RuntimeException('Expected catalog article thread title metadata.');
}
if (count($associatedFiles) !== 1 || ($associatedFiles[0]['checksum_matches'] ?? null) !== true) {
    throw new RuntimeException('Expected page-associated source file checksum metadata.');
}
if ($lines !== ['Thread title visible', 'Thread body visible']) {
    throw new RuntimeException('Expected StructParents ParentTree visible text order.');
}
if (str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'Thread Review Title')
    || str_contains($plainText, 'Heading MCID review')
    || str_contains($plainText, 'thread-source.xml')
    || str_contains($plainText, 'thread-4')
) {
    throw new RuntimeException('Expected StructParents, AF, and thread metadata to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-structparents-af-threads-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-review-parser',
    'native_boundary' => 'page /StructParents ParentTree rows composed with page /AF FileSpec checksums and catalog /Threads beads before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'page_label' => $pageReview['page_label'] ?? null,
    'struct_parents' => $pageReview['struct_parents'] ?? null,
    'parent_tree_mcids' => $pageReview['parent_tree']['mcids'] ?? [],
    'parent_tree_roles' => $pageReview['parent_tree']['roles'] ?? [],
    'article_thread_titles' => $pageReview['article_thread_titles'] ?? [],
    'article_thread_beads' => array_column($pageReview['article_thread_beads'] ?? [], 'bead_object'),
    'page_associated_relationships' => array_column($associatedFiles, 'relationship'),
    'page_associated_checksum_matches' => array_map(
        static fn (array $file): ?bool => $file['checksum_matches'] ?? null,
        $associatedFiles
    ),
    'raw_associated_content_exposed' => array_key_exists('content', $associatedFiles[0] ?? []),
    'visible_text_excludes_review_metadata' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'Thread Review Title')
        && !str_contains($plainText, 'Heading MCID review')
        && !str_contains($plainText, 'thread-source.xml')
        && !str_contains($plainText, 'thread-4'),
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-structparents-af-threads-review ' . $htmlJson([
    'pnum' => $pageReview['pnum'],
    'page_number' => $pageReview['page_number'] ?? null,
    'page_label' => $pageReview['page_label'] ?? null,
    'page_object' => $pageReview['page_object'],
    'struct_parents' => $pageReview['struct_parents'] ?? null,
    'parent_tree' => $pageReview['parent_tree'] ?? [],
    'article_thread_titles' => $pageReview['article_thread_titles'] ?? [],
    'article_thread_beads' => array_map(static fn (array $bead): array => [
        'thread_title' => $bead['thread_title'] ?? null,
        'bead_object' => $bead['bead_object'] ?? null,
        'page_label' => $bead['page_label'] ?? null,
        'rect' => $bead['rect'] ?? null,
    ], $pageReview['article_thread_beads'] ?? []),
    'page_associated_files' => array_map(static fn (array $file): array => [
        'filename' => $file['filename'] ?? null,
        'relationship' => $file['relationship'] ?? null,
        'mime_type' => $file['mime_type'] ?? null,
        'size' => $file['size'] ?? null,
        'checksum_algorithm' => $file['checksum_algorithm'] ?? null,
        'checksum_matches' => $file['checksum_matches'] ?? null,
        'content_sha256' => $file['content_sha256'] ?? null,
        'modified_at' => $file['modified_at'] ?? null,
    ], $associatedFiles),
]) . " -->\n";
