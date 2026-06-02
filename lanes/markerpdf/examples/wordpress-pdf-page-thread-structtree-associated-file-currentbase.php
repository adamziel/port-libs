<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$sourcePayload = '<wp-export><post id="thread-struct-associated"/></wp-export>';
$sourceChecksum = strtoupper(hash('md5', $sourcePayload));
$content = 'BT /F1 12 Tf '
    . '/ArticleTitle << /MCID 0 >> BDC 72 720 Td (Thread associated title visible) Tj EMC '
    . '/ArticleBody << /MCID 1 >> BDC 72 684 Td (Thread associated body visible) Tj EMC ET';

$pdf = "%PDF-2.0\n"
    . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Threads [20 0 R] /MarkInfo << /Marked true >> /StructTreeRoot 40 0 R /PageLabels << /Nums [0 << /P (thread-struct-) /S /D /St 48 >>] >> >>\nendobj\n"
    . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
    . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 48 /Contents 30 0 R >>\nendobj\n"
    . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
    . "10 0 obj\n<< /Type /Filespec /F (thread-struct-source.xml) /Desc (Threaded tagged source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
    . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($sourcePayload) . " /CheckSum <{$sourceChecksum}> /ModDate (D:20260602203156Z) >> /Length " . strlen($sourcePayload) . " >>\nstream\n{$sourcePayload}\nendstream\nendobj\n"
    . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Struct associated article thread) >> >>\nendobj\n"
    . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 700 280 742] /N 22 0 R /V 22 0 R >>\nendobj\n"
    . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 660 280 699] /N 21 0 R /V 21 0 R >>\nendobj\n"
    . "30 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
    . "40 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ArticleTitle /H1 /ArticleBody /P >> /ParentTree 41 0 R /K [42 0 R 43 0 R] >>\nendobj\n"
    . "41 0 obj\n<< /Nums [48 [42 0 R 43 0 R]] >>\nendobj\n"
    . "42 0 obj\n<< /Type /StructElem /S /ArticleTitle /Pg 3 0 R /T (Thread associated heading structure) /AF [10 0 R] /K 0 >>\nendobj\n"
    . "43 0 obj\n<< /Type /StructElem /S /ArticleBody /Pg 3 0 R /T (Thread associated body structure) /K 1 >>\nendobj\n"
    . "trailer\n<< /Root 1 0 R >>\n%%EOF";

$pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
$textExtractor = new PdfTextExtractor();
$lines = $textExtractor->extractTextLines($pdf);
$plainText = $textExtractor->extractPlainText($pdf);

if (count($pageReviews) !== 1) {
    throw new RuntimeException('Expected one page thread StructTree associated-file review row.');
}

$pageReview = $pageReviews[0];
$articleBeads = $pageReview['article_thread_beads'] ?? [];
$firstBead = $articleBeads[0] ?? [];
$associatedFiles = $firstBead['target_structure_associated_files'] ?? [];
if (($pageReview['struct_parents'] ?? null) !== 48) {
    throw new RuntimeException('Expected page StructParents review key.');
}
if (($firstBead['target_structure_mcids'] ?? []) !== [0, 1]) {
    throw new RuntimeException('Expected article bead target StructTree MCIDs.');
}
if (count($associatedFiles) !== 1 || ($associatedFiles[0]['checksum_matches'] ?? null) !== true) {
    throw new RuntimeException('Expected StructElem associated source file checksum metadata on the article bead.');
}
if ($lines !== ['Thread associated title visible', 'Thread associated body visible']) {
    throw new RuntimeException('Expected tagged visible text order.');
}
if (str_contains($plainText, '<wp-export>')
    || str_contains($plainText, 'Struct associated article thread')
    || str_contains($plainText, 'Thread associated heading structure')
    || str_contains($plainText, 'thread-struct-source.xml')
    || str_contains($plainText, 'thread-struct-48')
) {
    throw new RuntimeException('Expected thread, StructTree, and associated-file metadata to stay out of visible WordPress text.');
}

$htmlJson = static function (array $value): string {
    return htmlspecialchars(json_encode($value, JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
};

echo '<!-- markerpdf-page-thread-structtree-associated-file-currentbase ' . $htmlJson([
    'support_component' => 'native-pdf-page-review-parser',
    'native_boundary' => 'catalog /Threads bead rows enriched with page StructTree MCID and StructElem /AF FileSpec provenance before WordPress import',
    'executes_python_or_models' => false,
    'executes_external_pdf_tools' => false,
    'page_label' => $pageReview['page_label'] ?? null,
    'struct_parents' => $pageReview['struct_parents'] ?? null,
    'article_thread_titles' => $pageReview['article_thread_titles'] ?? [],
    'article_thread_beads' => array_column($articleBeads, 'bead_object'),
    'bead_target_structure_mcids' => $firstBead['target_structure_mcids'] ?? [],
    'bead_target_structure_roles' => $firstBead['target_structure_roles'] ?? [],
    'bead_target_associated_filenames' => array_column($associatedFiles, 'filename'),
    'bead_target_associated_checksum_matches' => array_map(
        static fn (array $file): ?bool => $file['checksum_matches'] ?? null,
        $associatedFiles
    ),
    'raw_associated_content_exposed' => array_key_exists('content', $associatedFiles[0] ?? []),
    'visible_text_excludes_review_metadata' => !str_contains($plainText, '<wp-export>')
        && !str_contains($plainText, 'Struct associated article thread')
        && !str_contains($plainText, 'Thread associated heading structure')
        && !str_contains($plainText, 'thread-struct-source.xml')
        && !str_contains($plainText, 'thread-struct-48'),
]) . " -->\n";

foreach ($lines as $line) {
    echo "<!-- wp:paragraph -->\n";
    echo '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>\n";
    echo "<!-- /wp:paragraph -->\n\n";
}

echo '<!-- markerpdf:page-thread-structtree-associated-file-review ' . $htmlJson([
    'pnum' => $pageReview['pnum'],
    'page_number' => $pageReview['page_number'] ?? null,
    'page_label' => $pageReview['page_label'] ?? null,
    'page_object' => $pageReview['page_object'],
    'struct_parents' => $pageReview['struct_parents'] ?? null,
    'article_thread_beads' => array_map(static fn (array $bead): array => [
        'thread_title' => $bead['thread_title'] ?? null,
        'bead_object' => $bead['bead_object'] ?? null,
        'page_label' => $bead['page_label'] ?? null,
        'rect' => $bead['rect'] ?? null,
        'target_structure_mcids' => $bead['target_structure_mcids'] ?? [],
        'target_structure_roles' => $bead['target_structure_roles'] ?? [],
        'target_structure_associated_file_count' => $bead['target_structure_associated_file_count'] ?? 0,
        'target_structure_associated_files' => array_map(static fn (array $file): array => [
            'filename' => $file['filename'] ?? null,
            'relationship' => $file['relationship'] ?? null,
            'mime_type' => $file['mime_type'] ?? null,
            'size' => $file['size'] ?? null,
            'checksum_algorithm' => $file['checksum_algorithm'] ?? null,
            'checksum_matches' => $file['checksum_matches'] ?? null,
            'content_sha256' => $file['content_sha256'] ?? null,
            'modified_at' => $file['modified_at'] ?? null,
        ], $bead['target_structure_associated_files'] ?? []),
    ], $articleBeads),
]) . " -->\n";
