<?php

declare(strict_types=1);

use PortLibs\MarkerPDF\PdfPagePropertyExtractor;
use PortLibs\MarkerPDF\PdfTextExtractor;

$pageAssociatedMarkedAltPayload = '<wp-export><post id="page-af-alt"/></wp-export>';
$pageAssociatedMarkedAltChecksum = strtoupper(hash('md5', $pageAssociatedMarkedAltPayload));
$pageAssociatedMarkedAltPdf = static function () use (
    $pageAssociatedMarkedAltPayload,
    $pageAssociatedMarkedAltChecksum
): string {
    $content = 'BT /F1 12 Tf '
        . '/Figure << /MCID 0 /Alt (Alt text for source-associated figure) >> BDC '
        . '72 720 Td (Noisy figure glyphs) Tj EMC ET';

    return "%PDF-2.0\n"
        . "1 0 obj\n<< /Type /Catalog /Pages 2 0 R /MarkInfo << /Marked true >> /StructTreeRoot 30 0 R /PageLabels << /Nums [0 << /P (af-alt-) /S /D /St 9 >>] >> >>\nendobj\n"
        . "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n"
        . "3 0 obj\n<< /Type /Page /Parent 2 0 R /StructParents 9 /Contents 4 0 R /AF [10 0 R] >>\nendobj\n"
        . "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n"
        . "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n"
        . "10 0 obj\n<< /Type /Filespec /F (page-source.xml) /Desc (Page source export) /AFRelationship /Source /EF << /F 11 0 R >> >>\nendobj\n"
        . "11 0 obj\n<< /Type /EmbeddedFile /Subtype /text#2Fxml /Params << /Size " . strlen($pageAssociatedMarkedAltPayload) . " /CheckSum <{$pageAssociatedMarkedAltChecksum}> /ModDate (D:20260602215000Z) >> /Length " . strlen($pageAssociatedMarkedAltPayload) . " >>\nstream\n{$pageAssociatedMarkedAltPayload}\nendstream\nendobj\n"
        . "30 0 obj\n<< /Type /StructTreeRoot /RoleMap << /Figure /Figure >> /ParentTree 31 0 R /K [40 0 R] >>\nendobj\n"
        . "31 0 obj\n<< /Nums [9 [40 0 R]] >>\nendobj\n"
        . "40 0 obj\n<< /Type /StructElem /S /Figure /Pg 3 0 R /T (Figure structure row) /Alt (Structure review alt stays metadata) /K 0 >>\nendobj\n"
        . "trailer\n<< /Root 1 0 R >>\n%%EOF";
};

return [
    'carries page associated files onto marked-content Alt review rows without payload promotion' => static function (TestRunner $t) use (
        $pageAssociatedMarkedAltPdf,
        $pageAssociatedMarkedAltPayload,
        $pageAssociatedMarkedAltChecksum
    ): void {
        $pdf = $pageAssociatedMarkedAltPdf();
        $pages = (new PdfPagePropertyExtractor())->extractPageReviewMetadata($pdf);
        $textExtractor = new PdfTextExtractor();
        $plainText = $textExtractor->extractPlainText($pdf);

        $t->same(1, count($pages));
        $page = $pages[0];
        $t->same('af-alt-9', $page['page_label']);
        $t->same(9, $page['struct_parents']);
        $t->same(1, count($page['page_associated_files']));
        $t->same('page_associated_files', $page['page_associated_files'][0]['source']);
        $t->same('page-source.xml', $page['page_associated_files'][0]['filename']);
        $t->same('Source', $page['page_associated_files'][0]['relationship']);

        $rows = $page['structure_marked_content'];
        $t->same(1, count($rows));
        $row = $rows[0];
        $t->same('catalog_struct_tree_mcr', $row['source']);
        $t->same(0, $row['mcid']);
        $t->same('Figure', $row['raw_role']);
        $t->same('Figure', $row['role']);
        $t->same('Figure structure row', $row['title']);
        $t->same('Structure review alt stays metadata', $row['alternate_text']);
        $t->same(1, $row['page_associated_file_count']);
        $t->same(true, $row['page_associated_file_review_only']);

        $files = $row['page_associated_files'];
        $t->same(1, count($files));
        $t->same('page_associated_files', $files[0]['source']);
        $t->same('page-source.xml', $files[0]['filename']);
        $t->same('Source', $files[0]['relationship']);
        $t->same('text/xml', $files[0]['mime_type']);
        $t->same(hash('sha256', $pageAssociatedMarkedAltPayload), $files[0]['content_sha256']);
        $t->same(strtolower($pageAssociatedMarkedAltChecksum), $files[0]['checksum']);
        $t->same(hash('md5', $pageAssociatedMarkedAltPayload), $files[0]['computed_checksum']);
        $t->same(true, $files[0]['checksum_matches']);
        $t->same('D:20260602215000Z', $files[0]['modified_at']);
        $t->same(false, array_key_exists('content', $files[0]));

        $t->same(['Alt text for source-associated figure'], $textExtractor->extractTextLines($pdf));
        $t->contains('Alt text for source-associated figure', $plainText);
        $t->same(false, str_contains($plainText, 'Noisy figure glyphs'));
        $t->same(false, str_contains($plainText, '<wp-export>'));
        $t->same(false, str_contains($plainText, 'page-source.xml'));
        $t->same(false, str_contains($plainText, 'Structure review alt stays metadata'));
    },
];
