<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageThreadStructTreeAssociatedPayload = '<wp-export><post id="thread-struct-associated"/></wp-export>';
$pageThreadStructTreeAssociatedChecksum = strtoupper(hash('md5', $pageThreadStructTreeAssociatedPayload));
$pageThreadStructTreeAssociatedPdf = static function () use (
    $pageThreadStructTreeAssociatedPayload,
    $pageThreadStructTreeAssociatedChecksum
): string {
    $content = 'BT /F1 12 Tf '
        . '/ArticleTitle << /MCID 0 >> BDC 72 720 Td (Thread associated title visible) Tj EMC '
        . '/ArticleBody << /MCID 1 >> BDC 72 684 Td (Thread associated body visible) Tj EMC ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Threads [20 0 R] /MarkInfo << /Marked true >> /StructTreeRoot 40 0 R /PageLabels << /Nums [0 << /P (thread-struct-) /S /D /St 48 >>] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 48 /Contents 30 0 R >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (thread-struct-source.xml) /Desc (Threaded tagged source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageThreadStructTreeAssociatedPayload) . " /CheckSum <{$pageThreadStructTreeAssociatedChecksum}> /ModDate (D:20260602203156Z) >> /Length " . strlen($pageThreadStructTreeAssociatedPayload) . " >>\nstream\n{$pageThreadStructTreeAssociatedPayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (Struct associated article thread) >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 700 280 742] /N 22 0 R /V 22 0 R >>\nendobj\n"
        . "22 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 660 280 699] /N 21 0 R /V 21 0 R >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /StructTreeRoot /RoleMap << /ArticleTitle /H1 /ArticleBody /P >> /ParentTree 41 0 R /K [42 0 R 43 0 R] >>\nendobj\n"
        . "41 0 obj\n<< /Nums [48 [42 0 R 43 0 R]] >>\nendobj\n"
        . "42 0 obj\n<< /Type /StructElem /S /ArticleTitle /Pg 3 0 R /T (Thread associated heading structure) /AF [10 0 R] /K 0 >>\nendobj\n"
        . "43 0 obj\n<< /Type /StructElem /S /ArticleBody /Pg 3 0 R /T (Thread associated body structure) /K 1 >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'attaches StructTree associated-file provenance to page article-thread bead review rows' => static function (TestRunner $t) use (
        $pageThreadStructTreeAssociatedPdf,
        $pageThreadStructTreeAssociatedPayload,
        $pageThreadStructTreeAssociatedChecksum
    ): void {
        $pdf = $pageThreadStructTreeAssociatedPdf();
        $pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $textExtractor = new PdfTextExtractor();
        $plainText = $textExtractor->extractPlainText($pdf);

        $t->same(1, count($pageReviews));
        $page = $pageReviews[0];
        $t->same('thread-struct-48', $page['page_label']);
        $t->same(48, $page['struct_parents']);
        $t->same(['Struct associated article thread'], $page['article_thread_titles']);
        $t->same([21, 22], array_column($page['article_thread_beads'], 'bead_object'));

        $rows = $page['structure_marked_content'];
        $t->same([0, 1], array_column($rows, 'mcid'));
        $t->same(['H1', 'P'], array_column($rows, 'role'));
        $t->same(1, $rows[0]['associated_file_count']);
        $t->same(false, array_key_exists('associated_file_count', $rows[1]));

        $bead = $page['article_thread_beads'][0];
        $t->same('catalog_article_threads', $bead['source']);
        $t->same([0, 1], $bead['target_structure_mcids']);
        $t->same(['H1', 'P'], $bead['target_structure_roles']);
        $t->same(1, $bead['target_structure_associated_file_count']);
        $t->same(2, count($bead['target_structure_marked_content']));
        $t->same(['ArticleTitle', 'ArticleBody'], array_column($bead['target_structure_marked_content'], 'raw_role'));
        $t->same([42, 43], array_column($bead['target_structure_marked_content'], 'struct_object'));
        $t->same(['thread-struct-48', 'thread-struct-48'], array_column($bead['target_structure_marked_content'], 'page_label'));

        $beadFiles = $bead['target_structure_associated_files'];
        $t->same(1, count($beadFiles));
        $t->same('structure_element_associated_files', $beadFiles[0]['source']);
        $t->same('thread-struct-source.xml', $beadFiles[0]['filename']);
        $t->same('Source', $beadFiles[0]['relationship']);
        $t->same('text/xml', $beadFiles[0]['mime_type']);
        $t->same(hash('sha256', $pageThreadStructTreeAssociatedPayload), $beadFiles[0]['content_sha256']);
        $t->same(strtolower($pageThreadStructTreeAssociatedChecksum), $beadFiles[0]['checksum']);
        $t->same(hash('md5', $pageThreadStructTreeAssociatedPayload), $beadFiles[0]['computed_checksum']);
        $t->same(true, $beadFiles[0]['checksum_matches']);
        $t->same('D:20260602203156Z', $beadFiles[0]['modified_at']);
        $t->same(false, array_key_exists('content', $beadFiles[0]));

        $secondBead = $page['article_thread_beads'][1];
        $t->same([0, 1], $secondBead['target_structure_mcids']);
        $t->same(1, $secondBead['target_structure_associated_file_count']);
        $t->same('thread-struct-source.xml', $secondBead['target_structure_associated_files'][0]['filename']);

        $t->same(['Thread associated title visible', 'Thread associated body visible'], $textExtractor->extractTextLines($pdf));
        $t->contains('Thread associated title visible', $plainText);
        $t->contains('Thread associated body visible', $plainText);
        $t->same(false, str_contains($plainText, '<wp-export>'));
        $t->same(false, str_contains($plainText, 'Struct associated article thread'));
        $t->same(false, str_contains($plainText, 'Thread associated heading structure'));
        $t->same(false, str_contains($plainText, 'thread-struct-source.xml'));
        $t->same(false, str_contains($plainText, 'thread-struct-48'));
    },
];
