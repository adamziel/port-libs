<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageStructParentsAfThreadsPdf = static function (): string {
    $sourcePayload = '<wp-export><post id="struct-thread"/></wp-export>';
    $sourceChecksum = strtoupper(hash('md5', $sourcePayload));
    $pageContent = 'BT /F1 12 Tf '
        . '/Body << /MCID 1 >> BDC 72 684 Td (Thread body visible) Tj EMC '
        . '/Title << /MCID 0 >> BDC 72 720 Td (Thread title visible) Tj EMC ET';

    return "%PDF-2.0\n"
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
};

return [
    'composes page StructParents ParentTree rows with associated files and article threads' => static function (TestRunner $t) use ($pageStructParentsAfThreadsPdf): void {
        $pdf = $pageStructParentsAfThreadsPdf();
        $pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $textExtractor = new PdfTextExtractor();
        $plainText = $textExtractor->extractPlainText($pdf);

        $t->same(1, count($pageReviews));
        $page = $pageReviews[0];
        $t->same(0, $page['pnum']);
        $t->same(1, $page['page_number']);
        $t->same(3, $page['page_object']);
        $t->same('thread-4', $page['page_label']);
        $t->same(7, $page['struct_parents']);
        $t->same(7, $page['parent_tree']['key']);
        $t->same([0, 1], $page['parent_tree']['mcids']);
        $t->same(['Title', 'Body'], array_column($page['parent_tree']['entries'], 'raw_role'));
        $t->same(['H1', 'P'], array_column($page['parent_tree']['entries'], 'role'));
        $t->same([42, 43], array_column($page['parent_tree']['entries'], 'struct_object'));
        $t->same(true, $page['resources']['inherited']);
        $t->same(2, $page['resources']['resource_owner_object']);
        $t->same(['F1'], $page['resources']['font_names']);

        $t->same(['Thread Review Title'], $page['article_thread_titles']);
        $t->same([21], array_column($page['article_thread_beads'], 'bead_object'));
        $t->same(['thread-4'], array_column($page['article_thread_beads'], 'page_label'));
        $t->same([[60.0, 672.0, 280.0, 744.0]], array_column($page['article_thread_beads'], 'rect'));

        $associated = $page['page_associated_files'];
        $t->same(1, count($associated));
        $t->same('thread-source.xml', $associated[0]['filename']);
        $t->same('Source', $associated[0]['relationship']);
        $t->same('text/xml', $associated[0]['mime_type']);
        $t->same('md5', $associated[0]['checksum_algorithm']);
        $t->same(true, $associated[0]['checksum_matches']);
        $t->same('D:20260602180302Z', $associated[0]['modified_at']);
        $t->same(false, array_key_exists('content', $associated[0]));

        $mcrRows = $page['structure_marked_content'];
        $t->same([0, 1], array_column($mcrRows, 'mcid'));
        $t->same(['H1', 'P'], array_column($mcrRows, 'role'));
        $t->same(['thread-4', 'thread-4'], array_column($mcrRows, 'page_label'));

        $t->same(['Thread title visible', 'Thread body visible'], $textExtractor->extractTextLines($pdf));
        $t->contains('Thread title visible', $plainText);
        $t->contains('Thread body visible', $plainText);
        $t->same(false, str_contains($plainText, '<wp-export>'));
        $t->same(false, str_contains($plainText, 'Thread Review Title'));
        $t->same(false, str_contains($plainText, 'Heading MCID review'));
        $t->same(false, str_contains($plainText, 'thread-source.xml'));
        $t->same(false, str_contains($plainText, 'thread-4'));
    },
];
