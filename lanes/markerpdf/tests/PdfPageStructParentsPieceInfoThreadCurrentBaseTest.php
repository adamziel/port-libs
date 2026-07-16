<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageStructParentsPieceInfoThreadSourcePayload = '<wp-export><post id="parent-tree-rich"/></wp-export>';
$pageStructParentsPieceInfoThreadSourceChecksum = strtoupper(hash('md5', $pageStructParentsPieceInfoThreadSourcePayload));
$pageStructParentsPieceInfoThreadPdf = static function () use (
    $pageStructParentsPieceInfoThreadSourcePayload,
    $pageStructParentsPieceInfoThreadSourceChecksum
): string {
    $content = 'BT /F1 12 Tf '
        . '/ArticleBody << /MCID 1 >> BDC 72 684 Td (ParentTree body visible) Tj EMC '
        . '/ArticleTitle << /MCID 0 >> BDC 72 720 Td (ParentTree title visible) Tj EMC ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /Threads [20 0 R] /MarkInfo << /Marked true >> /StructTreeRoot 40 0 R /PageLabels << /Nums [0 << /P (story-) /S /D /St 12 >>] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 6 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 12 /Contents 30 0 R /PieceInfo << /WPImport << /LastModified (D:20260602182324Z) /Private << /ThreadId (story-thread-12) /ReviewStage /parenttree-rich /NeedsReview true >> >> >> >>\nendobj\n"
        . "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (story-source.xml) /Desc (Original story source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageStructParentsPieceInfoThreadSourcePayload) . " /CheckSum <{$pageStructParentsPieceInfoThreadSourceChecksum}> /ModDate (D:20260602182200Z) >> /Length " . strlen($pageStructParentsPieceInfoThreadSourcePayload) . " >>\nstream\n{$pageStructParentsPieceInfoThreadSourcePayload}\nendstream\nendobj\n"
        . "20 0 obj\n<< /Type /Thread /F 21 0 R /I << /Title (ParentTree Article Thread) >> >>\nendobj\n"
        . "21 0 obj\n<< /Type /Bead /T 20 0 R /P 3 0 R /R [60 672 280 744] /N 21 0 R /V 21 0 R >>\nendobj\n"
        . "30 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "40 0 obj\n<< /Type /StructTreeRoot /Lang (en-US) /RoleMap << /ArticleTitle /H1 /ArticleBody /P >> /ParentTree 41 0 R /K [42 0 R 43 0 R] >>\nendobj\n"
        . "41 0 obj\n<< /Nums [12 [42 0 R 43 0 R]] >>\nendobj\n"
        . "42 0 obj\n<< /Type /StructElem /S /ArticleTitle /T (Story heading structure) /Lang (fr-CA) /Alt (Accessible story heading) /ActualText (Expanded story heading) /ID (story-title-12) /C [/feature /migration] /AF [10 0 R] /K 0 >>\nendobj\n"
        . "43 0 obj\n<< /Type /StructElem /S /ArticleBody /T (Story body structure) /E (Content management system) /K 1 >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'carries ParentTree StructElem review fields through page PieceInfo and article thread context' => static function (TestRunner $t) use (
        $pageStructParentsPieceInfoThreadPdf,
        $pageStructParentsPieceInfoThreadSourcePayload,
        $pageStructParentsPieceInfoThreadSourceChecksum
    ): void {
        $pdf = $pageStructParentsPieceInfoThreadPdf();
        $pageReviews = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $textExtractor = new PdfTextExtractor();
        $plainText = $textExtractor->extractPlainText($pdf);

        $t->same(1, count($pageReviews));
        $page = $pageReviews[0];
        $t->same(0, $page['pnum']);
        $t->same(1, $page['page_number']);
        $t->same(3, $page['page_object']);
        $t->same('story-12', $page['page_label']);
        $t->same(12, $page['struct_parents']);
        $t->same('D:20260602182324Z', $page['piece_info']['WPImport']['last_modified']);
        $t->same('story-thread-12', $page['piece_info']['WPImport']['private']['ThreadId']);
        $t->same('parenttree-rich', $page['piece_info']['WPImport']['private']['ReviewStage']);
        $t->same(true, $page['piece_info']['WPImport']['private']['NeedsReview']);

        $t->same(12, $page['parent_tree']['key']);
        $t->same([0, 1], $page['parent_tree']['mcids']);
        $t->same(['ArticleTitle', 'ArticleBody'], array_column($page['parent_tree']['entries'], 'raw_role'));
        $t->same(['H1', 'P'], array_column($page['parent_tree']['entries'], 'role'));
        $t->same([42, 43], array_column($page['parent_tree']['entries'], 'struct_object'));

        $t->same(['ParentTree Article Thread'], $page['article_thread_titles']);
        $t->same([21], array_column($page['article_thread_beads'], 'bead_object'));
        $t->same(['story-12'], array_column($page['article_thread_beads'], 'page_label'));

        $rows = $page['structure_marked_content'];
        $t->same(2, count($rows));
        $t->same(['page_structparents_parenttree_tagged_content', 'page_structparents_parenttree_tagged_content'], array_column($rows, 'source'));
        $t->same([0, 1], array_column($rows, 'mcid'));
        $t->same([42, 43], array_column($rows, 'struct_object'));
        $t->same(['ArticleTitle', 'ArticleBody'], array_column($rows, 'raw_role'));
        $t->same(['H1', 'P'], array_column($rows, 'role'));
        $t->same([true, true], array_column($rows, 'role_mapped'));
        $t->same(['Story heading structure', 'Story body structure'], array_column($rows, 'title'));
        $t->same('fr-CA', $rows[0]['language']);
        $t->same(false, $rows[0]['language_inherited']);
        $t->same('Accessible story heading', $rows[0]['alternate_text']);
        $t->same('Expanded story heading', $rows[0]['actual_text']);
        $t->same('story-title-12', $rows[0]['id']);
        $t->same(['feature', 'migration'], $rows[0]['classes']);
        $t->same('Content management system', $rows[1]['expansion_text']);
        $t->same(['story-12', 'story-12'], array_column($rows, 'page_label'));
        $t->same([['ArticleTitle'], ['ArticleBody']], array_column($rows, 'content_tags'));
        $t->same([true, true], array_column($rows, 'resources_resolved_for_tagged_text'));

        $structureFiles = $rows[0]['associated_files'] ?? [];
        $t->same(1, $rows[0]['associated_file_count']);
        $t->same(1, count($structureFiles));
        $t->same('structure_element_associated_files', $structureFiles[0]['source']);
        $t->same('story-source.xml', $structureFiles[0]['filename']);
        $t->same('Source', $structureFiles[0]['relationship']);
        $t->same('text/xml', $structureFiles[0]['mime_type']);
        $t->same(hash('sha256', $pageStructParentsPieceInfoThreadSourcePayload), $structureFiles[0]['content_sha256']);
        $t->same(strtolower($pageStructParentsPieceInfoThreadSourceChecksum), $structureFiles[0]['checksum']);
        $t->same(hash('md5', $pageStructParentsPieceInfoThreadSourcePayload), $structureFiles[0]['computed_checksum']);
        $t->same(true, $structureFiles[0]['checksum_matches']);
        $t->same(false, array_key_exists('content', $structureFiles[0]));

        $t->same(['ParentTree title visible', 'ParentTree body visible'], $textExtractor->extractTextLines($pdf));
        $t->contains('ParentTree title visible', $plainText);
        $t->contains('ParentTree body visible', $plainText);
        $t->same(false, str_contains($plainText, '<wp-export>'));
        $t->same(false, str_contains($plainText, 'ParentTree Article Thread'));
        $t->same(false, str_contains($plainText, 'Story heading structure'));
        $t->same(false, str_contains($plainText, 'Accessible story heading'));
        $t->same(false, str_contains($plainText, 'Expanded story heading'));
        $t->same(false, str_contains($plainText, 'story-thread-12'));
    },
];
